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
use Samla\Core\Storage\{FileService,LocalContentStore,StorageLockService,StoragePlugin,StorageWriteGuard};
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
    private function files(?CredentialAuthorization $auth=null,?RequestIdentity $identity=null):FileService
    {return new FileService($this->pdo,$auth??new AuthorizationService($this->runtime,$this->identity),new PdoMutationRunner($this->pdo,$this->runtime),new LocalContentStore($this->root,1024),lockTokens:$identity===null?[]:fn():array=>$identity->lockTokens);}
    private function request(string $method,string $path='/dav/a/Gr%C3%BC%C3%9Fe.txt',array $headers=[],string $body=''): \Samla\Core\Web\Response
    {
        $identity=new RequestIdentity();$auth=new CredentialAuthorization(new AuthorizationService($this->runtime,$this->identity),$this->passwords,$identity);
        $locks=new StorageLockService($this->pdo,$auth,new PdoMutationRunner($this->pdo,$this->runtime),new StorageWriteGuard($this->pdo));
        return (new Kernel($this->passwords,$identity,$this->files($auth,$identity),$locks,1024))->handle(new HTTP\Request($method,$path,$headers+['Authorization'=>'Basic '.base64_encode('alice:'.$this->password)],$body),'127.0.0.1');
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
    public function testUnimplementedPropertiesRemainDisabledAndOptionsListsImplementedMethods():void
    {
        $before=(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn();
        self::assertSame(405,$this->request('PROPPATCH')->status);
        self::assertSame($before,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
        $response=$this->request('OPTIONS','/dav/');self::assertSame(200,$response->status);self::assertSame('OPTIONS, PROPFIND, GET, HEAD, PUT, MKCOL, MOVE, COPY, DELETE, LOCK, UNLOCK',$response->headers['Allow']);self::assertSame('1, 2',$response->headers['DAV']);
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
    private function manage():void
    {$this->roles->save(new Role('reader',Scope::Space,['files.list','files.read','files.create','files.update','files.delete','files.restore','files.versions.read']),$this->runtime);}
    private function lock(string $path='/dav/a/Gr%C3%BC%C3%9Fe.txt',string $depth='0'):string
    {
        $xml='<d:lockinfo xmlns:d="DAV:"><d:lockscope><d:exclusive/></d:lockscope><d:locktype><d:write/></d:locktype></d:lockinfo>';
        $response=$this->request('LOCK',$path,['Depth'=>$depth,'Timeout'=>'Second-60'],$xml);
        self::assertSame(200,$response->status,$response->body);return $response->headers['Lock-Token'];
    }
    public function testPutCreatesAndVersionsExistingContentWithConflictProtection():void
    {
        $this->manage();$path='/dav/a/new.txt';$created=$this->request('PUT',$path,body:'first');self::assertSame(201,$created->status);
        self::assertSame('first',$this->body($this->request('GET',$path)));
        self::assertSame(204,$this->request('PUT',$path,['If-Match'=>$created->headers['ETag']],body:'second')->status);
        self::assertSame(412,$this->request('PUT',$path,['If-Match'=>$created->headers['ETag']],body:'stale')->status);
        $row=$this->files()->child('alice','a','','new.txt');$history=$this->files()->versions('alice','a',$row['id']);self::assertCount(1,$history['versions']);
        self::assertSame('second',$this->body($this->request('GET',$path)));
    }
    public function testDeleteFreesNameAndRestoreConflictKeepsBothContents():void
    {
        $this->manage();self::assertSame(204,$this->request('DELETE')->status);
        self::assertSame(201,$this->request('PUT',body:'replacement')->status);
        try{$this->files()->change('alice','a',$this->file,'restore','Grüße.txt','');self::fail('Conflicting restore accepted.');}
        catch(\Samla\PluginApi\MutationConflict){self::assertFalse($this->pdo->inTransaction());}
        self::assertSame('replacement',$this->body($this->request('GET')));
        self::assertSame(204,$this->request('DELETE')->status);self::assertSame(201,$this->request('PUT',body:'third')->status);
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries WHERE trashed_at IS NOT NULL')->fetchColumn());
    }
    public function testMoveCopyAndAtomicOverwritePreservePreviousTarget():void
    {
        $this->manage();self::assertSame(201,$this->request('MKCOL','/dav/a/Folder')->status);
        self::assertSame(201,$this->request('COPY',headers:['Destination'=>'/dav/a/copy.txt'])->status);
        self::assertSame(201,$this->request('MOVE',headers:['Destination'=>'https://samla.invalid/dav/a/Folder/moved.txt'])->status);
        self::assertSame(404,$this->request('GET')->status);
        self::assertSame(412,$this->request('MOVE','/dav/a/copy.txt',['Destination'=>'/dav/a/Folder/moved.txt','Overwrite'=>'F'])->status);
        self::assertSame('0123456789',$this->body($this->request('GET','/dav/a/copy.txt')));
        self::assertSame(204,$this->request('MOVE','/dav/a/copy.txt',['Destination'=>'/dav/a/Folder/moved.txt'])->status);
        self::assertSame(404,$this->request('GET','/dav/a/copy.txt')->status);
        self::assertSame('0123456789',$this->body($this->request('GET','/dav/a/Folder/moved.txt')));
        self::assertNotNull($this->pdo->query("SELECT trashed_at FROM storage_entries WHERE id='".$this->file."'")->fetchColumn());
        self::assertSame(501,$this->request('COPY','/dav/a/Folder',['Destination'=>'/dav/a/Another'])->status);
    }
    public function testSharedLocksProtectDavAndBrowserAndRequireOwnerProof():void
    {
        $this->manage();$token=$this->lock();
        self::assertSame(423,$this->request('PUT',body:'blocked')->status);self::assertSame(423,$this->request('DELETE')->status);
        self::assertSame(204,$this->request('PUT',headers:['If'=>'('.$token.')'],body:'updated')->status);
        $controller=new \Samla\Core\Web\FileController($this->files());$csrf=str_repeat('a',64);
        $response=$controller->handle(new \Samla\Core\Web\Request('POST','/files/change',post:['csrf'=>$csrf,'space'=>'a','entry'=>$this->file,'operation'=>'trash','expected_name'=>'Grüße.txt','expected_parent'=>'','parent'=>'']),'alice',$csrf,['actor'=>'alice','csrf'=>$csrf,'caps'=>[],'flash'=>null]);
        self::assertSame(423,$response->status);
        self::assertSame(200,$this->request('LOCK',headers:['If'=>'('.$token.')','Timeout'=>'Second-120'])->status);
        self::assertSame(204,$this->request('UNLOCK',headers:['Lock-Token'=>$token])->status);
        self::assertSame(204,$this->request('PUT',body:'unlocked')->status);
    }
    public function testRecursiveLockAndMalformedNewLockHaveNoPartialCreation():void
    {
        $this->manage();self::assertSame(201,$this->request('MKCOL','/dav/a/Folder')->status);$token=$this->lock('/dav/a/Folder','infinity');
        self::assertSame(423,$this->request('PUT','/dav/a/Folder/child',body:'blocked')->status);
        self::assertSame(201,$this->request('PUT','/dav/a/Folder/child',['If'=>'('.$token.')'],body:'allowed')->status);
        self::assertSame(400,$this->request('LOCK','/dav/a/missing',['Depth'=>'0'],'<d:lockinfo xmlns:d="DAV:"/>')->status);
        self::assertNull($this->files()->child('alice','a','','missing'));
    }
    public function testMalformedConditionsForeignDestinationAndOversizedPutAreRejected():void
    {
        $this->manage();
        foreach(['https://other.example/dav/a/x','https://samla.invalid/dav/a/%2e%2e/x','https://samla.invalid@other.example/dav/a/x','//other.example/dav/a/x']as$destination){self::assertSame(400,$this->request('COPY',headers:['Destination'=>$destination])->status);}
        self::assertSame(400,$this->request('PUT',headers:['If'=>'garbage'],body:'rejected')->status);
        self::assertSame(400,$this->request('PUT',headers:['If'=>'<https://other.example/dav/a/x> (<opaquelocktoken:'.str_repeat('a',32).'>)'],body:'rejected')->status);
        self::assertSame(413,$this->request('PUT',body:str_repeat('x',1025))->status);
        self::assertSame(413,$this->request('PUT',headers:['Content-Length'=>'1025'],body:'small')->status);
        self::assertSame(400,$this->request('PUT',headers:['X-Expected-Entity-Length'=>'2'],body:'')->status);
        self::assertSame('0123456789',$this->body($this->request('GET')));
    }
    public function testOtherActorCannotUseDiscoveredTokenToWriteOrUnlock():void
    {
        $this->manage();$token=$this->lock();
        $this->identity->createUser('bob','https://id.example','bob','Bob');$this->identity->replaceVerifiedGroups('bob',[]);$this->identity->addManualMembership('bob','readers');
        $password=$this->passwords->create('bob','Other device');$auth='Basic '.base64_encode('bob:'.$password);
        self::assertSame(423,$this->request('PUT',headers:['Authorization'=>$auth,'If'=>'('.$token.')'],body:'forged')->status);
        self::assertSame(403,$this->request('UNLOCK',headers:['Authorization'=>$auth,'Lock-Token'=>$token])->status);
        self::assertSame('0123456789',$this->body($this->request('GET')));
    }
    public function testCredentialRevocationByValidatorBlocksFinalWriteAndRollsBack():void
    {
        $this->manage();$pdo=$this->pdo;$id=substr($this->password,6,32);
        $extension=new \Samla\Tests\Fixtures\Extension('test.revoke',static function(\Samla\PluginApi\PluginRegistrar $r)use($pdo,$id):void{
            $r->validator(new class($pdo,$id) implements \Samla\PluginApi\ChangeValidator{
                public function __construct(private \PDO $pdo,private string $id){}
                public function priority():int{return 0;}
                public function validate(\Samla\PluginApi\MutationContext $context):void{
                    if($context->operation==='storage.file.replace'){$this->pdo->prepare('UPDATE identity_app_passwords SET revoked_at=? WHERE id=?')->execute([time(),$this->id]);}
                }
            });
        });
        $this->runtime=PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new RbacPlugin($this->roles,$this->identity),$extension]);
        $before=(int)$pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
        self::assertSame(403,$this->request('PUT',body:'must roll back')->status);
        self::assertSame($before,(int)$pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertTrue($this->passwords->isCredentialActive('alice',$id));self::assertSame('0123456789',$this->body($this->request('GET')));
    }
    public function testOverwriteAuditFailureRestoresSourceDestinationAndOutbox():void
    {
        $this->manage();self::assertSame(201,$this->request('PUT','/dav/a/target.txt',body:'previous target')->status);
        $before=(int)$this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn();
        $this->pdo->exec("CREATE TRIGGER test_dav_audit_failure BEFORE INSERT ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture'");
        try{self::assertSame(503,$this->request('MOVE',headers:['Destination'=>'/dav/a/target.txt'])->status);}
        finally{$this->pdo->exec('DROP TRIGGER test_dav_audit_failure');}
        self::assertSame('previous target',$this->body($this->request('GET','/dav/a/target.txt')));
        self::assertSame('0123456789',$this->body($this->request('GET')));
        self::assertSame($before,(int)$this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries WHERE trashed_at IS NOT NULL')->fetchColumn());
        self::assertSame(204,$this->request('COPY',headers:['Destination'=>'/dav/a/target.txt'])->status);
        self::assertSame('0123456789',$this->body($this->request('GET','/dav/a/target.txt')));
    }
    public function testLockedOverwriteTargetIsPreservedUntilExplicitUnlock():void
    {
        $this->manage();self::assertSame(201,$this->request('PUT','/dav/a/target.txt',body:'previous')->status);$token=$this->lock('/dav/a/target.txt');
        self::assertSame(423,$this->request('MOVE',headers:['Destination'=>'/dav/a/target.txt','If'=>'<https://samla.invalid/dav/a/target.txt> ('.$token.')'])->status);
        self::assertSame('previous',$this->body($this->request('GET','/dav/a/target.txt')));
        self::assertSame(204,$this->request('UNLOCK','/dav/a/target.txt',['Lock-Token'=>$token])->status);
        self::assertSame(204,$this->request('MOVE',headers:['Destination'=>'/dav/a/target.txt'])->status);
    }
    public function testAuthorizedDeleteReleasesItsLeaseAndAllowsCleanNameReuse():void
    {
        $this->manage();$token=$this->lock();self::assertSame(204,$this->request('DELETE',headers:['If'=>'('.$token.')'])->status);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_locks')->fetchColumn());
        self::assertSame(201,$this->request('PUT',body:'new')->status);
        $this->lock();self::assertSame(207,$this->request('PROPFIND','/dav/a/',['Depth'=>'1'])->status);
    }
    public function testCopyOverwriteQuotaFailureKeepsBothEntries():void
    {
        $this->manage();self::assertSame(201,$this->request('PUT','/dav/a/target.txt',body:'old')->status);
        $this->pdo->exec("UPDATE storage_spaces SET quota_bytes=13 WHERE id='a'");
        self::assertSame(413,$this->request('COPY',headers:['Destination'=>'/dav/a/target.txt'])->status);
        self::assertSame('old',$this->body($this->request('GET','/dav/a/target.txt')));
        self::assertSame('0123456789',$this->body($this->request('GET')));
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries WHERE trashed_at IS NOT NULL')->fetchColumn());
    }
    public function testPreUpgradeLeaseOnTrashedChildDoesNotBreakProtocolResolution():void
    {
        $this->manage();self::assertSame(201,$this->request('MKCOL','/dav/a/Folder')->status);
        self::assertSame(201,$this->request('MOVE',headers:['Destination'=>'/dav/a/Folder/child'])->status);$this->lock('/dav/a/Folder/child');
        $this->pdo->prepare('UPDATE storage_entries SET trashed_at=? WHERE id=?')->execute([time(),$this->file]);
        self::assertSame(423,$this->request('MOVE','/dav/a/Folder',['Destination'=>'/dav/a/Renamed'])->status);
    }
    public function testDeleteDoesNotRunLibraryPrefixCleanupOnSiblingLease():void
    {
        $this->manage();$sibling='/dav/a/Gr%C3%BC%C3%9Fe.txt.other';self::assertSame(201,$this->request('PUT',$sibling,body:'keep')->status);
        $this->lock($sibling);self::assertSame(204,$this->request('DELETE')->status);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_locks')->fetchColumn());
        self::assertSame(423,$this->request('PUT',$sibling,body:'no proof')->status);
        self::assertSame('keep',$this->body($this->request('GET',$sibling)));
    }
    private function lockXml(bool $shared=false):string
    {return '<d:lockinfo xmlns:d="DAV:"><d:lockscope><d:'.($shared?'shared':'exclusive').'/></d:lockscope><d:locktype><d:write/></d:locktype></d:lockinfo>';}
    public function testLockOnNewFileCreatesFileAndLeaseAtomically():void
    {
        $this->manage();$path='/dav/a/new%20Gr%C3%BC%C3%9Fe';$before=(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
        $response=$this->request('LOCK',$path,['Depth'=>'0','Timeout'=>'Second-60'],$this->lockXml());self::assertSame(201,$response->status,$response->body);
        $token=$response->headers['Lock-Token'];self::assertStringContainsString($path,$response->body);
        self::assertSame('',$this->body($this->request('GET',$path)));
        self::assertSame(423,$this->request('PUT',$path,body:'no proof')->status);
        self::assertSame(204,$this->request('PUT',$path,['If'=>'('.$token.')'],body:'first content')->status);
        self::assertSame($before+2,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        $event=$this->pdo->query("SELECT targets FROM audit_events WHERE operation='storage.file.create_locked'")->fetchColumn();
        self::assertArrayHasKey('lock',json_decode($event,true));self::assertStringNotContainsString(substr($token,1,-1),$event);
        self::assertSame(204,$this->request('UNLOCK',$path,['Lock-Token'=>$token])->status);
    }
    public function testNewLockNeedsCreateAndUpdateRightsAndRejectsSharedScope():void
    {
        self::assertSame(403,$this->request('LOCK','/dav/a/new',['Depth'=>'0'],$this->lockXml())->status);self::assertNull($this->files()->child('alice','a','','new'));
        $this->manage();self::assertSame(501,$this->request('LOCK','/dav/a/new',['Depth'=>'0'],$this->lockXml(true))->status);
        self::assertNull($this->files()->child('alice','a','','new'));self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_locks')->fetchColumn());
    }
    public function testSupportedLockAdvertisesOnlyExclusiveAndEncodedUris():void
    {
        $this->manage();$this->lock();$xml='<d:propfind xmlns:d="DAV:"><d:prop><d:supportedlock/><d:lockdiscovery/></d:prop></d:propfind>';
        $response=$this->request('PROPFIND',headers:['Depth'=>'0'],body:$xml);self::assertSame(207,$response->status);$body=$this->body($response);
        self::assertStringContainsString('exclusive',$body);self::assertStringNotContainsString('shared',$body);self::assertStringContainsString('Gr%C3%BC%C3%9Fe.txt',$body);
        $document=new \DOMDocument();self::assertTrue($document->loadXML($body));$xpath=new \DOMXPath($document);$xpath->registerNamespace('d','DAV:');
        self::assertSame(1,$xpath->query('//d:supportedlock/d:lockentry/d:lockscope/d:exclusive')->length);
    }
    public function testNewLockCannotOverlapARecursiveAncestorOrLeavePartialState():void
    {
        $this->manage();$token=$this->lock('/dav/a','infinity');
        self::assertSame(423,$this->request('LOCK','/dav/a/new',['Depth'=>'0','If'=>'('.$token.')'],$this->lockXml())->status);
        self::assertNull($this->files()->child('alice','a','','new'));self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_locks')->fetchColumn());
    }
    public function testFailedLeaseInsertRollsBackNewFileAndAudit():void
    {
        $this->manage();$before=(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
        $this->pdo->exec("CREATE TRIGGER test_dav_lock_failure BEFORE INSERT ON storage_locks FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture'");
        try{self::assertSame(503,$this->request('LOCK','/dav/a/new',['Depth'=>'0'],$this->lockXml())->status);}
        finally{$this->pdo->exec('DROP TRIGGER test_dav_lock_failure');}
        self::assertNull($this->files()->child('alice','a','','new'));self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_locks')->fetchColumn());
        self::assertSame($before,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testCatalogCapacityRejectsAllCreationPathsButStillAllowsDeletion():void
    {
        $this->manage();$this->pdo->beginTransaction();
        try{
            for($start=1;$start<10000;$start+=500){$values=[];$params=[];
                for($i=$start;$i<min(10000,$start+500);++$i){$values[]="(?, 'a', '', ?, 'folder', 1)";$params[]=str_pad(dechex($i),32,'0',STR_PAD_LEFT);$params[]='capacity.'.$i;}
                $this->pdo->prepare('INSERT INTO storage_entries (id,space_id,parent_id,name,kind,created_at) VALUES '.implode(',',$values))->execute($params);
            }
            $this->pdo->commit();
        }catch(\Throwable $error){$this->pdo->rollBack();throw $error;}
        self::assertSame(413,$this->request('MKCOL','/dav/a/overflow')->status);
        self::assertSame(413,$this->request('PUT','/dav/a/overflow',body:'new')->status);
        self::assertSame(413,$this->request('COPY',headers:['Destination'=>'/dav/a/overflow'])->status);
        self::assertSame(413,$this->request('LOCK','/dav/a/overflow',['Depth'=>'0'],$this->lockXml())->status);
        self::assertSame(10000,(int)$this->pdo->query("SELECT COUNT(*) FROM storage_entries WHERE space_id='a'")->fetchColumn());
        self::assertSame(204,$this->request('DELETE')->status);
    }
    public function testPluginCanVetoCombinedCreationBeforeEitherReferenceExists():void
    {
        $this->manage();$before=(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
        $extension=new \Samla\Tests\Fixtures\Extension('test.veto_lock_create',static function(\Samla\PluginApi\PluginRegistrar $r):void{
            $r->validator(new class implements \Samla\PluginApi\ChangeValidator{
                public function priority():int{return 0;}
                public function validate(\Samla\PluginApi\MutationContext $context):void{
                    if($context->operation==='storage.file.create_locked'){
                        self::check($context);throw new \Samla\PluginApi\AccessDenied();
                    }
                }
                private static function check(\Samla\PluginApi\MutationContext $context):void{
                    if($context->storageChange===null||$context->storageLock===null||!isset($context->targets['entry'],$context->targets['lock'])){throw new \LogicException();}
                }
            });
        });
        $this->runtime=PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new RbacPlugin($this->roles,$this->identity),$extension]);
        self::assertSame(403,$this->request('LOCK','/dav/a/new',['Depth'=>'0'],$this->lockXml())->status);
        self::assertNull($this->files()->child('alice','a','','new'));self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_locks')->fetchColumn());
        self::assertSame($before,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
}
