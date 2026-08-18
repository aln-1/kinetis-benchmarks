<?php

declare(strict_types=1);

namespace App\Repositories;

use Kinetis\Persistence\Contract\MysqlLink;
use App\Dto\WorldRow;
use Kinetis\QueryBuilder\Query;

use function Kinetis\Async\concurrently;

final readonly class WorldRepository
{
    public function __construct(
        private MysqlLink $db,
    ) {}

    public function randomRow(): WorldRow
    {
        $id = random_int(1, 10000);

        $row = new Query($this->db)->table('world')->where('id', '=', $id)->first(WorldRow::class);

        return $row;
    }

    public function randomRows(int $n): array
    {
        $tasks = [];

        for ($i = 0; $i < $n; $i++) {
            $tasks[] = fn (): WorldRow => $this->randomRow();
        }

        return concurrently($tasks);
    }

    public function updateRandomRows(int $n): array
    {
        $rows = $this->randomRows($n);
        $tasks = [];

        foreach ($rows as $row) {
            $tasks[] = function () use ($row): WorldRow {
                $newRandomNumber = random_int(1, 10000);

                new Query($this->db)
                    ->table('world')
                    ->where('id', '=', $row->id)
                    ->update(['randomNumber' => $newRandomNumber]);

                return new WorldRow($row->id, $newRandomNumber);
            };
        }

        $updateFanout = (int) (getenv('KINETIS_UPDATE_FANOUT') ?: 0);

        if ($updateFanout <= 0 || $updateFanout >= \count($tasks)) {
            return concurrently($tasks);
        }

        $results = [];

        foreach (array_chunk($tasks, $updateFanout) as $chunk) {
            $batch = concurrently($chunk);
            $results = [...$results, ...$batch];
        }

        return $results;
    }
}
