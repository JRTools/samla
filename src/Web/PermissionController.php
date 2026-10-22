<?php
declare(strict_types=1);
namespace Samla\Core\Web;

use Samla\Core\Administration\AccessAdministrationDirectory;
use Samla\PluginApi\{AccessDenied,AccessProfileAdministration,MutationConflict};

final readonly class PermissionController
{
    public function __construct(private AccessProfileAdministration $access,private AccessAdministrationDirectory $directory,
        private PageRenderer $renderer=new PageRenderer()){}
    public function canManage(string $actor):bool{return $this->access->canManage($actor);}
    public function handle(Request $request,string $actor,string $csrf,array $data):Response
    {
        try{
            if(!$this->canManage($actor)){throw new AccessDenied();}
            if(!in_array($request->path,['/permissions','/permissions/change'],true)){return $this->error(404,'Diese Seite wurde nicht gefunden.',$data);}
            $method=$request->path==='/permissions'?'GET':'POST';
            if($request->method!==$method){return $this->error(405,'Diese Methode ist hier nicht erlaubt.',$data,['Allow'=>$method]);}
            if($method==='POST'){
                if(!preg_match('/^[a-f0-9]{64}$/D',$csrf)||!hash_equals($csrf,$request->field('csrf',64))){throw new AccessDenied();}
                if($request->field('confirm',1)!=='1'){throw new \InvalidArgumentException();}
                $group=$request->field('group',64);$space=$request->field('space',64);$space=$space===''?null:$space;
                $selection=json_decode($request->field('selection',256),true,8,JSON_THROW_ON_ERROR);
                if(!is_array($selection)||array_keys($selection)!==[0,1]||!is_string($selection[0])||!is_string($selection[1])){throw new \InvalidArgumentException();}
                $operation=$request->field('operation',6);
                if(!in_array($operation,['grant','revoke'],true)){throw new \InvalidArgumentException();}
                $changed=$this->access->change($actor,$selection[0],$group,$space,$operation==='grant',$selection[1]);
                return new Response(303,headers:['Location'=>'/permissions?'.http_build_query(['group'=>$group,'space'=>$space??''])],
                    flash:$changed?'Berechtigungszuweisung geändert.':'Die gewünschte Zuweisung war bereits vorhanden oder entfernt.');
            }
            $choices=$this->directory->choices($actor);
            $group=$this->queryId($request,'group');$space=$this->queryId($request,'space');
            $selectedGroup=$group===null?null:$this->selected($choices['groups'],$group);
            $selectedSpace=$space===null?null:$this->selected($choices['spaces'],$space);
            $data+=['choices'=>$choices,'selected_group'=>$selectedGroup,'selected_space'=>$selectedSpace,
                'profiles'=>$group===null?[]:$this->access->state($actor,$group,$space)];
            return new Response(200,$this->renderer->render('permissions',$data));
        }catch(AccessDenied){return $this->error(403,'Für die Berechtigungsverwaltung fehlt die Berechtigung.',$data);}
        catch(MutationConflict){return $this->error(409,'Die Zuweisung oder Profildefinition wurde inzwischen geändert. Bitte lade die Auswahl erneut.',$data);}
        catch(\LengthException){return $this->error(413,'Die Auswahlliste überschreitet die Grenze von 1000 Einträgen.',$data);}
        catch(\InvalidArgumentException|\JsonException){return $this->error(400,'Die Auswahl oder Bestätigung ist ungültig.',$data);}
    }
    private function selected(array $rows,string $id):array
    {foreach($rows as$row){if($row['id']===$id){return $row;}}throw new \InvalidArgumentException('Unknown selection.');}
    private function queryId(Request $request,string $key):?string
    {
        $id=$request->query[$key]??'';
        if(!is_string($id)||($id!==''&&!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$id))){throw new \InvalidArgumentException();}
        return $id===''?null:$id;
    }
    private function error(int $status,string $message,array $data,array $headers=[]):Response
    {return new Response($status,$this->renderer->render('error',$data+['status'=>$status,'error'=>$message]),$headers);}
}
