<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Reuses the explicit field type, sensitivity, read permission and export opt-in contract. */
final readonly class ExportMetadataValue
{
    public function __construct(public MediaField $field,public string|int|null $value)
    {
        if(($value!==null&&($field->type==='integer'?!is_int($value):!is_string($value)))
            ||(is_string($value)&&(strlen($value)>1024||!mb_check_encoding($value,'UTF-8')))){
            throw new \InvalidArgumentException('Invalid export metadata value.');
        }
    }
}
