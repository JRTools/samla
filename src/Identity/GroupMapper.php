<?php
declare(strict_types=1);

namespace Samla\Core\Identity;

final class GroupMapper
{
    /**
     * Must only be called after OIDC signature/issuer/audience validation.
     * Inputs belong to exactly one configured issuer. This is not token validation.
     * @param list<GroupMapping> $mappings @param list<string> $manualGroups
     * @return list<DerivedMembership>
     */
    public function mapVerifiedClaim(mixed $claim, array $mappings, array $manualGroups = []): array
    {
        if (!is_array($claim) || !array_is_list($claim) || count($claim) > 1000) {
            throw new \InvalidArgumentException('Expected a bounded list of group IDs.');
        }
        $external = [];
        foreach ($claim as $value) {
            if (!is_string($value) || $value === '' || strlen($value) > 256) {
                throw new \InvalidArgumentException('Invalid external group ID.');
            }
            // Prefix keys so PHP does not coerce numeric string identifiers.
            $external['g:' . $value] = true;
        }
        $derived = [];
        foreach ($mappings as $mapping) {
            if (!$mapping instanceof GroupMapping) { throw new \InvalidArgumentException('Invalid mapping.'); }
            if (isset($external['g:' . $mapping->externalId])) {
                $key = 'g:' . $mapping->internalId;
                $derived[$key] ??= ['id' => $mapping->internalId, 'sources' => [], 'manual' => false];
                $derived[$key]['sources']['g:' . $mapping->externalId] = $mapping->externalId;
            }
        }
        foreach ($manualGroups as $id) {
            if (!is_string($id) || $id === '') { throw new \InvalidArgumentException('Invalid manual group ID.'); }
            $key = 'g:' . $id;
            $derived[$key] ??= ['id' => $id, 'sources' => [], 'manual' => false];
            $derived[$key]['manual'] = true;
        }
        ksort($derived, SORT_STRING);
        $result = [];
        foreach ($derived as $entry) {
            $sources = array_values($entry['sources']);
            sort($sources, SORT_STRING);
            $result[] = new DerivedMembership($entry['id'], $sources, $entry['manual']);
        }
        return $result;
    }
}
