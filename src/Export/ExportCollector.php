<?php
declare(strict_types=1);
namespace Samla\Core\Export;
use Samla\Core\Database\Transaction;

/** Trusted derived-artifact housekeeping. Original content and business retention are never touched. */
final readonly class ExportCollector
{
    public function __construct(private \PDO $pdo,private ZipArchiveStore $store){}
    public function collect(?string $owner=null):int
    {
        $q=$this->pdo->prepare("SELECT id FROM export_jobs WHERE (expires_at<=? OR status='cancelled')".($owner===null?'':' AND owner_id=?').' ORDER BY expires_at,id LIMIT 50');
        $q->execute($owner===null?[time()]:[time(),$owner]);$removed=0;
        foreach($q->fetchAll(\PDO::FETCH_COLUMN)as$id){
            $result=$this->store->locked($id,function()use($id,$owner):bool{
                $done=Transaction::run($this->pdo,function()use($id,$owner):bool{
                if($this->pdo->query('SELECT id FROM administration_lock WHERE id=1 FOR UPDATE')->fetchColumn()===false){throw new \RuntimeException('Administration lock missing.');}
                $q=$this->pdo->prepare("SELECT owner_id FROM export_jobs WHERE id=? AND (expires_at<=? OR status='cancelled')");$q->execute([$id,time()]);$actor=$q->fetchColumn();
                if($actor===false||($owner!==null&&$actor!==$owner)){return false;}
                $this->store->discard($id);$q=$this->pdo->prepare('DELETE FROM export_jobs WHERE id=?');$q->execute([$id]);return true;
                });
                if($done){$this->store->retire($id);}return $done;
            });
            if($result===true){++$removed;}
        }
        return $removed;
    }
}
