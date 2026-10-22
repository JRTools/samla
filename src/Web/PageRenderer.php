<?php
declare(strict_types=1);
namespace Samla\Core\Web;

final class PageRenderer
{
    public static function escape(string|int $value): string
    { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    public function render(string $page, array $data): string
    {
        if (!in_array($page, ['home','users','groups','error','login','account','spaces','files','file-detail','trash','app-passwords','versions','text-editor','file-search'], true)) { throw new \InvalidArgumentException('Unknown template.'); }
        ob_start();
        try { require __DIR__ . '/../../templates/layout.php'; return ob_get_clean(); }
        catch (\Throwable $error) { ob_end_clean(); throw $error; }
    }
}
