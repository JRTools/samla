<?php
declare(strict_types=1);
namespace Samla\Core\Configuration;

final readonly class ExportConfig
{
    public const MANIFEST_MAX_BYTES=8388608;
    public function __construct(public int $maxBytes=1073741824,public int $maxFiles=100,public int $seconds=20,
        public int $lifetimeSeconds=86400,public int $reservedBytes=5368709120)
    {
        if($maxBytes<1||$maxBytes>10737418240||$maxFiles<1||$maxFiles>100||$seconds<1||$seconds>30
            ||$lifetimeSeconds<60||$lifetimeSeconds>2592000||$reservedBytes<1||$reservedBytes>107374182400){throw new \InvalidArgumentException('Invalid export limits.');}
    }
    public static function fromEnvironment(array $environment):self
    {
        $values=[];
        foreach(['APP_EXPORT_MAX_BYTES'=>1073741824,'APP_EXPORT_MAX_FILES'=>100,'APP_EXPORT_SECONDS'=>20,
            'APP_EXPORT_LIFETIME_SECONDS'=>86400,'APP_EXPORT_RESERVED_BYTES'=>5368709120]as$key=>$default){
            $value=$environment[$key]??(string)$default;
            if(!is_string($value)||!ctype_digit($value)||strlen($value)>12){throw new \InvalidArgumentException('Invalid export setting.');}
            $values[]=(int)$value;
        }
        return new self(...$values);
    }
}
