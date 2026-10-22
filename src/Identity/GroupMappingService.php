<?php
declare(strict_types=1);

namespace Samla\Core\Identity;

use Samla\PluginApi\{AccessDenied, AuthorizationChecker, AuthorizationRequest, Decision, GroupMappingChange, GroupMappingPolicy, MutationContext, MutationRunner, ResourceDirectory, Scope};

/** Public mapping-edge administration, including existing verified snapshot projections. */
final readonly class GroupMappingService
{
    public function __construct(private PdoIdentityRepository $identities, private AuthorizationChecker $authorization,
        private GroupMappingPolicy $policy, private ResourceDirectory $resources, private MutationRunner $mutations) {}

    public function grant(string $actorId, string $issuer, string $externalId, string $groupId, ?bool $expectedPresent = null): bool
    { return $this->change($actorId, new GroupMappingChange($issuer, $externalId, $groupId, true, $expectedPresent)); }
    public function revoke(string $actorId, string $issuer, string $externalId, string $groupId, ?bool $expectedPresent = null): bool
    { return $this->change($actorId, new GroupMappingChange($issuer, $externalId, $groupId, false, $expectedPresent)); }

    private function change(string $actorId, GroupMappingChange $change): bool
    {
        $context = new MutationContext(bin2hex(random_bytes(16)), $actorId,
            $change->grant ? 'identity.mapping.grant' : 'identity.mapping.revoke', Scope::Global, null,
            ['group' => $change->groupId, 'mapping' => $change->mappingId()], groupMapping: $change);
        $continuity = new AdministrationAccessGuard($this->identities, $this->authorization);
        $protected = null;
        $guard = function () use ($actorId, $change, $continuity, &$protected): void {
            if(str_starts_with($change->groupId,\Samla\PluginApi\PersonalSpaceChange::GROUP_PREFIX)){throw new AccessDenied();}
            if ($this->authorization->decide(new AuthorizationRequest($actorId,
                    'identity.groups.mappings.manage', Scope::Global))->decision !== Decision::Permit
                || !$this->resources->hasGroup($change->groupId) || !$this->policy->allows($actorId, $change)) {
                throw new AccessDenied();
            }
            $protected ??= $continuity->snapshot();
        };
        return $this->mutations->run($context, $guard, function () use ($change, $continuity, &$protected): bool {
            $changed = $this->identities->changeGroupMappingInsideTransaction($change);
            $continuity->assertPreserved($protected);
            return $changed;
        });
    }
}
