<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Immutable proposal for space catalog changes, without implicit content access. */
final readonly class StorageSpaceChange
{
    public function __construct(public string $spaceId,public string $operation,public ?string $expectedState,
        public ?string $name,public ?int $quotaBytes)
    {
        if(!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$spaceId)||!in_array($operation,['create','update','delete'],true)
            ||($operation==='create'?$expectedState!==null:($expectedState===null||!preg_match('/^[a-f0-9]{64}$/D',$expectedState)))){
            throw new \InvalidArgumentException('Invalid space proposal.');
        }
        if($operation==='delete'){
            if($name!==null||$quotaBytes!==null){throw new \InvalidArgumentException('Deletion cannot rename a space.');}
        }elseif($name===null||$name===''||!mb_check_encoding($name,'UTF-8')||mb_strlen($name)>255||trim($name)!==$name
            ||preg_match('/[\x00-\x1f\x7f]/u',$name)||$quotaBytes===null||$quotaBytes<1||$quotaBytes>1099511627776){
            throw new \InvalidArgumentException('Invalid space name or quota.');
        }
    }
}
