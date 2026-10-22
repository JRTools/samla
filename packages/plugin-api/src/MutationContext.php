<?php
declare(strict_types=1);

namespace Samla\PluginApi;

/** Immutable validation/audit context; targets contain identifiers only, never credentials. */
final readonly class MutationContext
{
    /** @param array<string, string> $targets */
    public function __construct(
        public string $id,
        public string $actorId,
        public string $operation,
        public Scope $scope,
        public ?string $spaceId,
        public array $targets,
        public ?GroupDetailsChange $groupDetails = null,
        public ?GroupMappingChange $groupMapping = null,
        public ?StorageChange $storageChange = null,
        public ?StorageLockChange $storageLock = null,
        public ?StoragePurgeChange $storagePurge = null,
        public ?OutboxRetryChange $outboxRetry = null,
    ) {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id) || !preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $actorId)
            || !preg_match('/^[a-z][a-z0-9_.]{1,127}$/D', $operation)
            || ($scope === Scope::Space && ($spaceId === null || !preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $spaceId)))
            || ($scope === Scope::Global && $spaceId !== null) || count($targets) > 8) {
            throw new \InvalidArgumentException('Invalid mutation context.');
        }
        foreach ($targets as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z_]{0,31}$/D', $key)
                || !is_string($value) || !preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $value)) {
                throw new \InvalidArgumentException('Mutation targets must be bounded identifiers.');
            }
        }
        if (($operation==='operations.delivery.retry')!==($outboxRetry!==null)
            || ($outboxRetry!==null && ($scope!==Scope::Global || ($targets['event']??null)!==$outboxRetry->eventId
                || ($targets['subscriber']??null)!==hash('sha256',$outboxRetry->subscriberId)))) {throw new \InvalidArgumentException('Retry proposal must match its targets.');}
        $groupOperation = in_array($operation, ['identity.group.create', 'identity.group.rename'], true);
        $lockOperation = in_array($operation, ['storage.lock.acquire','storage.lock.renew','storage.lock.release','storage.file.create_locked'], true);
        if ($lockOperation !== ($storageLock !== null) || ($lockOperation && ($scope !== Scope::Space || !isset($targets['lock'])
            || (($operation === 'storage.lock.release') !== ($storageLock->seconds === 0))))) {
            throw new \InvalidArgumentException('Lock proposal must match its operation.');
        }
        $storageOperation = in_array($operation, ['storage.folder.create', 'storage.file.upload', 'storage.entry.rename', 'storage.entry.move',
            'storage.file.copy', 'storage.entry.trash', 'storage.entry.restore', 'storage.file.replace', 'storage.version.restore', 'storage.file.create_locked', 'storage.entry.purge'], true);
        if ($storageOperation !== ($storageChange !== null) || ($storageOperation && ($scope !== Scope::Space || !isset($targets['entry'])))) {
            throw new \InvalidArgumentException('Storage proposal must match its operation.');
        }
        if (($operation === 'storage.entry.purge') !== ($storagePurge !== null)
            || ($storagePurge !== null && !isset($storagePurge->entryRevisions[$targets['entry'] ?? '']))) { throw new \InvalidArgumentException('Purge proposal must include its root.'); }
        if ($groupOperation !== ($groupDetails !== null)
            || ($groupOperation && ($scope !== Scope::Global || array_keys($targets) !== ['group']
                || ($operation === 'identity.group.create') !== ($groupDetails->expectedName === null)))) {
            throw new \InvalidArgumentException('Group details must match the group operation and target.');
        }
        $mappingOperation = in_array($operation, ['identity.mapping.grant', 'identity.mapping.revoke'], true);
        if ($mappingOperation !== ($groupMapping !== null)
            || ($mappingOperation && ($scope !== Scope::Global || array_keys($targets) !== ['group', 'mapping']
                || $targets['group'] !== $groupMapping->groupId || $targets['mapping'] !== $groupMapping->mappingId()
                || ($operation === 'identity.mapping.grant') !== $groupMapping->grant))) {
            throw new \InvalidArgumentException('Mapping proposal must match the operation and targets.');
        }
    }
}
