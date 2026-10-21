<?php
declare(strict_types=1);

namespace Samla\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Samla\PluginApi\{GroupMappingChange, MutationContext, Scope};

final class GroupMappingChangeTest extends TestCase
{
    public function testMappingIdentifierFramesOpaqueBytesAndIsIndependentOfOperation(): void
    {
        $grant = new GroupMappingChange('ab', 'c', 'readers', true);
        self::assertNotSame($grant->mappingId(), (new GroupMappingChange('a', 'bc', 'readers', true))->mappingId());
        self::assertSame($grant->mappingId(), (new GroupMappingChange('ab', 'c', 'readers', false))->mappingId());
        self::assertNotSame($grant->mappingId(), (new GroupMappingChange('ab', 'C', 'readers', true))->mappingId());
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', (new GroupMappingChange("issuer\0", "\xff0", '0', true))->mappingId());
    }
    public function testIdentifiersAreImmutable(): void
    {
        $change = new GroupMappingChange('issuer', 'press', 'readers', true);
        $this->expectException(\Error::class); $change->issuer = 'other';
    }
    public static function invalidIdentifiers(): iterable
    {
        yield ['', 'press', 'readers'];
        yield [str_repeat('x', 1025), 'press', 'readers'];
        yield ['issuer', '', 'readers'];
        yield ['issuer', str_repeat('x', 257), 'readers'];
        yield ['issuer', 'press', ''];
        yield ['issuer', 'press', 'a group'];
    }
    #[DataProvider('invalidIdentifiers')]
    public function testMalformedIdentifiersAreRejected(string $issuer, string $external, string $group): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GroupMappingChange($issuer, $external, $group, true);
    }
    public function testBoundaryIdentifiersAndMatchingContextAreAccepted(): void
    {
        $change = new GroupMappingChange(str_repeat('i', 1024), str_repeat('x', 256), str_repeat('g', 64), true);
        $context = new MutationContext(str_repeat('a', 32), 'admin', 'identity.mapping.grant', Scope::Global, null,
            ['group' => $change->groupId, 'mapping' => $change->mappingId()], groupMapping: $change);
        self::assertSame($change, $context->groupMapping);
    }
    public static function mismatchedContexts(): iterable
    {
        $change = new GroupMappingChange('issuer', 'press', 'readers', true);
        $targets = ['group' => 'readers', 'mapping' => $change->mappingId()];
        yield ['identity.mapping.grant', Scope::Global, null, $targets, null];
        yield ['identity.mapping.revoke', Scope::Global, null, $targets, $change];
        yield ['identity.membership.grant', Scope::Global, null, $targets, $change];
        yield ['identity.mapping.grant', Scope::Space, 'media', $targets, $change];
        yield ['identity.mapping.grant', Scope::Global, null, ['group' => 'other', 'mapping' => $change->mappingId()], $change];
        yield ['identity.mapping.grant', Scope::Global, null, ['group' => 'readers', 'mapping' => str_repeat('0', 64)], $change];
    }
    #[DataProvider('mismatchedContexts')]
    public function testContextCannotMislabelMappingProposal(string $operation, Scope $scope, ?string $space, array $targets, ?GroupMappingChange $change): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MutationContext(str_repeat('a', 32), 'admin', $operation, $scope, $space, $targets, groupMapping: $change);
    }
}
