<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
ini_set('display_errors','0');
require dirname(__DIR__).'/vendor/autoload.php';
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\ConnectionFactory;
try {
    $args=array_slice($argv,1);$max=50;$statusOnly=false;
    if ($args===['--status']) {$statusOnly=true;}
    elseif($args!==[]) {
        if(count($args)!==1 || !preg_match('/^--max-events=([1-9][0-9]?)$/D',$args[0],$m) || (int)$m[1]>50){throw new \InvalidArgumentException();}
        $max=(int)$m[1];
    }
    $config=AppConfig::fromGlobals();if($config->maintenance){throw new \RuntimeException();}
    $worker=(require dirname(__DIR__).'/bootstrap/worker.php')((new ConnectionFactory())->connect($config->database),$config);
    $result=$statusOnly?['queue'=>$worker->status()]:$worker->run($max)+['queue'=>$worker->status()];
    fwrite(STDOUT,json_encode($result,JSON_THROW_ON_ERROR)."\n");
    exit(($result['errors']??0)>0?2:0);
}catch(\Throwable $error){fwrite(STDERR,'Worker unavailable: '.get_class($error).". Check arguments, maintenance and migrations.\n");exit(1);}
