<?php
declare(strict_types=1);
namespace Samla\Core\Web;

final readonly class Request
{
    public function __construct(public string $method, public string $path, public array $query = [], public array $post = [], public array $files = []) {}
    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($path) || strlen($path) > 256) { throw new \InvalidArgumentException('Invalid path.'); }
        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, $_GET, $_POST, $_FILES);
    }
    public function field(string $key, int $limit = 1024): string
    {
        $value = $this->post[$key] ?? null;
        if (!is_string($value) || strlen($value) > $limit) { throw new \InvalidArgumentException('Invalid form input.'); }
        return $value;
    }
    public function after(): ?string
    {
        $after = $this->query['after'] ?? null;
        if ($after !== null && (!is_string($after) || !preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $after))) {
            throw new \InvalidArgumentException('Invalid cursor.');
        }
        return $after;
    }
}
