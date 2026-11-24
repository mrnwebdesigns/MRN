# MRN Dev Server Runbook (Quick Copy/Paste)

Host:
- `mrndev`
- `167.99.54.77`

## 1) Fast Triage (Server Console)
```bash
date
uptime
free -m
vmstat 1 5
ps -eo pcpu,pmem,cmd --sort=-pcpu | head -n 20
tail -n 120 /home/mrndev-stack-manager/stack/runtime/stack-load-alerts-user.log
```

## 2) SSH Recovery (Server Console)
```bash
systemctl status ssh
ss -ltnp | grep ':22'
sshd -t
systemctl restart ssh
```

## 3) fail2ban Checks (Server Console)
```bash
fail2ban-client status
fail2ban-client status sshd
```

Unban IP:
```bash
fail2ban-client set sshd unbanip <YOUR_PUBLIC_IP>
```

Temporary whitelist (if needed):
```bash
cat >/etc/fail2ban/jail.d/sshd-local.conf <<'EOF'
[sshd]
ignoreip = 127.0.0.1/8 ::1 <YOUR_PUBLIC_IP>/32
EOF
systemctl restart fail2ban
```

Remove temporary whitelist:
```bash
cat >/etc/fail2ban/jail.d/sshd-local.conf <<'EOF'
[sshd]
ignoreip = 127.0.0.1/8 ::1
EOF
systemctl restart fail2ban
```

## 4) kyle User Repair (Server Console)
```bash
id kyle
getent passwd kyle
passwd -S kyle
chage -l kyle
install -d -m 700 -o kyle -g kyle /home/kyle/.ssh
touch /home/kyle/.ssh/authorized_keys
chown kyle:kyle /home/kyle/.ssh/authorized_keys
chmod 600 /home/kyle/.ssh/authorized_keys
```

Key login test (Local machine):
```bash
ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes kyle@167.99.54.77 "whoami && hostname"
```

## 5) SSH Hardening (Server Console)
```bash
cat >/etc/ssh/sshd_config.d/00-mrn-hardening.conf <<'EOF'
PermitRootLogin prohibit-password
PubkeyAuthentication yes
PasswordAuthentication no
KbdInteractiveAuthentication no
UsePAM yes
EOF
chmod 644 /etc/ssh/sshd_config.d/00-mrn-hardening.conf
sshd -t && systemctl restart ssh
sshd -T | egrep 'passwordauthentication|kbdinteractiveauthentication|pubkeyauthentication|permitrootlogin'
```

## 6) Monitor Script Ops

Status (Server):
```bash
/home/mrndev-stack-manager/stack/scripts/stack-load-alerts-user.sh --status
```

Manual run (Server):
```bash
/home/mrndev-stack-manager/stack/scripts/stack-load-alerts-user.sh --run
```

Test Slack notification (Server):
```bash
/home/mrndev-stack-manager/stack/scripts/stack-load-alerts-user.sh --test
```

Tail runtime log (Server):
```bash
tail -n 120 /home/mrndev-stack-manager/stack/runtime/stack-load-alerts-user.log
```

## 7) Server Config Sync (Local)
```bash
cd /Users/khofmeyer/Development/MRN
./stack/scripts/sync-mrndev-server-config.sh --pull
./stack/scripts/sync-mrndev-server-config.sh --push --dry-run
./stack/scripts/sync-mrndev-server-config.sh --push
```

## 8) Root Export Install (Server Console, One-Time)
```bash
install -o root -g root -m 750 /home/mrndev-stack-manager/stack/scripts/stack-config-export.sh /usr/local/sbin/stack-config-export.sh
install -o root -g root -m 644 /home/mrndev-stack-manager/stack/runtime/stack-config-export.cron /etc/cron.d/stack-config-export
/usr/local/sbin/stack-config-export.sh
ls -l /usr/local/sbin/stack-config-export.sh /etc/cron.d/stack-config-export /home/mrndev-stack-manager/stack/server-config-export/usr/local/sbin/stack-load-alerts.sh
```

## 9) Site Deploy Keys (Server Console)

Symptom: a site's GitHub deploy fails with `Permission denied (publickey)` while the MRN
operator key still logs in. Cause: CloudPanel left `/home/<site-user>` group-writable (770)
because another CloudPanel SSH user shares the site group, so sshd ignores
`~/.ssh/authorized_keys`. `chmod g-w` fixes it only until CloudPanel resets the mode.

Lasting fix: serve the site's deploy key through the AuthorizedKeysCommand, which does not
read the home folder. Install or refresh the command first, as root, with this repo's
`stack/server-config/mrndev/install-site-owner-authorized-keys-command.sh` (copy it to the
server if the server's Stack copy is older). It also creates `/etc/ssh/mrn-site-deploy-keys`:
```bash
bash ./install-site-owner-authorized-keys-command.sh
```

Register a site owner's deploy key (public half only; one key per site user):
```bash
install -o root -g root -m 644 /path/to/deploy-key.pub /etc/ssh/mrn-site-deploy-keys/<site-user>.pub
/usr/local/sbin/mrn-site-owner-authorized-keys <site-user>
```
The check prints the shared site-owner key and then the deploy key. Keys are served only to
CloudPanel site owners, never to extra CloudPanel SSH users, and only from root-owned files
that group and others cannot write. Remove a key by deleting its file.

## 10) Capacity Decision Gate

Scale resources only if sustained:
- `load/core > 1.25` for 20+ minutes repeatedly
- `mem_avail < 25%` with active swap IO
- repeated user-facing timeouts during incidents
