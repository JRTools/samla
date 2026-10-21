<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\PluginApi\{MutationContext, Scope, StorageChange};

final class StorageChangeTest extends TestCase
{
    public function testProposalPreservesMetadataWithoutPhysicalPath(): void
    {
        $proposal = new StorageChange('Älmhult.jpg', '', 4, hash('sha256', 'data'));
        $context = new MutationContext(str_repeat('a', 32), 'alice', 'storage.file.upload', Scope::Space, 'media', ['entry' => str_repeat('b', 32)], storageChange: $proposal);
        self::assertSame($proposal, $context->storageChange); self::assertSame('Älmhult.jpg', $proposal->name);
        self::assertSame(4, $proposal->size); self::assertSame(hash('sha256', 'data'), $proposal->sha256);
    }
    public function testStorageMutationRequiresMatchingTypedProposal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MutationContext(str_repeat('a', 32), 'alice', 'storage.file.upload', Scope::Space, 'media', ['entry' => str_repeat('b', 32)]);
    }
    public function testUnrelatedOperationCannotSmuggleStorageProposal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MutationContext(str_repeat('a', 32), 'alice', 'identity.login', Scope::Global, null, ['user' => 'alice'], storageChange: new StorageChange('file'));
    }
    public function testContentMetadataMustBePairedAndBounded(): void
    {
        foreach ([[4,null], [null,hash('sha256','data')], [-1,hash('sha256','data')], [4,'bad']] as [$size,$hash]) {
            try { new StorageChange('file', '', $size, $hash); self::fail('Malformed content proposal accepted.'); } catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
    }
    public function testNamesAndParentsAreLogicalIdentifiers(): void
    {
        foreach ([['../file',''], ['file','../folder'], ["a\nb",'']] as [$name,$parent]) {
            try { new StorageChange($name, $parent); self::fail('Invalid logical metadata accepted.'); } catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
    }
}
