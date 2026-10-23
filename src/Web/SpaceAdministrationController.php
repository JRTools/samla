<?php
declare(strict_types=1);
namespace Samla\Core\Web;

use Samla\Core\Administration\AccessAdministrationDirectory;
use Samla\Core\Storage\SpaceAdministration;
use Samla\PluginApi\{AccessDenied,MutationConflict};

final readonly class SpaceAdministrationController
{
    public function __construct(private SpaceAdministration $spaces,private AccessAdministrationDirectory $directory,
        private PageRenderer $renderer=new PageRenderer()){}
    public function handle(Request $request,string $actor,string $csrf,array $data):Response
    {
        try{
            if(!$this->spaces->canManage($actor)){throw new AccessDenied();}
            $posts=['/administration/spaces/create','/administration/spaces/update','/administration/spaces/delete'];
            if($request->path!=='/administration/spaces'&&!in_array($request->path,$posts,true)){return $this->error(404,'Diese Seite wurde nicht gefunden.',$data);}
            $method=in_array($request->path,$posts,true)?'POST':'GET';
            if($request->method!==$method){return $this->error(405,'Diese Methode ist hier nicht erlaubt.',$data,['Allow'=>$method]);}
            if($method==='POST'){
                if(!preg_match('/^[a-f0-9]{64}$/D',$csrf)||!hash_equals($csrf,$request->field('csrf',64))){throw new AccessDenied();}
                if($request->path==='/administration/spaces/delete'){
                    if($request->field('confirm',1)!=='1'){throw new \InvalidArgumentException();}
                    $this->spaces->delete($actor,$request->field('space',64),$request->field('expected',64));
                    return new Response(303,headers:['Location'=>'/administration/spaces'],flash:'Leerer Speicherbereich und zugehörige Rechtezuweisungen gelöscht.');
                }
                if(array_key_exists('quota_value',$request->post)||array_key_exists('quota_unit',$request->post)){
                    if(array_key_exists('quota',$request->post)){throw new \InvalidArgumentException();}
                    $quota=StorageSizeInput::parse($request->field('quota_value',20),$request->field('quota_unit',3));
                }else{
                    $quota=$request->field('quota',13);
                    if(!ctype_digit($quota)||strlen($quota)>13){throw new \InvalidArgumentException();}
                }
                $name=$request->field('name',1020);
                if($request->path==='/administration/spaces/create'){
                    $space=$this->spaces->create($actor,$name,(int)$quota);$message='Speicherbereich angelegt. Weise nun ausdrücklich Gruppenrechte zu.';
                }else{
                    $space=$request->field('space',64);$this->spaces->update($actor,$space,$name,(int)$quota,$request->field('expected',64));$message='Speicherbereich gespeichert.';
                }
                return new Response(303,headers:['Location'=>'/administration/spaces?'.http_build_query(['space'=>$space])],flash:$message);
            }
            $choices=$this->directory->choices($actor);$id=$request->query['space']??'';
            if(!is_string($id)){throw new \InvalidArgumentException();}
            $create=$request->query['create']??'';if(!is_string($create)||!in_array($create,['','1'],true)){throw new \InvalidArgumentException();}
            $data+=['show_create'=>$create==='1','choices'=>$choices,'selected_space'=>$id===''?null:$this->spaces->details($actor,$id)];
            return new Response(200,$this->renderer->render('space-administration',$data));
        }catch(AccessDenied){return $this->error(403,'Für die Bereichsverwaltung fehlt die Berechtigung.',$data);}
        catch(MutationConflict){return $this->error(409,'Der Bereich wurde geändert, die Quote liegt unter dem aktuellen Verbrauch oder der Bereich enthält noch Dateien, Papierkorb oder Sperren. Bitte lade die Ansicht erneut.',$data);}
        catch(\LengthException){return $this->error(413,'Die Auswahlliste überschreitet die Grenze von 1000 Einträgen.',$data);}
        catch(\InvalidArgumentException){return $this->error(400,'Name, Quote oder Auswahl sind ungültig.',$data);}
    }
    private function error(int $status,string $message,array $data,array $headers=[]):Response
    {return new Response($status,$this->renderer->render('error',$data+['status'=>$status,'error'=>$message]),$headers);}
}

