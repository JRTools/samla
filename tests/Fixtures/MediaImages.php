<?php
declare(strict_types=1);
namespace Samla\Tests\Fixtures;
/** Synthetic six-colour image and minimal TIFF IFD; no external image/test dependency. */
final class MediaImages
{
    public static function png(int $width=60,int $height=90):string
    {
        $image=imagecreatetruecolor($width,$height);$colors=[0xff0000,0x00ff00,0x0000ff,0xffff00,0xff00ff,0x00ffff];
        foreach($colors as$i=>$color){imagefilledrectangle($image,($i%2)*intdiv($width,2),intdiv($i,2)*intdiv($height,3),($i%2+1)*intdiv($width,2)-1,(intdiv($i,2)+1)*intdiv($height,3)-1,$color);}
        ob_start();try{imagepng($image);return ob_get_contents();}finally{ob_end_clean();}
    }
    public static function jpeg(int $orientation=1):string
    {
        $image=imagecreatefromstring(self::png());ob_start();try{imagejpeg($image,null,100);$jpg=ob_get_contents();}finally{ob_end_clean();}
        // Intel TIFF, Orientation SHORT and camera Make ASCII (including terminal NUL).
        $tiff="II".pack('vVv',42,8,2).pack('vvVv',0x112,3,1,$orientation)."\0\0".pack('vvVV',0x10f,2,6,38).pack('V',0)."Samla\0";
        $app="Exif\0\0".$tiff;return substr($jpg,0,2)."\xff\xe1".pack('n',strlen($app)+2).$app.substr($jpg,2);
    }
}
