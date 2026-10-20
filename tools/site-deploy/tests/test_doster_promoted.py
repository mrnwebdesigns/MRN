import copy
from pathlib import Path
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0,str(Path(__file__).parents[1]))
import kinsta
import promoted_store as promoted


class DosterCopiedRelease(unittest.TestCase):
    def setUp(self):
        self.plan = dict(kinsta.DOSTER_LIVE_TARGET, repository=kinsta.DOSTER_REPOSITORY,
                         slug='doster-and-brown-child', template='mrn-base-stack',
                         host_provider='kinsta', backup_provider='kinsta',
                         state_dir='/www/lowwd_975/deployments/mrn-site-deploy/live',
                         artifact_sha256='a'*64)
        self.promotion = dict(schema=2,doster_cutover='doster-live-20261020',
                              source_url=kinsta.DOSTER_TARGET['url'],source_environment_id=kinsta.DOSTER_DEV,
                              source_sha=promoted.DOSTER_CUTOVER_ORDER['source_sha'],
                              public_tree='759a5478cc0f41c236a580e49b9715f62b387ab618003874fdf0d71438e28505',
                              pointer=dict(schema=1,slug=self.plan['slug'],
                                           release_id='76611d9a671976f974b9391158303f8f31375a3fb18feda78d75d3eff09b2305',
                                           public_path='mrn-assets/doster-and-brown-child/922dc8722187320fb69b2265ba692ce49efa30ae92ff3b2cdafc8ef33566883b'))

    def test_only_the_reviewed_cutover_can_select_the_copied_dev_store(self):
        self.assertEqual(Path('/www/lowwd_975/deployments/mrn-site-deploy/dev'),promoted.validate(self.plan,self.promotion))
        self.assertEqual('dev',promoted.source_binding(self.plan,self.promotion)['environment'])
        for key in ('environment','repository','url','root','host','port','user','slug','template','state_dir','host_provider','backup_provider'):
            with self.subTest(key=key),self.assertRaises(ValueError):
                promoted.validate(dict(self.plan,**{key:'different'}),self.promotion)
        for key in self.promotion:
            with self.subTest(key=key),self.assertRaises(ValueError):
                promoted.validate(self.plan,dict(self.promotion,**{key:'different'}))
        for field in self.promotion['pointer']:
            p=copy.deepcopy(self.promotion);p['pointer'][field]='different'
            with self.subTest(pointer=field),self.assertRaises(ValueError):
                promoted.validate(self.plan,p)

    def test_order_cannot_be_discarded_or_replaced_with_another_qualified_run(self):
        expected=promoted.DOSTER_CUTOVER_ORDER
        self.assertTrue(promoted.source_order_matches(self.plan,self.promotion,expected))
        self.assertFalse(promoted.source_order_matches(self.plan,self.promotion,None))
        for key in expected:
            with self.subTest(key=key):
                self.assertFalse(promoted.source_order_matches(self.plan,self.promotion,dict(expected,**{key:'different'})))
        ordinary=dict(self.promotion,schema=1);ordinary.pop('doster_cutover')
        self.assertFalse(promoted.source_order_matches(self.plan,ordinary,expected))

    def test_owner_exception_requires_fresh_live_identity_without_api_fallback(self):
        adapter=kinsta.adapter('',kinsta.DOSTER_SITE,kinsta.DOSTER_LIVE,
                               kinsta.DOSTER_MANUAL_APPROVAL,kinsta.DOSTER_REPOSITORY)
        with self.assertRaises(ValueError):promoted.native_source(adapter,self.promotion,self.plan)
        adapter.identity(kinsta.DOSTER_LIVE_TARGET)
        with patch('urllib.request.urlopen',side_effect=AssertionError('No API allowed')):
            promoted.native_source(adapter,self.promotion,self.plan)
        dev=kinsta.adapter('',kinsta.DOSTER_SITE,kinsta.DOSTER_DEV,
                          kinsta.DOSTER_MANUAL_APPROVAL,kinsta.DOSTER_REPOSITORY)
        dev.identity(kinsta.DOSTER_TARGET)
        with self.assertRaises(ValueError):promoted.native_source(dev,self.promotion,self.plan)
        with self.assertRaises(ValueError):promoted.native_source(adapter,self.promotion)

    def test_current_release_cannot_be_its_own_qualification_candidate(self):
        with self.assertRaisesRegex(ValueError,'distinct candidate'):
            promoted.validate(dict(self.plan,artifact_sha256=self.promotion['pointer']['release_id']),self.promotion)
        for change in (dict(deployment_order={'run_id':'1'}),dict(adopt=True),dict(qualification=True),
                       dict(bind_imported_source='a'*40),dict(rollback_to='b'*64)):
            with self.subTest(change=change),self.assertRaises(ValueError):
                promoted.validate(dict(self.plan,**change),self.promotion)
