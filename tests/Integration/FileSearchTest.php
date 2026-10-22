<?php
declare(strict_types=1);
namespace Samla\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner};
use Samla\Core\Identity\{IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService, LocalContentStore, OrphanCollector, StoragePlugin};
use Samla\Core\Web\{FileController, Request};
use Samla\PluginApi\{AccessDenied, ChangeValidator, MutationConflict, MutationContext, PluginRegistrar, Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin, Role, RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class FileSearchTest extends TestCase
{
    private \PDO $pdo;
    private PdoIdentityRepository $identities;
    private PdoRoleRepository $roles;
    private PluginRuntime $runtime;
    private string $root;
    protected function setUp(): void
    {
        $config = AppConfig::fromGlobals();
        if ($config->environment !== 'test') { throw new \RuntimeException('Isolated test database required.'); }
        $this->pdo = (new ConnectionFactory())->connect($config->database);
        (new MigrationRunner($this->pdo))->migrate(require __DIR__ . '/../../bootstrap/migrations.php');
        foreach (['outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces'] as $table) { $this->pdo->exec('DELETE FROM ' . $table); }
        $this->identities = new PdoIdentityRepository($this->pdo, 3600);
        foreach (['alice', 'bob'] as $user) { $this->identities->createUser($user, 'https://id.example', $user, $user); $this->identities->replaceVerifiedGroups($user, []); }
        $this->identities->createGroup('editors', 'Editors'); $this->identities->addManualMembership('alice', 'editors');
        $this->roles = new PdoRoleRepository($this->pdo); $this->runtime = $this->boot();
        $this->pdo->exec("INSERT INTO storage_spaces (id, name, quota_bytes) VALUES ('a', 'Media', 100), ('b', 'Private', 100)");
        $this->roles->save(new Role('editor', Scope::Space, ['files.list','files.read','files.create','files.update','files.delete','files.restore','files.versions.read']), $this->runtime);
        $this->roles->assign(new RoleAssignment('editors', 'editor', Scope::Space, 'a'));
        $this->root = sys_get_temp_dir() . '/samla-files-' . bin2hex(random_bytes(8)); mkdir($this->root, 0700);
    }
    protected function tearDown(): void
    {
        foreach (new \DirectoryIterator($this->root) as $file) { if (!$file->isDot()) { unlink($file->getPathname()); } }
        rmdir($this->root);
    }
    private function boot(array $extra = []): PluginRuntime
    { return PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($this->roles, $this->identities), ...$extra]); }
    private function service(?PluginRuntime $runtime = null): FileService
    {
        $runtime ??= $this->runtime;
        return new FileService($this->pdo, new AuthorizationService($runtime, $this->identities), new PdoMutationRunner($this->pdo, $runtime), new LocalContentStore($this->root));
    }
    private function upload(string $name, string $content, string $parent = '', ?FileService $service = null): string
    {
        $stream = fopen('php://temp', 'w+b'); fwrite($stream, $content); rewind($stream);
        try { return ($service ?? $this->service())->upload('alice', 'a', $parent, $name, $stream); } finally { fclose($stream); }
    }
    public function testCaseInsensitiveLiteralNameSearchAndTypeFilters(): void
    {
        $folder=$this->service()->createFolder('alice','a','','Älmhult');
        $child=$this->upload('100%_Änderung.MD','secret needle',$folder);
        $this->upload('other.txt','100%_Änderung');
        self::assertSame([$child],array_column($this->service()->search('alice','a','%_änderung','file')['rows'],'id'));
        self::assertSame('Älmhult',$this->service()->search('alice','a','ÄNDERUNG')['rows'][0]['folder_path']);
        self::assertSame([],$this->service()->search('alice','a','needle')['rows']);
        self::assertSame([],$this->service()->search('alice','a','Änderung','folder')['rows']);
        self::assertSame([$folder],array_column($this->service()->search('alice','a','ÄLM','folder')['rows'],'id'));
    }
    public function testTrashedAncestorsAndMalformedAncestryAreExcluded(): void
    {
        $parent=$this->service()->createFolder('alice','a','','parent');$nested=$this->service()->createFolder('alice','a',$parent,'nested');
        $id=$this->upload('hidden.txt','data',$nested);
        $this->service()->change('alice','a',$parent,'trash','parent','');
        self::assertSame([],$this->service()->search('alice','a')['rows']);
        $this->service()->change('alice','a',$parent,'restore','parent','');
        self::assertSame([$id],array_column($this->service()->search('alice','a','hidden')['rows'],'id'));
        $q=$this->pdo->prepare('UPDATE storage_entries SET parent_id=? WHERE id=?');
        $q->execute([$nested,$parent]);self::assertSame([],$this->service()->search('alice','a')['rows']);
        $q->execute([str_repeat('f',32),$parent]);self::assertSame([],$this->service()->search('alice','a')['rows']);
        $q->execute([$id,$parent]);self::assertSame([],$this->service()->search('alice','a')['rows']);
    }
    public function testListRightDoesNotGrantContentAndForeignBlockedOrRestrictedActorsCannotSearch(): void
    {
        $this->upload('report.txt','private');$this->roles->save(new Role('editor',Scope::Space,['files.list']),$this->runtime);
        $page=$this->page(['q'=>'report']);self::assertSame(200,$page->status);self::assertStringContainsString('report.txt',$page->body);self::assertStringNotContainsString('/files/download',$page->body);
        self::assertSame(403,$this->page([],actor:'bob')->status);self::assertSame(403,$this->page(['space'=>'b'])->status);
        $this->pdo->exec("UPDATE identity_users SET status='blocked' WHERE id='alice'");self::assertSame(403,$this->page([])->status);
        $this->pdo->exec("UPDATE identity_users SET status='active' WHERE id='alice'");
        $extension=new Extension('test.search_restriction',static function(PluginRegistrar $r):void{
            $r->restriction(new class implements \Samla\PluginApi\AccessRestriction{
                public function decide(\Samla\PluginApi\AuthorizationRequest $request):\Samla\PluginApi\AuthorizationResult
                {return \Samla\PluginApi\AuthorizationResult::deny('test restriction');}
            });
        });
        try{$this->service($this->boot([$extension]))->search('alice','a');self::fail('Restriction bypassed.');}catch(AccessDenied){self::assertTrue(true);}
    }
    private function page(array $query,string $method='GET',string $actor='alice'): \Samla\Core\Web\Response
    {return (new FileController($this->service()))->handle(new Request($method,'/files/search',query:$query+['space'=>'a']),$actor,str_repeat('a',64),['actor'=>$actor,'csrf'=>str_repeat('a',64),'caps'=>[],'flash'=>null]);}
    public function testWebInputAndEscapingAreBounded(): void
    {
        $this->upload('<script>.txt','data');$page=$this->page(['q'=>'<script>']);self::assertSame(200,$page->status);self::assertStringContainsString('&lt;script&gt;.txt',$page->body);self::assertStringNotContainsString('<script>',$page->body);
        foreach([['q'=>[]],['kind'=>[]],['kind'=>'other'],['q'=>str_repeat('a',129)],['q'=>"\xff"],['q'=>"line\nbreak"],['after'=>'bad']] as $query){self::assertSame(400,$this->page($query)->status);}
        self::assertSame(405,$this->page([],'POST')->status);
    }
    private function folders(int $count): void
    {
        for($start=1;$start<=$count;$start+=500){$values=[];$args=[];
            for($i=$start;$i<min($start+500,$count+1);++$i){$values[]="(?, 'a', '', ?, 'folder', 1)";array_push($args,str_pad(dechex($i),32,'0',STR_PAD_LEFT),'folder '.$i);}
            $this->pdo->prepare('INSERT INTO storage_entries(id,space_id,parent_id,name,kind,created_at) VALUES '.implode(',',$values))->execute($args);
        }
    }
    public function testThousandEntryPaginationHasNoDuplicatesOrLostMatches(): void
    {
        $this->folders(1000);$ids=[];$after=null;$pages=0;
        do{$page=$this->service()->search('alice','a','folder','folder',$after);self::assertCount(100,$page['rows']);$ids=[...$ids,...array_column($page['rows'],'id')];$after=$page['next'];++$pages;}while($after!==null);
        self::assertSame(10,$pages);self::assertCount(1000,array_unique($ids));self::assertSame(str_pad(dechex(1000),32,'0',STR_PAD_LEFT),$ids[999]);
        $page=$this->page(['q'=>'folder','kind'=>'folder']);self::assertStringContainsString('q=folder&amp;kind=folder&amp;after=',$page->body);
    }
    public function testCatalogBoundRejectsPartialResultsInsteadOfSilentlyTruncating(): void
    {
        $this->folders(10001);$this->expectException(\LengthException::class);$this->service()->search('alice','a','missing');
    }
}
