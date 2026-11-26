import { readFileSync } from 'node:fs';
import { digest, requireThat } from './contracts.mjs';
import { compileManagementEvidenceSchema } from './content-management-schema.mjs';

// Immutable source-only contract import. Schema compatibility does not prove
// acquisition, register routes, issue a consumer binding or admit a report.
const source = JSON.parse(readFileSync(new URL('../contracts/content-management-evidence-source.json', import.meta.url)));
const load = name => {
  const bytes = readFileSync(new URL(`../contracts/${name}`, import.meta.url));
  requireThat(digest(bytes) === source.files[name]?.sha256 && source.runtimeAdoption === false && source.productionQualified === false,
    'MANAGEMENT_EVIDENCE_CONTRACT', 'The private evidence contract differs from its reviewed source.');
  const wire = JSON.parse(bytes);
  requireThat(wire.production_enabled === false && wire.consumer_binding_enabled === false,
    'MANAGEMENT_EVIDENCE_CONTRACT', 'Evidence schema import cannot activate production authority.');
  for (const [file, hash] of Object.entries(wire.frozen_dependencies))
    requireThat(digest(readFileSync(new URL(`../contracts/${file}`, import.meta.url))) === hash,
      'MANAGEMENT_EVIDENCE_CONTRACT', 'A frozen evidence dependency changed.');
  return wire;
};
export const managementReportWire = load('content-management-report-v1.json');
export const managementEvidenceWire = load('content-management-evidence-v1.json');
export const managementEvidenceTypes = Object.fromEntries(Object.keys(managementEvidenceWire.$defs).map(name => [name,
  compileManagementEvidenceSchema({ $ref: `#/$defs/${name}`, $defs: managementEvidenceWire.$defs })]));
export const managementReportTypes = Object.fromEntries(Object.keys(managementReportWire.$defs).map(name => [name,
  compileManagementEvidenceSchema({ $ref: `#/$defs/${name}`, $defs: managementReportWire.$defs })]));
