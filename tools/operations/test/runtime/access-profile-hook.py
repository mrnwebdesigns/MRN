#!/usr/bin/env python3
"""One genuine disposable callback, then freeze PHP and export redacted costs."""
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import re
import signal
import stat
import subprocess
import sys
import tempfile
import time

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('profile_parser', HERE / 'access-profile-parser.py')
parser = importlib.util.module_from_spec(spec)
spec.loader.exec_module(parser)
IMAGE = 'wordpress:6.9.4-php8.3-apache@sha256:5d2c212561c4b5442ebc4d98933a9cbadcf3dee8888ed3fd9ed44667c27cc905'
PIN = '127bbcb980400752221cfaa54bdc1420e6ef3c12'
VERIFY = '''$root="/var/www/html/wp-content/plugins/mrn-mainwp-operations-api/";
$m=json_decode(file_get_contents($root."release-source.json"),true,512,JSON_THROW_ON_ERROR);
if($m["source_commit"]!==$argv[1])exit(2);
foreach($m["files"] as $p=>$sha){if(is_link($root.$p)||hash_file("sha256",$root.$p)!==$sha)exit(3);}
echo hash_file("sha256",$root."release-source.json");'''
FREEZE = '''set -eu
test "$(cat /proc/1/comm)" = apache2
test "$(awk '/^State:/{print $2}' /proc/1/status)" = T
for f in /proc/[0-9]*/comm; do
  test -r "$f" || continue
  test "$(cat "$f")" = apache2 || continue
  pid=${f#/proc/}; pid=${pid%/comm}; test "$pid" = 1 || kill -STOP "$pid"
done
for f in /proc/[0-9]*/comm; do
  test -r "$f" || continue
  test "$(cat "$f")" = apache2 || continue
  pid=${f#/proc/}; pid=${pid%/comm}
  test "$(awk '/^State:/{print $2}' "/proc/$pid/status")" = T
done'''
RAW_INFO = '''$root="/dev/shm/mrn-access-profile";
$r=array("status"=>"root-refused","file_count"=>0,"bytes"=>0,"name"=>null);
$emit=static function($r){echo json_encode($r);};
if(is_link($root)||!is_dir($root)||(fileperms($root)&0777)!==0700){$emit($r);exit;}
$files=array_values(array_diff(scandir($root),array(".","..")));
$r["file_count"]=min(100,count($files));$r["status"]="entries-refused";
if(count($files)!==1){$emit($r);exit;}
$r["status"]="name-refused";
if(!preg_match('/^cachegrind[.]out[.][1-9][0-9]*$/D',$files[0])){$emit($r);exit;}
$p=$root."/".$files[0];$s=lstat($p);$r["status"]="kind-refused";
if(($s["mode"]&0170000)!==0100000){$emit($r);exit;}
$r["bytes"]=min(67108864,max(0,$s["size"]));$r["status"]="size-refused";
if($s["size"]<1||$s["size"]>33554432){$emit($r);exit;}
$r["status"]="ready";$r["name"]=$files[0];$emit($r);'''
RAW_READ = '''$p="/dev/shm/mrn-access-profile/".$argv[1];
if(!preg_match('/^cachegrind[.]out[.][1-9][0-9]*$/D',$argv[1])||is_link($p)||!is_file($p))exit(2);
$f=fopen($p,"rb");echo stream_get_contents($f,33554433);fclose($f);'''


def command(*args, timeout=15, maximum=65536):
    result = subprocess.run(args, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=timeout, check=True)
    if len(result.stdout) > maximum:
        raise ValueError('Excessive diagnostic command output')
    return result.stdout


def private(path, maximum):
    info = path.lstat()
    if not stat.S_ISREG(info.st_mode) or stat.S_IMODE(info.st_mode) != 0o600 or info.st_size > maximum:
        raise ValueError('Private bounded regular diagnostic file required')
    return path.read_bytes()


def main():
    os.umask(0o077)
    assert os.environ.get('GITHUB_ACTIONS') == 'true'
    assert os.environ.get('MRN_EXISTING_ACCESS_PROFILE_ONLY') == '1'
    assert len(sys.argv) == 5
    directory = Path(sys.argv[1])
    assert directory.is_absolute() and directory.resolve() == directory
    assert sys.argv[3] == str(directory / 'existing-binding-v2/wp-fixture-cli')
    assert sys.argv[4] == str(directory / 'existing-binding-v2/context.json')
    script = Path(sys.argv[3]).read_text()
    matches = set(re.findall(r'exec docker exec (mrn-api-[0-9]+-[0-9]+-existing-v2-cli) ', script))
    assert len(matches) == 1
    container = matches.pop()[:-3] + 'wp'
    build = Path(os.environ['MRN_ACCESS_PROFILER_BUILD'])
    output = Path(os.environ['MRN_ACCESS_PROFILE_REPORT'])
    assert output.is_absolute() and not output.exists()
    toolchain = json.loads(private(build / 'toolchain.json', 4096))
    assert set(toolchain) == {'schema', 'image', 'xdebug_commit', 'xdebug_tree', 'extension_sha256', 'php', 'extension', 'php_api'}
    assert toolchain['schema'] == 1 and toolchain['image'] == IMAGE and toolchain['xdebug_commit'] == PIN
    assert toolchain['extension'] == '3.5.3' and re.fullmatch(r'8\.3\.[0-9]+', toolchain['php'])
    assert toolchain['php_api'] == 'nts' and re.fullmatch('[a-f0-9]{40}', toolchain['xdebug_tree'])
    assert hashlib.sha256(private(build / 'xdebug.so', 16 * 1024 * 1024)).hexdigest() == toolchain['extension_sha256']
    lock = json.loads((HERE / 'sources.json').read_text())
    report = {'schema': 1, 'result': 'PROFILE_INCOMPLETE', 'scope': 'disposable-access-profile-only',
              'acceptance': False, 'native_capture': False, 'full_source': False, 'admission': False,
              'live_google': False, 'production_changes': False, 'source': lock, 'toolchain': toolchain,
              'capture_cap_bytes': 67108864, 'parser_cap_bytes': parser.MAXIMUM,
              'selected_requests': 0, 'cleanup': False, 'source_unchanged': False}
    owned = False
    stage = 'clone-validation'
    started = time.monotonic()
    # An outer timeout must enter cleanup too. The parent's hard kill remains.
    def interrupted(_signum, _frame):
        raise TimeoutError('Disposable diagnostic interrupted')
    signal.signal(signal.SIGTERM, interrupted)
    with tempfile.TemporaryDirectory(prefix='mrn-private-profile-') as temporary:
        temporary = Path(temporary)
        try:
            # Never retain/print full inspect (it contains synthetic credentials).
            state = json.loads(command('docker', 'inspect', container))[0]
            expected_image = command('docker', 'image', 'inspect', '--format', '{{.Id}}', IMAGE).decode().strip()
            assert state['Image'] == expected_image
            assert state['Name'] == '/' + container and state['State']['Running'] is True
            env = set(state['Config']['Env'])
            assert {'GITHUB_ACTIONS=true', 'MRN_DISPOSABLE_API_FIXTURE=1', 'MRN_DISPOSABLE_EXISTING_BINDING_TEST=1'} <= env
            assert 0 < state['HostConfig']['ShmSize'] <= report['capture_cap_bytes']
            assert not any(m['Destination'].startswith('/dev/shm') for m in state['Mounts'])
            owned = True
            stage = 'source-before'
            before = command('docker', 'exec', container, 'php', '-r', VERIFY, lock['provider']['commit']).decode()
            assert re.fullmatch('[a-f0-9]{64}', before)
            assert before == json.loads(Path(sys.argv[4]).read_text())['pins']['installed_manifest_sha256']
            # Fresh exclusive directory; never alter or purge shared /dev/shm.
            command('docker', 'exec', container, 'sh', '-c',
                    '. /etc/apache2/envvars; test "$(stat -f -c %T /dev/shm)" = tmpfs; '
                    'test ! -e /dev/shm/mrn-access-profile; mkdir -m 700 /dev/shm/mrn-access-profile; '
                    'chown "$APACHE_RUN_USER:$APACHE_RUN_GROUP" /dev/shm/mrn-access-profile')
            command('docker', 'cp', str(build / 'xdebug.so'), container + ':/tmp/mrn-access-profile-xdebug.so')
            ini = temporary / '99-mrn-access-profile.ini'
            ini.write_text('zend_extension=/tmp/mrn-access-profile-xdebug.so\n'
                           'xdebug.mode=profile\nxdebug.start_with_request=trigger\n'
                           'xdebug.trigger_value=mrn-access-profile-only\nxdebug.profiler_append=0\n'
                           'xdebug.use_compression=0\nxdebug.output_dir=/dev/shm/mrn-access-profile\n'
                           'xdebug.profiler_output_name=cachegrind.out.%p\nxdebug.log=\n')
            command('docker', 'cp', str(ini), container + ':/usr/local/etc/php/conf.d/99-mrn-access-profile.ini')
            command('docker', 'exec', container, 'sh', '-c',
                    'chmod 644 /tmp/mrn-access-profile-xdebug.so /usr/local/etc/php/conf.d/99-mrn-access-profile.ini; apache2ctl -k graceful')
            # The same hook, credentials, signatures and per-call deadlines.
            # Its diagnostic branch always exits nonzero to stop parent audits.
            result = subprocess.run(['node', str(HERE / 'check-existing-binding.mjs'), *sys.argv[1:]],
                                    timeout=max(1, 280 - (time.monotonic() - started)), check=False)
            assert result.returncode != 0
            work = directory / 'actual-gateway-existing-binding-v2'
            report['selected_requests'] = int(private(work / 'access-profile-claimed', 13) == b'PROFILE_ONLY\n')
            response_path = work / 'access-profile-response.json'
            response = {'profile_header': False, 'status': None}
            if response_path.exists():
                response = json.loads(private(response_path, 1024))
                assert set(response) == {'profile_header', 'status'}
                assert type(response['profile_header']) is bool and type(response['status']) is int and 100 <= response['status'] <= 599
            report['selected_response'] = response
            stage = 'source-after'
            after = command('docker', 'exec', container, 'php', '-r', VERIFY, lock['provider']['commit']).decode()
            report['source_unchanged'] = before == after
            # Freeze Apache parent and workers before reading; no HTTP work can
            # continue. docker cp cannot read /dev/shm reliably. A separate
            # `php -n` diagnostic process reads only the fixed private profile;
            # it loads neither WordPress nor the profiler/configuration.
            stage = 'freeze'
            # PID-namespace init cannot be stopped from inside its namespace.
            # Docker sends SIGSTOP from the ancestor; this is not SIGKILL.
            command('docker', 'kill', '--signal=SIGSTOP', container)
            command('docker', 'exec', container, 'sh', '-c', FREEZE)
            report['php_workers_frozen'] = True
            stage = 'collect'
            info = json.loads(command('docker', 'exec', container, 'php', '-n', '-r', RAW_INFO, maximum=1024))
            assert set(info) == {'status', 'file_count', 'bytes', 'name'}
            assert info['status'] in ('root-refused', 'entries-refused', 'name-refused', 'kind-refused', 'size-refused', 'ready')
            assert type(info['file_count']) is int and 0 <= info['file_count'] <= 100
            assert type(info['bytes']) is int and 0 <= info['bytes'] <= report['capture_cap_bytes']
            report['raw_metadata'] = {key: info[key] for key in ('status', 'file_count', 'bytes')}
            assert info['status'] == 'ready' and info['file_count'] == 1
            assert re.fullmatch(r'cachegrind\.out\.[1-9][0-9]*', info['name'])
            assert type(info['bytes']) is int and 0 < info['bytes'] <= parser.MAXIMUM
            report['raw_file_count'] = 1
            report['raw_file_bytes'] = info['bytes']
            raw = command('docker', 'exec', container, 'php', '-n', '-r', RAW_READ, info['name'], maximum=parser.MAXIMUM)
            assert len(raw) == info['bytes']
            stage = 'parse'
            report['profile'] = parser.parse_profile(io.BytesIO(raw))
            del raw
            # A transport timeout may hide the HTTP header; retain the complete
            # instrumented costs, but do not call that a completed profile run.
            if report['profile']['representative_path'] and response['profile_header'] and report['source_unchanged']:
                report['result'] = 'PROFILE_ONLY'
        except Exception as error:
            # Exception messages/arguments may contain private fixture data.
            report['result'] = 'PROFILE_INCOMPLETE'
            report['failure_stage'] = stage
            if stage == 'collect' and isinstance(error, subprocess.CalledProcessError):
                report['collect_returncode'] = max(-255, min(255, error.returncode))
            # Only these local fixed parser categories may leave the fixture.
            categories = ('Profile integer out of bounds', 'Malformed profile reference',
                          'Duplicate or excessive profile references', 'Unresolved profile reference',
                          'Oversized or truncated profile', 'Malformed profile text', 'Unknown profile version',
                          'Unqualified profiler or PHP version', 'Missing profile command', 'Multiple profile parts',
                          'Unknown profile positions', 'Unknown profile events', 'Malformed profile summary',
                          'Malformed profile cost', 'Trailing profile data', 'Unexpected profile structure',
                          'Excessive profile lines', 'Incomplete profile')
            if stage == 'parse' and type(error) is ValueError and str(error) in categories:
                report['parser_failure'] = str(error)
        finally:
            if owned:
                try:
                    command('docker', 'rm', '-f', container)
                    report['cleanup'] = True
                except Exception:
                    report['result'] = 'PROFILE_INCOMPLETE'
            encoded = json.dumps(report, sort_keys=True) + '\n'
            assert len(encoded.encode()) <= 16384
            with output.open('x') as stream:
                stream.write(encoded)
    print(report['result'] + ': diagnostic only; normal acceptance remains unverified.')
    return 1  # Never let this diagnostic satisfy the ordinary provider hook.


if __name__ == '__main__':
    try:
        sys.exit(main())
    except Exception:
        print('PROFILE_INCOMPLETE: setup failed; private diagnostics withheld.', file=sys.stderr)
        sys.exit(1)
