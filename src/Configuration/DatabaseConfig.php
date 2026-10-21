<?php
declare(strict_types=1);

namespace Samla\Core\Configuration;

final readonly class DatabaseConfig
{
    public function __construct(
        public string $engine,
        public string $host,
        public int $port,
        public string $database,
        public string $user,
        #[\SensitiveParameter] private string $password,
        public ?string $socket = null,
    ) {
        if (!in_array($engine, ['mysql', 'mariadb'], true)
            || !preg_match('/^[a-zA-Z0-9_.:\[\]-]+$/D', $host)
            || $port < 1 || $port > 65535
            || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/D', $database)
            || $user === '' || str_contains($user, "\0")
            || ($socket !== null && (!str_starts_with($socket, '/') || preg_match('/[;\x00-\x1f]/', $socket)))) {
            throw new \InvalidArgumentException('Invalid database configuration.');
        }
    }
    public function dsn(): string
    {
        $address = $this->socket !== null ? 'unix_socket=' . $this->socket : 'host=' . $this->host . ';port=' . $this->port;
        return 'mysql:' . $address . ';dbname=' . $this->database . ';charset=utf8mb4';
    }
    public function password(): string { return $this->password; }
    public function __debugInfo(): array { return ['engine' => $this->engine, 'database' => $this->database, 'credentials' => '[redacted]']; }
}
