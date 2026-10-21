<?php
declare(strict_types=1);
namespace Samla\Tests\Integration;
use PHPUnit\Framework\TestCase;
use Sabre\HTTP;
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory,MigrationRunner};
use Samla\Core\Identity\{AppPasswordService,IdentityPlugin,PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService,LocalContentStore,StoragePlugin};
use Samla\Core\WebDav\{CredentialAuthorization,Kernel,RequestIdentity};
use Samla\PluginApi\{AuthorizationRequest,Decision,Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository,RbacPlugin,Role,RoleAssignment};

final class WebDavTest extends TestCase
{
    private \PDO $pdo;
    private PdoIdentityRepository $identity;
    private PdoRoleRepository $roles;
    private PluginRuntime $runtime;
    private AppPasswordService $passwords;
    private string $password;
    private string $root;
    private string $file;
    protected function setUp(): void
    {
        $config=AppConfig::fromGlobals(); if($config->environment!=='test'){throw new \RuntimeException();}
        $this->pdo=(new ConnectionFactory())->connect($config->database);
        (new MigrationRunner($this->pdo))->migrate(require __DIR__.'/../../bootstrap/migrations.php');
        foreach(['outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces','identity_app_auth_limits'] as $table){$this->pdo->exec('DELETE FROM '.$table);}
        $this->identity=new PdoIdentityRepository($this->pdo,3600);
        $this->identity->createUser('alice','https://id.example','alice','Alice');$this->identity->replaceVerifiedGroups('alice',[]);
        $this->identity->createGroup('readers','Readers');$this->identity->addManualMembership('alice','readers');
        $this->roles=new PdoRoleRepository($this->pdo);
        $this->runtime=PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new RbacPlugin($this->roles,$this->identity)]);
        $this->roles->save(new Role('reader',Scope::Space,['files.list','files.read','files.create']),$this->runtime);
        $this->pdo->exec("INSERT INTO storage_spaces (id,name,quota_bytes) VALUES ('a','Personal files',1048576),('b','Secret',1048576)");
        $this->roles->assign(new RoleAssignment('readers','reader',Scope::Space,'a'));
        $this->root=sys_get_temp_dir().'/samla-dav-'.bin2hex(random_bytes(8));mkdir($this->root,0700);
        $service=$this->files();$stream=fopen('php://temp','w+b');fwrite($stream,'0123456789');rewind($stream);
        try{$this->file=$service->upload('alice','a','','Grüße.txt',$stream);}finally{fclose($stream);}
        $this->passwords=new AppPasswordService($this->pdo,$this->identity,new PdoMutationRunner($this->pdo,$this->runtime));
        $this->password=$this->passwords->create('alice','DAV test');
    }
    protected function tearDown(): void
    {foreach(new \DirectoryIterator($this->root) as $f){if(!$f->isDot()){unlink($f->getPathname());}}rmdir($this->root);}
    private function files(?CredentialAuthorization $auth=null):FileService
    {return new FileService($this->pdo,$auth??new AuthorizationService($this->runtime,$this->identity),new PdoMutationRunner($this->pdo,$this->runtime),new LocalContentStore($this->root));}
    private function request(string $method,string $path='/dav/a/Gr%C3%BC%C3%9Fe.txt',array $headers=[],string $body=''): \Samla\Core\Web\Response
    {
        $identity=new RequestIdentity();$auth=new CredentialAuthorization(new AuthorizationService($this->runtime,$this->identity),$this->passwords,$identity);
        return (new Kernel($this->passwords,$identity,$this->files($auth)))->handle(new HTTP\Request($method,$path,$headers+['Authorization'=>'Basic '.base64_encode('alice:'.$this->password)],$body),'127.0.0.1');
    }
    private function body(\Samla\Core\Web\Response $response):string
    {ob_start();echo $response->body;if($response->stream!==null){($response->stream)();}return ob_get_clean();}
    public function testListsOnlyAuthorizedSpacesAndEscapedNames():void
    {
        $response=$this->request('PROPFIND','/dav/',['Depth'=>'1']);self::assertSame(207,$response->status);$body=$this->body($response);
        self::assertStringContainsString('Personal files',$body);self::assertStringNotContainsString('Secret',$body);
        $response=$this->request('PROPFIND','/dav/a/',['Depth'=>'1']);self::assertSame(207,$response->status);self::assertStringContainsString('Grüße.txt',rawurldecode($this->body($response)));
        self::assertSame(404,$this->request('PROPFIND','/dav/b/',['Depth'=>'0'])->status);
    }
    public function testGetHeadRangeAndConditionalRead():void
    {
        $response=$this->request('GET');self::assertSame(200,$response->status);self::assertSame('0123456789',$this->body($response));
        $etag=$response->headers['ETag'];self::assertSame('"'.$this->file.'-1"',$etag);
        $response=$this->request('GET',headers:['Range'=>'bytes=3-4']);self::assertSame(206,$response->status);self::assertSame('34',$this->body($response));
        $response=$this->request('HEAD');self::assertSame(200,$response->status);self::assertSame('',$this->body($response));
        $response=$this->request('GET',headers:['If-None-Match'=>$etag]);self::assertSame(304,$response->status);self::assertSame('',$this->body($response));
        $response=$this->request('GET',headers:['X-Sabre-Original-Method'=>'HEAD']);self::assertSame('0123456789',$this->body($response));
    }
    public function testListPermissionCannotReadEvenWithMatchingEtag():void
    {
        $this->roles->save(new Role('reader',Scope::Space,['files.list']),$this->runtime);
        self::assertSame(403,$this->request('HEAD')->status);self::assertSame(403,$this->request('GET',headers:['If-None-Match'=>'"'.$this->file.'-1"'])->status);
        self::assertSame(207,$this->request('PROPFIND','/dav/a/',['Depth'=>'1'])->status);
    }
    public function testBadCredentialRevocationAndBlockedIdentity():void
    {
        $response=$this->request('GET',headers:['Authorization'=>'Basic '.base64_encode('alice:wrong')]);self::assertSame(401,$response->status);self::assertArrayHasKey('WWW-Authenticate',$response->headers);
        $this->passwords->revoke('alice',substr($this->password,6,32));self::assertSame(401,$this->request('GET')->status);
        $this->password=$this->passwords->create('alice','Another');$this->identity->setBlocked('alice',true);self::assertSame(401,$this->request('GET')->status);
    }
    public function testAlreadyAuthenticatedCredentialIsRecheckedOnEveryAuthorization():void
    {
        $identity=new RequestIdentity();$id=substr($this->password,6,32);$identity->bind('alice',$id);
        $auth=new CredentialAuthorization(new AuthorizationService($this->runtime,$this->identity),$this->passwords,$identity);
        $request=new AuthorizationRequest('alice','files.read',Scope::Space,'a');self::assertSame(Decision::Permit,$auth->decide($request)->decision);
        $this->passwords->revoke('alice',$id);self::assertSame(Decision::Deny,$auth->decide($request)->decision);
    }
    public function testXmlAndDepthAreBoundedAndDoNotExpandEntities():void
    {
        self::assertSame(403,$this->request('PROPFIND','/dav/a/')->status);
        self::assertSame(413,$this->request('PROPFIND','/dav/a/',['Depth'=>'1'],str_repeat('x',65537))->status);
        $xml='<!DOCTYPE x [<!ENTITY steal SYSTEM "file:///etc/passwd">]><d:propfind xmlns:d="DAV:"><d:prop>&steal;</d:prop></d:propfind>';
        self::assertSame(400,$this->request('PROPFIND','/dav/a/',['Depth'=>'0'],$xml)->status);
        self::assertSame(400,$this->request('PROPFIND','/dav/a/',['Depth'=>'0'],mb_convert_encoding('<?xml version="1.0" encoding="UTF-16"?>'.$xml,'UTF-16','UTF-8'))->status);
        self::assertSame(400,$this->request('PROPFIND','/dav/a/',['Depth'=>'0'],'<broken')->status);
    }
    public function testWritesRemainDisabledAndOptionsAdvertisesOnlyImplementedMethods():void
    {
        $before=(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn();
        foreach(['PUT','MKCOL','MOVE','COPY','DELETE','LOCK','UNLOCK','PROPPATCH'] as $method){self::assertSame(405,$this->request($method)->status);}
        self::assertSame($before,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
        $response=$this->request('OPTIONS','/dav/');self::assertSame(200,$response->status);self::assertSame('OPTIONS, PROPFIND, GET, HEAD',$response->headers['Allow']);self::assertSame('1',$response->headers['DAV']);
    }
    public function testTrashedParentsHideDescendantPaths():void
    {
        $folder=$this->files()->createFolder('alice','a','','Folder');$this->pdo->prepare('UPDATE storage_entries SET parent_id=? WHERE id=?')->execute([$folder,$this->file]);
        $this->pdo->prepare('UPDATE storage_entries SET trashed_at=? WHERE id=?')->execute([time(),$folder]);
        self::assertSame(404,$this->request('GET','/dav/a/Folder/Gr%C3%BC%C3%9Fe.txt')->status);
    }
    public function testStaleNodeCannotServeBytesUnderAnOlderEtag():void
    {
        $files=$this->files();$row=$files->details('alice','a',$this->file);
        $node=new \Samla\Core\WebDav\File($files,'alice','a',$row);
        $this->pdo->prepare('UPDATE storage_entries SET revision=revision+1 WHERE id=?')->execute([$this->file]);
        $this->expectException(\Samla\PluginApi\MutationConflict::class);$node->get();
    }
}
