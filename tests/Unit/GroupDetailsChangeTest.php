<?php
declare(strict_types=1);

namespace Samla\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Samla\PluginApi\{GroupDetailsChange, MutationContext, Scope};

final class GroupDetailsChangeTest extends TestCase
{
    public function testUnicodeNamesAreMeasuredInCharactersWithoutNormalization(): void
    {
        $name = str_repeat('😀', 255);
        $change = new GroupDetailsChange($name, 'Vorstand Älmhult');
        self::assertSame($name, $change->name);
        self::assertSame('Vorstand Älmhult', $change->expectedName);
        self::assertSame('0', (new GroupDetailsChange('0'))->name);
    }
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'space only' => [' '];
        yield 'leading space' => [' Board'];
        yield 'trailing unicode space' => ["Board\u{00A0}"];
        yield 'newline' => ["Board\nPress"];
        yield 'NUL' => ["Board\0"];
        yield 'DEL' => ["Board\x7f"];
        yield 'invalid UTF-8' => ["\xff"];
        yield '256 ASCII characters' => [str_repeat('a', 256)];
        yield '256 Unicode characters' => [str_repeat('😀', 256)];
    }
    #[DataProvider('invalidNames')]
    public function testInvalidProposedNamesAreRejected(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GroupDetailsChange($name);
    }
    public function testInvalidExpectedNameIsRejectedToo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GroupDetailsChange('Board', "Old\nName");
    }
    public function testProposalIsImmutable(): void
    {
        $change = new GroupDetailsChange('Board');
        $this->expectException(\Error::class);
        $change->name = 'Administrators';
    }
    public function testContextAcceptsTheMatchingTypedProposal(): void
    {
        $change = new GroupDetailsChange('Board', 'Readers');
        $context = new MutationContext(str_repeat('a', 32), 'admin', 'identity.group.rename', Scope::Global, null, ['group' => 'readers'], $change);
        self::assertSame($change, $context->groupDetails);
    }
    public static function mismatchedContexts(): iterable
    {
        yield 'missing proposal' => ['identity.group.create', Scope::Global, null, ['group' => 'readers'], null];
        yield 'wrong operation' => ['identity.membership.grant', Scope::Global, null, ['group' => 'readers'], new GroupDetailsChange('Board')];
        yield 'wrong scope' => ['identity.group.create', Scope::Space, 'media', ['group' => 'readers'], new GroupDetailsChange('Board')];
        yield 'wrong targets' => ['identity.group.create', Scope::Global, null, ['user' => 'alice'], new GroupDetailsChange('Board')];
        yield 'creation with expected name' => ['identity.group.create', Scope::Global, null, ['group' => 'readers'], new GroupDetailsChange('Board', 'Readers')];
        yield 'rename without expected name' => ['identity.group.rename', Scope::Global, null, ['group' => 'readers'], new GroupDetailsChange('Board')];
    }
    #[DataProvider('mismatchedContexts')]
    public function testContextRejectsMismatchedProposal(string $operation, Scope $scope, ?string $space, array $targets, ?GroupDetailsChange $proposal): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MutationContext(str_repeat('a', 32), 'admin', $operation, $scope, $space, $targets, $proposal);
    }
}
