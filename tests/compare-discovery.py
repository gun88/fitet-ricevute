"""Compare receipt anchors from the supplied request with discovered public URLs.
Only counts are printed. The request may contain secrets: never echo or save its text.
"""
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin, urlsplit, parse_qsl
import json
import sys

def identity(url):
    parts = urlsplit(url)
    return parts.path, tuple(sorted(parse_qsl(parts.query)))

class Receipts(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links = set()

    def handle_starttag(self, tag, attrs):
        if tag != 'a':
            return
        href = dict(attrs).get('href', '')
        if 'fpdf2/iscrizioni_gara_societa' in href:
            self.links.add(identity(urljoin('https://portale.fitet.org/login/iscrizioni/lista_partecipazione_tornei.asp', href)))

parser = Receipts()
parser.feed(Path(sys.argv[1]).read_text(encoding='utf-8-sig'))
found = {identity(url) for url in json.loads(Path('tests/.runtime/live-receipt-links.json').read_text())}
print(json.dumps({'expected': len(parser.links), 'discovered': len(found), 'missing': len(parser.links - found), 'additional': len(found - parser.links)}))
assert parser.links and parser.links == found
