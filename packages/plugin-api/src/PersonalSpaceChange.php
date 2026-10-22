<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Trusted self-provisioning proposal. IDs are derived from the stable internal account, never its label. */
final readonly class PersonalSpaceChange
{
    public const string GROUP_PREFIX='samla.personal.';
    public string $spaceId;
    public string $groupId;
    public function __construct(public string $ownerId,public string $name,public int $quotaBytes)
    {
        if(!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$ownerId)||$name===''||strlen($name)>1020
            ||!mb_check_encoding($name,'UTF-8')||mb_strlen($name,'UTF-8')>255||preg_match('/[\x00-\x1f\x7f]/u',$name)
            ||$quotaBytes<1||$quotaBytes>1099511627776){throw new \InvalidArgumentException('Invalid personal space proposal.');}
        $key=substr(hash('sha256',$ownerId),0,32);$this->spaceId='personal.'.$key;$this->groupId=self::GROUP_PREFIX.$key;
    }
}
