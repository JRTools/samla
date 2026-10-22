<?php
declare(strict_types=1);
namespace Samla\Core\Export;
use Samla\Core\Plugins\PluginRuntime;
use Samla\PluginApi\{AuthorizationChecker,AuthorizationRequest,Decision,ExportMetadataValue,ExportSource,Scope};

final readonly class ExportMetadata
{
    public function __construct(private PluginRuntime $runtime,private AuthorizationChecker $authorization){}
    public static function signature(PluginRuntime $runtime):string
    {
        $versions=[];foreach($runtime->exportProviders()as$entry){$provider=$entry['provider'];$versions[]=[$provider->id(),$provider->version()];}
        return hash('sha256',json_encode(['samla-export',1,$versions],JSON_THROW_ON_ERROR));
    }
    /** Unreadable/nonexportable fields are completely omitted; retained policies are rechecked at download. */
    public function collect(ExportSource $source):array
    {
        $values=[];$required=['files.list','files.read','files.export'];$seen=[];
        foreach($this->runtime->exportProviders()as$entry){
            $provider=$entry['provider'];$items=$provider->metadata($source);
            if(!array_is_list($items)||count($items)>64){throw new \LengthException('Unbounded export contribution.');}
            foreach($items as$item){
                if(!$item instanceof ExportMetadataValue||!str_starts_with($item->field->id,$entry['owner'].'.')||isset($seen[$item->field->id])){throw new \LogicException('Invalid export field ownership or duplicate.');}
                $field=$item->field;$seen[$field->id]=true;
                $permission=$this->runtime->find($field->readPermission);
                if($permission===null||!in_array(Scope::Space,$permission->scopes,true)){throw new \LogicException('Unknown export field read policy.');}
                if(!$field->exportable||!$this->allowed($source,$field->readPermission)||($field->sensitive&&!$this->allowed($source,'files.export.sensitive'))){continue;}
                $required[]=$field->readPermission;if($field->sensitive){$required[]='files.export.sensitive';}
                $values[]=['field'=>$field->id,'label'=>$field->label,'type'=>$field->type,'value'=>$item->value,'provider'=>$provider->id(),'provider_version'=>$provider->version()];
                if(count($values)>64){throw new \LengthException('Export field limit exceeded.');}
            }
        }
        $required=array_values(array_unique($required));sort($required,SORT_STRING);
        return ['values'=>$values,'required'=>$required];
    }
    private function allowed(ExportSource $source,string $action):bool
    {return $this->authorization->decide(new AuthorizationRequest($source->actorId,$action,Scope::Space,$source->spaceId))->decision===Decision::Permit;}
}
