<?php
declare(strict_types=1);

namespace Samla\PluginApi;

interface ChangeValidator
{
    /** Before write, inside the transaction. Throw to veto; no external side effects. */
    public function validate(MutationContext $context): void;
}
