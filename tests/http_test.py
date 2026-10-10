"""Isolated HTTP regression tests; no production environment or storage is used."""
import hashlib
import hmac
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = shutil.which('php') or r'C:\php\php.exe'
FLAGS = subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def main():
    count = 0
    def check(condition, name):
        nonlocal count
        if not condition:
            raise AssertionError(name)
        count += 1
        print('PASS - ' + name)

    with tempfile.TemporaryDirectory(prefix='presell-security-') as temp:
        root = Path(temp) / 'site'
        shutil.copytree(ROOT, root, ignore=shutil.ignore_patterns('.git', '.env', '.env.*', '*.json', '__pycache__'))
        password, secret = secrets.token_hex(24), secrets.token_hex(32)
        code = '<?php echo password_hash(' + json.dumps(password) + ', PASSWORD_DEFAULT);'
        password_hash = subprocess.check_output([PHP], input=code.encode(), creationflags=FLAGS).decode()
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        (root / '.env').write_text(f'APP_BASE_URL="{base}"\nAPP_ADMIN_USER="audit"\nAPP_ADMIN_PASSWORD_HASH="{password_hash}"\nPOSTBACK_SECRET="{secret}"\n')
        server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', 'router.php'], cwd=root,
                                  stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=FLAGS)
        jar = http.cookiejar.CookieJar()
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect())
        guest = urllib.request.build_opener(NoRedirect())
        def request(path, data=None, cookie=None, client=guest):
            headers = {'Cookie': cookie} if cookie else {}
            encoded = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
            req = urllib.request.Request(base + path, data=encoded, headers=headers)
            try:
                response = client.open(req, timeout=5)
            except urllib.error.HTTPError as error:
                response = error
            with response:
                return response.code, response.read().decode(), response.headers
        def postback(status='paid', order='order-1', sign=True, **overrides):
            data = dict(campaign='demo', status=status, amount='10.00', order_id=order)
            data.update(overrides)
            if sign:
                data['signature'] = hmac.new(secret.encode(), '|'.join(data[k] for k in ['campaign','status','amount','order_id']).encode(), hashlib.sha256).hexdigest()
            return request('/postback.php', data)
        try:
            for _ in range(50):
                try:
                    request('/admin/login.php')
                    break
                except urllib.error.URLError:
                    time.sleep(.1)
            else:
                raise RuntimeError('PHP server did not start')
            for path in ['/.env', '/storage/campaigns.json', '/app/helpers.php', '/tests/smoke_test.php', '/.git/config', '/README.md', '/router.php']:
                check(request(path)[0] == 403, 'HTTP blocks ' + path)
            # Concurrent PHP processes update the same isolated file under the lock.
            worker = "require 'app/helpers.php'; for ($i=0; $i<25; $i++) { updateJsonFile($argv[1], static fn(array $data): array => ['count'=>($data['count'] ?? 0)+1]); }"
            counter = root / 'storage/counter.json'
            workers = [subprocess.Popen([PHP,'-r',worker,str(counter)],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,creationflags=FLAGS) for _ in range(8)]
            check(all(worker.wait(timeout=15) == 0 for worker in workers), 'concurrent storage writers complete')
            check(json.loads(counter.read_text())['count'] == 200, 'concurrent storage updates lose no events')
            check(request('/admin/dashboard.php')[0] == 302, 'dashboard requires login')
            check(request('/admin/login.php', {'username':'audit','password':password})[0] == 419, 'login rejects missing CSRF')
            query = '?campaign=demo&gclid=AuditGclid&subid=AbC-123&campaignid=987'
            for consent in [None, 'presell_consent=rejected']:
                for path in ['/index.php', '/review.php', '/bridge.php']:
                    code, body, _ = request(path+query, cookie=consent)
                    check(code == 200 and 'AuditGclid' not in body, 'no tracking before consent: '+path+str(consent))
            check(json.loads((root/'storage/traffic.json').read_text()) == [], 'no traffic stored without consent')
            code, body, _ = request('/review.php'+query, cookie='presell_consent=accepted')
            check(code == 200 and 'subid=AbC-123' in html.unescape(body) and 'campaignid=987' in html.unescape(body), 'review forwards accepted parameters with original case')
            code, _, headers = request('/bridge.php'+query, cookie='presell_consent=accepted')
            check(code == 302 and 'gclid=AuditGclid' in headers['Location'], 'accepted bridge redirects with tracking')
            check(len(json.loads((root/'storage/traffic.json').read_text())) == 1, 'accepted bridge records one visit')
            check(postback(sign=False)[0] == 401, 'unsigned sale rejected')
            check(request('/postback.php', {'signature[]':'bad'})[0] == 401, 'array signature rejected')
            check(postback(order='')[0] == 422, 'empty order rejected')
            check(postback(campaign='missing')[0] == 422, 'unknown campaign rejected')
            check(json.loads(postback(status='click')[1])['accepted'] is False, 'click rejected as sale')
            check(json.loads(postback(status='accepted')[1])['accepted'] is False, 'ambiguous accepted status rejected')
            check(json.loads(postback()[1])['sale'] is True, 'signed sale recorded')
            check(json.loads(postback()[1])['duplicate'] is True, 'replay deduplicated')
            check(len(json.loads((root/'storage/postbacks.json').read_text())) == 1, 'duplicate does not append')
            check(json.loads(postback(status='refunded')[1])['sale'] is False, 'signed refund recorded')
            code, body, headers = request('/admin/login.php', client=opener)
            token = re.search(r'name="csrf_token" value="([a-f0-9]+)"',body).group(1)
            old_session = next(c.value for c in jar if c.name == 'PHPSESSID')
            check(headers.get('Cache-Control') == 'no-store' and headers.get('Referrer-Policy') == 'no-referrer', 'responses prevent caching and URL referrer leaks')
            check('HttpOnly' in headers.get('Set-Cookie','') and 'SameSite=Lax' in headers.get('Set-Cookie',''), 'session cookie protected')
            check(request('/admin/login.php', dict(username='audit', password=password, csrf_token=token), client=opener)[0] == 302, 'valid admin login succeeds')
            check(next(c.value for c in jar if c.name=='PHPSESSID') != old_session, 'login rotates session ID')
            code, body, _ = request('/admin/dashboard.php', client=opener)
            check(code == 200, 'authenticated dashboard works')
            token = re.search(r'name="csrf_token" value="([a-f0-9]+)"',body).group(1)
            check(request('/admin/dashboard.php',dict(slug='test', name='Test'),client=opener)[0] == 419, 'campaign save requires CSRF')
            campaigns_before = (root/'storage/campaigns.json').read_text()
            request('/admin/dashboard.php',dict(slug='test',name='Test',network='hotmart',offer_url='javascript:alert(1)',csrf_token=token), client=opener)
            check((root/'storage/campaigns.json').read_text() == campaigns_before, 'unsafe offer URL not saved')
            check(request('/admin/logout.php',client=opener)[0] == 405, 'logout requires POST')
            check(request('/admin/logout.php', {},client=opener)[0] == 419, 'logout requires CSRF')
            check(request('/admin/logout.php', {'csrf_token':token},client=opener)[0] == 302, 'valid logout succeeds')
            check(request('/admin/dashboard.php',client=opener)[0] == 302, 'logout clears authentication')
            _, body, _ = request('/admin/login.php',client=opener)
            token = re.search(r'name="csrf_token" value="([a-f0-9]+)"',body).group(1)
            for _ in range(5):
                request('/admin/login.php',dict(username='audit',password=secrets.token_hex(8),csrf_token=token),client=opener)
            check(request('/admin/login.php',dict(username='audit',password=password,csrf_token=token),client=opener)[0] == 200, 'five failures block subsequent login')
            # PHP reads environment on every request; safely test missing configuration in the isolated copy.
            (root/'.env').write_text(f'APP_BASE_URL="{base}"\nAPP_ADMIN_USER="audit"\n')
            check(postback()[0] == 503, 'missing postback secret disables endpoint')
            (root/'storage/login_attempts.json').write_text('[]')
            check(request('/admin/login.php',dict(username='audit',password=password,csrf_token=token),client=opener)[0] == 200, 'missing admin hash disables login')
        finally:
            server.terminate()
            server.wait(timeout=5)
    print(f'{count} HTTP checks passed')

if __name__ == '__main__':
    main()
