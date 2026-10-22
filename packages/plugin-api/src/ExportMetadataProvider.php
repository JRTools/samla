<?php
declare(strict_types=1);
namespace Samla\PluginApi;

interface ExportMetadataProvider
{
    public function id():string;
    /** Bump on changes to metadata interpretation or field/export policy. */
    public function version():string;
    /** @return list<ExportMetadataValue> At most 64 bounded, explicitly exportable fields. */
    public function metadata(ExportSource $source):array;
}
