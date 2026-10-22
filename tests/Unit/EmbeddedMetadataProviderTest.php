<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\Core\Media\EmbeddedMetadataProvider;
use Samla\PluginApi\MediaSource;
use Samla\Tests\Fixtures\MediaImages;
final class EmbeddedMetadataProviderTest extends TestCase
{
    private function extract(string $bytes):\Samla\PluginApi\MediaResult
    {return (new EmbeddedMetadataProvider())->extract(new MediaSource($bytes,hash('sha256',$bytes)));}
    private function values(\Samla\PluginApi\MediaResult $r):array{return array_column($r->values,null,'fieldId');}
    public function testStructuredXmpDistinguishesGeneratedEditedOtherMissingAndConflicts():void
    {
        $base='http://cv.iptc.org/newscodes/digitalsourcetype/';
        foreach(['trainedAlgorithmicMedia'=>'Als KI-generiert gekennzeichnet','compositeWithTrainedAlgorithmicMedia'=>'Als mit KI bearbeitet gekennzeichnet','algorithmicMedia'=>'Andere deklarierte Herkunft','computationalCapture'=>'Andere deklarierte Herkunft']as$type=>$label){
            $xmp=MediaImages::xmp('<i:DigitalSourceType rdf:resource="'.$base.$type.'"/><dc:title><rdf:Alt><rdf:li xml:lang="x-default">&lt;script&gt;Titel</rdf:li></rdf:Alt></dc:title>');
            $bytes=MediaImages::jpegSegment("http://ns.adobe.com/xap/1.0/\0".$xmp);$hash=hash('sha256',$bytes);$r=$this->extract($bytes);self::assertSame('ready',$r->status);$v=$this->values($r);self::assertSame($label,$v['platform.media.declared_origin']->normalized);self::assertSame('<script>Titel',$v['platform.media.embedded_title']->normalized);self::assertSame($base.$type,$v['platform.media.digital_source_type']->raw);self::assertSame($hash,hash('sha256',$bytes));
        }
        $r=$this->extract(MediaImages::pngText('Software','Famous AI editor'));self::assertSame('Unbekannt',$this->values($r)['platform.media.declared_origin']->normalized);self::assertStringContainsString('Keine Kennzeichnung',$this->values($r)['platform.media.ai_marker_status']->normalized);
        $xml=MediaImages::xmp('<i:DigitalSourceType>'.$base.'trainedAlgorithmicMedia</i:DigitalSourceType><i:DigitalSourceType>'.$base.'digitalCapture</i:DigitalSourceType>');$r=$this->extract(MediaImages::pngText('XML:com.adobe.xmp',$xml,true));self::assertSame('Widersprüchliche Dateiangaben',$this->values($r)['platform.media.declared_origin']->normalized);
    }
    public function testXmpNamespaceAttributesAndDeclaredAiSystemPromptAndWriterRemainSeparate():void
    {
        $xml=MediaImages::xmp('<i:AISystemUsed>Example generator</i:AISystemUsed><i:AISystemVersionUsed>1.2</i:AISystemVersionUsed><i:AIPromptInformation>private prompt</i:AIPromptInformation><i:AIPromptWriterName>private name</i:AIPromptWriterName>');
        $r=$this->extract(MediaImages::pngText('XML:com.adobe.xmp',$xml,true));$v=$this->values($r);self::assertSame('Example generator',$v['platform.media.ai_system']->normalized);self::assertSame('1.2',$v['platform.media.ai_system_version']->normalized);self::assertSame('private prompt',$v['platform.media.embedded_prompt']->normalized);self::assertSame('private name',$v['platform.media.embedded_prompt_author']->normalized);self::assertSame('Unbekannt',$v['platform.media.declared_origin']->normalized);
        $xml=str_replace('<rdf:Description ','<rdf:Description i:DigitalSourceType="http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia" ',MediaImages::xmp(''));
        self::assertSame('Als KI-generiert gekennzeichnet',$this->values($this->extract(MediaImages::pngText('XML:com.adobe.xmp',$xml,true)))['platform.media.declared_origin']->normalized);
        $xml=str_replace('http://iptc.org/std/Iptc4xmpExt/2008-02-29/','https://attacker.invalid/fake',MediaImages::xmp('<i:DigitalSourceType>http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia</i:DigitalSourceType>'));
        self::assertSame('Unbekannt',$this->values($this->extract(MediaImages::pngText('XML:com.adobe.xmp',$xml,true)))['platform.media.declared_origin']->normalized);
    }
    public function testPngTextUsesLatin1WhileInternationalTextUsesUtf8AndPromptsAreSensitive():void
    {
        $r=$this->extract(MediaImages::pngText('Title',"Gr\xfc\xdfe"));self::assertSame('Grüße',$this->values($r)['platform.media.embedded_title']->normalized);
        $r=$this->extract(MediaImages::pngText('Description','Älmhult',true));self::assertSame('Älmhult',$this->values($r)['platform.media.embedded_description']->normalized);
        $r=$this->extract(MediaImages::pngText('parameters','secret free-text prompt'));self::assertSame('Unbekannt',$this->values($r)['platform.media.declared_origin']->normalized);
        foreach(EmbeddedMetadataProvider::fields()as$field){if(in_array($field->id,['platform.media.embedded_prompt','platform.media.embedded_prompt_author'],true)){self::assertTrue($field->sensitive);self::assertSame('media.sensitive.read',$field->readPermission);self::assertFalse($field->exportable);}}
    }
    private function iim(int $record,int $dataset,string $text):string{return "\x1c".chr($record).chr($dataset).pack('n',strlen($text)).$text;}
    private function iptc(string $iim):string
    {return MediaImages::jpegSegment("Photoshop 3.0\0".'8BIM'.pack('n',0x0404)."\0\0".pack('N',strlen($iim)).$iim.(strlen($iim)%2?"\0":''),0xed);}
    public function testPhotoshopIptcReadsRepeatedFieldsWithExplicitEncodingWithoutGuessing():void
    {
        $iim=$this->iim(1,90,"\x1b%G").$this->iim(2,5,'Älmhult').$this->iim(2,25,'one').$this->iim(2,25,'two');$r=$this->extract($this->iptc($iim));self::assertSame('ready',$r->status);self::assertSame('Älmhult',$this->values($r)['platform.media.embedded_title']->normalized);self::assertCount(2,array_filter($r->values,static fn($v)=>$v->fieldId==='platform.media.embedded_keyword'));
        $r=$this->extract($this->iptc($this->iim(2,5,"\xfc")));self::assertSame('partial',$r->status);self::assertNull($this->values($r)['platform.media.embedded_title']->normalized);self::assertSame('unsupported',$this->values($r)['platform.media.embedded_title']->status);
    }
    public function testXmlEntitiesCompressedChunksBrokenCrcAndLargeValuesAreExplicitlyPartial():void
    {
        $xml='<!DOCTYPE x [<!ENTITY external SYSTEM "file:///etc/passwd">]><x>&external;</x>';$r=$this->extract(MediaImages::pngText('XML:com.adobe.xmp',$xml,true));self::assertSame('partial',$r->status);self::assertStringNotContainsString('root:',json_encode($r));self::assertSame('Unbekannt',$this->values($r)['platform.media.declared_origin']->normalized);
        $png=MediaImages::png();$compressed=substr($png,0,33).MediaImages::pngChunk('zTXt',"parameters\0\0not inflated").substr($png,33);self::assertSame('partial',$this->extract($compressed)->status);
        $png=MediaImages::pngText('Title','text');$bad=substr_replace($png,"\0\0\0\0",45,4);self::assertSame('partial',$this->extract($bad)->status);
        $r=$this->extract(MediaImages::pngText('Title',str_repeat('x',1025)));self::assertSame('limit',$r->status);self::assertSame('limit',$this->values($r)['platform.media.embedded_title']->status);
        self::assertSame('unsupported',$this->extract('not an image')->status);
    }
    public function testMalformedContainersAndLimitsDoNotProducePositiveOriginClaims():void
    {
        $xml=MediaImages::xmp('<i:DigitalSourceType>not a controlled-vocabulary URI</i:DigitalSourceType>');$r=$this->extract(MediaImages::pngText('XML:com.adobe.xmp',$xml,true));self::assertSame('partial',$r->status);self::assertSame('Unbekannt',$this->values($r)['platform.media.declared_origin']->normalized);
        foreach([MediaImages::pngText('Title','text'),MediaImages::jpeg()]as$bytes){for($i=2;$i<80;++$i){self::assertContains($this->extract(substr($bytes,0,$i))->status,['partial','limit','unsupported']);}}
        $xml=MediaImages::xmp(str_repeat('<dc:subject><rdf:Bag><rdf:li>keyword</rdf:li></rdf:Bag></dc:subject>',30));$r=$this->extract(MediaImages::pngText('XML:com.adobe.xmp',$xml,true));self::assertSame('limit',$r->status);self::assertCount(32,$r->values);self::assertSame('Unbekannt',$this->values($r)['platform.media.declared_origin']->normalized);
        $xml=MediaImages::xmp(str_repeat('<a/>',2050));self::assertSame('limit',$this->extract(MediaImages::pngText('XML:com.adobe.xmp',$xml,true))->status);
        $xml=mb_convert_encoding(MediaImages::xmp(''),'UTF-16LE','UTF-8');self::assertSame('partial',$this->extract(MediaImages::pngText('XML:com.adobe.xmp',$xml,true))->status);
    }

    public function testCooperativeTimeBudgetIsExplicitAndCannotTurnIntoANegativeAiClaim():void
    {
        $now=0;$provider=new EmbeddedMetadataProvider(static function()use(&$now):int{$now+=1000000001;return $now;});$bytes=MediaImages::png();$r=$provider->extract(new MediaSource($bytes,hash('sha256',$bytes)));self::assertSame('limit',$r->status);self::assertSame('Unbekannt',$this->values($r)['platform.media.declared_origin']->normalized);
    }

}
