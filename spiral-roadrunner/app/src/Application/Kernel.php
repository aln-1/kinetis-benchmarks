<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Bootloader\DatabaseConfigBootloader;
use App\Application\Bootloader\RoutesBootloader;
use Spiral\Bootloader\Http\HttpBootloader;
use Spiral\Bootloader\Http\RouterBootloader;
use Spiral\Boot\Bootloader\CoreBootloader;
use Spiral\Nyholm\Bootloader\NyholmBootloader;
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;

final class Kernel extends \Spiral\Framework\Kernel
{
    #[\Override]
    public function defineSystemBootloaders(): array
    {
        return [
            CoreBootloader::class,
        ];
    }

    #[\Override]
    public function defineBootloaders(): array
    {
        return [
            RoadRunnerBridge\LoggerBootloader::class,
            RoadRunnerBridge\HttpBootloader::class,

            HttpBootloader::class,
            RouterBootloader::class,
            NyholmBootloader::class,

            DatabaseConfigBootloader::class,
            RoutesBootloader::class,
        ];
    }

    #[\Override]
    public function defineAppBootloaders(): array
    {
        return [];
    }
}
