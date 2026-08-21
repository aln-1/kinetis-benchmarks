<?php

declare(strict_types=1);

namespace App\Application\Bootloader;

use Cycle\Database\Config;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseManager;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Raw SQL access via Cycle's DBAL layer only — no ORM entities, no
 * schema/annotated bootloaders. Same DB_HOST/DB_PORT/DB_NAME/DB_USER/
 * DB_PASSWORD env vars every other target in this benchmark suite reads.
 */
final class DatabaseConfigBootloader extends Bootloader
{
    protected const SINGLETONS = [
        DatabaseManager::class => [self::class, 'createDatabaseManager'],
        DatabaseInterface::class => [self::class, 'getDefaultDatabase'],
    ];

    private function createDatabaseManager(): DatabaseManager
    {
        $config = new Config\DatabaseConfig([
            'databases' => [
                'default' => ['connection' => 'tfbench'],
            ],
            'connections' => [
                'tfbench' => new Config\MySQLDriverConfig(
                    connection: new Config\MySQL\TcpConnectionConfig(
                        database: getenv('DB_NAME') ?: 'tfbench',
                        host: getenv('DB_HOST') ?: '127.0.0.1',
                        port: (int) (getenv('DB_PORT') ?: '3306'),
                        user: getenv('DB_USER') ?: 'tfbench',
                        password: getenv('DB_PASSWORD') ?: 'tfbench',
                    ),
                    queryCache: true,
                ),
            ],
        ]);

        return new DatabaseManager($config);
    }

    private function getDefaultDatabase(DatabaseManager $manager): DatabaseInterface
    {
        return $manager->database('default');
    }
}
