<?php
declare(strict_types=1);
namespace Samla\PluginApi;
/** Bounded immutable bytes supplied by trusted storage, never a request path or URL. */
final readonly class MediaSource
{
    public const MAX_BYTES=16777216;
    public function __construct(public string $bytes,public string $sha256)
    {if(strlen($bytes)>self::MAX_BYTES||!preg_match('/^[a-f0-9]{64}$/D',$sha256)||!hash_equals($sha256,hash('sha256',$bytes))){throw new \InvalidArgumentException('Invalid media source.');}}
    /** @return resource Fresh private stream for each provider. */
    public function open()
    {$s=fopen('php://temp','w+b');if($s===false){throw new \RuntimeException();}if(fwrite($s,$this->bytes)!==strlen($this->bytes)){fclose($s);throw new \RuntimeException();}rewind($s);return $s;}
}
