<?php
declare(strict_types=1);
namespace Samla\PluginApi;
/** Bounded immutable proposal. IDs/revisions only, never filenames or content. */
final readonly class MediaBatchRefreshChange
{
    public array $entryRevisions;
    public function __construct(array $entryRevisions)
    {
        if(count($entryRevisions)<1||count($entryRevisions)>100){throw new \InvalidArgumentException('Invalid media batch size.');}
        $copy=[];foreach($entryRevisions as$id=>$revision){if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id)||!is_int($revision)||$revision<1){throw new \InvalidArgumentException('Invalid media batch reference.');}$copy[$id]=(int)$revision;}
        ksort($copy,SORT_STRING);$this->entryRevisions=$copy;
    }
    public function signature():string{return hash('sha256',json_encode($this->entryRevisions,JSON_THROW_ON_ERROR));}
}
