<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Exact selected file/catalog revisions and which entries actually change. */
final readonly class CatalogBatchChange
{
    public array $entries;
    /** @param array<string,array{int,int,bool}> $entries entry => [file revision, catalog revision, changes] */
    public function __construct(public string $spaceId,public string $tagId,public string $operation,array $entries)
    {
        if(!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$spaceId)||!preg_match('/^[a-f0-9]{32}$/D',$tagId)
            ||!in_array($operation,['add','remove'],true)||count($entries)<1||count($entries)>100){throw new \InvalidArgumentException('Invalid tag batch.');}
        $copy=[];foreach($entries as$id=>$state){
            if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id)||!is_array($state)||array_keys($state)!==[0,1,2]
                ||!is_int($state[0])||$state[0]<1||!is_int($state[1])||$state[1]<0||!is_bool($state[2])){throw new \InvalidArgumentException('Invalid tag batch state.');}
            $copy[$id]=[$state[0],$state[1],$state[2]];
        }ksort($copy,SORT_STRING);$this->entries=$copy;
    }
    public function signature():string
    {return hash('sha256',json_encode([$this->spaceId,$this->tagId,$this->operation,$this->entries],JSON_THROW_ON_ERROR));}
    public function changedEntries():array
    {return array_keys(array_filter($this->entries,static fn(array $state):bool=>$state[2]));}
}
