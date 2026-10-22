<?php
declare(strict_types=1);
namespace Samla\Tests\Integration;
use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\{AccessAdministrationDirectory,AdministrationQueryService,PdoMutationRunner,PdoResourceDirectory};
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Catalog\CatalogPlugin;
use Samla\Core\Configuration\{AppConfig,PersonalSpaceConfig};
use Samla\Core\Database\{ConnectionFactory,MigrationRunner};
use Samla\Core\Export\ExportPlugin;
use Samla\Core\Identity\{GroupDetailsService,GroupMappingService,GroupMembershipService,IdentityPlugin,PdoIdentityRepository};
use Samla\Core\Media\MediaPlugin;
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService,LocalContentStore,PersonalSpaceService,SpaceAdministration,StoragePlugin};
use Samla\PluginApi\{AccessDenied,AuthorizationRequest,ChangeValidator,Decision,MutationConflict,MutationContext,PersonalSpaceChange,PluginRegistrar,Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository,PersonalSpaceAccessProvisioner,ProfileAccessAdministration,RbacGroupMappingPolicy,RbacGroupMembershipPolicy,RbacPlugin,Role,RoleAssignment,RoleAssignmentService};
use Samla\Tests\Fixtures\Extension;
final class PersonalSpaceTest extends TestCase
{
    private \PDO $pdo;private PdoIdentityRepository $identity;private PdoRoleRepository $roles;private PluginRuntime $runtime;
    private AuthorizationService $auth;private PersonalSpaceService $personal;private FileService $files;private ProfileAccessAdministration $access;private string $root;
    protected function setUp():void
    {
        $config=AppConfig::fromGlobals();if($config->environment!=='test'){throw new \RuntimeException('Isolated DB required.');}
        $this->pdo=(new ConnectionFactory())->connect($config->database);(new MigrationRunner($this->pdo))->migrate(require __DIR__.'/../../bootstrap/migrations.php');
        foreach(['personal_storage_spaces','catalog_entry_tags','catalog_entries','catalog_tags','outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces']as$table){$this->pdo->exec('DELETE FROM '.$table);}
        $this->identity=new PdoIdentityRepository($this->pdo,3600);$this->roles=new PdoRoleRepository($this->pdo);
        foreach(['alice','bob','admin']as$user){$this->identity->createUser($user,'https://id.example',$user,ucfirst($user));$this->identity->replaceVerifiedGroups($user,[]);}
        $this->identity->createGroup('administrators','Administration');$this->identity->addManualMembership('admin','administrators');
        $this->root=sys_get_temp_dir().'/samla-personal-'.bin2hex(random_bytes(8));mkdir($this->root,0700);$this->boot();
        $this->roles->save(new Role('administrator',Scope::Global,['identity.users.read','identity.groups.read','identity.users.status.manage','identity.groups.details.manage','identity.groups.memberships.manage','identity.groups.mappings.manage']),$this->runtime);
        $this->roles->assign(new RoleAssignment('administrators','administrator',Scope::Global));
    }
    protected function tearDown():void
    {
        $this->pdo->exec('DELETE FROM personal_storage_spaces');
        foreach(new \DirectoryIterator($this->root)as$file){if(!$file->isDot()){unlink($file->getPathname());}}rmdir($this->root);
    }
    private function boot(array $extensions=[],?PersonalSpaceConfig $config=null):void
    {
        $this->runtime=PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new CatalogPlugin(),new MediaPlugin(),new ExportPlugin(),new RbacPlugin($this->roles,$this->identity),...$extensions]);
        $this->auth=new AuthorizationService($this->runtime,$this->identity);$runner=new PdoMutationRunner($this->pdo,$this->runtime);$resources=new PdoResourceDirectory($this->pdo);
        $this->access=new ProfileAccessAdministration($this->roles,$this->auth,$this->runtime,$resources,$runner);
        $this->personal=new PersonalSpaceService($this->pdo,$this->identity,$runner,new PersonalSpaceAccessProvisioner($this->roles,$this->identity,$this->runtime,$resources),$this->auth,$config??new PersonalSpaceConfig(true,1000000));
        $this->files=new FileService($this->pdo,$this->auth,$runner,new LocalContentStore($this->root,1000000));
    }
    private function allowed(string $actor,string $space,string $permission='files.read'):bool
    {return$this->auth->decide(new AuthorizationRequest($actor,$permission,Scope::Space,$space))->decision===Decision::Permit;}
    private function spaces():SpaceAdministration
    {return new SpaceAdministration($this->pdo,$this->access,$this->access,new PdoMutationRunner($this->pdo,$this->runtime));}
    public function testFirstLoginCreatesOneOwnerSpaceWithNoImplicitAdministratorOrOtherUserAccess():void
    {
        $before=$this->pdo->query("SELECT groups_confirmed_at FROM identity_users WHERE id='alice'")->fetchColumn();$space=$this->personal->ensure('alice');
        self::assertTrue($this->allowed('alice',$space));self::assertTrue($this->allowed('alice',$space,'catalog.write'));self::assertTrue($this->allowed('alice',$space,'files.export'));
        self::assertFalse($this->allowed('bob',$space));self::assertFalse($this->allowed('admin',$space));
        self::assertSame($before,$this->pdo->query("SELECT groups_confirmed_at FROM identity_users WHERE id='alice'")->fetchColumn());
        self::assertCount(1,$this->files->spaces('alice')['rows']);self::assertSame([],$this->files->spaces('admin')['rows']);
        $s=fopen('php://temp','w+b');fwrite($s,'private');rewind($s);try{$id=$this->files->upload('alice',$space,'','Privat.txt',$s);}finally{fclose($s);}
        self::assertSame('Privat.txt',$this->files->details('alice',$space,$id)['name']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation='storage.space.personal.provision'")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM personal_storage_spaces')->fetchColumn());
        $this->identity->setBlocked('alice',true);self::assertFalse($this->allowed('alice',$space));
    }
    public function testRepeatedLoginPreservesConfiguredNameQuotaAndOwnerIdentity():void
    {
        $space=$this->personal->ensure('alice');$details=$this->spaces()->details('admin',$space);self::assertSame('Alice',$details['owner_name']);
        $this->spaces()->update('admin',$space,'Mein Bereich',2000000,$details['fingerprint']);$this->pdo->exec("UPDATE identity_users SET display_name='Anderer Name' WHERE id='alice'");
        $this->boot(config:new PersonalSpaceConfig(true,3000000));self::assertSame($space,$this->personal->ensure('alice'));
        $details=$this->spaces()->details('admin',$space);self::assertSame('Mein Bereich',$details['name']);self::assertSame(2000000,(int)$details['quota_bytes']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation='storage.space.personal.provision'")->fetchColumn());
        self::assertNotSame($space,$this->personal->ensure('bob'));
    }
    public function testBlockedStaleAndDisabledProvisioningNeverCreatesAnArea():void
    {
        $this->identity->setBlocked('alice',true);
        try{$this->personal->ensure('alice');self::fail('Blocked owner accepted.');}catch(AccessDenied){self::assertTrue(true);}
        $this->pdo->exec("UPDATE identity_users SET groups_confirmed_at=1 WHERE id='bob'");
        try{$this->personal->ensure('bob');self::fail('Stale owner accepted.');}catch(AccessDenied){self::assertTrue(true);}
        $this->boot(config:new PersonalSpaceConfig(false,1000));self::assertNull($this->personal->ensure('admin'));
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM personal_storage_spaces')->fetchColumn());
    }
    public function testReservedGroupsCannotBeChangedByOrdinaryMembershipMappingOrProfileAdministration():void
    {
        $space=$this->personal->ensure('alice');$change=new PersonalSpaceChange('alice','Personal',1000);$resources=new PdoResourceDirectory($this->pdo);$runner=new PdoMutationRunner($this->pdo,$this->runtime);
        $policy=new RbacGroupMembershipPolicy($this->roles,$this->identity,$this->auth,$this->runtime,$resources,$this->access);
        foreach([
            fn()=>(new GroupMembershipService($this->identity,$this->auth,$policy,$resources,$runner))->grant('admin','bob',$change->groupId),
            fn()=>(new GroupMappingService($this->identity,$this->auth,new RbacGroupMappingPolicy($policy),$resources,$runner))->grant('admin','https://id.example','team',$change->groupId),
            fn()=>(new GroupDetailsService($this->identity,$this->auth,$runner))->rename('admin',$change->groupId,'Persönlich: Alice','Geändert'),
            fn()=>$this->access->state('admin',$change->groupId,$space),
            fn()=>(new RoleAssignmentService($this->roles,$this->identity,$this->auth,$this->runtime,$resources,$runner))->grant('admin',new RoleAssignment('administrators',PersonalSpaceAccessProvisioner::ROLE,Scope::Space,$space)),
        ]as$call){try{$call();self::fail('Reserved ownership administration bypassed.');}catch(AccessDenied){self::assertTrue(true);}}
        self::assertNotContains($change->groupId,array_column((new AccessAdministrationDirectory($this->pdo,$this->access))->choices('admin')['groups'],'id'));
        self::assertNotContains($change->groupId,array_column((new AdministrationQueryService($this->pdo,$this->auth))->mappingChoices('admin'),'id'));
        self::assertFalse($this->allowed('bob',$space));
    }
    public function testPersonalDeletionIsBlockedWhileSharedEmptyCatalogStillCanBeManaged():void
    {
        $space=$this->personal->ensure('alice');$details=$this->spaces()->details('admin',$space);
        try{$this->spaces()->delete('admin',$space,$details['fingerprint']);self::fail('Automatic owner area deleted.');}catch(MutationConflict){self::assertFalse($this->pdo->inTransaction());}
        $shared=$this->spaces()->create('admin','Gemeinsam',1000);$row=$this->spaces()->details('admin',$shared);self::assertNull($row['owner_id']);
        self::assertTrue($this->spaces()->delete('admin',$shared,$row['fingerprint']));self::assertSame($space,$this->personal->ensure('alice'));
    }
    public function testVetoAndSqlFailureRollbackOwnerGroupMembershipRoleSpaceAndAudit():void
    {
        $validator=new class implements ChangeValidator {public ?PersonalSpaceChange $seen=null;public function validate(MutationContext $c):void{if($c->personalSpace!==null){$this->seen=$c->personalSpace;throw new AccessDenied();}}};
        $this->boot([new Extension('example.owner',static function(PluginRegistrar $r)use($validator):void{$r->validator($validator);})]);
        try{$this->personal->ensure('alice');self::fail('Veto ignored.');}catch(AccessDenied){self::assertSame('alice',$validator->seen->ownerId);}
        $this->boot();$this->pdo->exec("CREATE TRIGGER fail_personal_space BEFORE INSERT ON personal_storage_spaces FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='injected owner failure'");
        try{try{$this->personal->ensure('alice');self::fail('SQL failure ignored.');}catch(\PDOException){self::assertFalse($this->pdo->inTransaction());}}
        finally{$this->pdo->exec('DROP TRIGGER fail_personal_space');}
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_spaces')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM identity_groups WHERE id LIKE 'samla.personal.%'")->fetchColumn());
        self::assertNull($this->roles->find(PersonalSpaceAccessProvisioner::ROLE));self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testReservedIdentifierAndRoleCollisionsFailClosed():void
    {
        $change=new PersonalSpaceChange('alice','Personal',1000);$this->identity->createGroup($change->groupId,'Fremde Gruppe');
        try{$this->personal->ensure('alice');self::fail('Existing group reused.');}catch(MutationConflict){self::assertTrue(true);}
        $this->pdo->prepare('DELETE FROM identity_groups WHERE id=?')->execute([$change->groupId]);
        $this->roles->save(new Role(PersonalSpaceAccessProvisioner::ROLE,Scope::Space,['files.read']),$this->runtime);
        try{$this->personal->ensure('alice');self::fail('Modified owner role overwritten.');}catch(\LogicException){self::assertTrue(true);}
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM personal_storage_spaces')->fetchColumn());self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_spaces')->fetchColumn());
    }
    public function testProvisioningUsesSharedAdministrationLockAndThenRemainsIdempotent():void
    {
        $other=(new ConnectionFactory())->connect(AppConfig::fromGlobals()->database);$other->beginTransaction();$other->query('SELECT id FROM administration_lock WHERE id=1 FOR UPDATE')->fetchAll();
        $this->pdo->exec('SET SESSION innodb_lock_wait_timeout=1');
        try{try{$this->personal->ensure('alice');self::fail('Unlocked owner write.');}catch(\PDOException $e){self::assertSame(1205,$e->errorInfo[1]);self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM personal_storage_spaces')->fetchColumn());}}
        finally{$other->rollBack();}
        $space=$this->personal->ensure('alice');self::assertSame($space,$this->personal->ensure('alice'));self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM personal_storage_spaces')->fetchColumn());
    }
    public function testProvisioningPreservesExistingAdministrativeAccessAcrossValidators():void
    {
        $identity=$this->identity;
        $validator=new class($identity) implements ChangeValidator {
            public function __construct(private PdoIdentityRepository $identity){}
            public function validate(MutationContext $c):void{if($c->personalSpace!==null){$this->identity->setManualMembershipInsideTransaction('admin','administrators',false);}}
        };
        $this->boot([new Extension('example.continuity',static function(PluginRegistrar $r)use($validator):void{$r->validator($validator);})]);
        try{$this->personal->ensure('alice');self::fail('Administrative foundation removed.');}catch(AccessDenied){self::assertFalse($this->pdo->inTransaction());}
        self::assertContains('administrators',$this->identity->groupIds('admin'));self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM personal_storage_spaces')->fetchColumn());
    }

}
