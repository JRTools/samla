<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Samla\Core\Web\PageRenderer;

final class WorkspaceRenderingTest extends TestCase
{
    private function data(): array
    {
        return ['actor'=>'private.internal.id','display_name'=>'Alex <Team>', 'csrf'=>str_repeat('a',64),
            'caps'=>[], 'flash'=>null, 'oidc'=>true];
    }

    public function testWorkspaceDoesNotExposeInternalIdentityOrAdministrativeNavigationToReader(): void
    {
        $html=(new PageRenderer())->render('home',$this->data());
        self::assertStringContainsString('Alex &lt;Team&gt;',$html);
        self::assertStringNotContainsString('private.internal.id',$html);
        self::assertStringNotContainsString('aria-label="Verwaltung"',$html);
        self::assertStringContainsString('aria-controls="site-navigation"',$html);
        self::assertStringContainsString('action="/auth/logout"',$html);
        self::assertStringContainsString('name="csrf"',$html);
    }

    public function testAnonymousLoginHasOneMainActionAndNoAccountOrAdministrativeLinks(): void
    {
        $data=$this->data();$data['actor']=null;
        $html=(new PageRenderer())->render('login',$data);
        self::assertStringContainsString('signed-out',$html);
        self::assertStringNotContainsString('id="site-navigation"',$html);
        self::assertStringNotContainsString('action="/auth/logout"',$html);
        self::assertSame(1,substr_count($html,'action="/auth/login"'));
    }
    public function testReadOnlyFileListingOffersContentButNoUploadOrExportForm(): void
    {
        $data=$this->data()+['space'=>['id'=>'team','name'=>'Team <Dateien>','quota_bytes'=>1024],
            'folder'=>null,'parent'=>'','used'=>0,'can_create'=>false,'can_read'=>true,
            'can_view_trash'=>false,'can_catalog_read'=>false,'can_tag_batch'=>false,'can_export'=>false,
            'next'=>null,'rows'=>[['id'=>str_repeat('a',32),'name'=>'Notiz <1>.txt','kind'=>'file','size_bytes'=>2,'revision'=>3]]];
        $html=(new PageRenderer())->render('files',$data);
        self::assertStringNotContainsString('action="/files/upload"',$html);
        self::assertStringNotContainsString('action="/exports/create"',$html);
        self::assertStringContainsString('Notiz &lt;1&gt;.txt',$html);
        self::assertStringContainsString('/files/detail?space=team&amp;entry=',$html);
        self::assertStringContainsString('aria-label="Ordnerpfad"',$html);
    }
}
