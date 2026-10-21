<?php
declare(strict_types=1);

namespace Samla\PluginApi;

interface MutationRunner
{
    /** Guard runs before validation and immediately before write. Write returns whether state changed. */
    public function run(MutationContext $context, \Closure $guard, \Closure $write): bool;
}
