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
initialFds=snapshot('initial')['fds']
asset='/wp-content/mu-plugins/mrn-admin-ui-css/mrn-admin-tokens.css'
for label,prefix,url in [('loopback',base,'http://127.0.0.1:8088'),('forward',[],'http://127.0.0.1:8088'),('proxy',[],'https://admin-editor-pilot.localhost')]:
 codes=[]
 for i in range(int(sys.argv[2]) if len(sys.argv)>2 else 8):
  codes.append(run(prefix+['curl','-ksS','--max-time','10','-H','Host: admin-editor-pilot.localhost','-H','Connection: close','-o','/dev/null','-w','%{http_code}',url+asset]))
 time.sleep(2);d=snapshot(label);d['statuses']=dict((c,codes.count(c)) for c in set(codes))
 if set(codes)!={'200'}:raise RuntimeError('Non-200 asset response')
for i in range(3):time.sleep(10);snapshot('idle-'+str(i))
pathlib.Path(sys.argv[1]).write_text(json.dumps(records,indent=2)+'\n')
