<?php
declare(strict_types=1);
namespace Samla\Core\Media;
use Samla\PluginApi\{MediaPreview,MediaProvider,MediaResult,MediaSource,MediaValue};

/** Only selected technical/EXIF fields. No raw EXIF dump, GPS, thumbnails or script formats. */
final readonly class RasterProvider implements MediaProvider
{
    public function __construct(private bool $enableGd=true,private bool $enableExif=true,private int $budgetBytes=0){}
    public function id():string{return 'platform.media.raster';}
    public function version():string{return '1.0';}
    public function extract(MediaSource $source):MediaResult
    {
        $warnings=0;set_error_handler(static function()use(&$warnings):bool{++$warnings;return true;});
        try{
            $info=getimagesizefromstring($source->bytes);
            if($info===false){return new MediaResult(preg_match('/^(?:\x89PNG|\xff\xd8|GIF8|RIFF)/',$source->bytes)?'error':'unsupported');}
            $types=[IMAGETYPE_JPEG=>'image/jpeg',IMAGETYPE_PNG=>'image/png',IMAGETYPE_GIF=>'image/gif',IMAGETYPE_WEBP=>'image/webp',IMAGETYPE_TIFF_II=>'image/tiff',IMAGETYPE_TIFF_MM=>'image/tiff'];
            if(!isset($types[$info[2]])){return new MediaResult('unsupported');}
            $width=(int)$info[0];$height=(int)$info[1];$values=[
                new MediaValue('platform.media.mime','image.header.mime',$types[$info[2]],$types[$info[2]]),
                new MediaValue('platform.media.width','image.header.width',$width,$width),
                new MediaValue('platform.media.height','image.header.height',$height,$height),
            ];
            if($width<1||$height<1||$width>16384||$height>16384||$width*$height>16777216){return new MediaResult('limit',$values);}
            $estimate=$width*$height*8+strlen($source->bytes)*4+33554432;
            $budget=$this->budgetBytes>0?$this->budgetBytes:self::memoryBudget();
            if($budget!==PHP_INT_MAX&&$estimate+memory_get_usage(true)>$budget){return new MediaResult('limit',$values);}
            $orientation=1;$exifUnavailable=false;$partial=false;
            if(in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_TIFF_II,IMAGETYPE_TIFF_MM],true)){
                if(!$this->enableExif||!function_exists('exif_read_data')){$exifUnavailable=true;}
                else{
                    $stream=$source->open();$before=$warnings;
                    try{$exif=exif_read_data($stream,'IFD0,EXIF',true,false);}finally{fclose($stream);}
                    if($exif===false||$warnings>$before){$partial=true;}
                    $raw=is_array($exif)?($exif['IFD0']['Orientation']??null):null;
                    if($raw!==null&&(!is_int($raw)||$raw<1||$raw>8)){$partial=true;$orientation=0;}
                    elseif(is_int($raw)){$orientation=$raw;}
                    $values[]=new MediaValue('platform.media.orientation','EXIF.IFD0.Orientation',is_int($raw)?$raw:null,$orientation?:null,$raw===null?'not_found':($orientation===0?'error':'extracted'));
                    foreach(['Make'=>'camera_make','Model'=>'camera_model']as$key=>$field){$values[]=$this->textValue('platform.media.'.$field,'EXIF.IFD0.'.$key,is_array($exif)?($exif['IFD0'][$key]??null):null);}
                    $values[]=$this->textValue('platform.media.captured_at','EXIF.EXIF.DateTimeOriginal',is_array($exif)?($exif['EXIF']['DateTimeOriginal']??null):null);
                }
            }
            if($types[$info[2]]==='image/tiff'){return new MediaResult($exifUnavailable?'unavailable':'partial',$values);}
            if(!$this->enableGd||!function_exists('imagecreatefromstring')||$exifUnavailable){return new MediaResult('unavailable',$values);}
            $before=$warnings;$image=imagecreatefromstring($source->bytes);
            if($image===false||$warnings>$before){return new MediaResult('error',$values);}
            if($orientation===0){return new MediaResult('partial',$values);}
            $image=self::orient($image,$orientation);
            $scale=min(1,480/max(imagesx($image),imagesy($image)));$w=max(1,(int)floor(imagesx($image)*$scale));$h=max(1,(int)floor(imagesy($image)*$scale));
            $preview=imagecreatetruecolor($w,$h);if($preview===false){throw new \RuntimeException();}
            imagealphablending($preview,false);imagesavealpha($preview,true);imagefill($preview,0,0,imagecolorallocatealpha($preview,0,0,0,127));
            if(!imagecopyresampled($preview,$image,0,0,0,0,$w,$h,imagesx($image),imagesy($image))){throw new \RuntimeException();}
            ob_start();try{if(!imagepng($preview,null,6)){throw new \RuntimeException();}$png=ob_get_contents();}finally{ob_end_clean();}
            return new MediaResult($partial?'partial':'ready',$values,new MediaPreview($png));
        }catch(\Throwable){return new MediaResult('error');}finally{restore_error_handler();}
    }
    private static function orient(\GdImage $image,int $orientation):\GdImage
    {
        if(in_array($orientation,[2,5,7],true)){imageflip($image,IMG_FLIP_HORIZONTAL);}
        if($orientation===4){imageflip($image,IMG_FLIP_VERTICAL);}
        $angle=match($orientation){3=>180,5,8=>90,6,7=>270,default=>0};
        if($angle!==0){$rotated=imagerotate($image,$angle,0);if($rotated===false){throw new \RuntimeException();}return $rotated;}
        return $image;
    }
    private function textValue(string $field,string $path,mixed $value):MediaValue
    {
        if($value===null){return new MediaValue($field,$path,null,null,'not_found');}
        if(!is_string($value)||strlen($value)>512){return new MediaValue($field,$path,null,null,'limit');}
        if(!mb_check_encoding($value,'UTF-8')){return new MediaValue($field,$path,null,null,'error');}
        $normalized=trim($value,"\0 \t\r\n");
        if(preg_match('/[\x00-\x1f\x7f]/',$normalized)){return new MediaValue($field,$path,$value,null,'error');}
        return new MediaValue($field,$path,$value,$normalized);
    }
    private static function memoryBudget():int
    {
        $limit=trim((string)ini_get('memory_limit'));if($limit==='-1'){return PHP_INT_MAX;}
        if(!preg_match('/^(\d+)\s*([KMG]?)$/i',$limit,$m)){return 67108864;}
        return min(PHP_INT_MAX,(int)$m[1]*(match(strtoupper($m[2])){'K'=>1024,'M'=>1048576,'G'=>1073741824,default=>1}));
    }
}
