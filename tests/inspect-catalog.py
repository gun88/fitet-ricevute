"""Explicit read-only inspection of catalog HTML using credentials supplied in a request file."""
from pathlib import Path
from urllib.request import build_opener, HTTPCookieProcessor, Request
from urllib.parse import parse_qs, urlencode
from http.cookiejar import CookieJar
import re, sys
request = Path(sys.argv[1]).read_text(encoding='utf-8-sig')
payload = parse_qs(re.search(r"--data-raw '([^']+)'", request, re.S)[1])
body = urlencode({key: payload[key][0] for key in ('login','password')} | {'paginaOriginale':''}).encode()
opener = build_opener(HTTPCookieProcessor(CookieJar()))
origin='https://portale.fitet.org'
opener.open(origin+'/login/login.asp', timeout=20).read()
opener.open(Request(origin+'/login/autentica.asp?COMIT=30', data=body, headers={'Origin':origin,'Referer':origin+'/login/login.asp'}),timeout=20).read()
for name,path in [('regional-header','testa_iscrizioni_3.asp?TORNEO=1&TIPO=36&COMITATO=4'),('regional-left','iscrizioni_torneo_reg_left.asp?TORNEO=1&TIPO=36&COMITATO=4'),('national-header','testa_iscrizioni_2.asp?TORNEO=939'),('national-left','iscrizioni_torneo_left.asp?X=0&TORNEO=939')]:
    html=opener.open(origin+'/login/iscrizioni/'+path,timeout=20).read().decode('cp1252')
    Path('tests/.runtime/catalog-'+name+'.html').write_text(html,encoding='utf-8')
    print(name)
    for line in html.splitlines():
        if any(word in line.lower() for word in ('href=', 'src=', '<form', 'anno', 'ricevut')):
            if not any(word in line.lower() for word in ('password','login=', 'value=')):
                print(line[:1200])
