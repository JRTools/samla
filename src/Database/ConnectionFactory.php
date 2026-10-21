<?php
declare(strict_types=1);

namespace Samla\Core\Database;

use Samla\Core\Configuration\DatabaseConfig;

final class ConnectionFactory
{
    public function connect(DatabaseConfig $config): \PDO
    {
        if (!extension_loaded('pdo_mysql')) { throw new \RuntimeException('pdo_mysql is required.'); }
        $pdo = new \PDO($config->dsn(), $config->user, $config->password(), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_PERSISTENT => false,
            \Pdo\Mysql::ATTR_MULTI_STATEMENTS => false,
            \Pdo\Mysql::ATTR_LOCAL_INFILE => false,
        ]);
        ServerVersion::assertSupported($config->engine, (string) $pdo->query('SELECT VERSION()')->fetchColumn());
        $pdo->exec("SET SESSION time_zone = '+00:00'");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        return $pdo;
    }
}
