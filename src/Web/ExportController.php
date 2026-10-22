<?php
declare(strict_types=1);
namespace Samla\Core\Web;
use Samla\Core\Export\ExportService;
use Samla\Core\Storage\StorageUnavailable;
use Samla\PluginApi\{AccessDenied,MutationConflict};
final readonly class ExportController
{
    public function __construct(private ExportService $exports,private PageRenderer $renderer=new PageRenderer()){}
    public function handle(Request $request,string $actor,string $csrf,array $data):Response
    {
        try{
            $posts=['/exports/create','/exports/retry','/exports/remove'];$gets=['/exports','/exports/download'];
            if(!in_array($request->path,[...$posts,...$gets],true)){return $this->error(404,'Diese Seite wurde nicht gefunden.',$data);}
            $method=in_array($request->path,$posts,true)?'POST':'GET';
            if($request->method!==$method){return $this->error(405,'Diese Methode ist hier nicht erlaubt.',$data,['Allow'=>$method]);}
            if($method==='POST'){
                if(!preg_match('/^[a-f0-9]{64}$/D',$csrf)||!hash_equals($csrf,$request->field('csrf',64))){throw new AccessDenied();}
                if($request->path==='/exports/create'){
                    $entries=$request->post['entries']??null;if(!is_array($entries)||!array_is_list($entries)||count($entries)<1||count($entries)>100){throw new \InvalidArgumentException();}
                    $selection=[];foreach($entries as$entry){
                        if(!is_string($entry)||!preg_match('/^([a-f0-9]{32}):([1-9][0-9]{0,17})$/D',$entry,$match)||isset($selection[$match[1]])){throw new \InvalidArgumentException();}
                        $selection[$match[1]]=(int)$match[2];
                    }
                    $this->exports->request($actor,$request->field('space',64),$selection);
                    return new Response(303,headers:['Location'=>'/exports'],flash:'Export beauftragt. Der konfigurierte Worker erstellt das Paket.');
                }
                $revision=$request->field('revision',18);if(!ctype_digit($revision)||(int)$revision<1){throw new \InvalidArgumentException();}
                if($request->field('confirm',1)!=='1'){throw new \InvalidArgumentException();}
                $this->exports->change($actor,$request->field('export',32),(int)$revision,$request->path==='/exports/remove'?'remove':'retry');
                return new Response(303,headers:['Location'=>'/exports'],flash:$request->path==='/exports/remove'?'Export entfernt beziehungsweise zur Bereinigung markiert.':'Export erneut vorgemerkt.');
            }
            if($request->path==='/exports/download'){
                $id=$request->query['export']??null;if(!is_string($id)){throw new \InvalidArgumentException();}
                $artifact=$this->exports->download($actor,$id);
                return new Response(200,headers:['Content-Type'=>'application/zip','Content-Length'=>(string)$artifact['size'],
                    'Content-Disposition'=>'attachment; filename="samla-export-'.$id.'.zip"'],stream:static function()use($artifact):void{
                        try{fpassthru($artifact['stream']);}finally{fclose($artifact['stream']);}
                    });
            }
            return new Response(200,$this->renderer->render('exports',$data+['rows'=>$this->exports->listing($actor),'zip_available'=>$this->exports->available()]));
        }catch(AccessDenied){return $this->error(403,'Dieser Export ist für deinen aktuellen Zugang nicht verfügbar.',$data);}
        catch(MutationConflict){return $this->error(409,'Exportstatus, Dateien oder Erweiterungen wurden geändert oder das Paket ist abgelaufen. Bitte lade die Ansicht neu.',$data);}
        catch(\LengthException){return $this->error(413,'Dateianzahl, Paketgröße, Auftragsanzahl oder reservierter Speicher überschreiten die Exportgrenze. Entferne alte Pakete oder verkleinere die Auswahl.',$data);}
        catch(StorageUnavailable){return $this->error(503,'ZIP-Fähigkeit oder privater Exportspeicher sind in dieser Laufzeit nicht verfügbar.',$data);}
        catch(\InvalidArgumentException){return $this->error(400,'Die Exportauswahl oder Bestätigung ist ungültig.',$data);}
    }
    private function error(int $status,string $message,array $data,array $headers=[]):Response
    {return new Response($status,$this->renderer->render('error',$data+['status'=>$status,'error'=>$message]),$headers);}
}
