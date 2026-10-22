<?php
declare(strict_types=1);
namespace Samla\Core\Media;
use Samla\Core\Database\Transaction;
/** Trusted worker housekeeping following an explicitly authorized permanent purge. */
final readonly class MediaCacheCollector
{
    public function __construct(private \PDO $pdo){}
    public function collect():int
    {
        return Transaction::run($this->pdo,function():int{
            if($this->pdo->query('SELECT id FROM administration_lock WHERE id=1 FOR UPDATE')->fetchColumn()===false){throw new \RuntimeException();}
            $q=$this->pdo->query('SELECT DISTINCT m.blob_id FROM media_assets m WHERE NOT EXISTS(SELECT 1 FROM storage_entries e WHERE e.blob_id=m.blob_id) AND NOT EXISTS(SELECT 1 FROM storage_versions v WHERE v.blob_id=m.blob_id) LIMIT 20001');$ids=$q->fetchAll(\PDO::FETCH_COLUMN);
            if(count($ids)>20000){throw new \LengthException('Media cache capacity exceeded.');}
            $removed=0;foreach(array_chunk($ids,100)as$chunk){$q=$this->pdo->prepare('DELETE FROM media_assets WHERE blob_id IN ('.implode(',',array_fill(0,count($chunk),'?')).')');$q->execute($chunk);$removed+=$q->rowCount();}return $removed;
        });
    }
}
