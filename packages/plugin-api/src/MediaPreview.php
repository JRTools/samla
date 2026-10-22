<?php
declare(strict_types=1);
namespace Samla\PluginApi;
/** Canonical small PNG only. Originals, scripts, SVG and arbitrary response headers are excluded. */
final readonly class MediaPreview
{
    public const MAX_BYTES=1048576;
    public function __construct(public string $png)
    {
        $info=str_starts_with($png,"\x89PNG\r\n\x1a\n")?@getimagesizefromstring($png):false;
        if(strlen($png)>self::MAX_BYTES||$info===false||$info[2]!==IMAGETYPE_PNG||$info[0]<1||$info[1]<1||$info[0]>480||$info[1]>480){throw new \InvalidArgumentException('Invalid preview.');}
        $offset=8;$idat=false;$header=false;$ended=false;
        while($offset<strlen($png)){
            if($offset+12>strlen($png)){throw new \InvalidArgumentException('Truncated PNG.');}
            $length=unpack('N',substr($png,$offset,4))[1];$type=substr($png,$offset+4,4);
            if($length>self::MAX_BYTES||$offset+12+$length>strlen($png)||!in_array($type,['IHDR','PLTE','tRNS','IDAT','IEND','pHYs','gAMA','sRGB'],true)
                ||($offset===8&&$type!=='IHDR')||($type==='IHDR'&&($header||$length!==13))
                ||($type==='pHYs'&&$length!==9)||($type==='gAMA'&&$length!==4)||($type==='sRGB'&&$length!==1)
                ||!hash_equals(hash('crc32b',substr($png,$offset+4,$length+4)),bin2hex(substr($png,$offset+8+$length,4)))){throw new \InvalidArgumentException('Noncanonical preview PNG.');}
            $header=$header||$type==='IHDR';$idat=$idat||$type==='IDAT';$offset+=12+$length;
            if($type==='IEND'){$ended=$length===0&&$offset===strlen($png);break;}
        }
        if(!$ended||!$idat){throw new \InvalidArgumentException('Incomplete preview PNG.');}
    }
}
