<?php
declare(strict_types=1);
namespace Samla\PluginApi;
final readonly class MediaValue
{
    public function __construct(public string $fieldId,public string $source,public string|int|null $raw,public string|int|null $normalized,public string $status='extracted')
    {
        if(!preg_match('/^[a-z][a-z0-9_.]{1,95}$/D',$fieldId)||strlen($source)>160||$source===''||preg_match('//u',$source)!==1||preg_match('/[\x00-\x1f\x7f]/',$source)
            ||!in_array($status,['extracted','not_found','unsupported','error','limit'],true)){throw new \InvalidArgumentException('Invalid media value.');}
        foreach([$raw,$normalized]as$value){if(is_string($value)&&(strlen($value)>1024||preg_match('//u',$value)!==1)){throw new \InvalidArgumentException('Invalid metadata text.');}}
    }
}
