<?php
declare(strict_types=1);

namespace Samla\Core\Database;

final class Transaction
{
    public static function run(\PDO $pdo, \Closure $operation): mixed
    {
        if ($pdo->inTransaction()) { throw new \LogicException('Nested transactions are not supported.'); }
        $pdo->beginTransaction();
        try {
            $result = $operation();
            $pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        }
    }
}
