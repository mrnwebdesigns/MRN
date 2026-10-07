"""Narrow GitHub REST adapter for MRN-owned, Dev-only enrollment.

Tokens stay in memory. API requests do not follow redirects with authorization.
No GitHub CLI, account-wide website credential, or legacy uploader is used.
"""
import base64
import io
import json
import urllib.error
import urllib.parse
import urllib.request
import zipfile


class GitHubError(RuntimeError):
    def __init__(self, status):
        self.status = status
        super().__init__(f'GitHub enrollment request failed (HTTP {status}); inspect permissions or private service logs')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class GitHub:
    def __init__(self, token, repository, owner_type='User'):
        self.token, self.repository = token, repository
        if owner_type not in ('User', 'Organization'):
            raise ValueError('Unsupported GitHub owner type')
        self.owner_type = owner_type
        self.prefix = '/repos/' + repository
        self.opener = urllib.request.build_opener(NoRedirect)

    def request(self, method, path, data=None, missing=False):
        if not path.startswith('/') or path.startswith('//') or '..' in path:
            raise ValueError('Invalid GitHub API path')
        request = urllib.request.Request('https://api.github.com' + path,
            data=None if data is None else json.dumps(data).encode(), method=method,
            headers={'Authorization': 'Bearer ' + self.token, 'Accept': 'application/vnd.github+json',
                     'Content-Type': 'application/json', 'X-GitHub-Api-Version': '2022-11-28'})
        try:
            with self.opener.open(request, timeout=30) as response:
                body = response.read(8 * 1024 * 1024 + 1)
                if len(body) > 8 * 1024 * 1024:
                    raise ValueError('GitHub response exceeds enrollment limit')
                return json.loads(body) if body else None
        except urllib.error.HTTPError as error:
            if missing and error.code == 404:
                return None
            raise GitHubError(error.code) from None
        except (OSError, urllib.error.URLError):
            raise RuntimeError('GitHub request outcome unavailable; enrollment will reconcile before retrying') from None

    def ensure_repository(self, binding):
        repo = self.request('GET', self.prefix, missing=True)
        description = 'MRN Dev enrollment ' + binding
        if repo is None:
            owner, name = self.repository.split('/')
            identity = self.request('GET', '/users/' + owner)
            if identity.get('login', '').lower() != owner.lower() or identity.get('type') != self.owner_type:
                raise ValueError('Configured GitHub owner type does not match its current identity')
            if self.owner_type == 'User':
                actor = self.request('GET', '/user')
                if actor.get('login', '').lower() != owner.lower() or actor.get('type') != 'User':
                    raise ValueError('User-owned repository creation requires the exact MRN owner identity')
                path = '/user/repos'
            else:
                path = '/orgs/' + owner + '/repos'
            repo = self.request('POST', path, {
                'name': name, 'private': True, 'auto_init': True,
                'description': description, 'has_wiki': False, 'has_projects': False})
        if (repo.get('full_name', '').lower() != self.repository.lower() or repo.get('private') is not True
                or repo.get('description') != description or repo.get('fork') or repo.get('archived')
                or repo.get('owner', {}).get('type') != self.owner_type):
            raise ValueError('Existing repository is not owned by this enrollment; no source or settings were replaced')
        if repo.get('default_branch') != 'main':
            # Only this freshly created/resumed repository is eligible; no branch renaming.
            raise ValueError('Enrollment repository must use main as its default branch')
        head = self.head()
        commit = self.request('GET', self.prefix + '/git/commits/' + head)
        tree = self.request('GET', self.prefix + '/git/trees/' + commit['tree']['sha'] + '?recursive=1')
        if tree.get('truncated') or [(row.get('path'), row.get('type')) for row in tree.get('tree', [])] != [('README.md', 'blob')]:
            raise ValueError('Existing repository contains work beyond the enrollment seed; refusing to import or overwrite it')
        return repo

    def head(self):
        return self.request('GET', self.prefix + '/git/ref/heads/main')['object']['sha']

    def make_commit(self, base, files, message):
        parent = self.request('GET', self.prefix + '/git/commits/' + base)
        entries = []
        for path, content in sorted(files.items()):
            blob = self.request('POST', self.prefix + '/git/blobs', {
                'content': base64.b64encode(content).decode(), 'encoding': 'base64'})
            entries.append({'path': path, 'mode': '100644', 'type': 'blob', 'sha': blob['sha']})
        tree = self.request('POST', self.prefix + '/git/trees', {'base_tree': parent['tree']['sha'], 'tree': entries})
        return self.request('POST', self.prefix + '/git/commits', {
            'message': message, 'tree': tree['sha'], 'parents': [base]})['sha']

    def publish(self, base, commit):
        current = self.head()
        if current == commit:
            return
        if current != base:
            raise ValueError('main advanced outside enrollment; refusing to overwrite work')
        self.request('PATCH', self.prefix + '/git/refs/heads/main', {'sha': commit, 'force': False})
        if self.head() != commit:
            raise ValueError('Published enrollment commit did not read back exactly')

    def variable(self, name, value, environment=None):
        prefix = self.prefix + ('/environments/' + environment if environment else '/actions') + '/variables'
        prior = self.request('GET', prefix + '/' + name, missing=True)
        # GitHub rejects empty variable values. Absence is the workflow's empty
        # default, so clear one-commit authorization with a verified deletion.
        if value == '':
            if prior:
                self.request('DELETE', prefix + '/' + name)
            if self.request('GET', prefix + '/' + name, missing=True) is not None:
                raise ValueError('GitHub variable deletion readback mismatch: ' + name)
            return
        if prior and prior.get('value') == value:
            return
        self.request('PATCH' if prior else 'POST', prefix + ('/' + name if prior else ''),
                     {'name': name, 'value': value})
        if self.request('GET', prefix + '/' + name).get('value') != value:
            raise ValueError('GitHub variable readback mismatch: ' + name)

    def secret(self, name, value, environment=None):
        # PyNaCl implements the libsodium sealed box required by GitHub.
        from nacl.public import PublicKey, SealedBox
        prefix = self.prefix + ('/environments/' + environment if environment else '/actions') + '/secrets'
        key = self.request('GET', prefix + '/public-key')
        encrypted = SealedBox(PublicKey(base64.b64decode(key['key']))).encrypt(value.encode())
        self.request('PUT', prefix + '/' + name,
                     {'key_id': key['key_id'], 'encrypted_value': base64.b64encode(encrypted).decode()})

    def configure(self, variables, secrets):
        self.request('PUT', self.prefix + '/environments/dev', {
            'deployment_branch_policy': {'protected_branches': False, 'custom_branch_policies': True}})
        policies = self.request('GET', self.prefix + '/environments/dev/deployment-branch-policies')['branch_policies']
        if any(row.get('name') != 'main' or row.get('type', 'branch') != 'branch' for row in policies):
            raise ValueError('Unexpected Dev environment policy; operator review required')
        if not policies:
            self.request('POST', self.prefix + '/environments/dev/deployment-branch-policies', {'name': 'main', 'type': 'branch'})
        self.request('PUT', self.prefix + '/actions/permissions/workflow',
                     {'default_workflow_permissions': 'read', 'can_approve_pull_request_reviews': False})
        for name, value in variables.items():
            self.variable(name, str(value), 'dev')
        for name, value in secrets.items():
            self.secret(name, value, None if name == 'MRN_QA_ENGINE_TOKEN' else 'dev')

    def grant_teams(self, teams):
        org, _ = self.repository.split('/')
        for team in teams:
            path = '/orgs/' + org + '/teams/' + team + '/repos/' + self.repository
            self.request('PUT', path, {'permission': 'push'})
            result = self.request('GET', path)
            if not result.get('permissions', {}).get('push'):
                raise ValueError('Developer team access was not verified')

    def grant_access(self, teams, collaborators):
        if teams and self.owner_type != 'Organization':
            raise ValueError('GitHub user accounts cannot grant organization team access')
        self.grant_teams(teams)
        ready = True
        for login in collaborators:
            path = self.prefix + '/collaborators/' + login
            permission = self.request('GET', path + '/permission', missing=True)
            if permission and permission.get('permission') in ('write', 'admin'):
                continue
            invitations = self.request('GET', self.prefix + '/invitations?per_page=100')
            if len(invitations) >= 100:
                raise ValueError('Invitation inventory exceeds the enrollment limit')
            pending = [row for row in invitations if (row.get('invitee') or {}).get('login', '').lower() == login.lower()]
            if len(pending) > 1 or (pending and pending[0].get('permissions') not in ('write', 'admin')):
                raise ValueError('Unexpected pending developer invitation; operator review required')
            if not pending:
                self.request('PUT', path, {'permission': 'push'})
            # An invitation is not access. Keep polling the same invitation;
            # do not repeatedly send invitations or report ready before acceptance.
            permission = self.request('GET', path + '/permission', missing=True)
            if not permission or permission.get('permission') not in ('write', 'admin'):
                ready = False
        return ready

    def dispatch(self, enrollment_id):
        self.request('POST', self.prefix + '/actions/workflows/site-deploy.yml/dispatches', {
            'ref': 'main', 'inputs': {'target': 'dev', 'mode': 'deploy', 'source_branch': 'main',
                                    'qualify_dev': True, 'enrollment_id': enrollment_id}})

    def runs(self, workflow, sha=None):
        query = {'per_page': 100}
        if sha:
            query['head_sha'] = sha
        return self.request('GET', self.prefix + '/actions/workflows/' + workflow + '/runs?' +
                            urllib.parse.urlencode(query))['workflow_runs']

    def deployment_evidence(self, run, sha):
        artifacts = self.request('GET', self.prefix + '/actions/runs/' + str(run['id']) + '/artifacts?per_page=100')['artifacts']
        matches = [row for row in artifacts if row['name'] == 'site-deployment-dev-' + sha and not row.get('expired')]
        if len(matches) != 1:
            raise ValueError('Exact deployment evidence artifact is missing or ambiguous')
        return self.evidence(matches[0]['id'])

    def proof(self, run, sha, url, slug, qualification=False, signal_number=None, prior_release=None):
        if run.get('status') != 'completed':
            return None
        if run.get('conclusion') != 'success':
            raise ValueError('Enrollment deployment failed; review the GitHub run before resuming')
        jobs = self.request('GET', self.prefix + '/actions/runs/' + str(run['id']) + '/jobs?per_page=100')['jobs']
        names = {step['name']: step.get('conclusion') for job in jobs for step in job.get('steps', [])}
        for name in ('Bind successful QA to this commit', 'Verify browser-loaded assets and responsive layout contracts', 'Run runtime QA'):
            if names.get(name) != 'success':
                raise ValueError('Enrollment requires passing source, browser and runtime QA: ' + name)
        receipt, browser, runtime = self.deployment_evidence(run, sha)
        if runtime != {'outcome': 'success', 'source_sha': sha, 'environment': 'dev', 'url': url}:
            raise ValueError('Runtime acceptance did not pass for the enrolled site and commit')
        if (receipt.get('status') != 'public-verified' or receipt.get('source_sha') != sha
                or receipt.get('repository') != self.repository or receipt.get('environment') != 'dev'
                or receipt.get('url') != url or receipt.get('slug') != slug
                or not receipt.get('current', {}).get('release_id')
                or receipt.get('transfer_backup', {}).get('valid') is not True):
            raise ValueError('Deployment evidence is not bound to the enrolled site and commit')
        order = receipt.get('deployment_order', {})
        if signal_number is not None and (order.get('source_run_number') != signal_number or order.get('event') != 'workflow_run'
                or str(order.get('run_id')) != str(run['id']) or order.get('source_branch') != 'main'):
            raise ValueError('Push pilot receipt does not belong to the expected automatic source signal')
        required = {'adopt', 'stage', 'activate', 'rollback-test', 'reactivate'} if qualification else {'stage', 'activate'}
        if prior_release:
            if not qualification or receipt.get('previous', {}).get('release_id') != prior_release:
                raise ValueError('Requalification did not start from the authorized retained release')
            required.remove('adopt')
        steps = {row['operation']: row.get('backup', {}).get('valid') for row in receipt.get('steps', [])}
        if not all(steps.get(key) is True for key in required):
            raise ValueError('Missing verified backups or rollback exercise')
        if not browser or any(row.get('errors') or not row.get('assets') for row in browser):
            raise ValueError('Released browser asset delivery did not pass')
        if {row.get('viewport') for row in browser} != {'desktop', 'tablet', 'mobile'}:
            raise ValueError('Responsive browser evidence is incomplete')
        return {'run_id': run['id'], 'run_url': run['html_url'], 'source_sha': sha,
                'release_id': receipt['current']['release_id'], 'receipt': receipt, 'browser_checks': len(browser)}

    def evidence(self, artifact_id):
        request = urllib.request.Request('https://api.github.com' + self.prefix + '/actions/artifacts/' + str(artifact_id) + '/zip',
                                        headers={'Authorization': 'Bearer ' + self.token})
        try:
            self.opener.open(request, timeout=30)
        except urllib.error.HTTPError as error:
            if error.code != 302:
                raise GitHubError(error.code) from None
            location = error.headers.get('Location', '')
        else:
            raise ValueError('Expected GitHub artifact redirect')
        parsed = urllib.parse.urlsplit(location)
        if parsed.scheme != 'https' or parsed.username or parsed.password or not parsed.hostname:
            raise ValueError('Unsafe artifact location')
        # No authorization is forwarded to the signed download URL.
        with urllib.request.build_opener(NoRedirect).open(location, timeout=60) as response:
            body = response.read(64 * 1024 * 1024 + 1)
        if len(body) > 64 * 1024 * 1024:
            raise ValueError('Enrollment evidence exceeds size limit')
        with zipfile.ZipFile(io.BytesIO(body)) as archive:
            def member(name):
                matches = [row for row in archive.infolist() if row.filename == name]
                if len(matches) != 1 or matches[0].file_size > 8 * 1024 * 1024:
                    raise ValueError('Invalid evidence member: ' + name)
                return json.loads(archive.read(matches[0]))
            return member('site-deployment.json'), member('site-browser/browser-assets.json'), member('site-runtime-result.json')
