#!/usr/bin/env python3
"""Exercise unmodified WordPress login handlers over HTTP in disposable local WP.

Requires a disposable MySQL database (never a client database). Credentials are
read from --database-config JSON: DB_NAME, DB_USER, DB_PASSWORD, DB_HOST.
Only loopback HTTP is used; all mail is intercepted before transport. No secrets,
response bodies, reset URLs, cookies, or account addresses are printed.
"""

import argparse
import http.cookiejar
import json
import os
from pathlib import Path
import re
import html
import secrets
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def check(condition, label):
    if not condition:
        raise AssertionError(label)
    print("PASS:", label, flush=True)


def request(opener, url, data=None):
    encoded = None if data is None else urllib.parse.urlencode(data).encode()
    try:
        response = opener.open(url, encoded, timeout=20)
    except urllib.error.HTTPError as error:
        response = error
    return response.code, response.headers, response.read().decode(errors="replace")


def php_string(value):
    return "'" + str(value).replace("\\", "\\\\").replace("'", "\\'") + "'"


def run_case(args, db, subdir, slug, split_home=False):
    root = Path(tempfile.mkdtemp(prefix="mrn-login-http-"))
    root.chmod(0o700)
    public = root / "public"
    public.mkdir()
    wp_root = public / subdir if subdir else public
    wp_root.mkdir(exist_ok=True)
    for item in args.wp_source.iterdir():
        if item.name in ("wp-admin", "wp-includes"):
            shutil.copytree(item, wp_root / item.name)
        elif item.is_file() and item.suffix == ".php" and item.name != "wp-config.php":
            shutil.copy2(item, wp_root / item.name)
    mu = wp_root / "wp-content" / "mu-plugins"
    mu.mkdir(parents=True)
    theme = args.wp_source / "wp-content" / "themes" / "twentytwentyfive"
    shutil.copytree(theme, wp_root / "wp-content" / "themes" / "twentytwentyfive")
    shutil.copy2(args.plugin_source, mu / "mrn-public-security-hardening.php")
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        port = sock.getsockname()[1]
    origin = f"http://127.0.0.1:{port}"
    base = origin + ("/" + subdir if subdir else "")
    canonical = base + "/" + slug + "/"
    prefix = "mrnlogin_" + secrets.token_hex(5) + "_"
    password = secrets.token_urlsafe(30)
    new_password = secrets.token_urlsafe(30)
    credentials = root / "setup.json"
    credentials.write_text(json.dumps({"slug": slug, "base": base, "home": origin if split_home else base}))
    credentials.chmod(0o600)
    mail_file = root / "mail.jsonl"
    config = "<?php\n"
    for key, value in db.items():
        if key not in ("DB_NAME", "DB_USER", "DB_PASSWORD", "DB_HOST"):
            raise ValueError("Unexpected database configuration key")
        config += f"define({php_string(key)}, {php_string(value)});\n"
    config += f"$table_prefix = '{prefix}';\n"
    config += "define('DISABLE_WP_CRON', true);\ndefine('WP_DEBUG', false);\n"
    config += "define('WP_ENVIRONMENT_TYPE', 'local');\n"
    config += "define('AUTH_KEY', '" + secrets.token_hex(32) + "');\n"
    config += "define('ABSPATH', __DIR__ . '/');\nrequire ABSPATH . 'wp-settings.php';\n"
    (wp_root / "wp-config.php").write_text(config)
    (wp_root / "wp-config.php").chmod(0o600)
    (mu / "test-fixtures.php").write_text("<?php\nadd_filter('pre_wp_mail', static function($result, $mail) { file_put_contents(" + php_string(mail_file) + ", json_encode($mail) . PHP_EOL, FILE_APPEND); return true; }, 10, 2);\n"
        # This is a dedicated login fixture, so MRN QA's homepage probe also
        # exercises the login screen, without testing an unrelated theme menu.
        "add_action('template_redirect', static function() { if (is_front_page()) { wp_safe_redirect(wp_login_url()); exit; } });\n")
    setup = root / "setup.php"
    setup.write_text("<?php\n$c=json_decode(file_get_contents(" + json.dumps(str(credentials)) + "),true);\nupdate_option('siteurl',$c['base']); update_option('home',$c['home']); update_option('mrn_public_security_login_slug',$c['slug']); update_option('users_can_register',1); update_option('permalink_structure','/%postname%/');\n")
    router = root / "router.php"
    router.write_text("<?php\n$p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);\n$f=__DIR__.'/public'.$p;\nif(is_file($f) || (is_dir($f) && is_file($f.'/index.php'))){return false;}\nrequire " + json.dumps(str(wp_root / "index.php")) + ";\n")
    env = dict(os.environ, WP_CLI_PHP_ARGS="-d error_reporting=22527")

    def wp(*cmd):
        result = subprocess.run(["wp", "--path=" + str(wp_root), *cmd], env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        if result.returncode:
            raise RuntimeError("Disposable WordPress CLI setup/cleanup failed; output suppressed to protect credentials")

    server = None
    installed = False
    qa_passed = True
    try:
        # Core install via CLI prompts keeps the random password out of argv.
        result = subprocess.run(["wp", "--path=" + str(wp_root), "core", "install", "--url=" + base, "--title=Login regression", "--admin_user=mrn_login_test", "--admin_email=login-test@example.invalid", "--skip-email", "--prompt=admin_password"], input=(password + "\n").encode(), env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        if result.returncode:
            raise RuntimeError("Disposable WordPress install failed; output suppressed")
        installed = True
        wp("eval-file", str(setup))
        mail_file.write_text("")
        mail_file.chmod(0o600)
        server = subprocess.Popen(["php", "-d", "display_errors=0", "-S", f"127.0.0.1:{port}", "-t", str(public), str(router)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(100):
            try:
                with socket.create_connection(("127.0.0.1", port), timeout=.1):
                    break
            except OSError:
                time.sleep(.05)
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
        check(request(opener, canonical)[0] == 200, "custom login GET " + (subdir or "root") + (" split home" if split_home else ""))
        check(request(opener, base + "/wp-login.php")[0] == 404, "bare default endpoint remains protected")
        status, headers, body = request(opener, canonical + "?action=lostpassword", {"user_login": "mrn_login_test", "wp-submit": "Get New Password", "redirect_to": ""})
        location = headers.get("Location", "")
        messages = [json.loads(line) for line in mail_file.read_text().splitlines()]
        check(status == 302 and len(messages) == 1, "valid lost-password POST generates one captured email then redirects")
        if args.expect_bug:
            check(urllib.parse.urljoin(canonical, location) == canonical + "wp-login.php?checkemail=confirm", "baseline reproduces nested core redirect")
            check(request(opener, urllib.parse.urljoin(canonical, location))[0] == 404, "baseline complete redirect chain ends at 404")
            return True
        check(location == canonical + "?checkemail=confirm", "lost-password Location is canonical and absolute")
        status, _, body = request(opener, location)
        check(status == 200 and "Check your email" in body, "confirmation page renders HTTP 200")
        reset_links = re.findall(r"http://[^\s<>]+", messages[0]["message"])
        reset_link = next(link for link in reset_links if "action=rp" in link)
        check(reset_link.startswith(canonical + "?"), "reset email uses configured origin and login slug")
        status, headers, _ = request(opener, reset_link)
        check(status == 302 and "key=" not in headers.get("Location", ""), "reset link sets cookie and removes key from URL")
        status, _, body = request(opener, urllib.parse.urljoin(canonical, headers["Location"]))
        check(status == 200 and 'name="pass1"' in body, "action=rp renders password form")
        reset_nonce = re.search(r'name="rp_key"[^>]*value="([^"]*)"', body)
        check(reset_nonce is not None, "reset form contains key for controlled account")
        status, _, body = request(opener, canonical + "?action=resetpass", {"pass1": new_password, "pass2": new_password, "rp_key": reset_nonce.group(1)})
        check(status == 200 and "password has been reset" in body, "action=resetpass completes password change")
        request(opener, canonical)
        destination = base + "/wp-admin/"
        status, headers, _ = request(opener, canonical, {"log": "mrn_login_test", "pwd": new_password, "wp-submit": "Log In", "testcookie": "1", "redirect_to": destination})
        check(status == 302 and headers.get("Location") == destination, "login preserves administrative redirect_to")
        status, _, body = request(opener, destination)
        check(status == 200 and 'id="wpbody"' in body and "wpadminbar" in body, "authenticated administrator reaches dashboard")
        logout = re.search(r'href=[\"\']([^\"\']+action=logout[^\"\']*)', body)
        check(logout is not None, "dashboard provides nonce-protected logout link")
        status, headers, _ = request(opener, html.unescape(logout.group(1)))
        check(status == 302 and "loggedout=true" in headers.get("Location", "") and "wp-login.php" not in headers.get("Location", ""), "logout preserves loggedout and canonical route")
        check(request(opener, urllib.parse.urljoin(canonical, headers["Location"]))[0] == 200, "logged-out confirmation renders")
        reauth = canonical + "?reauth=1&redirect_to=" + urllib.parse.quote(destination, safe="")
        status, _, body = request(opener, reauth)
        check(status == 200 and 'name="log"' in body and 'name="redirect_to"' in body, "administrative reauthentication form renders")
        form_action = html.unescape(re.search(r'<form name="loginform"[^>]+action="([^"]+)"', body).group(1))
        status, headers, _ = request(opener, form_action, {"log": "mrn_login_test", "pwd": new_password, "testcookie": "1", "redirect_to": destination})
        check(status == 302 and headers.get("Location") == destination, "administrative reauthentication returns to requested admin page")
        guest = urllib.request.build_opener(NoRedirect())
        status, headers, _ = request(guest, canonical + "?action=register", {"user_login": "mrn_registration_test", "user_email": "registration-test@example.invalid"})
        check(status == 302 and headers.get("Location") == canonical + "?checkemail=registered", "enabled registration redirects to canonical confirmation")
        check(request(guest, headers["Location"])[0] == 200, "registration confirmation returns HTTP 200")
        status, headers, _ = request(guest, canonical + "?action=lostpassword", {"user_login": "mrn_login_test", "redirect_to": base + "/?return=1&flow=reset"})
        check(status == 302 and headers.get("Location") == base + "/?return=1&flow=reset", "explicit non-login reset destination remains unchanged")
        if args.mrn_qa_report and not subdir:
            qa_env = dict(env, MRN_QA_CODE_ANALYSIS_SCOPE="all", MRN_QA_SAMPLE_PATH="/" + slug + "/")
            result = subprocess.run(["mrn-qa", "run", "--project-root", str(Path(__file__).resolve().parents[1]), "--site-path", str(wp_root), "--site-url", base, "--mode", args.qa_mode, "--smoke-strict", "1", "--run-smoke", "always", "--run-accessibility", "always", "--run-performance", "always", "--run-api", "always", "--output-file", str(args.mrn_qa_report)], env=qa_env, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            qa_passed = result.returncode == 0
            print(("PASS" if qa_passed else "FAIL") + ": component MRN QA; see the requested report", flush=True)
    finally:
        if server:
            server.terminate()
            server.wait(timeout=10)
        if installed:
            wp("db", "clean", "--yes")
        shutil.rmtree(root)
    return qa_passed


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--wp-source", type=Path, required=True)
    parser.add_argument("--plugin-source", type=Path, default=Path(__file__).resolve().parents[1] / "mrn-public-security-hardening.php")
    parser.add_argument("--database-config", type=Path, required=True)
    parser.add_argument("--expect-bug", action="store_true")
    parser.add_argument("--mrn-qa-report", type=Path, help="Run full component QA while the root test runtime is live")
    parser.add_argument("--qa-mode", choices=("standard", "release"), default="standard")
    args = parser.parse_args()
    db = json.loads(args.database_config.read_text())
    if not db.get("DB_NAME", "").startswith("mrn_login_test_") or not db.get("DB_HOST", "").startswith("127.0.0.1"):
        raise SystemExit("Refusing non-disposable database: require mrn_login_test_* and loopback DB_HOST")
    results = [run_case(args, db, subdir, slug, split) for subdir, slug, split in [("", "site-login", False), ("blog", "team-access", False), ("cms", "staff-signin", True)]]
    if not all(results):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
