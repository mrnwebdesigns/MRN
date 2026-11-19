"""Reference wiring for an enrolled child theme on a site-owner host.

Install and import this into a protected configure() file. The injected provider
implements the site-specific methods shown here and supplies fixed connection
settings. No site credentials, production permissions or target are included.
"""
from release_v1.host import AtomicCode, WordPressMigrations
from release_v1.proof import playwright_proof


class HostAdapter:
    def __init__(self, provider, store, source_path, wp_root, proof_script):
        self.provider = provider
        self.code = AtomicCode(store, source_path)
        self.content = WordPressMigrations(wp_root, provider.identify)
        self.proof_script = proof_script

    def identify(self):
        return self.provider.identify()

    def current(self):
        return self.code.current()

    def authorize(self, phase, request):
        return self.provider.authorize(phase, request)

    def local_health_options(self):
        return self.provider.local_health_options()

    def preflight(self, request, code, manifest, urls):
        self.code.check(code, manifest)
        return self.provider.qualify(request, manifest['code_sha256'], urls)

    def backup(self, request, challenge, purpose, fence):
        return self.provider.verified_database_backup(request, challenge, purpose, fence)

    def verify_backup(self, request, backup):
        return self.provider.backup_is_recoverable(request, backup)

    def activate(self, request, code, manifest, current, backup, fence):
        self.code.activate(code, manifest, current)

    def proof(self, request, release, urls):
        return playwright_proof(self.proof_script, release, urls)

    def migrate(self, request, row, root, files, fingerprint, backup, fence):
        return self.content.apply(request, row, root, files, fingerprint, backup, fence)

    def refresh(self, request, urls, fence):
        return self.provider.refresh_html(request, urls, fence)

    def verify(self, request, release, urls, migrations):
        return self.provider.verify_public(request, release, urls, migrations)

    def rollback(self, request, prior, current, backup, fence):
        self.code.rollback(prior, current)

    def quiescent(self, request, intents, current):
        return self.provider.reconcile_jobs(request, intents, current)
