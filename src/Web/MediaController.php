<?php
declare(strict_types=1);
namespace Samla\Core\Web;
use Samla\Core\Media\{MediaNotReady,MediaService};
use Samla\PluginApi\{AccessDenied,MutationConflict};
final readonly class MediaController
{
    public function __construct(private MediaService $service,private PageRenderer $renderer=new PageRenderer()){}
    public function handle(Request $request,string $actor,string $csrf,array $data):Response
    {
        try{
            if($request->path==='/media/rebuild'){
                $space=$request->method==='POST'?$request->field('space',64):$this->query($request,'space',64);
                if($request->method==='GET'){$data+=$this->service->rebuildPlan($actor,$space,$request->after());return new Response(200,$this->renderer->render('media-rebuild',$data));}
                if($request->method!=='POST'){return new Response(405,headers:['Allow'=>'GET, POST']);}
                if(!preg_match('/^[a-f0-9]{64}$/D',$csrf)||!hash_equals($csrf,$request->field('csrf',64))){throw new AccessDenied();}
                $plan=json_decode($request->field('plan',12000),true,4,JSON_THROW_ON_ERROR);
                if(!is_array($plan)){throw new \InvalidArgumentException();}
                $after=$request->field('after',32);if($after!==''&&!preg_match('/^[a-f0-9]{32}$/D',$after)){throw new \InvalidArgumentException();}
                $this->service->rebuild($actor,$space,new \Samla\PluginApi\MediaBatchRefreshChange($plan));
                return new Response(303,headers:['Location'=>'/media/rebuild?'.http_build_query(['space'=>$space,...($after===''?[]:['after'=>$after])])],flash:'Die Dateiseite wurde für den Worker vorgemerkt.');
            }
            if($request->path==='/media/refresh'){
                if($request->method!=='POST'){return new Response(405,headers:['Allow'=>'POST']);}
                if(!preg_match('/^[a-f0-9]{64}$/D',$csrf)||!hash_equals($csrf,$request->field('csrf',64))){throw new AccessDenied();}
                $space=$request->field('space',64);$entry=$request->field('entry',32);$revision=$this->revision($request->field('revision',18));
                $changed=$this->service->refresh($actor,$space,$entry,$revision);
                return new Response(303,headers:['Location'=>'/media/detail?'.http_build_query(['space'=>$space,'entry'=>$entry])],flash:$changed?'Die Medienanalyse wurde für den Worker vorgemerkt.':'Die Analyse wurde bereits kürzlich vorgemerkt.');
            }
            if(!in_array($request->path,['/media','/media/detail','/media/preview'],true)){return new Response(404);}
            if($request->method!=='GET'){return new Response(405,headers:['Allow'=>'GET']);}
            $space=$this->query($request,'space',64);
            if($request->path==='/media/preview'){
                $png=$this->service->preview($actor,$space,$this->query($request,'entry',32),$this->revision($this->query($request,'revision',18)));
                return new Response(200,$png,['Content-Type'=>'image/png','Content-Length'=>(string)strlen($png),'Content-Disposition'=>'inline; filename="samla-preview.png"']);
            }
            if($request->path==='/media/detail'){$data+=$this->service->details($actor,$space,$this->query($request,'entry',32));return new Response(200,$this->renderer->render('media-detail',$data));}
            if($space===''){$data+=$this->service->spaces($actor,$request->after());$data['media_areas']=true;}
            else{$data+=$this->service->gallery($actor,$space,$this->query($request,'parent',32),$request->after());$data['media_areas']=false;}
            return new Response(200,$this->renderer->render('media-gallery',$data));
        }catch(AccessDenied){$status=403;$message='Für diese Medienfunktion fehlt die aktuelle Berechtigung.';}
        catch(MutationConflict){$status=409;$message=$request->path==='/media/rebuild'?'Der Analyseplan ist veraltet oder enthält gerade vorgemerkte Dateien. Bitte die Planungsseite neu laden.':'Die Datei wurde inzwischen geändert. Bitte die Medienansicht neu laden.';}
        catch(MediaNotReady){$status=404;$message='Für diesen Dateistand ist noch keine Vorschau verfügbar.';}
        catch(\LengthException){$status=413;$message='Eine technische Medien- oder Kataloggrenze wurde überschritten. Der normale Download bleibt verfügbar.';}
        catch(\InvalidArgumentException|\JsonException){$status=400;$message='Bitte Medienkennung, Revision und Filter prüfen.';}
        return new Response($status,$this->renderer->render('error',$data+['status'=>$status,'error'=>$message]));
    }
    private function query(Request $r,string $key,int $limit):string
    {$v=$r->query[$key]??'';if(!is_string($v)||strlen($v)>$limit||($v!==''&&!preg_match('/^[a-zA-Z0-9_.:-]+$/D',$v))){throw new \InvalidArgumentException();}return $v;}
    private function revision(string $value):int
    {if(!ctype_digit($value)||(int)$value<1){throw new \InvalidArgumentException();}return (int)$value;}
}
