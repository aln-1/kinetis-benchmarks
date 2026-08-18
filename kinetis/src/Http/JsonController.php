<?php

declare(strict_types=1);

namespace App\Http;

use Kinetis\Http\Attributes\Get;

final readonly class JsonController
{
    #[Get('/json')]
    public function json(): array
    {
        return ['message' => 'Hello, World!'];
    }
}
