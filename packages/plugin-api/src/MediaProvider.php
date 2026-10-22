<?php
declare(strict_types=1);
namespace Samla\PluginApi;
interface MediaProvider
{
    public function id():string;
    /** Stable processing version; independent of the PHP version of CLI versus FPM. */
    public function version():string;
    public function extract(MediaSource $source):MediaResult;
}
