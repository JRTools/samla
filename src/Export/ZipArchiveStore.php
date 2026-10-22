<?php
declare(strict_types=1);
namespace Samla\Core\Export;
use Samla\Core\Configuration\ExportConfig;
use Samla\Core\Storage\{LocalContentStore,StorageUnavailable};
use Samla\PluginApi\ExportSource;

/** Internal private derived-artifact store. Callers must authorize sources and delivery separately. */
final class ZipArchiveStore
{
    private string $root;
    private array $locks=[];
    public function __construct(string $storageRoot,private ExportConfig $config=new ExportConfig(),private ?\Closure $clock=null)
    {$this->root=(new LocalContentStore(rtrim($storageRoot,'/').'/.exports'))->root;}
    public static function available():bool
    {return class_exists(\ZipArchive::class)&&method_exists(\ZipArchive::class,'registerCancelCallback');}
    private function now():int{return $this->clock===null?hrtime(true):($this->clock)();}
    private function prepare():void
    {
        if(!is_dir(dirname($this->root))||is_link($this->root)){throw new StorageUnavailable('Export storage unavailable.');}
        if(!is_dir($this->root)&&!@mkdir($this->root,0700)&&!is_dir($this->root)){throw new StorageUnavailable('Export storage unavailable.');}
        if(is_link($this->root)||!is_dir($this->root)||!is_writable($this->root)||!chmod($this->root,0700)){throw new StorageUnavailable('Export storage unavailable.');}
    }
    private function id(string $id):void
    {if(!preg_match('/^[a-f0-9]{32}$/D',$id)){throw new \InvalidArgumentException('Invalid export identifier.');}}
    /** A busy artifact is skipped, never modified concurrently. @return mixed null when busy */
    public function locked(string $id,\Closure $operation):mixed
    {
        $this->id($id);$this->prepare();if(isset($this->locks[$id])){throw new \LogicException('Recursive export lock.');}
        $path=$this->root.'/'.$id.'.lock';if(is_link($path)){throw new StorageUnavailable('Invalid export lock.');}
        $lock=@fopen($path,'c+b');if($lock===false){throw new StorageUnavailable('Export lock unavailable.');}
        try{
            $stat=fstat($lock);if($stat===false||($stat['mode']&0170000)!==0100000||!chmod($path,0600)){throw new StorageUnavailable('Invalid export lock.');}
            if(!flock($lock,LOCK_EX|LOCK_NB)){return null;}
            $this->locks[$id]=true;
            try{return $operation();}finally{unset($this->locks[$id]);flock($lock,LOCK_UN);}
        }finally{fclose($lock);}
    }
    private function requireLock(string $id):void
    {$this->id($id);if(!isset($this->locks[$id])){throw new \LogicException('An artifact lock is required.');}}
    public static function archivePath(ExportSource $source):string
    {
        $name=preg_replace('/[\x00-\x20\x7f<>:"\/\\\\|?*]+/u','_',$source->name);
        $name=rtrim($name??'',' ._');$name=ltrim($name,'.');
        if($name===''||in_array($name,['.','..'],true)){$name='datei';}
        if(preg_match('/^(CON|PRN|AUX|NUL|COM[0-9]|LPT[0-9])(?:\.|$)/i',$name)){$name='_'.$name;}
        $name=mb_strcut($name,0,100,'UTF-8');$name=rtrim($name,' .');
        return 'files/'.$source->entryId.'/'.$name;
    }
    /** @param list<ExportSource> $sources $open must return a readable original stream.
     * $metadata returns ExportMetadata::collect's bounded values/policies. */
    public function build(string $id,array $sources,\Closure $open,\Closure $metadata):array
    {
        $this->requireLock($id);if(!self::available()){throw new StorageUnavailable('Native ZIP export unavailable.');}
        if(!array_is_list($sources)||$sources===[]||count($sources)>$this->config->maxFiles){throw new \LengthException('Export file count limit.');}
        $total=0;$ids=[];$actor=$sources[0] instanceof ExportSource?$sources[0]->actorId:null;$space=$sources[0] instanceof ExportSource?$sources[0]->spaceId:null;
        foreach($sources as$source){
            if(!$source instanceof ExportSource||isset($ids[$source->entryId])||$source->actorId!==$actor||$source->spaceId!==$space){throw new \InvalidArgumentException('Invalid export selection.');}
            $ids[$source->entryId]=true;$total+=$source->sizeBytes;
            if($total>$this->config->maxBytes){throw new \LengthException('Export content limit.');}
        }
        usort($sources,static fn(ExportSource $a,ExportSource $b):int=>strcmp($a->entryId,$b->entryId));
        $this->discard($id);$work=$this->root.'/'.$id.'.work';
        if(!mkdir($work,0700)){throw new StorageUnavailable('Export staging unavailable.');}
        $deadline=$this->now()+$this->config->seconds*1000000000;$zip=null;$opened=false;
        $check=function()use($deadline):void{if($this->now()>=$deadline){throw new ExportBudgetExceeded('Export time budget exceeded.');}};
        try{
            $files=[];$required=['files.list','files.read','files.export'];$stage=[];
            foreach($sources as$index=>$source){
                $check();$path=$work.'/'.$index.'.bin';$input=$open($source);$output=null;
                if(!is_resource($input)||get_resource_type($input)!=='stream'){throw new \RuntimeException('Export source stream unavailable.');}
                try{
                    $output=fopen($path,'xb');if($output===false||!chmod($path,0600)){throw new StorageUnavailable('Export staging unavailable.');}
                    $hash=hash_init('sha256');$size=0;
                    while(!feof($input)){
                        $check();$chunk=fread($input,65536);
                        if($chunk===false||($chunk===''&&!feof($input))){throw new \RuntimeException('Export source read failed.');}
                        $size+=strlen($chunk);if($size>$source->sizeBytes){throw new \RuntimeException('Export source size mismatch.');}
                        hash_update($hash,$chunk);$offset=0;
                        while($offset<strlen($chunk)){$n=fwrite($output,substr($chunk,$offset));if($n===false||$n===0){throw new \RuntimeException('Export stage write failed.');}$offset+=$n;}
                    }
                    if($size!==$source->sizeBytes||!hash_equals($source->sha256,hash_final($hash))){throw new \RuntimeException('Export source integrity mismatch.');}
                    if(!fflush($output)||!fsync($output)){throw new \RuntimeException('Export stage flush failed.');}
                }finally{fclose($input);if(is_resource($output)){fclose($output);}}
                $contribution=$metadata($source);$check();
                if(!is_array($contribution)||!is_array($contribution['values']??null)||count($contribution['values'])>64
                    ||!is_array($contribution['required']??null)||count($contribution['required'])>128){throw new \LogicException('Invalid export contribution.');}
                $required=[...$required,...$contribution['required']];
                $files[]=['entry_id'=>$source->entryId,'revision'=>$source->revision,'archive_path'=>self::archivePath($source),'original_name'=>$source->name,
                    'size_bytes'=>$source->sizeBytes,'sha256'=>$source->sha256,'metadata'=>$contribution['values']];
                $stage[]=$path;
            }
            $manifest=json_encode(['format'=>'samla-export','version'=>1,'files'=>$files],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            if(strlen($manifest)>ExportConfig::MANIFEST_MAX_BYTES){throw new \LengthException('Export manifest limit.');}
            $zip=new \ZipArchive();
            $this->native(function()use($zip,$work,$deadline,&$opened,$files,$stage,$manifest):void{
                if($zip->open($work.'/package.zip',\ZipArchive::CREATE|\ZipArchive::EXCL)!==true){throw new \RuntimeException('ZIP creation failed.');}$opened=true;
                if(!$zip->registerCancelCallback(fn():int=>$this->now()>=$deadline?1:0)){throw new \RuntimeException('ZIP cancellation unavailable.');}
                foreach($files as$index=>$file){
                    if(!$zip->addFile($stage[$index],$file['archive_path'],0,\ZipArchive::LENGTH_TO_END,\ZipArchive::FL_ENC_UTF_8|\ZipArchive::FL_OPEN_FILE_NOW)
                        ||!$zip->setCompressionName($file['archive_path'],\ZipArchive::CM_STORE)||!$zip->setMtimeName($file['archive_path'],315532800)
                        ||!$zip->setExternalAttributesName($file['archive_path'],\ZipArchive::OPSYS_UNIX,0100600<<16)){throw new \RuntimeException('ZIP entry failed.');}
                }
                if(!$zip->addFromString('manifest.json',$manifest,\ZipArchive::FL_ENC_UTF_8)||!$zip->setCompressionName('manifest.json',\ZipArchive::CM_STORE)
                    ||!$zip->setMtimeName('manifest.json',315532800)||!$zip->setExternalAttributesName('manifest.json',\ZipArchive::OPSYS_UNIX,0100600<<16)){throw new \RuntimeException('ZIP manifest failed.');}
                $success=$zip->close();$opened=false;if(!$success){throw new \RuntimeException('ZIP finalization failed.');}
            });
            $check();$size=filesize($work.'/package.zip');
            if($size===false||$size>$total+ExportConfig::MANIFEST_MAX_BYTES+count($files)*1024){throw new \LengthException('Export archive limit.');}
            $stream=fopen($work.'/package.zip','rb');if($stream===false){throw new \RuntimeException('Export verification failed.');}
            try{$hash=hash_init('sha256');while(!feof($stream)){$check();$chunk=fread($stream,65536);if($chunk===false||($chunk===''&&!feof($stream))){throw new \RuntimeException('Export verification failed.');}hash_update($hash,$chunk);}$sha=hash_final($hash);}finally{fclose($stream);}
            if(!chmod($work.'/package.zip',0600)||!rename($work.'/package.zip',$this->root.'/'.$id.'.zip')){throw new \RuntimeException('Export publication failed.');}
            $required=array_values(array_unique($required));sort($required,SORT_STRING);
            return ['size'=>$size,'sha256'=>$sha,'required'=>$required,'manifest'=>$manifest];
        }finally{
            if($opened&&$zip!==null){try{$this->native(static function()use($zip):void{$zip->unchangeAll();$zip->close();});}catch(\Throwable){}}
            unset($zip);$this->cleanWork($work);
        }
    }
    private function native(\Closure $operation):void
    {set_error_handler(static function():never{throw new \RuntimeException('Native ZIP operation failed.');});try{$operation();}finally{restore_error_handler();}}
    private function cleanWork(string $work):void
    {
        if(is_link($work)){throw new StorageUnavailable('Invalid export work directory.');}
        if(!is_dir($work)){return;}
        foreach(new \DirectoryIterator($work)as$file){
            if($file->isDot()){continue;}$name=$file->getFilename();
            if($file->isLink()||!$file->isFile()){
                throw new StorageUnavailable('Unexpected export staging object.');
            }
            if(!unlink($file->getPathname())){throw new StorageUnavailable('Export staging cleanup failed.');}
        }
        if(!rmdir($work)){throw new StorageUnavailable('Export staging cleanup failed.');}
    }
    public function discard(string $id):void
    {
        $this->requireLock($id);$path=$this->root.'/'.$id.'.zip';
        if(is_link($path)){throw new StorageUnavailable('Invalid export archive.');}
        if(is_file($path)&&!unlink($path)){throw new StorageUnavailable('Export cleanup failed.');}
        $this->cleanWork($this->root.'/'.$id.'.work');
    }
    /** Call only after the immutable job row has been removed; IDs are never reused. */
    public function retire(string $id):void
    {
        $this->requireLock($id);
        if(file_exists($this->root.'/'.$id.'.zip')||file_exists($this->root.'/'.$id.'.work')){throw new \LogicException('Export artifacts must be discarded first.');}
        $path=$this->root.'/'.$id.'.lock';if(is_link($path)||!unlink($path)){throw new StorageUnavailable('Export lock cleanup failed.');}
    }
    /** Read only an authenticated job's expected immutable derived bytes. @return resource */
    public function open(string $id,int $size)
    {
        $this->id($id);$path=$this->root.'/'.$id.'.zip';
        if(is_link($this->root)||is_link($path)||!is_file($path)){throw new StorageUnavailable('Export unavailable.');}
        $stream=@fopen($path,'rb');if($stream===false){throw new StorageUnavailable('Export unavailable.');}
        $stat=fstat($stream);if($stat===false||($stat['mode']&0170000)!==0100000||$stat['size']!==$size){fclose($stream);throw new StorageUnavailable('Export integrity mismatch.');}
        return $stream;
    }
}
