<?php
declare(strict_types=1);
namespace Samla\Tests\Integration;
use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Catalog\{CatalogPlugin,CatalogService};
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory,MigrationRunner};
use Samla\Core\Identity\{IdentityPlugin,PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService,LocalContentStore,StoragePlugin};
use Samla\Core\Web\{CatalogController,Request,Response};
use Samla\PluginApi\{AccessDenied,CatalogEntryChange,CatalogTagChange,ChangeValidator,MutationConflict,MutationContext,PluginRegistrar,Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository,RbacPlugin,Role,RoleAssignment};
use Samla\Tests\Fixtures\Extension;
final class CatalogTest extends TestCase
{
    private \PDO $pdo;private PdoIdentityRepository $identity;private PdoRoleRepository $roles;private PluginRuntime $runtime;
    private FileService $files;private CatalogService $catalog;private string $root;private string $entry;private string $csrf;
    protected function setUp():void
    {
        $config=AppConfig::fromGlobals();if($config->environment!=='test'){throw new \RuntimeException('Isolated DB required.');}
        $this->pdo=(new ConnectionFactory())->connect($config->database);(new MigrationRunner($this->pdo))->migrate(require __DIR__.'/../../bootstrap/migrations.php');
        foreach(['catalog_entry_tags','catalog_entries','catalog_tags','outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces']as$table){$this->pdo->exec('DELETE FROM '.$table);}
        $this->identity=new PdoIdentityRepository($this->pdo,3600);$this->roles=new PdoRoleRepository($this->pdo);
        foreach(['alice','bob']as$user){$this->identity->createUser($user,'https://id.example',$user,$user);$this->identity->replaceVerifiedGroups($user,[]);}
        $this->identity->createGroup('team','Team');$this->identity->addManualMembership('alice','team');
        $this->pdo->exec("INSERT INTO storage_spaces(id,name,quota_bytes) VALUES('space','Team',1000000),('other','Andere',1000000)");
        $this->root=sys_get_temp_dir().'/samla-catalog-'.bin2hex(random_bytes(8));mkdir($this->root,0700);$this->csrf=str_repeat('a',64);$this->boot();
        $this->roles->save(new Role('editor',Scope::Space,['files.list','files.read','files.create','files.update','files.delete','files.restore','files.purge','catalog.read','catalog.write','catalog.tags.manage']),$this->runtime);
        $this->roles->assign(new RoleAssignment('team','editor',Scope::Space,'space'));
        $source=fopen('php://temp','w+b');fwrite($source,'original');rewind($source);
        try{$this->entry=$this->files->upload('alice','space','','Hallo <Team>.txt',$source);}finally{fclose($source);}
    }
    protected function tearDown():void
    {
        foreach(['catalog_entry_tags','catalog_entries','catalog_tags']as$table){$this->pdo->exec('DELETE FROM '.$table);}
        foreach(new \DirectoryIterator($this->root)as$file){if(!$file->isDot()){unlink($file->getPathname());}}rmdir($this->root);
    }
    private function boot(array $extensions=[]):void
    {
        $this->runtime=PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new CatalogPlugin(),new RbacPlugin($this->roles,$this->identity),...$extensions]);
        $auth=new AuthorizationService($this->runtime,$this->identity);$runner=new PdoMutationRunner($this->pdo,$this->runtime);
        $this->files=new FileService($this->pdo,$auth,$runner,new LocalContentStore($this->root,1000000));$this->catalog=new CatalogService($this->pdo,$this->files,$auth,$runner);
    }
    private function tag(string $name='Sommer'):string
    {$id=bin2hex(random_bytes(16));$this->catalog->changeTag('alice',new CatalogTagChange('space',$id,'create',null,$name));return$id;}
    private function save(string $description,array $tags=[],int $catalogRevision=0,int $entryRevision=1):bool
    {return$this->catalog->save('alice',new CatalogEntryChange('space',$this->entry,$entryRevision,$catalogRevision,$description,$tags));}
    private function page(string $path='/catalog',string $method='GET',array $post=[],string $actor='alice'):Response
    {return(new CatalogController($this->catalog))->handle(new Request($method,$path,['space'=>'space','entry'=>$this->entry],$post),$actor,$this->csrf,['actor'=>$actor,'csrf'=>$this->csrf,'caps'=>[],'flash'=>null]);}
    public function testDescriptionsAndTagsPreserveOriginalAndHaveIndependentRevisionAndDataminimalEvents():void
    {
        $tag=$this->tag();self::assertTrue($this->save("Manuell <script>\n",[$tag]));$d=$this->catalog->details('alice','space',$this->entry);
        self::assertSame(1,(int)$d['catalog']['revision']);self::assertSame([$tag],$d['selected_tags']);self::assertSame(1,(int)$d['entry']['revision']);
        $download=$this->files->download('alice','space',$this->entry,1);self::assertSame('original',stream_get_contents($download['stream']));fclose($download['stream']);
        self::assertFalse($this->save("Manuell <script>\n",[$tag],1));
        $wire=implode('',$this->pdo->query("SELECT payload FROM outbox_events WHERE event_type='catalog.entry.update.completed'")->fetchAll(\PDO::FETCH_COLUMN));
        self::assertStringNotContainsString('Manuell',$wire);self::assertStringNotContainsString('Sommer',$wire);self::assertStringContainsString($this->entry,$wire);
        self::assertSame(200,$this->page()->status);self::assertStringContainsString('Manuell &lt;script&gt;',$this->page()->body);
        self::assertStringContainsString('Sommer</option>',$this->page()->body);
    }
    public function testCatalogAndFileRevisionConflictsRollbackWithoutOverwriting():void
    {
        $this->save('Erster Stand');
        try{$this->save('Veraltet');self::fail('Stale metadata accepted.');}catch(MutationConflict){self::assertFalse($this->pdo->inTransaction());}
        $s=fopen('php://temp','w+b');fwrite($s,'new');rewind($s);try{$this->files->replace('alice','space',$this->entry,1,$s);}finally{fclose($s);}
        try{$this->save('Alte Datei',[],1,1);self::fail('Stale source accepted.');}catch(MutationConflict){self::assertFalse($this->pdo->inTransaction());}
        self::assertSame('Erster Stand',$this->catalog->details('alice','space',$this->entry)['catalog']['description']);self::assertTrue($this->save('Aktuell',[],1,2));
    }
    public function testFileAndCatalogRightsAreRequiredAndCurrent():void
    {
        self::assertSame(403,$this->page(actor:'bob')->status);
        $this->roles->save(new Role('editor',Scope::Space,['files.list','files.read','catalog.read']),$this->runtime);
        self::assertSame(200,$this->page()->status);self::assertStringNotContainsString('Beschreibung und Tags speichern',$this->page()->body);
        try{$this->save('forbidden');self::fail('Write without permission.');}catch(AccessDenied){self::assertTrue(true);}
        $this->roles->save(new Role('editor',Scope::Space,['files.list','catalog.read','catalog.write']),$this->runtime);
        self::assertSame(403,$this->page()->status);
        $this->identity->setBlocked('alice',true);self::assertSame(403,$this->page()->status);
    }
    public function testTagReferencesCannotCrossSpacesAndTagNamesAreUniqueAndRevisionProtected():void
    {
        $tag=$this->tag('Sommer');
        try{$this->tag('sommer');self::fail('Case-equivalent duplicate.');}catch(MutationConflict){self::assertTrue(true);}
        $foreign=bin2hex(random_bytes(16));$q=$this->pdo->prepare('INSERT INTO catalog_tags(id,space_id,name,name_key) VALUES(?,?,?,?)');$q->execute([$foreign,'other','Fremd',hash('sha256','fremd')]);
        try{$this->save('cross',[$foreign]);self::fail('Foreign tag accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}
        $this->catalog->changeTag('alice',new CatalogTagChange('space',$tag,'rename',1,'Herbst'));
        try{$this->catalog->changeTag('alice',new CatalogTagChange('space',$tag,'delete',1,null));self::fail('Stale tag accepted.');}catch(MutationConflict){self::assertTrue(true);}
        self::assertSame('Herbst',$this->catalog->tags('alice','space')[0]['name']);
    }
    public function testTagDeletionProtectsAssociationsIncludingTrashAndPurgeRemovesOnlyCatalogReferences():void
    {
        $tag=$this->tag();$this->save('Bleibt erhalten',[$tag]);
        $this->files->change('alice','space',$this->entry,'trash','Hallo <Team>.txt','','Hallo <Team>.txt','');
        try{$this->catalog->details('alice','space',$this->entry);self::fail('Trash leaked.');}catch(\InvalidArgumentException){self::assertTrue(true);}
        try{$this->catalog->changeTag('alice',new CatalogTagChange('space',$tag,'delete',1,null));self::fail('Used tag deleted.');}catch(MutationConflict){self::assertTrue(true);}
        $this->files->change('alice','space',$this->entry,'restore','Hallo <Team>.txt','','Hallo <Team>.txt','');
        self::assertSame('Bleibt erhalten',$this->catalog->details('alice','space',$this->entry)['catalog']['description']);
        $this->files->change('alice','space',$this->entry,'trash','Hallo <Team>.txt','','Hallo <Team>.txt','');
        $q=$this->pdo->prepare('SELECT revision FROM storage_entries WHERE id=?');$q->execute([$this->entry]);$this->files->purge('alice','space',$this->entry,(int)$q->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM catalog_entries')->fetchColumn());self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM catalog_entry_tags')->fetchColumn());
        self::assertTrue($this->catalog->changeTag('alice',new CatalogTagChange('space',$tag,'delete',1,null)));
    }
    public function testPluginCanVetoExactManualProposalAtomically():void
    {
        $validator=new class implements ChangeValidator {public ?CatalogEntryChange $seen=null;public function validate(MutationContext $context):void{if($context->catalogEntry!==null){$this->seen=$context->catalogEntry;throw new AccessDenied();}}};
        $this->boot([new Extension('example.catalog',static function(PluginRegistrar $r)use($validator):void{$r->validator($validator);})]);
        try{$this->save('privat');self::fail('Veto ignored.');}catch(AccessDenied){self::assertSame('privat',$validator->seen->description);}
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM catalog_entries')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation='catalog.entry.update'")->fetchColumn());
    }
    public function testHttpFormsRequireCsrfAndValidateMultiSelectWithoutTypingEntityIds():void
    {
        $tag=$this->tag();$post=['csrf'=>$this->csrf,'space'=>'space','entry'=>$this->entry,'entry_revision'=>'1','catalog_revision'=>'0','description'=>'Hallo','tags'=>[$tag]];
        self::assertSame(303,$this->page('/catalog/save','POST',$post)->status);
        self::assertSame(409,$this->page('/catalog/save','POST',$post)->status);
        self::assertSame(403,$this->page('/catalog/save','POST',array_replace($post,['csrf'=>str_repeat('b',64)]))->status);
        foreach([['tags'=>'x'],['tags'=>[$tag,$tag]],['description'=>str_repeat('x',16385)],['entry_revision'=>'-1']]as$bad){self::assertSame(400,$this->page('/catalog/save','POST',array_replace($post,$bad))->status);}
        self::assertSame(405,$this->page('/catalog/save','GET')->status);
        self::assertSame(200,$this->page()->status);self::assertStringContainsString('name="tags[]" multiple',$this->page()->body);
    }
    public function testTagCapacityAndSelectionBoundsAreEnforcedInsideMutation():void
    {
        $ids=[];for($i=0;$i<100;$i++){$ids[]=$this->tag('Tag '.str_pad((string)$i,3,'0',STR_PAD_LEFT));}
        try{$this->tag('Overflow');self::fail('Unbounded dictionary.');}catch(\LengthException){self::assertFalse($this->pdo->inTransaction());}
        self::assertTrue($this->save('Fünfzig',array_slice($ids,0,50)));
        try{$this->save('Too many',array_slice($ids,0,51),1);self::fail('Unbounded assignments.');}catch(\InvalidArgumentException){self::assertSame('Fünfzig',$this->catalog->details('alice','space',$this->entry)['catalog']['description']);}
    }

}
