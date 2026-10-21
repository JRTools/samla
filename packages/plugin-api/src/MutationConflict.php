<?php
declare(strict_types=1);

namespace Samla\PluginApi;

/** The supplied expected state no longer matches. Reload before proposing another change. */
final class MutationConflict extends \RuntimeException {}
