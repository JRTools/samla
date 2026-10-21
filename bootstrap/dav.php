<?php
declare(strict_types=1);
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Identity\{AppPasswordService, IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService, LocalContentStore, StoragePlugin};
use Samla\Core\WebDav\{CredentialAuthorization, Kernel, RequestIdentity};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin};

return static function (\PDO $pdo, AppConfig $config): Kernel {
    $identity = new PdoIdentityRepository($pdo, $config->groupMaxAgeSeconds);
    $runtime = PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin(new PdoRoleRepository($pdo), $identity)]);
    $mutations = new PdoMutationRunner($pdo, $runtime);
    $passwords = new AppPasswordService($pdo, $identity, $mutations); $requestIdentity = new RequestIdentity();
    $auth = new CredentialAuthorization(new AuthorizationService($runtime, $identity), $passwords, $requestIdentity);
    return new Kernel($passwords, $requestIdentity, new FileService($pdo, $auth, $mutations, new LocalContentStore($config->storageRoot, $config->maxUploadBytes)));
};
