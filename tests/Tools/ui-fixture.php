<?php
declare(strict_types=1);
// Render-only synthetic fixture. No database, .env, login or mutation handlers.
if (PHP_SAPI !== 'cli-server' || getenv('SAMLA_UI_FIXTURE') !== '1'
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' || ($_SERVER['SERVER_NAME'] ?? '') !== '127.0.0.1') {
    http_response_code(404); exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); exit; }
require __DIR__ . '/../bootstrap.php';
use Samla\Core\Web\{PageRenderer, Response};
use Samla\PluginApi\{AccessProfile, Scope};
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$assets=['/assets/samla.css'=>'text/css','/assets/workspace.js'=>'text/javascript'];
if(isset($assets[$path])){header('Content-Type: '.$assets[$path].'; charset=utf-8');readfile(__DIR__.'/../../public'.$path);exit;}
if($path==='/media/preview'){
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="480" height="320" viewBox="0 0 480 320"><rect width="480" height="320" fill="#e2e8f0"/><path d="M0 280 150 90 260 230 340 130 480 280v40H0" fill="#15803d"/><circle cx="370" cy="70" r="28" fill="#facc15"/></svg>';exit;
}
$pages=['/'=>'home','/files'=>'files','/files/detail'=>'file-detail','/media'=>'media-gallery','/media/detail'=>'media-detail','/permissions'=>'permissions','/administration/spaces'=>'space-administration','/catalog'=>'catalog','/account'=>'account','/auth/login'=>'login'];
if(!isset($pages[$path])){http_response_code(404);exit;}
$reader=($_GET['reader']??'')==='1';$page=$pages[$path];
$caps=[];foreach(['identity.users.read','identity.groups.read','identity.groups.mappings.manage','access.profiles.manage','operations.worker.read'] as$cap){$caps[$cap]=!$reader;}
$entry=['id'=>str_repeat('a',32),'name'=>'Notiz <Team>.txt','kind'=>'file','parent_id'=>'','revision'=>3,'size_bytes'=>2400,'has_preview'=>true,'media_status'=>'ready'];
$space=['id'=>'team','name'=>'Gemeinsame Dateien','quota_bytes'=>10737418240,'used_bytes'=>2400,'owner_id'=>null,'entry_count'=>1,'fingerprint'=>str_repeat('b',64)];
$data=['actor'=>$page==='login'?null:'synthetic.test','display_name'=>'Alex Beispiel','name'=>'Alex Beispiel','csrf'=>str_repeat('a',64),'caps'=>$caps,'flash'=>null,'oidc'=>true,'fresh'=>true,
    'space'=>$space,'space_id'=>'team','folder'=>null,'parent'=>'','used'=>2400,'can_create'=>!$reader,'can_read'=>true,'can_view_trash'=>!$reader,'can_catalog_read'=>true,'can_tag_batch'=>!$reader,'can_export'=>!$reader,'can_catalog'=>true,
    'upload_max_bytes'=>16777216,'next'=>null,'rows'=>[$entry,['id'=>str_repeat('b',32),'name'=>str_repeat('EinSehrLangerOrdnername',7),'kind'=>'folder','size_bytes'=>0,'revision'=>1]],
    'entry'=>$entry,'destinations'=>[''=>'Bereichswurzel',str_repeat('b',32)=>'Gemeinsame Dokumente'],'file_caps'=>['read'=>true,'update'=>!$reader,'delete'=>!$reader,'create'=>!$reader,'restore'=>!$reader,'versions.read'=>true],
    'media_areas'=>false,'media_caps'=>['process'=>!$reader,'metadata.read'=>true],'has_preview'=>true,'can_download'=>true,'media_status'=>'ready','processed_at'=>null,'metadata'=>[],
    'choices'=>['groups'=>[['id'=>'group.team','name'=>'Redaktion <Team>']],'spaces'=>[$space]],'selected_group'=>['id'=>'group.team','name'=>'Redaktion <Team>'],'selected_space'=>($_GET['create']??'')==='1'?null:$space,'show_create'=>($_GET['create']??'')==='1',
    'profiles'=>[
        ['profile'=>new AccessProfile('samla.files.read','Dateien lesen','Dateien öffnen und herunterladen.',Scope::Space,['files.list','files.read']),'assigned'=>true,'available'=>true,'fingerprint'=>str_repeat('c',64)],
        ['profile'=>new AccessProfile('samla.files.contribute','Dateien bearbeiten','Dateien hochladen und neue Versionen speichern.',Scope::Space,['files.create','files.update']),'assigned'=>false,'available'=>true,'fingerprint'=>str_repeat('d',64)]
    ],
    'catalog'=>['revision'=>2,'description'=>'Eine gemeinsame Notiz mit einer verständlichen Beschreibung.'],'tags'=>[['id'=>str_repeat('e',32),'name'=>'Familie','revision'=>1],['id'=>str_repeat('f',32),'name'=>'Planung','revision'=>1]],'selected_tags'=>[str_repeat('e',32)],'can_write'=>!$reader,'can_manage_tags'=>!$reader
];
if(($_GET['draft']??'')==='1'){$data['form_error']='Die Angaben wurden inzwischen geändert. Dein Entwurf bleibt erhalten.';$data['catalog']['description']='Entwurf <nicht verlieren>';$data['submitted_catalog_revision']='1';}
$response=new Response(200,(new PageRenderer())->render($page,$data));
foreach($response->headers as$name=>$value){header($name.': '.$value);}
echo $response->body;
