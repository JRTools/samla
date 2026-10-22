<?php
declare(strict_types=1);
namespace Samla\Core\Web;

use Samla\Core\Administration\AdministrationQueryService;
use Samla\Core\Identity\GroupMappingService;
use Samla\PluginApi\{AccessDenied,MutationConflict};

/** The configured issuer is trusted composition input; forms cannot choose an issuer or submit claims. */
final readonly class GroupMappingController
{
    public function __construct(private GroupMappingService $service,private AdministrationQueryService $directory,
        private ?string $issuer=null,private ?string $groupsClaim=null,private PageRenderer $renderer=new PageRenderer()){}
    public function handle(Request $request,string $actor,string $csrf,array $data):Response
    {
        try{
            $choices=$this->directory->mappingChoices($actor);
            if(!in_array($request->path,['/administration/group-mappings','/administration/group-mappings/change'],true)){return $this->error(404,'Diese Seite wurde nicht gefunden.',$data);}
            $method=$request->path==='/administration/group-mappings'?'GET':'POST';
            if($request->method!==$method){return $this->error(405,'Diese Methode ist hier nicht erlaubt.',$data,['Allow'=>$method]);}
            $group=$method==='POST'?$request->field('group',64):($request->query['group']??'');
            if(!is_string($group)){throw new \InvalidArgumentException();}
            $selected=null;foreach($choices as$row){if($row['id']===$group){$selected=$row;break;}}
            if($group!==''&&$selected===null){throw new \InvalidArgumentException();}
            $rows=$group===''?[]:$this->directory->mappings($actor,$group);
            if($method==='POST'){
                if(!preg_match('/^[a-f0-9]{64}$/D',$csrf)||!hash_equals($csrf,$request->field('csrf',64))){throw new AccessDenied();}
                if($selected===null||$request->field('confirm',1)!=='1'){throw new \InvalidArgumentException();}
                $operation=$request->field('operation',6);
                if($operation==='grant'){
                    if(count($rows)>=100){throw new \LengthException();}
                    if($this->issuer===null||$this->groupsClaim===null){throw new MutationConflict();}
                    $external=$request->field('external',256);
                    if($external===''||!mb_check_encoding($external,'UTF-8')||preg_match('/[\x00-\x1f\x7f]/u',$external)){throw new \InvalidArgumentException();}
                    $this->service->grant($actor,$this->issuer,$external,$group,false);
                }elseif($operation==='revoke'){
                    $id=$request->field('mapping',64);$edge=null;
                    foreach($rows as$row){if(hash_equals($row['id'],$id)){$edge=$row;break;}}
                    if($edge===null){throw new MutationConflict();}
                    $this->service->revoke($actor,$edge['issuer'],$edge['external_id'],$group,true);
                }else{throw new \InvalidArgumentException();}
                return new Response(303,headers:['Location'=>'/administration/group-mappings?'.http_build_query(['group'=>$group])],flash:'Die IdP-Gruppenzuordnung wurde geändert.');
            }
            return new Response(200,$this->renderer->render('group-mappings',$data+['groups'=>$choices,'selected_group'=>$selected,'mappings'=>$rows,'issuer'=>$this->issuer,'groups_claim'=>$this->groupsClaim]));
        }catch(AccessDenied){return $this->error(403,'Die Berechtigung oder Delegation für diese Gruppenzuordnung fehlt.',$data);}
        catch(MutationConflict){return $this->error(409,'Die Zuordnung wurde geändert oder der signierte Gruppenclaim ist nicht konfiguriert. Bitte lade die Seite erneut.',$data);}
        catch(\LengthException){return $this->error(413,'Die Auswahl überschreitet 1000 Gruppen oder 100 Zuordnungen je Gruppe.',$data);}
        catch(\InvalidArgumentException){return $this->error(400,'Die Gruppenauswahl, externe Kennung oder Bestätigung ist ungültig.',$data);}
    }
    private function error(int $status,string $message,array $data,array $headers=[]):Response
    {return new Response($status,$this->renderer->render('error',$data+['status'=>$status,'error'=>$message]),$headers);}
}
