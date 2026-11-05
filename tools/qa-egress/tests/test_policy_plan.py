import copy
import importlib.util
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('qa_egress_policy', ROOT / 'policy_plan.py')
plan = importlib.util.module_from_spec(spec)
spec.loader.exec_module(plan)

POLICY = '''{
 // Administrator access must survive; brackets in comments: ] }
 "grants": [{"src":["admin@example.test"],"dst":["tag:infrastructure"],"ip":["tcp:22"]},],
 "tagOwners": {"tag:qa":["autogroup:admin"],},
 "tests": [{"src":"admin@example.test","deny":["tag:infrastructure:80"]},],
 "ssh": [{"action":"check","src":["admin@example.test"],"dst":["tag:qa"],"users":["mrn-admin"]}],
 "groups": {"group:admins":["admin@example.test"]},
 "note": "quoted brackets { [ ] } and escaped quote \\\"",
}'''


class PolicyPlan(unittest.TestCase):
    def test_only_scoped_additions_preserve_administrative_policy_and_comments(self):
        before = plan.decode(POLICY)
        text = plan.prepare(POLICY)
        after = plan.decode(text)
        self.assertIn('// Administrator access must survive; brackets in comments: ] }', text)
        for key in ('ssh', 'groups', 'note'):
            self.assertEqual(after[key], before[key])
        self.assertEqual(after['grants'][:-1], before['grants'])
        self.assertEqual(after['tests'][:-2], before['tests'])
        self.assertEqual(after['tagOwners']['tag:qa'], before['tagOwners']['tag:qa'])
        self.assertEqual(after['grants'][-1], plan.GRANT)

    def test_repeat_preparation_is_byte_for_byte_idempotent(self):
        first = plan.prepare(POLICY)
        self.assertEqual(plan.prepare(first), first)

    def test_changed_existing_ci_permission_is_never_adopted(self):
        for key in ('grants', 'tests'):
            data = plan.decode(POLICY)
            data[key].append({'src': [plan.CI_TAG], 'dst': ['*'], 'ip': ['*']})
            with self.subTest(key=key), self.assertRaisesRegex(ValueError, 'differs'):
                plan.prepare(json.dumps(data))

    def test_existing_ci_tag_owned_by_a_different_identity_blocks(self):
        data = plan.decode(POLICY)
        data['tagOwners'][plan.CI_TAG] = ['tag:unrelated']
        with self.assertRaisesRegex(ValueError, 'ownership differs'):
            plan.prepare(json.dumps(data))

    def test_existing_broad_grants_or_acls_require_manual_review(self):
        for name in ('grants', 'acls'):
            for source in ('*', 'autogroup:tagged'):
                data = plan.decode(POLICY)
                data.setdefault(name, []).append({'src': [source], 'dst': ['*'], 'ip': ['*']})
                with self.subTest(name=name, source=source), self.assertRaisesRegex(ValueError, 'Broad existing'):
                    plan.prepare(json.dumps(data))

    def test_duplicate_keys_and_incomplete_policies_fail_closed(self):
        with self.assertRaises(ValueError):
            plan.prepare('{"grants":[],"grants":[],"tests":[],"tagOwners":{}}')
        for key in ('grants', 'tests', 'tagOwners'):
            data = plan.decode(POLICY)
            del data[key]
            with self.subTest(key=key), self.assertRaises(ValueError):
                plan.prepare(json.dumps(data))

    def test_public_web_egress_grants_no_infrastructure_or_ssh_destination(self):
        self.assertEqual(plan.GRANT['dst'], ['autogroup:internet'])
        self.assertEqual(plan.GRANT['via'], ['tag:qa'])
        self.assertEqual(set(plan.GRANT['ip']), {'tcp:80', 'tcp:443', 'tcp:53', 'udp:53'})
        self.assertIn('tag:infrastructure:22', plan.DENY_TEST['deny'])
        self.assertNotIn('autoApprovers', plan.decode(plan.prepare(POLICY)))

    def test_federation_is_bound_to_private_main_workflow_and_environment(self):
        identity = plan.IDENTITY
        self.assertEqual(identity['scopes'], ['auth_keys'])
        self.assertEqual(identity['tags'], [plan.CI_TAG])
        self.assertEqual(identity['subject'],
                         'repo:mrnwebdesigns@273106862/amishmike-theme@1421114071:environment:production')
        self.assertEqual(identity['customClaimRules']['ref'], 'refs/heads/main')
        self.assertEqual(identity['customClaimRules']['repository_id'], '1421114071')
        self.assertEqual(identity['customClaimRules']['repository_visibility'], 'private')
        self.assertEqual(identity['customClaimRules']['workflow_ref'],
                         'mrnwebdesigns/amishmike-theme/.github/workflows/deploy-kinsta.yml@refs/heads/main')


if __name__ == '__main__':
    unittest.main()
