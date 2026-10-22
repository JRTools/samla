<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Manual catalog values are separate from original bytes and extracted fields. */
final readonly class CatalogEntryChange
{
    public const int MAX_DESCRIPTION_BYTES=16384;
    public array $tagIds;
    public function __construct(public string $spaceId,public string $entryId,public int $entryRevision,
        public int $catalogRevision,public string $description,array $tagIds)
    {
        if(!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$spaceId)||!preg_match('/^[a-f0-9]{32}$/D',$entryId)
            ||$entryRevision<1||$catalogRevision<0||strlen($description)>self::MAX_DESCRIPTION_BYTES||!mb_check_encoding($description,'UTF-8')
            ||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u',$description)||!array_is_list($tagIds)||count($tagIds)>50){throw new \InvalidArgumentException('Invalid catalog values.');}
        $copy=[];foreach($tagIds as$id){if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id)){throw new \InvalidArgumentException('Invalid tag reference.');}$copy[]=$id;}
        if(count(array_unique($copy))!==count($copy)){throw new \InvalidArgumentException('Duplicate tag reference.');}
        sort($copy,SORT_STRING);$this->tagIds=$copy;
    }
}
