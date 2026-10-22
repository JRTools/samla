<?php
declare(strict_types=1);
namespace Samla\Core\Media;
use Samla\PluginApi\{MediaField,MediaProvider,MediaResult,MediaSource,MediaValue};

/** Bounded JPEG XMP/IPTC and PNG text reader. No network, decompression or source rewriting. */
final class EmbeddedMetadataProvider implements MediaProvider
{
    public function __construct(private readonly ?\Closure $clock=null){}
    private function now():int{return $this->clock===null?hrtime(true):($this->clock)();}
    private function checkTime(int $deadline):void{if($this->now()>$deadline){throw new \LengthException('Metadata processing budget exceeded.');}}
    private const XMP="http://ns.adobe.com/xap/1.0/\0";
    private const DC='http://purl.org/dc/elements/1.1/';
    private const IPTC='http://iptc.org/std/Iptc4xmpExt/2008-02-29/';
    public function id():string{return 'platform.media.embedded';}
    public function version():string{return '1.0';}
    public static function fields():array
    {
        $fields=[];foreach(['embedded_title'=>'Eingebetteter Titel','embedded_description'=>'Eingebettete Beschreibung','embedded_keyword'=>'Eingebettetes Schlagwort',
            'embedded_creator'=>'Eingebetteter Urheber','embedded_credit'=>'Eingebetteter Bildnachweis','embedded_copyright'=>'Eingebettete Rechteangabe','embedded_source'=>'Eingebettete Quelle',
            'embedded_software'=>'Benanntes Programm, kein KI-Nachweis','digital_source_type'=>'Deklarierter Digital Source Type','declared_origin'=>'Deklarierte Entstehung, ungeprüfte Dateiangabe',
            'ai_system'=>'Deklariertes KI-System','ai_system_version'=>'Deklarierte KI-Systemversion','embedded_coverage'=>'Untersuchte Metadatenquellen','ai_marker_status'=>'Auslesestatus strukturierter Herkunftsangaben']as$id=>$label){$fields[]=new MediaField('platform.media.'.$id,$label);}
        $fields[]=new MediaField('platform.media.embedded_prompt_author','Deklarierter Promptautor',sensitive:true,readPermission:'media.sensitive.read');
        $fields[]=new MediaField('platform.media.embedded_prompt','Eingebettete Prompt-/Workflowangaben',sensitive:true,readPermission:'media.sensitive.read');return $fields;
    }
    public function extract(MediaSource $source):MediaResult
    {
        $jpeg=str_starts_with($source->bytes,"\xff\xd8");$png=str_starts_with($source->bytes,"\x89PNG\r\n\x1a\n");if(!$jpeg&&!$png){return new MediaResult('unsupported');}
        $values=[];$issues=[];$budget=0;$deadline=$this->now()+1000000000;$warnings=0;set_error_handler(static function()use(&$warnings):bool{++$warnings;return true;});
        try{if($jpeg){$this->jpeg($source->bytes,$values,$issues,$budget,$deadline);}else{$this->png($source->bytes,$values,$issues,$budget,$deadline);}}
        catch(\LengthException){$issues['limit']=true;}catch(\Throwable){$issues['error']=true;}finally{restore_error_handler();}
        if($warnings>0){$issues['error']=true;}foreach($values as$value){if(in_array($value->status,['error','limit','unsupported'],true)){$issues[$value->status]=true;}}$origins=[];
        foreach($values as$v){if($v->fieldId==='platform.media.digital_source_type'&&$v->normalized!==null){$origins[$v->normalized]=true;}}
        if(count($values)>29){$values=array_slice($values,0,29);$issues['limit']=true;}
        $origin='Unbekannt';
        if(count($origins)>1){$origin='Widersprüchliche Dateiangaben';}
        elseif(count($origins)===1&&$issues===[]){$uri=array_key_first($origins);$origin=match($uri){
            'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia'=>'Als KI-generiert gekennzeichnet',
            'http://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia'=>'Als mit KI bearbeitet gekennzeichnet',default=>'Andere deklarierte Herkunft'};}
        $marker=$issues!==[]?'Nur teilweise untersucht':($origins===[]?'Keine Kennzeichnung in den untersuchten Feldern':'Strukturierte Dateiangaben ausgelesen');
        $coverage=$jpeg?'JPEG APP1 Standard-XMP und APP13 Photoshop-IPTC-IIM':'PNG tEXt und unkomprimiertes iTXt einschließlich Standard-XMP';
        $values[]=new MediaValue('platform.media.embedded_coverage','provider.coverage',$coverage,$coverage);
        $values[]=new MediaValue('platform.media.declared_origin','provider.declared_origin',null,$origin);
        $values[]=new MediaValue('platform.media.ai_marker_status','provider.ai_marker_status',null,$marker);
        return new MediaResult(isset($issues['limit'])?'limit':($issues===[]?'ready':'partial'),$values);
    }
    private function budget(int $size,int &$budget):void
    {$budget+=$size;if($budget>262144){throw new \LengthException();}}
    private function value(array &$values,string $field,string $path,string $raw,bool $latin1=false):void
    {
        if(count($values)>=128){throw new \LengthException();}
        if($latin1){$raw=mb_convert_encoding($raw,'UTF-8','ISO-8859-1');}
        if(strlen($raw)>1024){$values[]=new MediaValue('platform.media.'.$field,$path,null,null,'limit');return;}
        if(!mb_check_encoding($raw,'UTF-8')){$values[]=new MediaValue('platform.media.'.$field,$path,null,null,'error');return;}
        $normalized=trim($raw);if($field==='digital_source_type'&&!preg_match('~^http://cv\.iptc\.org/newscodes/digitalsourcetype/[A-Za-z][A-Za-z0-9]{0,63}$~D',$normalized)){$normalized=null;}
        if($normalized!==null&&preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$normalized)){$normalized=null;}
        $values[]=new MediaValue('platform.media.'.$field,$path,$raw,$normalized,$normalized===null?'error':'extracted');
    }
    private function jpeg(string $bytes,array &$values,array &$issues,int &$budget,int $deadline):void
    {
        $offset=2;$segments=0;$length=strlen($bytes);
        while($offset<$length){$this->checkTime($deadline);if(++$segments>512||$offset>1048576){throw new \LengthException();}
            if(ord($bytes[$offset++])!==255){throw new \InvalidArgumentException();}
            while($offset<$length&&ord($bytes[$offset])===255){if(++$offset>1048576){throw new \LengthException();}}
            if($offset>=$length){throw new \InvalidArgumentException();}$marker=ord($bytes[$offset++]);
            if($marker===0xda||$marker===0xd9){return;}if($marker===1||($marker>=0xd0&&$marker<=0xd7)){continue;}
            if($offset+2>$length){throw new \InvalidArgumentException();}$size=unpack('n',substr($bytes,$offset,2))[1];
            if($size<2||$offset+$size>$length){throw new \InvalidArgumentException();}$data=substr($bytes,$offset+2,$size-2);$offset+=$size;
            if($marker===0xe1&&str_starts_with($data,self::XMP)){$this->budget(strlen($data),$budget);$this->xmp(substr($data,strlen(self::XMP)),'JPEG.APP1['.$segments.'].XMP',$values,$deadline);}
            elseif($marker===0xe1&&str_starts_with($data,"http://ns.adobe.com/xmp/extension/\0")){$issues['unsupported']=true;}
            elseif($marker===0xed&&str_starts_with($data,"Photoshop 3.0\0")){$this->budget(strlen($data),$budget);$this->iptc($data,$values,$issues,$deadline);}
        }
        throw new \InvalidArgumentException();
    }
    private function iptc(string $data,array &$values,array &$issues,int $deadline):void
    {
        $offset=14;$resources=0;$length=strlen($data);
        while($offset<$length){$this->checkTime($deadline);if(++$resources>64){throw new \LengthException();}if($offset+7>$length||substr($data,$offset,4)!=='8BIM'){throw new \InvalidArgumentException();}
            $id=unpack('n',substr($data,$offset+4,2))[1];$offset+=6;$name=ord($data[$offset])+1;$offset+=$name+($name%2);
            if($offset+4>$length){throw new \InvalidArgumentException();}$size=unpack('N',substr($data,$offset,4))[1];$offset+=4;if($offset+$size>$length){throw new \InvalidArgumentException();}
            if($id===0x0404){$tags=iptcparse(substr($data,$offset,$size));if($tags===false){throw new \InvalidArgumentException();}
                $utf8=($tags['1#090'][0]??null)==="\x1b%G";
                foreach(['2#005'=>'embedded_title','2#120'=>'embedded_description','2#025'=>'embedded_keyword','2#080'=>'embedded_creator','2#110'=>'embedded_credit','2#116'=>'embedded_copyright','2#115'=>'embedded_source']as$tag=>$field){
                    foreach($tags[$tag]??[]as$i=>$raw){if($i>=16){throw new \LengthException();}if(!$utf8&&((isset($tags['1#090'])&&$tags['1#090'][0]!=="")||preg_match('/[^\x00-\x7f]/',$raw))){$issues['unsupported']=true;$values[]=new MediaValue('platform.media.'.$field,'JPEG.APP13.IPTC.'.$tag.'['.$i.']',null,null,'unsupported');}else{$this->value($values,$field,'JPEG.APP13.IPTC.'.$tag.'['.$i.']',$raw);}}}
            }
            $offset+=$size+($size%2);
        }
        if($offset!==$length){throw new \InvalidArgumentException();}
    }
    private function xmp(string $xml,string $path,array &$values,int $deadline):void
    {
        $this->checkTime($deadline);if(strlen($xml)>65536){throw new \LengthException();}
        if(!mb_check_encoding($xml,'UTF-8')||str_contains($xml,"\0")||preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i',$xml)){throw new \InvalidArgumentException();}
        $previous=libxml_use_internal_errors(true);
        try{$doc=new \DOMDocument();$doc->resolveExternals=false;$doc->substituteEntities=false;$doc->validateOnParse=false;
            if(!$doc->loadXML($xml,LIBXML_NONET|LIBXML_NOBLANKS|LIBXML_COMPACT)||$doc->doctype!==null){throw new \InvalidArgumentException();}if($doc->getElementsByTagName('*')->length>2048){throw new \LengthException();}
            $xp=new \DOMXPath($doc);$xp->registerNamespace('rdf','http://www.w3.org/1999/02/22-rdf-syntax-ns#');
            foreach([[self::DC,'title','embedded_title'],[self::DC,'description','embedded_description'],[self::DC,'subject','embedded_keyword'],[self::DC,'creator','embedded_creator'],[self::DC,'rights','embedded_copyright'],
                ['http://ns.adobe.com/photoshop/1.0/','Credit','embedded_credit'],['http://ns.adobe.com/photoshop/1.0/','Source','embedded_source'],['http://ns.adobe.com/xap/1.0/','CreatorTool','embedded_software'],[self::IPTC,'DigitalSourceType','digital_source_type'],[self::IPTC,'AISystemUsed','ai_system'],[self::IPTC,'AISystemVersionUsed','ai_system_version'],[self::IPTC,'AIPromptInformation','embedded_prompt'],[self::IPTC,'AIPromptWriterName','embedded_prompt_author']]as[$ns,$local,$field]){$this->checkTime($deadline);
                $xp->registerNamespace('selected',$ns);$nodes=$xp->query('//*[namespace-uri()="http://www.w3.org/1999/02/22-rdf-syntax-ns#" and local-name()="Description"]/@selected:'.$local.' | //selected:'.$local);
                foreach($nodes as$n){if($n instanceof \DOMAttr){$texts=[$n->value];}
                    else{$items=$xp->query('.//rdf:li',$n);$texts=[];if($items->length>0){foreach($items as$item){$resource=$item->getAttributeNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#','resource');$texts[]=$resource!==''?$resource:$item->textContent;}}else{$resource=$n->getAttributeNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#','resource');$texts[]=$resource!==''?$resource:$n->textContent;}}
                    foreach($texts as$i=>$text){$this->value($values,$field,$path.'.'.$local.'['.$i.']',$text);}
                }
            }
        }finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
    }
    private function png(string $bytes,array &$values,array &$issues,int &$budget,int $deadline):void
    {
        $offset=8;$chunks=0;$length=strlen($bytes);
        while($offset<$length){$this->checkTime($deadline);if(++$chunks>512){throw new \LengthException();}if($offset+12>$length){throw new \InvalidArgumentException();}
            $size=unpack('N',substr($bytes,$offset,4))[1];$type=substr($bytes,$offset+4,4);if($size>$length-$offset-12){throw new \InvalidArgumentException();}
            if(!hash_equals(hash('crc32b',substr($bytes,$offset+4,$size+4)),bin2hex(substr($bytes,$offset+8+$size,4)))){throw new \InvalidArgumentException();}
            if(in_array($type,['tEXt','iTXt','zTXt'],true)){$this->budget($size,$budget);if($size>65536){throw new \LengthException();}$data=substr($bytes,$offset+8,$size);$sep=strpos($data,"\0");if($sep===false||$sep<1||$sep>79){throw new \InvalidArgumentException();}$key=substr($data,0,$sep);$text=substr($data,$sep+1);
                if($type==='zTXt'){$issues['unsupported']=true;}
                else{
                    if($type==='iTXt'){if(strlen($text)<4){throw new \InvalidArgumentException();}if(ord($text[0])!==0){$issues['unsupported']=true;$offset+=$size+12;continue;}if(ord($text[1])!==0){throw new \InvalidArgumentException();}
                        $parts=explode("\0",substr($text,2),3);if(count($parts)!==3){throw new \InvalidArgumentException();}$text=$parts[2];}
                    if($key==='XML:com.adobe.xmp'){$this->xmp($text,'PNG.'.$type.'['.$chunks.'].XMP',$values,$deadline);}
                    else{$field=['Title'=>'embedded_title','Description'=>'embedded_description','Author'=>'embedded_creator','Copyright'=>'embedded_copyright','Source'=>'embedded_source','Software'=>'embedded_software','parameters'=>'embedded_prompt','prompt'=>'embedded_prompt','workflow'=>'embedded_prompt'][$key]??null;
                        if($field!==null){$this->value($values,$field,'PNG.'.$type.'.'.$key,$text,$type==='tEXt');}}
                }
            }
            $offset+=$size+12;if($type==='IEND'){if($size!==0||$offset!==$length){throw new \InvalidArgumentException();}return;}
        }
        throw new \InvalidArgumentException();
    }
}
