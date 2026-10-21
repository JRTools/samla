<?php
declare(strict_types=1);

namespace Samla\Tests\Unit;

use Samla\PluginApi\{MutationContext, Scope};
use PHPUnit\Framework\TestCase;

final class MutationContextTest extends TestCase
{
    public function testContextCannotBeMutatedByAValidator(): void
    {
        $context = new MutationContext(str_repeat('a', 32), 'admin', 'rbac.assignment.grant', Scope::Space, 'media', ['group' => 'readers']);
        $this->expectException(\Error::class);
        $context->targets['group'] = 'managers';
    }
    public function testContextRejectsFreeTextAndOversizedTargets(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MutationContext(str_repeat('a', 32), 'admin', 'rbac.assignment.grant', Scope::Space, 'media', ['secret' => 'not a resource identifier']);
    }
}
