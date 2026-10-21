<?php
declare(strict_types=1);

namespace Samla\Tests\Unit;

use Samla\Core\Identity\{GroupMapper, GroupMapping};
use PHPUnit\Framework\TestCase;

final class GroupMapperTest extends TestCase
{
    public function testOneExternalGroupMapsToSeveralInternalGroups(): void
    {
        $result = (new GroupMapper())->mapVerifiedClaim(['board'], [new GroupMapping('board', 'documents'), new GroupMapping('board', 'media')]);
        self::assertSame(['documents', 'media'], array_column($result, 'internalId'));
    }
    public function testRemovingOneOfSeveralSourcesPreservesMembership(): void
    {
        $mappings = [new GroupMapping('board', 'media'), new GroupMapping('press', 'media')];
        $mapper = new GroupMapper();
        self::assertSame(['board', 'press'], $mapper->mapVerifiedClaim(['board', 'press'], $mappings)[0]->externalSources);
        self::assertSame(['press'], $mapper->mapVerifiedClaim(['press'], $mappings)[0]->externalSources);
        self::assertSame([], $mapper->mapVerifiedClaim([], $mappings));
    }
    public function testManualMembershipRemainsAfterAutomaticSourceDisappears(): void
    {
        $result = (new GroupMapper())->mapVerifiedClaim([], [new GroupMapping('board', 'media')], ['media']);
        self::assertTrue($result[0]->manual);
        self::assertSame([], $result[0]->externalSources);
    }
    public function testManualAndAutomaticOriginsRemainDistinguishable(): void
    {
        $result = (new GroupMapper())->mapVerifiedClaim(['board'], [new GroupMapping('board', 'media')], ['media']);
        self::assertTrue($result[0]->manual);
        self::assertSame(['board'], $result[0]->externalSources);
    }
    public function testComparisonIsExactAndUnknownGroupsGrantNothing(): void
    {
        $result = (new GroupMapper())->mapVerifiedClaim(['Board', 'unknown'], [new GroupMapping('board', 'media')]);
        self::assertSame([], $result);
    }
    public function testDuplicateSourcesAreDeduplicated(): void
    {
        $mapping = new GroupMapping('1', '42');
        $result = (new GroupMapper())->mapVerifiedClaim(['1', '1'], [$mapping, $mapping]);
        self::assertSame('42', $result[0]->internalId);
        self::assertSame(['1'], $result[0]->externalSources);
    }
    public function testMalformedClaimsAreRejected(): void
    {
        foreach ([null, 'board', ['groups' => 'board'], [1], [''], [new \stdClass()], array_fill(0, 1001, 'board'), [str_repeat('a', 257)]] as $claim) {
            try {
                (new GroupMapper())->mapVerifiedClaim($claim, []);
                self::fail('Malformed claim accepted');
            } catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
    }
}
