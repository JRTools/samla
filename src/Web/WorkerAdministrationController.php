<?php
declare(strict_types=1);
namespace Samla\Core\Web;
use Samla\Core\Background\WorkerAdministration;
use Samla\PluginApi\{AccessDenied,MutationConflict};
final readonly class WorkerAdministrationController
{
    public function __construct(private WorkerAdministration $service,private PageRenderer $renderer=new PageRenderer()){}
    public function handle(Request $request,string $actor,string $csrf,array $data):Response
    {
        try{
            if($request->path==='/operations/worker'){
                if($request->method!=='GET'){return new Response(405,headers:['Allow'=>'GET']);}
                $after=$request->query['after']??null;$status=$request->query['status']??'all';
                if(($after!==null&&(!is_string($after)||strlen($after)>129))||!is_string($status)){throw new \InvalidArgumentException();}
                $data+=$this->service->listing($actor,$after,$status);return new Response(200,$this->renderer->render('worker',$data));
            }
            if($request->path!=='/operations/worker/retry'){return new Response(404);}
            if($request->method!=='POST'){return new Response(405,headers:['Allow'=>'POST']);}
            if(!preg_match('/^[a-f0-9]{64}$/D',$csrf)||!hash_equals($csrf,$request->field('csrf',64))){throw new AccessDenied();}
            $revision=$request->field('revision',18);if(!ctype_digit($revision)||(int)$revision<1){throw new \InvalidArgumentException();}
            $this->service->retry($actor,$request->field('subscriber',96),$request->field('event',32),(int)$revision);
            return new Response(303,headers:['Location'=>'/operations/worker?status=failed'],flash:'Die Zustellung wurde zur erneuten Verarbeitung vorgemerkt.');
        }catch(AccessDenied){$status=403;$message='Für diese Betriebsaktion fehlt die aktuelle Berechtigung.';}
        catch(MutationConflict){$status=409;$message='Die Zustellung wurde inzwischen geändert. Bitte die Liste neu laden.';}
        catch(\InvalidArgumentException){$status=400;$message='Bitte Kennungen, Filter und Revision prüfen.';}
        return new Response($status,$this->renderer->render('error',$data+['status'=>$status,'error'=>$message]));
    }
}
