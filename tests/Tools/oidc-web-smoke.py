from pathlib import Path
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import base64, datetime, hashlib, http.cookiejar, http.client, ipaddress, json, os, re, ssl, subprocess, tempfile, threading, time, urllib.parse, urllib.request, urllib.error
from cryptography import x509
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import rsa, padding
from cryptography.x509.oid import NameOID

# Optional integration tool: requires Python cryptography and an isolated test DB.
project=Path(__file__).resolve().parents[2]
commands=json.loads(os.environ['SAMLA_TEST_PHP_COMMANDS'])
db={k:os.environ['TEST_DB_'+k] for k in ['ENGINE','HOST','PORT','NAME','USER','PASSWORD']}
assert db['NAME'].startswith('media_platform_test_'), 'An isolated test DB is required'
assert db['ENGINE'] in ['mysql','mariadb']

def b64(value):return base64.urlsafe_b64encode(value).decode().rstrip('=')
key=rsa.generate_private_key(public_exponent=65537,key_size=2048); public=key.public_key().public_numbers()
jwks={'keys':[{'kty':'RSA','kid':'test-key','use':'sig','alg':'RS256','n':b64(public.n.to_bytes((public.n.bit_length()+7)//8,'big')),'e':b64(public.e.to_bytes((public.e.bit_length()+7)//8,'big'))}]}
codes={};token_requests=[];bad_nonce=False;bad_signature=False
class Idp(BaseHTTPRequestHandler):
    def log_message(self,*args):pass
    def reply(self,status,body,headers={}):
        self.send_response(status)
        for name,value in headers.items():self.send_header(name,value)
        self.end_headers();self.wfile.write(body)
    def do_GET(self):
        u=urllib.parse.urlsplit(self.path)
        if u.path=='/.well-known/openid-configuration':
            doc={'issuer':issuer,'authorization_endpoint':issuer+'/authorize','token_endpoint':issuer+'/token','jwks_uri':issuer+'/keys','response_types_supported':['code'],'subject_types_supported':['public'],'id_token_signing_alg_values_supported':['RS256'],'code_challenge_methods_supported':['S256'],'token_endpoint_auth_methods_supported':['client_secret_basic']}
            self.reply(200,json.dumps(doc).encode(),{'Content-Type':'application/json'})
        elif u.path=='/keys':self.reply(200,json.dumps(jwks).encode(),{'Content-Type':'application/json'})
        else:self.reply(404,b'')
    def do_POST(self):
        data=urllib.parse.parse_qs(self.rfile.read(int(self.headers['Content-Length'])).decode());token_requests.append(data)
        assert self.path=='/token' and self.headers.get('Authorization')=='Basic '+base64.b64encode(b'client:synthetic-secret').decode()
        assert 'client_secret' not in data
        code=data['code'][0];original=codes.pop(code)
        assert data['redirect_uri'][0]==original['redirect_uri'][0]
        assert b64(hashlib.sha256(data['code_verifier'][0].encode()).digest())==original['code_challenge'][0]
        now=int(time.time());claims={'iss':issuer,'sub':'synthetic-subject','name':'OIDC Test User','aud':['client','project'],'azp':'client','iat':now-1,'exp':now+600,'nonce':'wrong' if bad_nonce else original['nonce'][0]}
        wire=b64(b'{"alg":"RS256","kid":"test-key"}')+'.'+b64(json.dumps(claims).encode())
        signature=key.sign(wire.encode(),padding.PKCS1v15(),hashes.SHA256())
        token=wire+'.'+b64(signature)
        if bad_signature:token=token[:-5]+'AAAAA'
        self.reply(200,json.dumps({'id_token':token,'access_token':'synthetic-access','token_type':'Bearer'}).encode(),{'Content-Type':'application/json'})
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
class AppTls(BaseHTTPRequestHandler):
    def log_message(self,*args):pass
    def relay(self):
        body=self.rfile.read(int(self.headers.get('Content-Length','0')))
        conn=http.client.HTTPConnection('127.0.0.1',8080,timeout=20)
        headers={k:v for k,v in self.headers.items() if k.lower() not in ['connection','content-length']}
        conn.request(self.command,self.path,body,headers);response=conn.getresponse();payload=response.read()
        self.send_response(response.status)
        for key,value in response.getheaders():
            if key.lower() not in ['connection','transfer-encoding','content-length']:self.send_header(key,value)
        self.send_header('Content-Length',str(len(payload)));self.end_headers();self.wfile.write(payload);conn.close()
    do_GET=relay
    do_POST=relay
    do_PROPFIND=relay
    do_HEAD=relay
    do_OPTIONS=relay
    do_PUT=relay
    do_MKCOL=relay
    do_MOVE=relay
    do_COPY=relay
    do_DELETE=relay
    do_LOCK=relay
    do_UNLOCK=relay
with tempfile.TemporaryDirectory(prefix='samla-oidc-http-') as temp:
    p=Path(temp);name=x509.Name([x509.NameAttribute(NameOID.COMMON_NAME,'Samla test CA')]);now=datetime.datetime.now(datetime.timezone.utc)
    cert=(x509.CertificateBuilder().subject_name(name).issuer_name(name).public_key(key.public_key()).serial_number(x509.random_serial_number()).not_valid_before(now-datetime.timedelta(minutes=1)).not_valid_after(now+datetime.timedelta(hours=1)).add_extension(x509.BasicConstraints(ca=True,path_length=None),critical=True).add_extension(x509.SubjectAlternativeName([x509.IPAddress(ipaddress.ip_address('127.0.0.1'))]),critical=False).sign(key,hashes.SHA256()))
    (p/'cert.pem').write_bytes(cert.public_bytes(serialization.Encoding.PEM));(p/'key.pem').write_bytes(key.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.PKCS8,serialization.NoEncryption()));(p/'key.pem').chmod(0o600)
    idp=ThreadingHTTPServer(('127.0.0.1',0),Idp);issuer='https://127.0.0.1:'+str(idp.server_port)
    ctx=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);ctx.load_cert_chain(str(p/'cert.pem'),str(p/'key.pem'));idp.socket=ctx.wrap_socket(idp.socket,server_side=True)
    thread=threading.Thread(target=idp.serve_forever,daemon=True);thread.start()
    try:
        for version,secure in [(v,False) for v in commands]+[(next(iter(commands)),True)]:
            php=list(commands[version])+['-d','openssl.cafile='+str(p/'cert.pem'),'-d','session.cookie_domain=.ignored.example']
            gateway=None;gateway_thread=None
            if secure:
                gateway=ThreadingHTTPServer(('127.0.0.1',0),AppTls);gateway.socket=ctx.wrap_socket(gateway.socket,server_side=True)
                gateway_thread=threading.Thread(target=gateway.serve_forever,daemon=True);gateway_thread.start()
                (p/'trusted-https.php').write_text("<?php $_SERVER['HTTPS']='on';")
                php+=['-d','auto_prepend_file='+str(p/'trusted-https.php')]
            app_url='https://127.0.0.1:'+str(gateway.server_port) if secure else 'http://127.0.0.1:8080'
            env={k:v for k,v in os.environ.items() if not k.startswith(('APP_','DB_','OIDC_')) and k.lower() not in ['http_proxy','https_proxy','all_proxy']}
            env.update(APP_ENV='development',APP_MAINTENANCE='0',APP_WEB_DEV_ACCESS='0',APP_GROUP_MAX_AGE_SECONDS='3600',DB_ENGINE=db['ENGINE'],DB_HOST=db['HOST'],DB_PORT=db['PORT'],DB_USER=db['USER'],DB_PASSWORD=db['PASSWORD'],DB_NAME=db['NAME'],OIDC_ENABLED='1',OIDC_ISSUER=issuer,OIDC_CLIENT_ID='client',OIDC_CLIENT_SECRET='synthetic-secret',OIDC_REDIRECT_URI='http://127.0.0.1:8080/auth/callback',OIDC_GROUPS_CLAIM='')
            if secure:env.update(APP_ENV='production',OIDC_REDIRECT_URI=app_url+'/auth/callback')
            init="require 'vendor/autoload.php';$c=Samla\\Core\\Configuration\\AppConfig::fromGlobals();$p=(new Samla\\Core\\Database\\ConnectionFactory())->connect($c->database);(new Samla\\Core\\Database\\MigrationRunner($p))->migrate(require 'bootstrap/migrations.php');foreach(['outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces']as$t){$p->exec('DELETE FROM '.$t);}"
            subprocess.run(php+['-r',init],cwd=project,env=env,check=True)
            storage=p/('storage'+version+str(secure));storage.mkdir(mode=0o700);env['APP_STORAGE_ROOT']=str(storage)
            sessions=p/('sessions'+version+str(secure));sessions.mkdir(mode=0o700)
            jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),NoRedirect(),urllib.request.HTTPSHandler(context=ssl.create_default_context(cafile=str(p/'cert.pem'))))
            def req(path='/',data=None,extra_headers=None):
                request=urllib.request.Request(app_url+path,data=data if isinstance(data,bytes) else None if data is None else urllib.parse.urlencode(data).encode(),headers=extra_headers or {})
                try:response=opener.open(request,timeout=20)
                except urllib.error.HTTPError as e:response=e
                return response.code,response.read().decode(),response.headers
            def csrf(body):return re.search(r'name="csrf" value="([a-f0-9]{64})"',body).group(1)
            def login():
                status,body,headers=req('/auth/login');assert status==200
                status,body,headers=req('/auth/login',{'csrf':csrf(body)});assert status==303,(status,body)
                query=urllib.parse.parse_qs(urllib.parse.urlsplit(headers['Location']).query)
                assert query['code_challenge_method']==['S256']
                code=os.urandom(16).hex();codes[code]=query
                return '/auth/callback?'+urllib.parse.urlencode({'code':code,'state':query['state'][0]})
            with tempfile.TemporaryFile(mode='w+') as log:
                server=subprocess.Popen(php+['-d','session.save_path='+str(sessions),'-S','127.0.0.1:8080','-t','public','public/router.php'],cwd=project,env=env,stdout=log,stderr=log)
                try:
                    for _ in range(60):
                        if server.poll() is not None:log.seek(0);raise RuntimeError(log.read())
                        try:
                            probe=http.client.HTTPConnection('127.0.0.1',8080,timeout=1);probe.connect();probe.close();status,body,headers=req();break
                        except (OSError,urllib.error.URLError):time.sleep(.1)
                    else:raise RuntimeError('Startup timeout')
                    assert status==200 and 'Organisationskonto' in body
                    assert 'HttpOnly' in headers.get('Set-Cookie','') and 'SameSite=Lax' in headers.get('Set-Cookie','')
                    assert 'domain=' not in headers.get('Set-Cookie','').lower()
                    if secure:assert '__Host-samla=' in headers.get('Set-Cookie','') and 'secure' in headers.get('Set-Cookie','').lower()
                    assert req('/auth/login',{'csrf':'bad'})[0]==403
                    before={c.name:c.value for c in jar};callback=login();count=len(token_requests)
                    wrong=callback.replace('state=','state=wrong')
                    assert req(wrong)[0]==401 and len(token_requests)==count
                    callback=login();status,body,headers=req(callback);assert status==303,(status,body)
                    assert {c.name:c.value for c in jar}!=before
                    status,body,_=req('/account');assert status==200 and 'OIDC Test User' in body,(status,body)
                    user=re.search(r'oidc\.[a-f0-9]{59}',body).group()
                    assert req('/users')[0]==403
                    status,credentials,_=req('/account/app-passwords');assert status==200
                    status,created,headers=req('/account/app-passwords/create',{'csrf':csrf(credentials),'label':'HTTP laptop','days':'30'})
                    assert status==201 and headers['Cache-Control']=='no-store'
                    secret=re.search(r'samla_[a-f0-9]{32}\.[a-f0-9]{64}',created).group()
                    status,credentials,_=req('/account/app-passwords');assert status==200 and secret not in credentials
                    credential=re.search(r'name="credential" value="([a-f0-9]{32})"',credentials).group(1)
                    assert req('/account/app-passwords/revoke',{'csrf':csrf(credentials),'credential':credential})[0]==303
                    assert 'Widerrufen' in req('/account/app-passwords')[1]
                    assert req(callback)[0]==401
                    assert req('/account')[0]==200,'Invalid callback must not log out a signed-in user'
                    bootstrap=dict(env,APP_MAINTENANCE='1')
                    initialized=subprocess.run(php+['bin/storage-init.php'],cwd=project,env=bootstrap,capture_output=True,text=True)
                    assert initialized.returncode==0,initialized.stdout+initialized.stderr
                    for _ in range(2):
                        result=subprocess.run(php+['bin/admin-bootstrap.php',user],cwd=project,env=bootstrap,capture_output=True,text=True)
                        assert result.returncode==0,result.stdout+result.stderr
                    assert req('/users')[0]==200
                    status,body,_=req('/groups');assert status==200
                    assert req('/groups/create',{'csrf':csrf(body),'name':'OIDC created group'})[0]==303
                    assert 'OIDC created group' in req('/groups')[1]
                    # Real multipart upload through PHP's uploaded-file validation, plus streamed attachment.
                    result=subprocess.run(php+['bin/storage-space-create.php',user,'samla.administrators','HTTP files','1048576'],cwd=project,env=bootstrap,capture_output=True,text=True)
                    assert result.returncode==0,result.stdout+result.stderr
                    space=re.search(r'space\.[a-f0-9]{32}',result.stdout).group()
                    status,files,_=req('/files?space='+space);assert status==200
                    token=csrf(files)
                    assert req('/files/folder',{'csrf':token,'space':space,'parent':'','name':'Documents'})[0]==303
                    status,files,_=req('/files?space='+space)
                    parent=re.search(r'parent=([a-f0-9]{32})',files).group(1)
                    boundary='SamlaBoundary'+os.urandom(8).hex()
                    def multipart(token,extra=None,content='<?php echo 123;'):
                        parts=[]
                        for field,value in [('csrf',token),('space',space),('parent',parent)]+list((extra or {}).items()):
                            parts.append('--'+boundary+'\r\nContent-Disposition: form-data; name="'+field+'"\r\n\r\n'+value+'\r\n')
                        parts.append('--'+boundary+'\r\nContent-Disposition: form-data; name="file"; filename="test.php"\r\nContent-Type: application/x-php\r\n\r\n'+content+'\r\n--'+boundary+'--\r\n')
                        return ''.join(parts).encode()
                    path='/files/upload';headers={'Content-Type':'multipart/form-data; boundary='+boundary}
                    assert req(path,multipart('bad'),headers)[0]==403
                    held=Path(str(storage)+'-held');storage.rename(held)
                    try:
                        unavailable=req(path,multipart(token),headers)
                        assert unavailable[0]==503 and 'Dateispeicher' in unavailable[1]
                    finally:held.rename(storage)
                    assert req(path,multipart(token),headers)[0]==303
                    assert req(path,multipart(token),headers)[0]==409
                    status,files,_=req('/files?space='+space+'&parent='+parent);assert status==200 and 'test.php' in files
                    entry=re.search(r'entry=([a-f0-9]{32})',files).group(1)
                    status,content,headers=req('/files/download?space='+space+'&entry='+entry)
                    assert status==200 and content=='<?php echo 123;'
                    assert headers['Content-Type']=='application/octet-stream' and headers['Content-Disposition'].startswith('attachment;')
                    # DAV is independent of browser cookies and accepts only an active app password.
                    status,credentials,_=req('/account/app-passwords')
                    status,created,_=req('/account/app-passwords/create',{'csrf':csrf(credentials),'label':'DAV HTTP','days':'30'})
                    assert status==201
                    dav_secret=re.search(r'samla_[a-f0-9]{32}\.[a-f0-9]{64}',created).group()
                    dav_auth='Basic '+base64.b64encode((user+':'+dav_secret).encode()).decode()
                    def dav(method,path,headers=None,body=None):
                        request=urllib.request.Request(app_url+path,data=None if body is None else body.encode(),headers={'Authorization':dav_auth,**(headers or {})},method=method)
                        # A separate opener deliberately has no browser cookie jar.
                        try:response=urllib.request.build_opener(NoRedirect,urllib.request.HTTPSHandler(context=ssl.create_default_context(cafile=str(p/'cert.pem')))).open(request,timeout=20)
                        except urllib.error.HTTPError as error:response=error
                        return response.status,response.read().decode(),response.headers
                    dav_path='/dav/'+space+'/Documents/test.php'
                    dav_listing=dav('PROPFIND','/dav/'+space+'/Documents/',{'Depth':'1'})
                    assert dav_listing[0]==207,dav_listing[:2]
                    status,content,dav_headers=dav('GET',dav_path)
                    assert status==200 and content=='<?php echo 123;' and 'Set-Cookie' not in dav_headers
                    assert dav_headers['Content-Type']=='application/octet-stream' and dav_headers['Content-Disposition'].startswith('attachment;')
                    assert dav('GET',dav_path,{'Range':'bytes=2-4'})[:2]==(206,'php')
                    assert dav('GET',dav_path,{'If-None-Match':dav_headers['ETag']})[0]==304
                    status,credentials,_=req('/account/app-passwords')
                    assert req('/account/app-passwords/revoke',{'csrf':csrf(credentials),'credential':dav_secret[6:38]})[0]==303
                    assert dav('GET',dav_path)[0]==401
                    for _ in range(2):
                        granted=subprocess.run(php+['bin/storage-space-access.php',user,space,'samla.administrators','manage'],cwd=project,env=bootstrap,capture_output=True,text=True)
                        assert granted.returncode==0,granted.stdout+granted.stderr
                    status,credentials,_=req('/account/app-passwords')
                    status,created,_=req('/account/app-passwords/create',{'csrf':csrf(credentials),'label':'DAV writes','days':'30'})
                    assert status==201
                    dav_secret=re.search(r'samla_[a-f0-9]{32}\.[a-f0-9]{64}',created).group()
                    dav_auth='Basic '+base64.b64encode((user+':'+dav_secret).encode()).decode()
                    dav_folder='/dav/'+space+'/DAV'
                    assert dav('MKCOL',dav_folder)[0]==201
                    dav_file=dav_folder+'/device.txt'
                    status,_,dav_headers=dav('PUT',dav_file,body='created via DAV');assert status==201
                    lock_xml='<d:lockinfo xmlns:d="DAV:"><d:lockscope><d:exclusive/></d:lockscope><d:locktype><d:write/></d:locktype></d:lockinfo>'
                    status,_,lock_headers=dav('LOCK',dav_file,{'Depth':'0','Timeout':'Second-60','Content-Type':'application/xml'},lock_xml)
                    assert status==200
                    device_lock=lock_headers['Lock-Token']
                    assert dav('PUT',dav_file,body='blocked')[0]==423
                    assert dav('PUT',dav_file,{'If':'('+device_lock+')','If-Match':dav_headers['ETag']},'updated via DAV')[0]==204
                    assert dav('UNLOCK',dav_file,{'Lock-Token':device_lock})[0]==204
                    status,_,new_lock_headers=dav('LOCK',dav_folder+'/locked-new.txt',{'Depth':'0','Content-Type':'application/xml'},lock_xml)
                    assert status==201
                    new_device_lock=new_lock_headers['Lock-Token']
                    assert dav('PUT',dav_folder+'/locked-new.txt',{'If':'('+new_device_lock+')'},'atomic new file')[0]==204
                    assert dav('UNLOCK',dav_folder+'/locked-new.txt',{'Lock-Token':new_device_lock})[0]==204
                    assert dav('COPY',dav_file,{'Destination':app_url+dav_folder+'/copy.txt'})[0]==201
                    assert dav('MOVE',dav_folder+'/copy.txt',{'Destination':app_url+dav_folder+'/moved.txt'})[0]==201
                    assert dav('DELETE',dav_folder+'/moved.txt')[0]==204
                    assert dav('PUT',dav_folder+'/moved.txt',body='name reused')[0]==201
                    assert dav('COPY',dav_file,{'Destination':app_url+dav_folder+'/moved.txt','Overwrite':'F'})[0]==412
                    assert dav('COPY',dav_file,{'Destination':app_url+dav_folder+'/moved.txt'})[0]==204
                    assert dav('GET',dav_folder+'/moved.txt')[1]=='updated via DAV'
                    assert dav('PUT',dav_folder+'/replacement.txt',body='temporary save')[0]==201
                    assert dav('MOVE',dav_folder+'/replacement.txt',{'Destination':app_url+dav_folder+'/moved.txt'})[0]==204
                    assert dav('GET',dav_folder+'/replacement.txt')[0]==404
                    assert dav('GET',dav_folder+'/moved.txt')[1]=='temporary save'
                    assert dav('GET',dav_file)[1]=='updated via DAV'
                    assert req('/files/detail?space='+space+'&entry='+entry)[0]==200
                    headers={'Content-Type':'multipart/form-data; boundary='+boundary}
                    replacement=multipart(token,{'entry':entry,'revision':'1'},'updated version')
                    assert req('/files/replace',replacement,headers)[0]==303
                    assert req('/files/replace',replacement,headers)[0]==409
                    assert req('/files/download?space='+space+'&entry='+entry)[1]=='updated version'
                    assert req('/files/versions?space='+space+'&entry='+entry)[0]==403
                    granted=subprocess.run(php+['bin/storage-space-access.php',user,space,'samla.administrators','history'],cwd=project,env=bootstrap,capture_output=True,text=True)
                    assert granted.returncode==0,granted.stdout+granted.stderr
                    status,versions,_=req('/files/versions?space='+space+'&entry='+entry);assert status==200
                    historical_version=re.search(r'name="version" value="([a-f0-9]{32})"',versions).group(1)
                    assert req('/files/version/download?space='+space+'&entry='+entry+'&version='+historical_version)[1]=='<?php echo 123;'
                    assert req('/files/version/restore',{'csrf':token,'space':space,'entry':entry,'parent':parent,'version':historical_version,'revision':'2'})[0]==303
                    assert req('/files/download?space='+space+'&entry='+entry)[1]=='<?php echo 123;'
                    lock_fixture="require 'vendor/autoload.php';$c=Samla\\Core\\Configuration\\AppConfig::fromGlobals();if(!str_starts_with($c->database->database,'media_platform_test_')){exit(1);}$p=(new Samla\\Core\\Database\\ConnectionFactory())->connect($c->database);$i=new Samla\\Core\\Identity\\PdoIdentityRepository($p,3600);$r=Samla\\Core\\Plugins\\PluginRuntime::boot([new Samla\\Core\\Identity\\IdentityPlugin(),new Samla\\Core\\Storage\\StoragePlugin(),new Samla\\Plugins\\Rbac\\RbacPlugin(new Samla\\Plugins\\Rbac\\PdoRoleRepository($p),$i)]);$l=new Samla\\Core\\Storage\\StorageLockService($p,new Samla\\Core\\Authorization\\AuthorizationService($r,$i),new Samla\\Core\\Administration\\PdoMutationRunner($p,$r),new Samla\\Core\\Storage\\StorageWriteGuard($p));if(isset($argv[4])){$l->release($argv[1],$argv[2],$argv[3],$argv[4]);}else{echo $l->acquire($argv[1],$argv[2],$argv[3],seconds:60)['token'];}"
                    locked=subprocess.run(php+['-r',lock_fixture,user,space,entry],cwd=project,env=env,capture_output=True,text=True)
                    assert locked.returncode==0,locked.stderr
                    lock_token=locked.stdout
                    assert req('/files/change',{'csrf':token,'space':space,'entry':entry,'operation':'trash','expected_name':'test.php','expected_parent':parent,'parent':parent})[0]==423
                    assert req('/files/replace',multipart(token,{'entry':entry,'revision':'3'},'blocked'),headers)[0]==423
                    assert req('/files/download?space='+space+'&entry='+entry)[0]==200
                    unlocked=subprocess.run(php+['-r',lock_fixture,user,space,entry,lock_token],cwd=project,env=env,capture_output=True,text=True)
                    assert unlocked.returncode==0,unlocked.stderr
                    def change(identifier,operation,name,expected_parent,target='',new_name=''):
                        return req('/files/change',{'csrf':token,'space':space,'entry':identifier,'operation':operation,'expected_name':name,'expected_parent':expected_parent,'parent':target,'name':new_name})[0]
                    assert change(entry,'rename','test.php',parent,new_name='renamed.php')==303
                    assert change(entry,'rename','test.php',parent,new_name='stale.php')==409
                    assert change(entry,'copy','renamed.php',parent,new_name='copy.php')==303
                    assert change(parent,'trash','Documents','')==303
                    assert req('/files/download?space='+space+'&entry='+entry)[0]==400
                    assert req('/files/trash?space='+space)[0]==200
                    assert change(parent,'restore','Documents','')==303
                    assert req('/files/download?space='+space+'&entry='+entry)[0]==200
                    granted=subprocess.run(php+['bin/storage-space-access.php',user,space,'samla.administrators','purge'],cwd=project,env=bootstrap,capture_output=True,text=True)
                    assert granted.returncode==0,granted.stdout+granted.stderr
                    assert dav('PUT',dav_folder+'/to-purge.txt',body='purge test')[0]==201
                    assert dav('DELETE',dav_folder+'/to-purge.txt')[0]==204
                    status,purge_page,_=req('/files/trash?space='+space);assert status==200
                    purge_entry=re.search(r'<h3>to-purge\.txt</h3>.*?name="entry" value="([a-f0-9]{32})"',purge_page,re.S).group(1)
                    purge_revision=re.search(r'<h3>to-purge\.txt</h3>.*?name="revision" value="([0-9]+)"',purge_page,re.S).group(1)
                    purge_form={'csrf':csrf(purge_page),'space':space,'entry':purge_entry,'parent':'','revision':purge_revision}
                    assert req('/files/purge',purge_form)[0]==400
                    assert req('/files/purge',{**purge_form,'confirm':'delete'})[0]==303
                    assert 'to-purge.txt' not in req('/files/trash?space='+space)[1]
                    status,editor_page,_=req('/files/edit?space='+space+'&entry='+entry);assert status==200
                    edit_revision=re.search(r'name="revision" value="([0-9]+)"',editor_page).group(1)
                    edit_form={'csrf':csrf(editor_page),'space':space,'entry':entry,'revision':edit_revision,'text':'</textarea><script>draft</script>'}
                    assert req('/files/edit/save',{**edit_form,'csrf':'bad'})[0]==403
                    assert req('/files/edit/save',edit_form)[0]==303
                    assert req('/files/download?space='+space+'&entry='+entry)[1]==edit_form['text']
                    status,conflict,_=req('/files/edit/save',{**edit_form,'text':'unsaved conflict'});assert status==409 and 'unsaved conflict' in conflict
                    status,draft,draft_headers=req('/files/edit/draft',{**edit_form,'text':'unsaved conflict'});assert status==200 and draft=='unsaved conflict' and 'attachment' in draft_headers['Content-Disposition']
                    status,editor_page,_=req('/files/edit?space='+space+'&entry='+entry);assert status==200 and '&lt;/textarea&gt;&lt;script&gt;' in editor_page
                    edit_revision=re.search(r'name="revision" value="([0-9]+)"',editor_page).group(1)
                    # Maximum LF text becomes CRLF in a browser form; test the real request limit too.
                    assert req('/files/edit/save',{**edit_form,'revision':edit_revision,'text':'\r\n'*65536})[0]==303
                    assert req('/files/download?space='+space+'&entry='+entry)[1]=='\n'*65536
                    checked=subprocess.run(php+['bin/storage-check.php'],cwd=project,env=bootstrap,capture_output=True,text=True)
                    assert checked.returncode==2 and 'Unreferenced object:' in checked.stdout,checked.stdout+checked.stderr
                    gc=subprocess.run(php+['bin/storage-gc.php'],cwd=project,env=bootstrap,capture_output=True,text=True)
                    assert gc.returncode==0 and 'removed: 0' in gc.stdout,gc.stdout+gc.stderr
                    assert req('/auth/logout')[0]==405
                    assert req('/auth/logout',{'csrf':'bad'})[0]==403
                    assert req('/auth/logout',{'csrf':csrf(body)})[0]==303
                    assert req('/users')[0]==303
                    bad_nonce=True;callback=login();assert req(callback)[0]==401;bad_nonce=False
                    assert req('/users')[0]==303
                    bad_signature=True;callback=login();assert req(callback)[0]==401;bad_signature=False
                    callback=login();assert req(callback)[0]==303
                    cookie=next(c for c in jar if c.name==('__Host-samla' if secure else 'samla_oidc_dev'))
                    session_file=sessions/('sess_'+cookie.value)
                    content=session_file.read_text();expired=re.sub(r'("expires";i:)\d+',r'\g<1>1',content)
                    assert content!=expired;session_file.write_text(expired)
                    assert req('/account')[0]==303
                    callback=login();assert req(callback)[0]==303
                    # Local blocking takes effect in an already authenticated session.
                    block="require 'vendor/autoload.php';$c=Samla\\Core\\Configuration\\AppConfig::fromGlobals();$p=(new Samla\\Core\\Database\\ConnectionFactory())->connect($c->database);$q=$p->prepare(\"UPDATE identity_users SET status='blocked' WHERE id=?\");$q->execute([$argv[1]]);"
                    subprocess.run(php+['-r',block,user],cwd=project,env=env,check=True)
                    assert req('/account')[0]==403
                    callback=login();assert req(callback)[0]==401
                    print(version+(' production HTTPS' if secure else ' development HTTP')+': OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, quota/conflicts, lifecycle, consistency, logout and blocking: OK',flush=True)
                finally:
                    server.terminate();server.wait(timeout=10)
                    log.seek(0);text=log.read()
                    assert 'PHP Warning' not in text and 'PHP Fatal' not in text and 'synthetic-secret' not in text,text
                    if gateway is not None:gateway.shutdown();gateway.server_close();gateway_thread.join()
    finally:
        idp.shutdown();idp.server_close();thread.join()
