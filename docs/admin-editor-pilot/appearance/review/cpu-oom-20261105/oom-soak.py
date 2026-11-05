import subprocess,json,time,sys,pathlib
base=['/opt/homebrew/bin/limactl','shell','mrn-openlitespeed','--']
def run(args):
 return subprocess.check_output(args,text=True,timeout=25).strip()
def snapshot(label):
 code='''import pathlib,subprocess,json
pid=subprocess.check_output(['systemctl','show','lima-guestagent','-p','MainPID','--value'],text=True).strip()
p=pathlib.Path('/proc')/pid
s=dict(l.split(':',1) for l in (p/'status').read_text().splitlines() if ':' in l)
m=dict(l.split(':',1) for l in pathlib.Path('/proc/meminfo').read_text().splitlines() if ':' in l)
fds=list((p/'fd').iterdir())
print(json.dumps({'pid':int(pid),'fds':len(fds),'sockets':sum(f.readlink().as_posix().startswith('socket:') for f in fds),'rssKiB':int(s['VmRSS'].split()[0]),'availableKiB':int(m['MemAvailable'].split()[0]),'swapFreeKiB':int(m['SwapFree'].split()[0]),'pressure':pathlib.Path('/proc/pressure/memory').read_text().strip()}))'''
 d=json.loads(run(base+['sudo','python3','-c',code]));d.update(label=label,at=time.time());records.append(d);print(json.dumps(d),flush=True)
 if d['availableKiB']<1500000 or d['fds']>initialFds+600: raise RuntimeError('Bounded probe safety stop')
 return d
records=[];initialFds=100000
initialFds=snapshot('soak-start')['fds']
asset='https://admin-editor-pilot.localhost/wp-content/mu-plugins/mrn-admin-ui-css/mrn-admin-tokens.css'
for i in range(10):
 codes=[run(['curl','-ksS','--max-time','10','-H','Connection: close','-o','/dev/null','-w','%{http_code}',asset]) for _ in range(50)]
 if set(codes)!={'200'}:raise RuntimeError('Non-200 response')
 time.sleep(30);snapshot('batch-'+str(i+1))
 pathlib.Path(sys.argv[1]).write_text(json.dumps(records,indent=2)+'\n')
