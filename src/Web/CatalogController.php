<?php
declare(strict_types=1);
namespace Samla\Core\Web;
use Samla\Core\Catalog\CatalogService;
use Samla\PluginApi\{AccessDenied,CatalogEntryChange,CatalogTagChange,MutationConflict};
final readonly class CatalogController
{
    public function __construct(private CatalogService $catalog,private PageRenderer $renderer=new PageRenderer()){}
    public function handle(Request $request,string $actor,string $csrf,array $data):Response
    {
        try{
            if(!in_array($request->path,['/catalog','/catalog/save','/catalog/tag','/catalog/batch','/catalog/batch/change','/catalog/search'],true)){return $this->error(404,'Diese Seite wurde nicht gefunden.',$data);}
            $method=in_array($request->path,['/catalog','/catalog/batch','/catalog/search'],true)?'GET':'POST';if($request->method!==$method){return $this->error(405,'Diese Methode ist hier nicht erlaubt.',$data,['Allow'=>$method]);}
            $space=$method==='GET'?($request->query['space']??null):$request->field('space',64);
            if(!is_string($space)||!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$space)){throw new \InvalidArgumentException();}
            if($request->path==='/catalog/search'){
                $text=$request->query['q']??'';$tag=$request->query['tag']??'';$extension=$request->query['extension']??'';
                if(!is_string($text)||!is_string($tag)||!is_string($extension)){throw new \InvalidArgumentException();}
                return new Response(200,$this->renderer->render('catalog-search',$data+$this->catalog->search($actor,$space,$text,$tag===''?null:$tag,strtolower(ltrim($extension,'.')),$request->after())));
            }
            if(in_array($request->path,['/catalog/batch','/catalog/batch/change'],true)){return $this->batch($request,$actor,$csrf,$data,$space);}
            $entry=$method==='GET'?($request->query['entry']??null):$request->field('entry',32);
            if(!is_string($space)||!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$space)||!is_string($entry)||!preg_match('/^[a-f0-9]{32}$/D',$entry)){throw new \InvalidArgumentException();}
            $current=$this->catalog->details($actor,$space,$entry);
            if($method==='GET'){return new Response(200,$this->renderer->render('catalog',$data+$current+['space_id'=>$space]));}
            if(!preg_match('/^[a-f0-9]{64}$/D',$csrf)||!hash_equals($csrf,$request->field('csrf',64))){throw new AccessDenied();}
            if($request->path==='/catalog/save'){
                $tags=$request->post['tags']??[];if(!is_array($tags)){throw new \InvalidArgumentException();}
                $changed=$this->catalog->save($actor,new CatalogEntryChange($space,$entry,$this->number($request,'entry_revision'),$this->number($request,'catalog_revision'),$request->field('description',CatalogEntryChange::MAX_DESCRIPTION_BYTES),$tags));
            }else{
                if($request->field('confirm',1)!=='1'){throw new \InvalidArgumentException();}
                $operation=$request->field('operation',6);$id=bin2hex(random_bytes(16));$revision=null;
                if($operation!=='create'){
                    $selection=$request->field('selection',64);
                    if(!preg_match('/^([a-f0-9]{32}):([1-9][0-9]{0,17})$/D',$selection,$m)){throw new \InvalidArgumentException();}
                    $id=$m[1];$revision=(int)$m[2];
                }
                $name=$operation==='delete'?null:trim($request->field('name',320));
                $changed=$this->catalog->changeTag($actor,new CatalogTagChange($space,$id,$operation,$revision,$name));
            }
            return new Response(303,headers:['Location'=>'/catalog?'.http_build_query(['space'=>$space,'entry'=>$entry])],flash:$changed?'Katalogangaben gespeichert.':'Die Angaben sind unverändert.');
        }catch(AccessDenied){return $this->error(403,'Für diese Katalogangaben fehlt die Berechtigung.',$data);}
        catch(MutationConflict){return $this->error(409,'Datei, Katalog oder Tag wurde geändert; der Tagname ist bereits belegt oder der Tag wird noch verwendet. Bitte lade die Angaben erneut.',$data);}
        catch(\LengthException){return $this->error(413,'Die Auswahl oder der Katalog überschreitet die unterstützte Größe.',$data);}
        catch(\InvalidArgumentException){return $this->error(400,'Die Katalogeingabe oder Auswahl ist ungültig.',$data);}
    }
    private function batch(Request $request,string $actor,string $csrf,array $data,string $space):Response
    {
        $parent=$request->method==='GET'?($request->query['parent']??''):$request->field('parent',32);
        if(!is_string($parent)||($parent!==''&&!preg_match('/^[a-f0-9]{32}$/D',$parent))){throw new \InvalidArgumentException();}
        if($request->method==='GET'){
            return new Response(200,$this->renderer->render('catalog-batch',$data+$this->catalog->batchListing($actor,$space,$parent,$request->after())));
        }
        if(!preg_match('/^[a-f0-9]{64}$/D',$csrf)||!hash_equals($csrf,$request->field('csrf',64))){throw new AccessDenied();}
        if($request->field('confirm',1)!=='1'){throw new \InvalidArgumentException();}
        $rows=$request->post['entries']??null;
        if(!is_array($rows)||!array_is_list($rows)||count($rows)<1||count($rows)>100){throw new \InvalidArgumentException();}
        $entries=[];foreach($rows as$value){
            if(!is_string($value)||!preg_match('/^([a-f0-9]{32}):([1-9][0-9]{0,17}):(0|[1-9][0-9]{0,17})$/D',$value,$m)||isset($entries[$m[1]])){throw new \InvalidArgumentException();}
            $entries[$m[1]]=[(int)$m[2],(int)$m[3]];
        }
        $changed=$this->catalog->batch($actor,$space,$request->field('tag',32),$request->field('operation',6),$entries);
        return new Response(303,headers:['Location'=>'/catalog/batch?'.http_build_query(['space'=>$space,'parent'=>$parent])],flash:$changed?'Tagzuordnungen gemeinsam geändert.':'Alle gewählten Dateien hatten bereits die gewünschte Zuordnung.');
    }
    private function number(Request $request,string $field):int
    {$v=$request->field($field,18);if(!preg_match('/^(0|[1-9][0-9]{0,17})$/D',$v)){throw new \InvalidArgumentException();}return(int)$v;}
    private function error(int $status,string $message,array $data,array $headers=[]):Response
    {return new Response($status,$this->renderer->render('error',$data+['status'=>$status,'error'=>$message]),$headers);}
}
