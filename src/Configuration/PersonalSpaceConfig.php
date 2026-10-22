<?php
declare(strict_types=1);
namespace Samla\Core\Configuration;
final readonly class PersonalSpaceConfig
{
    public function __construct(public bool $enabled=true,public int $quotaBytes=10737418240)
    {if($quotaBytes<1||$quotaBytes>1099511627776){throw new \InvalidArgumentException('Invalid personal quota.');}}
    public static function fromEnvironment(array $env):self
    {
        $enabled=$env['APP_PERSONAL_SPACE_ENABLED']??'1';$quota=$env['APP_PERSONAL_SPACE_QUOTA_BYTES']??'10737418240';
        if(!in_array($enabled,['0','1'],true)||!is_string($quota)||!ctype_digit($quota)||strlen($quota)>13){throw new \InvalidArgumentException('Invalid personal space settings.');}
        return new self($enabled==='1',(int)$quota);
    }
}
