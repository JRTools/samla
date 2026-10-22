<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\PluginApi\FileCatalogEntry;
final class FileCatalogEntryTest extends TestCase
{
    public function testPublicEntryContainsOnlyBoundedLabelsAndRevisions():void
    {
        $entry=new FileCatalogEntry(str_repeat('a',32),'space','','Bild.png','file',1,20,'Veranstaltung / Bilder');
        self::assertSame('Veranstaltung / Bilder',$entry->folderPath);self::assertSame(20,$entry->sizeBytes);
        foreach([
            fn()=>new FileCatalogEntry('bad','space','','Bild.png','file',1,20,''),
            fn()=>new FileCatalogEntry(str_repeat('a',32),'space','../','Bild.png','file',1,20,''),
            fn()=>new FileCatalogEntry(str_repeat('a',32),'space','','Bild.png','file',0,20,''),
            fn()=>new FileCatalogEntry(str_repeat('a',32),'space','','Bild.png','file',1,-1,''),
            fn()=>new FileCatalogEntry(str_repeat('a',32),'space','','Bild.png','file',1,20,str_repeat('x',131073)),
        ]as$make){try{$make();self::fail('Invalid public entry accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
}
