<?php
declare(strict_types=1);

namespace Samla\PluginApi;

enum Decision: string { case Permit = 'permit'; case Deny = 'deny'; case NotApplicable = 'not_applicable'; }
