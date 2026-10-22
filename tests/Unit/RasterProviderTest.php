<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\Core\Media\RasterProvider;
use Samla\PluginApi\{MediaSource,MediaPreview};
use Samla\Tests\Fixtures\MediaImages;
final class RasterProviderTest extends TestCase
{
    private function extract(string $bytes,?RasterProvider $provider=null):\Samla\PluginApi\MediaResult
    {return ($provider??new RasterProvider())->extract(new MediaSource($bytes,hash('sha256',$bytes)));}
    public function testAllEightOrientationsPreserveOriginalAndMapEveryColour():void
    {
        self::assertTrue(extension_loaded('exif'));self::assertTrue(extension_loaded('gd'));
        $maps=[1=>[0,1,2,3,4,5],2=>[1,0,3,2,5,4],3=>[5,4,3,2,1,0],4=>[4,5,2,3,0,1],5=>[0,2,4,1,3,5],6=>[4,2,0,5,3,1],7=>[5,3,1,4,2,0],8=>[1,3,5,0,2,4]];
        $colors=[0xff0000,0x00ff00,0x0000ff,0xffff00,0xff00ff,0x00ffff];
        foreach($maps as$orientation=>$map){$bytes=MediaImages::jpeg($orientation);$hash=hash('sha256',$bytes);$result=$this->extract($bytes);self::assertNotNull($result->preview,'orientation '.$orientation);self::assertSame('ready',$result->status);
            $image=imagecreatefromstring($result->preview->png);$cols=$orientation>=5?3:2;$rows=$orientation>=5?2:3;
            foreach($map as$i=>$color){$actual=imagecolorat($image,(int)(($i%$cols+0.5)*imagesx($image)/$cols),(int)((intdiv($i,$cols)+0.5)*imagesy($image)/$rows));
                foreach([16,8,0]as$shift){self::assertEqualsWithDelta(($colors[$color]>>$shift)&255,($actual>>$shift)&255,12,'orientation '.$orientation);}}
            self::assertSame($hash,hash('sha256',$bytes));$values=array_column($result->values,null,'fieldId');self::assertSame($orientation,$values['platform.media.orientation']->normalized);self::assertSame('Samla',$values['platform.media.camera_make']->normalized);self::assertCount(7,$values);
        }
    }
    public function testPreviewDimensionsAndUnavailableModulesAreExplicit():void
    {
        $png=MediaImages::png(600,900);$r=$this->extract($png);$info=getimagesizefromstring($r->preview->png);self::assertSame([320,480],array_slice($info,0,2));
        self::assertSame('unavailable',$this->extract($png,new RasterProvider(enableGd:false))->status);
        self::assertSame('unavailable',$this->extract(MediaImages::jpeg(),new RasterProvider(enableExif:false))->status);
        self::assertNotNull($this->extract($png,new RasterProvider(enableExif:false))->preview);
        self::assertSame('limit',$this->extract($png,new RasterProvider(budgetBytes:1))->status);
        self::assertSame('partial',$this->extract(MediaImages::jpeg(9))->status);self::assertNull($this->extract(MediaImages::jpeg(9))->preview);
    }
    public function testMalformedUnsupportedAndPixelLimitsDoNotEmitWarnings():void
    {
        self::assertSame('unsupported',$this->extract('<svg onload="alert(1)"/>')->status);
        self::assertSame('error',$this->extract("\xff\xd8broken")->status);
        $png=MediaImages::png();$data=substr_replace($png,pack('NN',16385,1),16,8);self::assertSame('limit',$this->extract($data)->status);
        self::assertNotNull($this->extract(MediaImages::png())->preview);
    }
    public function testCanonicalPreviewRejectsMetadataChunksTrailingDataAndBadCrc():void
    {
        $png=$this->extract(MediaImages::png())->preview->png;
        foreach(['tEXt','eXIf','iTXt','zTXt']as$type){$payload=$type.'private GPS metadata';$chunk=pack('N',strlen($payload)-4).$payload.hex2bin(hash('crc32b',$payload));$bad=substr($png,0,33).$chunk.substr($png,33);try{new MediaPreview($bad);self::fail('Metadata accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
        foreach([$png.'tail',substr_replace($png,"\0\0\0\0",29,4)]as$bad){try{new MediaPreview($bad);self::fail('Bad PNG accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
    public function testTransparentPngAndGifFirstImageAreSafeCanonicalPreviews():void
    {
        $image=imagecreatetruecolor(2,2);imagealphablending($image,false);imagesavealpha($image,true);imagefill($image,0,0,imagecolorallocatealpha($image,10,20,30,127));
        ob_start();try{imagepng($image);$png=ob_get_contents();}finally{ob_end_clean();}
        $preview=$this->extract($png)->preview;self::assertNotNull($preview);$decoded=imagecreatefromstring($preview->png);self::assertSame(127,(imagecolorat($decoded,0,0)>>24)&127);
        $image=imagecreatefromstring(MediaImages::png());ob_start();try{imagegif($image);$gif=ob_get_contents();}finally{ob_end_clean();}
        $result=$this->extract($gif);self::assertSame('ready',$result->status);self::assertNotNull($result->preview);self::assertSame('image/gif',$result->values[0]->normalized);
    }

}
