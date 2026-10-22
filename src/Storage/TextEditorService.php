<?php
declare(strict_types=1);
namespace Samla\Core\Storage;

use Samla\PluginApi\AccessDenied;

final readonly class TextEditorService
{
    public function __construct(private FileService $files) {}
    public function open(string $actor, string $space, string $entry): array
    {
        $row = $this->files->details($actor, $space, $entry);
        if ($row['kind'] !== 'file' || !TextDocument::supports($row['name'])) { throw new \InvalidArgumentException('Unsupported editor format.'); }
        $download = $this->files->download($actor, $space, $entry, (int) $row['revision']);
        try {
            if ((int) $download['row']['size_bytes'] > TextDocument::MAX_BYTES) { throw new \LengthException('Text editor capacity exceeded.'); }
            $text = stream_get_contents($download['stream'], TextDocument::MAX_BYTES + 1);
            if ($text === false || strlen($text) !== (int) $download['row']['size_bytes']) { throw new \RuntimeException('Incomplete text content.'); }
            return ['entry' => $row, 'text' => TextDocument::normalize($text), 'can_update' => $this->files->allowed($actor, 'files.update', $space)];
        } finally { fclose($download['stream']); }
    }
    public function save(string $actor, string $space, string $entry, int $revision, string $text): void
    {
        if (!$this->files->allowed($actor, 'files.update', $space)) { throw new AccessDenied(); }
        $text = TextDocument::normalize($text);
        // Bind supported name, read access and source content to the submitted revision.
        $document = $this->open($actor, $space, $entry);
        if ((int) $document['entry']['revision'] !== $revision) { throw new \Samla\PluginApi\MutationConflict(); }
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) { throw new \RuntimeException('Editor stream unavailable.'); }
        try {
            if (fwrite($stream, $text) !== strlen($text)) { throw new \RuntimeException('Incomplete editor stream.'); }
            rewind($stream);
            $this->files->replace($actor, $space, $entry, $revision, $stream, requireRead: true);
        } finally { fclose($stream); }
    }
}
