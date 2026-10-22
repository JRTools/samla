<?php
declare(strict_types=1);
namespace Samla\PluginApi;
interface FileCatalogReader
{
    /** Current files.list required. At most 10000 visible entries; original reading requires separate rights.
     * @return list<FileCatalogEntry> Ordered by stable entry ID, excluding trash and invisible parent chains.
     */
    public function visibleEntries(string $actorId,string $spaceId):array;
}
