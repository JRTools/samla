<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Immutable, explicit permission bundle, independent of an authorization provider's storage. */
final readonly class AccessProfile
{
    public array $permissions;
    public function __construct(public string $id, public string $label, public string $description,
        public Scope $scope, array $permissions)
    {
        if (!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $id) || $label === '' || strlen($label) > 200
            || strlen($description) > 2000 || !mb_check_encoding($label . $description, 'UTF-8')
            || $permissions === [] || count($permissions) > 64) { throw new \InvalidArgumentException('Invalid access profile.'); }
        $normalized=[];
        foreach ($permissions as $permission) {
            if (!is_string($permission) || !preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/D', $permission)) {
                throw new \InvalidArgumentException('Invalid profile permission.');
            }
            $normalized[]=$permission;
        }
        $permissions = array_values(array_unique($normalized)); sort($permissions, SORT_STRING);
        $this->permissions = $permissions;
    }
}
