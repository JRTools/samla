"""Real HTTP smoke checks. Run against an isolated, seeded local development instance.
Usage: python3 tests/http-smoke.py http://127.0.0.1:8080
"""
import http.cookiejar
import json
import sys
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser

class Forms(HTMLParser):
    def __init__(self, html):
        super().__init__(); self.forms=[]; self.current=None; self.feed(html)
    def handle_starttag(self, tag, attrs):
        a=dict(attrs)
        if tag=='form': self.current={'action':a.get('action'), 'fields':{}}; self.forms.append(self.current)
        elif tag=='input' and self.current is not None and a.get('name'):
            self.current['fields'][a['name']]=a.get('value','')
    def handle_endtag(self, tag):
        if tag=='form': self.current=None

def run(base):
    assert base.startswith('http://127.0.0.1:') or base.startswith('http://localhost:')
    jar=http.cookiejar.CookieJar()
    browser=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    def request(path, data=None, headers=None):
        body=urllib.parse.urlencode(data).encode() if data is not None else None
        try: response=browser.open(urllib.request.Request(base+path, body, headers or {}))
        except urllib.error.HTTPError as e: response=e
        return response.status, response.read().decode(), response.headers
    status,html,headers=request('/groups')
    assert status==200 and 'Gruppenverzeichnis' in html
    assert headers['Cache-Control']=='no-store'
    assert "frame-ancestors 'none'" in headers['Content-Security-Policy']
    assert any(c.name=='samla_dev' and c.has_nonstandard_attr('HttpOnly') and c.get_nonstandard_attr('SameSite')=='Strict' for c in jar)
    token=next(f for f in Forms(html).forms if f['action']=='/groups/create')['fields']['csrf']
    assert len(token)==64
    assert request('/groups/create',{'csrf':'0'*64,'name':'CSRF attack'})[0]==403
    status,html,_=request('/groups/create',{'csrf':token,'name':'HTTP smoke group'})
    assert status==200 and 'HTTP smoke group' in html and 'Die Änderung wurde gespeichert.' in html
    rename=next(f for f in Forms(html).forms if f['action']=='/groups/rename' and f['fields'].get('name')=='HTTP smoke group')
    renamed=rename['fields'] | {'name':'HTTP renamed group'}
    assert request('/groups/rename',renamed)[0]==200
    assert request('/groups/rename',rename['fields'] | {'name':'stale rename'})[0]==409
    assert request('/users/status',{'csrf':token,'user':'dev.member','status':'blocked'})[0]==200
    status,users,_=request('/users'); assert status==200 and 'Gesperrt' in users
    assert request('/users/status',{'csrf':token,'user':'dev.admin','status':'blocked'})[0]==403
    assert request('/memberships/change',{'csrf':token,'user':'dev.member','group':rename['fields']['group'],'operation':'grant'})[0]==200
    assert request('/memberships/change',{'csrf':token,'user':'dev.member','group':'dev.operators','operation':'grant'})[0]==403
    assert request('/users/status',{'csrf':token,'user':'dev.member','status':'active'})[0]==200
    assert request('/groups',headers={'Host':'evil.example'})[0]==503
    assert request('/groups/create')[0]==405
    assert request('/unknown')[0]==404
    assert request('/config/environment.example')[0]==404
    assert request('/assets/samla.css')[0]==200
    assert 'Datei- und Medienplattform' in html
    print(json.dumps({'http_smoke':'passed','checks':'session cookie, CSRF, CRUD forms, conflict, protected admin, membership delegation, host gate, routes, headers'},ensure_ascii=False))

if __name__=='__main__': run(sys.argv[1].rstrip('/'))
