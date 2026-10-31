"""Check archives and optionally reject secrets from an explicitly supplied cURL request.
Prints only archive metadata; never prints request data or matched secret values.
"""
from pathlib import Path
from urllib.parse import parse_qs, quote, quote_plus
import json
import re
import sys
import zipfile

root = Path(__file__).resolve().parents[1]
version = json.loads((root / 'app/package.json').read_text())['version']
secrets = set()
if len(sys.argv) > 1:
    request = Path(sys.argv[1]).read_text(encoding='utf-8-sig')
    match = re.search(r"--data-raw '([^']+)'", request, re.S)
    assert match, 'Request body not found'
    payload = parse_qs(match[1])
    for key in ('login', 'password'):
        for value in payload.get(key, []):
            secrets.update((value.encode(), quote(value, safe='').encode(), quote_plus(value).encode()))
    for cookie_header in re.findall(r"(?:-b|--cookie) '([^']+)'", request):
        for cookie in cookie_header.split(';'):
            name, _, value = cookie.strip().partition('=')
            if value and len(value) >= 8:
                secrets.add(value.encode())
    del request, match, payload

for suffix in ('', '-source'):
    archive = root / 'release' / f'fitet-ricevute-{version}{suffix}.zip'
    with zipfile.ZipFile(archive) as package:
        assert package.testzip() is None, 'Archive corruption'
        names = package.namelist()
        assert 'fitet-ricevute/fitet-ricevute.php' in names
        assert 'fitet-ricevute/includes/committees.php' in names
        assert 'fitet-ricevute/assets/admin.css' in names
        assert 'fitet-ricevute/assets/app/index.html' in names
        assert not any('/.runtime/' in name or '/node_modules/' in name or '/.idea/' in name for name in names)
        assert f'Version: {version}'.encode() in package.read('fitet-ricevute/fitet-ricevute.php')
        for info in package.infolist():
            if not info.is_dir():
                contents = package.read(info)
                assert not any(secret in contents for secret in secrets), 'Private request data found in archive'
    print(json.dumps({'archive': archive.name, 'bytes': archive.stat().st_size, 'files': len(names), 'integrity': 'OK', 'request_secrets_absent': bool(secrets)}))
