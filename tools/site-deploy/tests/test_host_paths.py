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
