<?php

declare(strict_types=1);

namespace App\Repositories;

use Kinetis\Persistence\Contract\MysqlLink;
use App\Dto\FortuneRow;
use Kinetis\QueryBuilder\Query;

final readonly class FortuneRepository
{
    public function __construct(
        private MysqlLink $db,
    ) {}

    public function all(): array
    {
        return new Query($this->db)->table('fortune')->get(FortuneRow::class);
    }
}
