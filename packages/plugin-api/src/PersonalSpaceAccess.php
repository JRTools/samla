<?php
declare(strict_types=1);
namespace Samla\PluginApi;
/** Trusted composition hook inside the audited self-provisioning transaction; no standalone administration endpoint. */
interface PersonalSpaceAccess
{
    public function provisionInsideTransaction(PersonalSpaceChange $change):void;
}
