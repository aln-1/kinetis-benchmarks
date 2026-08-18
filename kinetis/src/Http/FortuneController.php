<?php

declare(strict_types=1);

namespace App\Http;

use App\Dto\FortuneRow;
use App\Repositories\FortuneRepository;
use Kinetis\Http\Attributes\Get;
use Kinetis\Http\Responses\HtmlResponse;
use League\Plates\Engine;
use Psr\Http\Message\ResponseInterface;

final readonly class FortuneController
{
    public function __construct(
        private FortuneRepository $fortunes,
    ) {}

    #[Get('/fortunes')]
    public function fortunes(): ResponseInterface
    {
        $fortunes = $this->fortunes->all();
        $fortunes[] = new FortuneRow(0, 'Additional fortune added at request time.');

        usort($fortunes, static fn (FortuneRow $a, FortuneRow $b): int => $a->message <=> $b->message);

        $engine = new Engine(dirname(__DIR__, 2) . '/resources/views');

        return HtmlResponse::create($engine->render('fortunes', ['fortunes' => $fortunes]));
    }
}
