<?php
declare(strict_types=1);
namespace Samla\PluginApi;
final readonly class MediaField
{
    public function __construct(public string $id,public string $label,public string $type='text',public bool $sensitive=false,
        public string $readPermission='media.metadata.read',public bool $exportable=false)
    {
        if(!preg_match('/^[a-z][a-z0-9_.]{1,95}$/D',$id)||strlen($label)>160||$label===''||preg_match('//u',$label)!==1||preg_match('/[\x00-\x1f\x7f]/',$label)
            ||!in_array($type,['text','integer'],true)||!preg_match('/^[a-z][a-z0-9_.]{1,127}$/D',$readPermission)
            ||($sensitive&&$readPermission==='media.metadata.read')){throw new \InvalidArgumentException('Invalid media field.');}
    }
}
