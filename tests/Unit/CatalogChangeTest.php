<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\PluginApi\{CatalogEntryChange,CatalogTagChange,MutationContext,Scope};
final class CatalogChangeTest extends TestCase
{
    public function testCatalogProposalCopiesReferencesAndBindsExactEntryAndSpace():void
    {
        $id=str_repeat('a',32);$tag=str_repeat('b',32);
        $change=new CatalogEntryChange('space',$id,1,0,"Beschreibung\n",[&$tag]);$tag=str_repeat('c',32);
        self::assertSame([str_repeat('b',32)],$change->tagIds);
        $context=new MutationContext(str_repeat('d',32),'alice','catalog.entry.update',Scope::Space,'space',['entry'=>$id],catalogEntry:$change);
        self::assertSame($change,$context->catalogEntry);
        foreach([
            fn()=>new MutationContext(str_repeat('d',32),'alice','catalog.entry.update',Scope::Space,'other',['entry'=>$id],catalogEntry:$change),
            fn()=>new MutationContext(str_repeat('d',32),'alice','catalog.entry.update',Scope::Space,'space',['entry'=>$id]),
            fn()=>new CatalogEntryChange('space',$id,1,0,'',[$tag,$tag]),
            fn()=>new CatalogEntryChange('space',$id,1,0,"\xff",[]),
            fn()=>new CatalogEntryChange('space',$id,1,0,str_repeat('x',16385),[]),
            fn()=>new CatalogEntryChange('space',$id,0,0,'',[]),
            fn()=>new CatalogEntryChange('space',$id,1,0,'',[1]),
        ]as$make){try{$make();self::fail('Invalid catalog proposal accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
    public function testTagChangesBindOperationAndRevision():void
    {
        $id=str_repeat('a',32);$change=new CatalogTagChange('space',$id,'rename',1,'Veranstaltung');
        $context=new MutationContext(str_repeat('d',32),'alice','catalog.tag.rename',Scope::Space,'space',['tag'=>$id],catalogTag:$change);self::assertSame($change,$context->catalogTag);
        foreach([
            fn()=>new CatalogTagChange('space',$id,'create',1,'Name'),fn()=>new CatalogTagChange('space',$id,'delete',null,null),
            fn()=>new CatalogTagChange('space',$id,'rename',1,' '),fn()=>new CatalogTagChange('space',$id,'rename',1,str_repeat('ä',81)),
            fn()=>new CatalogTagChange('space',$id,'rename',1,"abc\n"),
            fn()=>new MutationContext(str_repeat('d',32),'alice','catalog.tag.delete',Scope::Space,'space',['tag'=>$id],catalogTag:$change),
        ]as$make){try{$make();self::fail('Invalid tag proposal accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
}
