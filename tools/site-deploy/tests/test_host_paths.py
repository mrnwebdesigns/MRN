import sys
from pathlib import Path
import unittest
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).parents[1]))
from host_paths import qualify_provider

class ProviderIdentity(unittest.TestCase):
    def check(self, provider, root, state, **extra):
        plan=dict(environment='live',host_provider=provider,backup_provider='updraft',url='https://example.org',root=root,state_dir=state,**extra)
        wordpress=dict(root=root,content=root+'/wp-content')
        # Provider layouts are Linux paths; macOS aliases /home locally.
        with patch.object(Path, "resolve", lambda path: path):
            return qualify_provider(plan,wordpress)

    def test_wpengine_only_allows_account_bound_protected_storage(self):
        root='/nas/content/live/account'
        valid=dict(ssh_user='account',ssh_host='account.ssh.wpengine.net')
        result=self.check('wpengine',root,root+'/_wpeprivate/mrn-site-deploy/live',**valid)
        self.assertEqual(root+'/_wpeprivate',result['protected_state_root'])
        for state in [root+'/uploads/private',root+'/_wpeprivate/mrn-site-deploy/dev']:
            with self.assertRaises(ValueError): self.check('wpengine',root,state,**valid)
        with self.assertRaises(ValueError): self.check('wpengine',root,root+'/_wpeprivate/mrn-site-deploy/live',ssh_user='other',ssh_host='other.ssh.wpengine.net')

    def test_siteground_requires_exact_public_root_and_private_storage(self):
        root='/home/customer/www/example.org/public_html'
        self.check('siteground',root,'/home/customer/private-backups/mrn-site-deploy/site-live')
        with self.assertRaises(ValueError): self.check('siteground',root,'/home/customer/www/private')
        with self.assertRaises(ValueError): self.check('siteground','/home/customer/www/other.org/public_html','/home/customer/private-backups/mrn-site-deploy/site-live')

    def test_unknown_provider_and_cloudpanel_live_remain_blocked(self):
        for provider in ['unknown','cloudpanel']:
            with self.assertRaises(ValueError): self.check(provider,'/tmp/site','/tmp/state')

    def test_kinsta_requires_native_backup_and_exact_private_sibling(self):
        plan = dict(environment='live', host_provider='kinsta', backup_provider='kinsta',
                    url='https://example.org', root='/www/example_123/public',
                    state_dir='/www/example_123/private-backups/mrn-site-deploy/live')
        wordpress = dict(root=plan['root'], content=plan['root']+'/wp-content')
        with patch.object(Path, 'resolve', lambda path: path):
            self.assertEqual('kinsta', qualify_provider(plan, wordpress)['provider'])
            for change in [dict(backup_provider='updraft'), dict(state_dir='/www/other/private-backups/mrn-site-deploy/live'),
                           dict(state_dir='/www/example_123/private-backups/mrn-site-deploy/dev')]:
                with self.assertRaises(ValueError):
                    qualify_provider(dict(plan, **change), wordpress)

    def test_staging_is_explicit_for_the_three_enrolled_providers(self):
        from host_paths import activation_environment
        for provider in ('wpengine', 'siteground', 'kinsta'):
            activation_environment('staging', provider)
        for provider in ('cloudpanel', 'nexcess', 'unknown'):
            with self.assertRaises(ValueError): activation_environment('staging', provider)

    def test_cloudpanel_requires_exact_development_origin_and_wordpress_environment(self):
        root = '/home/example/htdocs/example.mrndev.io'
        url = 'https://example.mrndev.io'
        plan = dict(environment='dev', host_provider='cloudpanel', backup_provider='updraft',
                    root=root, state_dir='/home/example/.mrn-site-deploy/dev', url=url)
        wordpress = dict(root=root, content=root+'/wp-content', siteurl=url, environment_type='development')
        with patch.object(Path, 'resolve', lambda path: path):
            self.assertEqual('cloudpanel', qualify_provider(plan, wordpress)['provider'])
            for change in (dict(environment='live'), dict(environment='staging'), dict(url='https://example.org'),
                           dict(url=url+'/subsite'), dict(url='https://ssh.example.mrndev.io'), dict(backup_provider='kinsta')):
                with self.subTest(change=change), self.assertRaises(ValueError):
                    qualify_provider(dict(plan, **change), wordpress)
            for change in (dict(environment_type='production'), dict(siteurl='https://other.mrndev.io')):
                with self.subTest(change=change), self.assertRaises(ValueError):
                    qualify_provider(plan, dict(wordpress, **change))

    def test_kinsta_staging_uses_only_the_php_readable_deployment_sibling(self):
        plan = dict(environment='staging', host_provider='kinsta', backup_provider='kinsta',
                    url='https://stg-example-migration.kinsta.cloud', root='/www/example_123/public',
                    state_dir='/www/example_123/deployments/mrn-site-deploy/staging')
        wordpress = dict(root=plan['root'], content=plan['root']+'/wp-content')
        with patch.object(Path, 'resolve', lambda path: path):
            self.assertEqual(plan['state_dir'], qualify_provider(plan, wordpress)['state_dir'])
            for change in [dict(host_provider='cloudpanel'), dict(backup_provider='updraft'),
                           dict(state_dir='/www/example_123/private-backups/mrn-site-deploy/staging'),
                           dict(state_dir='/www/other/deployments/mrn-site-deploy/staging'),
                           dict(state_dir='/www/example_123/deployments/mrn-site-deploy/live'),
                           dict(environment='preview')]:
                with self.assertRaises(ValueError): qualify_provider(dict(plan, **change), wordpress)
