import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdirSync, readFileSync } from 'node:fs';
import { join, isAbsolute } from 'node:path';
import { randomUUID } from 'node:crypto';
import { digest, requireThat } from './contracts.mjs';

const run = promisify(execFile);
export class QaAdapter {
  constructor({ executable, stateDir, registry, store, executor = run }) { Object.assign(this, { executable, stateDir, registry, store, executor }); this.busy = false; }
  async run(actor, target) {
    this.registry.current(actor, target, 'test');
    requireThat(isAbsolute(target.qaProject || ''), 'QA_ROUTE_UNAVAILABLE', 'The registered QA checkout must be an absolute service path.');
    requireThat(!this.busy, 'QA_BUSY', 'The QA worker is busy. Run site QA serially to keep its browser state isolated.');
    this.busy = true;
    const id = randomUUID(); const directory = join(this.stateDir, 'qa', id); mkdirSync(directory, { recursive: true, mode: 0o700 });
    const report = join(directory, 'report.md'); let exit = 0;
    try {
      this.store.audit(actor, target, id, 'qa', 'attempted');
      try {
        await this.executor(this.executable, ['run', '--project-root', target.qaProject, '--site-url', target.url, '--scope', 'site-only',
          '--run-api', 'always', '--run-accessibility', 'always', '--run-performance', 'always', '--run-cwv', 'always',
          '--run-smoke', 'never', '--run-phpcbf', 'never', '--output-file', report], {
          timeout: 600000, maxBuffer: 1024 * 1024,
          env: { PATH: process.env.PATH, HOME: process.env.HOME, TMPDIR: directory,
            MRN_QA_SAMPLE_PATH: '/', MRN_QA_A11Y_DYNAMIC_CONTENT: '0', MRN_QA_A11Y_KEYBOARD_NAV: '0',
            MRN_QA_RUN_SMOKE: 'never', MRN_QA_RUN_PHPCBF: 'never' },
        });
      } catch { exit = 1; }
      const bytes = readFileSync(report);
      const record = this.store.create('qa', actor, target, { status: exit ? 'needs_attention' : 'completed',
        result: { reportSha256: digest(bytes), reportRef: `qa/${id}/report.md`, commandExitSuccessful: exit === 0,
          explanation: 'Review the MRN QA report for individual results. Completion does not mean every row passed.',
          unrun: ['form validation', 'form submission', 'delivery', 'CRM/payment effects', 'interactive browser smoke'] } });
      this.store.audit(actor, target, record.id, 'qa', record.status); return { id: record.id, status: record.status, ...record.result };
    } finally { this.busy = false; }
  }
}
