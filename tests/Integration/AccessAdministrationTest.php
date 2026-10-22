<?php
declare(strict_types=1);
namespace Samla\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\{AccessAdministrationDirectory,PdoMutationRunner,PdoResourceDirectory};
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Background\WorkerPlugin;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory,MigrationRunner};
use Samla\Core\Identity\{GroupMembershipService,IdentityPlugin,PdoIdentityRepository};
use Samla\Core\Media\MediaPlugin;
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{SpaceAdministration,StoragePlugin};
use Samla\Core\Web\{PermissionController,SpaceAdministrationController,Request,Response};
use Samla\PluginApi\{AccessDenied,AuthorizationRequest,ChangeValidator,Decision,MutationContext,MutationConflict,PluginRegistrar,Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository,ProfileAccessAdministration,RbacGroupMembershipPolicy,RbacPlugin,Role,RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class AccessAdministrationTest extends TestCase
{
    private \PDO $pdo;private PdoIdentityRepository $identity;private PdoRoleRepository $roles;private PluginRuntime $runtime;
    private ProfileAccessAdministration $access;private AuthorizationService $auth;private SpaceAdministration $spaces;
    private PermissionController $controller;private SpaceAdministrationController $spaceController;
    private string $csrf;
    protected function setUp():void
    {
        $config=AppConfig::fromGlobals();if($config->environment!=='test'){throw new \RuntimeException('Isolated test database required.');}
        $this->pdo=(new ConnectionFactory())->connect($config->database);
        (new MigrationRunner($this->pdo))->migrate(require __DIR__.'/../../bootstrap/migrations.php');
        foreach(['outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces']as$table){$this->pdo->exec('DELETE FROM '.$table);}
        $this->identity=new PdoIdentityRepository($this->pdo,3600);$this->roles=new PdoRoleRepository($this->pdo);$this->csrf=str_repeat('a',64);
        foreach(['admin'=>'Administration','bob'=>'Berta <Team>']as$id=>$name){$this->identity->createUser($id,'https://id.example',$id,$name);$this->identity->replaceVerifiedGroups($id,[]);}
        $this->identity->createGroup('administrators','Administration');$this->identity->createGroup('team','Redaktion <Team>');
        $this->identity->addManualMembership('admin','administrators');$this->identity->addManualMembership('bob','team');
        $this->pdo->exec("INSERT INTO storage_spaces(id,name,quota_bytes) VALUES('space','Medien <Team>',1000000),('other','Andere Medien',2000000)");
        $this->boot();
        $this->roles->save(new Role('operator',Scope::Global,['identity.users.read','identity.groups.read','identity.users.status.manage','identity.groups.memberships.manage','identity.groups.details.manage']),$this->runtime);
        $this->roles->assign(new RoleAssignment('administrators','operator',Scope::Global));
    }
    private function boot(array $extensions=[]):void
    {
        $this->runtime=PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new WorkerPlugin(),new MediaPlugin(),new RbacPlugin($this->roles,$this->identity),...$extensions]);
        $this->auth=new AuthorizationService($this->runtime,$this->identity);$runner=new PdoMutationRunner($this->pdo,$this->runtime);$resources=new PdoResourceDirectory($this->pdo);
        $this->access=new ProfileAccessAdministration($this->roles,$this->auth,$this->runtime,$resources,$runner);
        $directory=new AccessAdministrationDirectory($this->pdo,$this->access);$this->spaces=new SpaceAdministration($this->pdo,$this->access,$this->access,$runner);
        $this->controller=new PermissionController($this->access,$directory);$this->spaceController=new SpaceAdministrationController($this->spaces,$directory);
    }
    private function state(string $profile='samla.files.read',string $group='team',?string $space='space'):array
    {foreach($this->access->state('admin',$group,$space)as$row){if($row['profile']->id===$profile){return $row;}}throw new \LogicException();}
    private function request(string $path,string $method='GET',array $query=[],array $post=[],string $actor='admin'):Response
    {
        $data=['actor'=>$actor,'csrf'=>$this->csrf,'caps'=>['access.profiles.manage'=>$this->access->canManage($actor)],'flash'=>null,'oidc'=>true];
        $request=new Request($method,$path,$query,$post);
        return (str_starts_with($path,'/administration/spaces')?$this->spaceController:$this->controller)->handle($request,$actor,$this->csrf,$data);
    }
    private function assignmentPost(string $profile='samla.files.read',?string $space='space',string $operation='grant',?string $state=null):array
    {return ['csrf'=>$this->csrf,'group'=>'team','space'=>$space??'','selection'=>json_encode([$profile,$state??$this->state($profile,'team',$space)['fingerprint']],JSON_THROW_ON_ERROR),'operation'=>$operation,'confirm'=>'1'];}
    private function allowed(string $actor,string $action,string $space):bool
    {return $this->auth->decide(new AuthorizationRequest($actor,$action,Scope::Space,$space))->decision===Decision::Permit;}
    public function testDropdownsDisplayNamesAndProtectAdministrativeMetadata():void
    {
        $response=$this->request('/permissions',query:['group'=>'team','space'=>'space']);self::assertSame(200,$response->status);
        self::assertStringContainsString('<select id="permission-group"',$response->body);
        self::assertStringContainsString('Redaktion &lt;Team&gt;</option>',$response->body);
        self::assertStringContainsString('Medien &lt;Team&gt;</option>',$response->body);
        self::assertStringContainsString('Lesen (nicht zugewiesen)',$response->body);
        self::assertStringNotContainsString('>team</option>',$response->body);
        self::assertSame(403,$this->request('/permissions',actor:'bob')->status);
        self::assertSame(403,$this->request('/administration/spaces',actor:'bob')->status);
        self::assertSame(400,$this->request('/permissions',query:['group'=>['team']])->status);
        self::assertSame(400,$this->request('/permissions',query:['group'=>'unknown'])->status);
        self::assertSame(405,$this->request('/permissions/change')->status);
    }
    public function testGrantRevokeAndMultipleProfilesUseCurrentRightsWithoutImplicitAdminContentAccess():void
    {
        self::assertFalse($this->allowed('admin','files.read','space'));
        $post=$this->assignmentPost();self::assertSame(303,$this->request('/permissions/change','POST',post:$post)->status);
        self::assertTrue($this->allowed('bob','files.read','space'));self::assertFalse($this->allowed('bob','files.read','other'));
        self::assertFalse($this->allowed('admin','files.read','space'));
        self::assertSame(303,$this->request('/permissions/change','POST',post:$this->assignmentPost('samla.files.manage'))->status);
        self::assertSame(303,$this->request('/permissions/change','POST',post:$this->assignmentPost(operation:'revoke'))->status);
        self::assertTrue($this->allowed('bob','files.read','space'));
        self::assertSame(303,$this->request('/permissions/change','POST',post:$this->assignmentPost('samla.files.manage',operation:'revoke'))->status);
        self::assertFalse($this->allowed('bob','files.read','space'));
        self::assertSame(4,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation LIKE 'rbac.profile.%'")->fetchColumn());
        self::assertSame(4,(int)$this->pdo->query("SELECT COUNT(*) FROM outbox_events WHERE event_type LIKE 'rbac.profile.%'")->fetchColumn());
    }
    public function testCsrfConfirmationUnknownProfilesScopeTamperingAndStaleFormsCannotWrite():void
    {
        $post=$this->assignmentPost();
        foreach([array_replace($post,['csrf'=>str_repeat('b',64)]),array_replace($post,['confirm'=>'0']),array_replace($post,['selection'=>'["samla.administrator","'.str_repeat('a',64).'"]']),array_replace($post,['space'=>'']),array_replace($post,['selection'=>'[1,2]'])]as$invalid){
            self::assertContains($this->request('/permissions/change','POST',post:$invalid)->status,[400,403]);
        }
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertSame(303,$this->request('/permissions/change','POST',post:$post)->status);
        self::assertSame(409,$this->request('/permissions/change','POST',post:$post)->status);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        $this->identity->setBlocked('admin',true);self::assertSame(403,$this->request('/permissions/change','POST',post:$post)->status);
    }
    public function testReservedRolesAreNeverSilentlyExpandedAndSensitiveRightsStaySeparate():void
    {
        $this->roles->save(new Role('samla.files.read',Scope::Space,['files.read','files.list','files.create']),$this->runtime);
        self::assertFalse($this->state()['available']);self::assertSame(403,$this->request('/permissions/change','POST',post:$this->assignmentPost())->status);
        self::assertSame(303,$this->request('/permissions/change','POST',post:$this->assignmentPost('samla.files.media'))->status);
        self::assertFalse($this->allowed('bob','media.sensitive.read','space'));
        self::assertSame(303,$this->request('/permissions/change','POST',post:$this->assignmentPost('samla.files.media-sensitive'))->status);
        self::assertTrue($this->allowed('bob','media.sensitive.read','space'));
    }
    public function testGlobalWorkerProfilesDoNotGrantFileOrIdentityAdministration():void
    {
        self::assertSame(303,$this->request('/permissions/change','POST',post:$this->assignmentPost('samla.worker.manage',null))->status);
        self::assertSame(Decision::Permit,$this->auth->decide(new AuthorizationRequest('bob','operations.worker.retry',Scope::Global))->decision);
        self::assertFalse($this->access->canManage('bob'));self::assertFalse($this->allowed('bob','files.read','space'));
        self::assertSame(303,$this->request('/permissions/change','POST',post:$this->assignmentPost('samla.worker.manage',null,'revoke'))->status);
        self::assertSame(Decision::Deny,$this->auth->decide(new AuthorizationRequest('bob','operations.worker.retry',Scope::Global))->decision);
    }
    public function testTypedPluginVetoAndPrivilegeLossBetweenGuardChecksRollbackEverything():void
    {
        $pdo=$this->pdo;
        $this->boot([new Extension('test.access',static function(PluginRegistrar $registrar)use($pdo):void{
            $registrar->validator(new class($pdo)implements ChangeValidator{
                public function __construct(private \PDO $pdo){}
                public function validate(MutationContext $context):void{
                    if($context->accessProfile!==null){
                        if($context->accessProfile->profile->id==='samla.files.media-sensitive'){throw new AccessDenied();}
                        $this->pdo->exec("DELETE FROM rbac_assignments WHERE group_id='administrators'");
                    }
                }
            });
        })]);
        self::assertSame(403,$this->request('/permissions/change','POST',post:$this->assignmentPost('samla.files.media-sensitive'))->status);
        self::assertSame(403,$this->request('/permissions/change','POST',post:$this->assignmentPost())->status);
        self::assertTrue($this->access->canManage('admin'));self::assertNull($this->roles->find('samla.files.read'));
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testSpaceCreateRenameQuotaAndEmptyDeleteAreAuditedAndDoNotGrantContentAccess():void
    {
        $response=$this->request('/administration/spaces/create','POST',post:['csrf'=>$this->csrf,'name'=>'Projekt Älmhult','quota'=>'100000000000']);
        self::assertSame(303,$response->status);parse_str(parse_url($response->headers['Location'],PHP_URL_QUERY),$query);$id=$query['space'];
        self::assertFalse($this->allowed('admin','files.list',$id));$details=$this->spaces->details('admin',$id);self::assertSame(100000000000,(int)$details['quota_bytes']);
        self::assertTrue($this->spaces->update('admin',$id,'Umbenannt',110000000000,$details['fingerprint']));
        try{$this->spaces->update('admin',$id,'Veraltet',100,$details['fingerprint']);self::fail('Expected revision conflict.');}catch(MutationConflict){self::assertTrue(true);}
        $details=$this->spaces->details('admin',$id);self::assertSame('Umbenannt',$details['name']);
        self::assertTrue($this->spaces->delete('admin',$id,$details['fingerprint']));
        self::assertSame(3,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation LIKE 'storage.space.catalog.%'")->fetchColumn());
        self::assertSame(3,(int)$this->pdo->query("SELECT COUNT(*) FROM outbox_events WHERE event_type LIKE 'storage.space.catalog.%'")->fetchColumn());
    }
    public function testSpaceDeletionCleansAllAssignmentsAndDelegationsButPreservesOtherSpacesAndRoles():void
    {
        foreach(['space','other']as$space){$row=$this->state(space:$space);$this->access->change('admin','samla.files.read','team',$space,true,$row['fingerprint']);}
        $this->pdo->exec("INSERT INTO rbac_delegations(grantor_group_id,role_id,space_id) VALUES('team','samla.files.read','space')");
        $this->pdo->exec("INSERT INTO rbac_delegation_permissions(grantor_group_id,role_id,space_id,permission_id) VALUES('team','samla.files.read','space','files.read')");
        $details=$this->spaces->details('admin','space');self::assertTrue($this->spaces->delete('admin','space',$details['fingerprint']));
        self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM rbac_assignments WHERE space_id='space'")->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM rbac_delegations')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM rbac_delegation_permissions')->fetchColumn());
        self::assertTrue($this->allowed('bob','files.read','other'));self::assertNotNull($this->roles->find('samla.files.read'));
    }
    public function testSpaceQuotaCountsTrashAndHistoryAndNonemptyDeleteCannotCascade():void
    {
        $this->pdo->exec("INSERT INTO storage_entries(id,space_id,parent_id,name,kind,blob_id,size_bytes,sha256,created_at,trashed_at) VALUES('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','space','','Video','file',REPEAT('a',64),100,REPEAT('b',64),1,1)");
        $this->pdo->exec("INSERT INTO storage_versions(id,entry_id,revision,blob_id,size_bytes,sha256,created_at) VALUES(REPEAT('c',32),REPEAT('a',32),0,REPEAT('d',64),200,REPEAT('e',64),1)");
        $details=$this->spaces->details('admin','space');self::assertSame(300,$details['used_bytes']);
        try{$this->spaces->update('admin','space','Medien',299,$details['fingerprint']);self::fail('Expected quota conflict.');}catch(MutationConflict){self::assertTrue(true);}
        try{$this->spaces->delete('admin','space',$details['fingerprint']);self::fail('Expected nonempty conflict.');}catch(MutationConflict){self::assertTrue(true);}
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_versions')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testSpaceFormsRequireCsrfConfirmationAndValidReferences():void
    {
        self::assertSame(200,$this->request('/administration/spaces',query:['space'=>'space'])->status);
        $details=$this->spaces->details('admin','space');$post=['csrf'=>$this->csrf,'space'=>'space','expected'=>$details['fingerprint']];
        self::assertSame(400,$this->request('/administration/spaces/delete','POST',post:$post)->status);
        self::assertSame(403,$this->request('/administration/spaces/delete','POST',post:$post+['confirm'=>'1'],actor:'bob')->status);
        self::assertSame(403,$this->request('/administration/spaces/create','POST',post:['csrf'=>str_repeat('b',64),'name'=>'Team','quota'=>'100'])->status);
        self::assertSame(405,$this->request('/administration/spaces/delete')->status);
        self::assertSame(400,$this->request('/administration/spaces',query:['space'=>['space']])->status);
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_spaces')->fetchColumn());
    }
    public function testProfileGroupMembershipIsUsableButAdministrationAndCustomRolesRemainProtected():void
    {
        $row=$this->state();$this->access->change('admin','samla.files.read','team','space',true,$row['fingerprint']);
        $resources=new PdoResourceDirectory($this->pdo);
        $policy=new RbacGroupMembershipPolicy($this->roles,$this->identity,$this->auth,$this->runtime,$resources,$this->access);
        $service=new GroupMembershipService($this->identity,$this->auth,$policy,$resources,new PdoMutationRunner($this->pdo,$this->runtime));
        self::assertTrue($service->revoke('admin','bob','team'));self::assertFalse($this->allowed('bob','files.read','space'));
        self::assertTrue($service->grant('admin','bob','team'));self::assertTrue($this->allowed('bob','files.read','space'));
        self::assertFalse($policy->allows('admin','bob','administrators',true));
        $this->roles->save(new Role('custom',Scope::Space,['files.read']),$this->runtime);$this->roles->assign(new RoleAssignment('team','custom',Scope::Space,'space'));
        self::assertFalse($policy->allows('admin','bob','team',true));
    }
    public function testActiveLocksAndLifecycleFailurePreventPartialSpaceDeletion():void
    {
        $row=$this->state();$this->access->change('admin','samla.files.read','team','space',true,$row['fingerprint']);
        $q=$this->pdo->prepare("INSERT INTO storage_locks(id,token,space_id,entry_id,actor_id,recursive_lock,owner_label,expires_at) VALUES(REPEAT('a',32),'opaque','space','','admin',1,'',?)");$q->execute([time()+60]);
        $details=$this->spaces->details('admin','space');
        try{$this->spaces->delete('admin','space',$details['fingerprint']);self::fail('Expected active lock conflict.');}catch(MutationConflict){self::assertTrue(true);}
        $this->pdo->exec('UPDATE storage_locks SET expires_at=1');
        $access=$this->access;
        $cleanup=new class($access)implements \Samla\PluginApi\SpaceAccessCleanup{
            public function __construct(private ProfileAccessAdministration $access){}
            public function removeSpaceInsideTransaction(string $actor,string $space):void{
                $this->access->removeSpaceInsideTransaction($actor,$space);throw new \RuntimeException('Synthetic lifecycle failure.');
            }
        };
        $service=new SpaceAdministration($this->pdo,$access,$cleanup,new PdoMutationRunner($this->pdo,$this->runtime));
        try{$service->delete('admin','space',$details['fingerprint']);self::fail('Expected lifecycle rollback.');}catch(\RuntimeException $error){self::assertSame('Synthetic lifecycle failure.',$error->getMessage());}
        self::assertTrue($this->allowed('bob','files.read','space'));self::assertSame('Medien <Team>',$this->spaces->details('admin','space')['name']);
        self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation='storage.space.catalog.delete'")->fetchColumn());
        self::assertTrue($this->spaces->delete('admin','space',$details['fingerprint']));
    }

    public function testExistingCliSpaceAssignmentsCanBeRevokedByNamedDropdownWithoutAcceptingCustomRoles():void
    {
        $legacy='files.'.str_repeat('a',32);
        $this->roles->save(new Role($legacy,Scope::Space,['files.list','files.read','files.create']),$this->runtime);
        $this->roles->assign(new RoleAssignment('team',$legacy,Scope::Space,'space'));
        $state=$this->state($legacy);self::assertTrue($state['assigned']);
        $response=$this->request('/permissions',query:['group'=>'team','space'=>'space']);
        self::assertStringContainsString('Hochladen (bisherige Zuweisung) (zugewiesen)',$response->body);
        self::assertSame(303,$this->request('/permissions/change','POST',post:$this->assignmentPost($legacy,operation:'revoke'))->status);
        self::assertFalse($this->allowed('bob','files.read','space'));
        self::assertNotNull($this->roles->find($legacy));
        self::assertSame(400,$this->request('/permissions/change','POST',post:['csrf'=>$this->csrf,'group'=>'team','space'=>'space','selection'=>json_encode(['operator',str_repeat('a',64)]),'operation'=>'revoke','confirm'=>'1'])->status);
    }

}
