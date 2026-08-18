<?php

declare(strict_types=1);

namespace App\Http;

use Kinetis\Http\Attributes\Get;
use Kinetis\Http\Responses\PlainTextResponse;
use Psr\Http\Message\ResponseInterface;

final readonly class PlaintextController
{
    #[Get('/plaintext')]
    public function plaintext(): ResponseInterface
    {
        return PlainTextResponse::create('Hello, World!');
    }
}
