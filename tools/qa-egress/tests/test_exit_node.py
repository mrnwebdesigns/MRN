import copy
import importlib.util
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('exit_node', Path(__file__).resolve().parents[1] / 'exit_node.py')
node = importlib.util.module_from_spec(spec)
spec.loader.exec_module(node)


class ExitNodeTests(unittest.TestCase):
    def setUp(self):
        self.before = {'services': {'api': {'image': 'existing', 'config_hash': 'private'}},
                       'prefs': {'AdvertiseRoutes': None, 'RunSSH': False, 'RouteAll': False},
                       'forwarding': {node.KEYS[0]: '1', node.KEYS[1]: '0'}}
        self.after = copy.deepcopy(self.before)
        self.after['prefs']['AdvertiseRoutes'] = ['0.0.0.0/0', '::/0']
        self.after['forwarding'] = {key: '1' for key in node.KEYS}

    def test_only_forwarding_and_exit_advertisement_change(self):
        node.verify(self.before, self.after, True)

    def test_app_image_configuration_or_restart_change_blocks(self):
        for field in ('image', 'config_hash', 'started'):
            with self.subTest(field=field):
                current = copy.deepcopy(self.after)
                current['services']['api'][field] = 'changed'
                with self.assertRaisesRegex(ValueError, 'service identity'):
                    node.verify(self.before, current, True)

    def test_unrelated_tailscale_preferences_are_preserved(self):
        for key in ('RunSSH', 'RouteAll', 'ShieldsUp'):
            current = copy.deepcopy(self.after)
            current['prefs'][key] = True
            with self.subTest(key=key), self.assertRaisesRegex(ValueError, 'preference change'):
                node.verify(self.before, current, True)

    def test_both_forwarding_families_are_required(self):
        self.after['forwarding'][node.KEYS[1]] = '0'
        with self.assertRaisesRegex(ValueError, 'Forwarding state'):
            node.verify(self.before, self.after, True)

    def test_rollback_must_restore_the_exact_prior_network_state(self):
        node.verify(self.before, copy.deepcopy(self.before), False)
        with self.assertRaises(ValueError):
            node.verify(self.before, self.after, False)

    def test_rollback_restores_network_even_when_http_health_is_unavailable(self):
        with tempfile.TemporaryDirectory() as directory:
            config = Path(directory) / 'owned.conf'
            config.write_text(node.CONTENT)
            with patch.object(node, 'CONFIG', config), \
                    patch.object(node, 'network_state', return_value=self.after), \
                    patch.object(node, 'call', return_value='') as command, \
                    patch.object(node, 'observed', side_effect=RuntimeError('HTTP health unavailable')):
                with self.assertRaisesRegex(RuntimeError, 'HTTP health'):
                    node.rollback(self.before)
                self.assertFalse(config.exists())
                self.assertEqual(command.call_args_list[0].args[0],
                                 ['tailscale', 'set', '--advertise-exit-node=false'])
                self.assertIn(['sysctl', '-w', node.KEYS[1] + '=0'],
                              [row.args[0] for row in command.call_args_list])

    def test_rollback_cannot_remove_another_administrators_routes(self):
        current = copy.deepcopy(self.after)
        current['prefs']['AdvertiseRoutes'].append('10.20.0.0/16')
        with patch.object(node, 'network_state', return_value=current), patch.object(node, 'call') as command:
            with self.assertRaisesRegex(ValueError, 'Concurrent route changes'):
                node.rollback(self.before)
            command.assert_not_called()


if __name__ == '__main__':
    unittest.main()
