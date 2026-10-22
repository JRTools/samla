<?php
declare(strict_types=1);
namespace Samla\PluginApi;
final readonly class ExportChange
{
    public array $entryRevisions;
    public function __construct(public string $jobId,public string $spaceId,public string $operation,array $entryRevisions,public ?int $expectedRevision=null)
    {
        if(!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$spaceId)||!preg_match('/^[a-f0-9]{32}$/D',$jobId)||!in_array($operation,['request','retry','remove'],true)
            ||($operation==='request'?$expectedRevision!==null:($expectedRevision===null||$expectedRevision<1))
            ||count($entryRevisions)>100||($operation!=='remove'&&$entryRevisions===[])){
            throw new \InvalidArgumentException('Invalid export change.');
        }
        $copy=[];foreach($entryRevisions as$id=>$revision){if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id)||!is_int($revision)||$revision<1){throw new \InvalidArgumentException('Invalid export file revision.');}$copy[$id]=$revision;}
        ksort($copy,SORT_STRING);$this->entryRevisions=$copy;
    }
}
