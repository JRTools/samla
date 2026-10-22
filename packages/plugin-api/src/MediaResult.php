<?php
declare(strict_types=1);
namespace Samla\PluginApi;
final readonly class MediaResult
{
    public array $values;
    public function __construct(public string $status,array $values=[],public ?MediaPreview $preview=null)
    {
        if(!in_array($status,['ready','partial','unsupported','unavailable','error','limit'],true)||count($values)>32){throw new \InvalidArgumentException('Invalid media result.');}
        $copy=[];foreach($values as$v){if(!$v instanceof MediaValue){throw new \InvalidArgumentException();}$copy[]=$v;}$this->values=$copy;
    }
}
