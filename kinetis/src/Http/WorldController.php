<?php

declare(strict_types=1);

namespace App\Http;

use App\Dto\WorldRow;
use App\Http\Support\QueryCount;
use App\Repositories\WorldRepository;
use Kinetis\Http\Attributes\Get;
use Kinetis\Http\Attributes\Query;

final readonly class WorldController
{
    public function __construct(
        private WorldRepository $world,
    ) {}

    #[Get('/db')]
    public function db(): WorldRow
    {
        return $this->world->randomRow();
    }

    #[Get('/queries')]
    public function queries(#[Query] ?string $queries = null): array
    {
        return $this->world->randomRows(QueryCount::clamp($queries));
    }

    #[Get('/updates')]
    public function updates(#[Query] ?string $queries = null): array
    {
        return $this->world->updateRandomRows(QueryCount::clamp($queries));
    }
}
