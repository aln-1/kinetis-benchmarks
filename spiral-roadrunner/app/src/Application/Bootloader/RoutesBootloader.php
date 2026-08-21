<?php

declare(strict_types=1);

namespace App\Application\Bootloader;

use Cycle\Database\DatabaseInterface;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Bootloader\Http\RoutesBootloader as BaseRoutesBootloader;
use Spiral\Router\Loader\Configurator\RoutingConfigurator;

/**
 * The same six TechEmpower-methodology routes every other target in this
 * suite implements — see kinetis/src/Http/* or slim/src/routes.php for
 * the reference contract. Closures rather than controller classes, the
 * same minimal-target style slim/codeigniter already use; DatabaseInterface
 * is resolved once here (framework boot-time DI on defineRoutes() itself
 * takes no arguments, so it's pulled from DatabaseConfigBootloader's own
 * singleton binding via the constructor instead) and captured per closure.
 */
final class RoutesBootloader extends BaseRoutesBootloader
{
    public function __construct(
        private readonly DatabaseInterface $database,
    ) {}

    private function clampedQueryCount(ServerRequestInterface $request): int
    {
        $raw = $request->getQueryParams()['queries'] ?? null;

        if ($raw === null || !is_numeric($raw)) {
            return 1;
        }

        $count = (int) $raw;

        return max(1, min(500, $count));
    }

    private function json(ResponseInterface $response, mixed $data): ResponseInterface
    {
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withBody(Stream::create(json_encode($data, JSON_THROW_ON_ERROR)));
    }

    #[\Override]
    protected function globalMiddleware(): array
    {
        return [];
    }

    #[\Override]
    protected function middlewareGroups(): array
    {
        return [];
    }

    #[\Override]
    protected function defineRoutes(RoutingConfigurator $routes): void
    {
        $database = $this->database;

        $routes->add(name: 'json', pattern: '/json')
            ->methods('GET')
            ->callable(fn (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
                => $this->json($response, ['message' => 'Hello, World!']));

        $routes->add(name: 'plaintext', pattern: '/plaintext')
            ->methods('GET')
            ->callable(fn (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
                => $response->withHeader('Content-Type', 'text/plain')->withBody(Stream::create('Hello, World!')));

        $routes->add(name: 'db', pattern: '/db')
            ->methods('GET')
            ->callable(function (ServerRequestInterface $request, ResponseInterface $response) use ($database): ResponseInterface {
                $row = $database->query('SELECT id, randomNumber FROM world WHERE id = ?', [random_int(1, 10000)])->fetch();

                return $this->json($response, [
                    'id' => (int) $row['id'],
                    'randomNumber' => (int) $row['randomNumber'],
                ]);
            });

        $routes->add(name: 'queries', pattern: '/queries')
            ->methods('GET')
            ->callable(function (ServerRequestInterface $request, ResponseInterface $response) use ($database): ResponseInterface {
                $count = $this->clampedQueryCount($request);
                $results = [];

                for ($i = 0; $i < $count; $i++) {
                    $row = $database->query('SELECT id, randomNumber FROM world WHERE id = ?', [random_int(1, 10000)])->fetch();
                    $results[] = ['id' => (int) $row['id'], 'randomNumber' => (int) $row['randomNumber']];
                }

                return $this->json($response, $results);
            });

        $routes->add(name: 'updates', pattern: '/updates')
            ->methods('GET')
            ->callable(function (ServerRequestInterface $request, ResponseInterface $response) use ($database): ResponseInterface {
                $count = $this->clampedQueryCount($request);
                $results = [];

                for ($i = 0; $i < $count; $i++) {
                    $row = $database->query('SELECT id, randomNumber FROM world WHERE id = ?', [random_int(1, 10000)])->fetch();
                    $id = (int) $row['id'];
                    $newRandomNumber = random_int(1, 10000);

                    $database->execute('UPDATE world SET randomNumber = ? WHERE id = ?', [$newRandomNumber, $id]);

                    $results[] = ['id' => $id, 'randomNumber' => $newRandomNumber];
                }

                return $this->json($response, $results);
            });

        $routes->add(name: 'fortunes', pattern: '/fortunes')
            ->methods('GET')
            ->callable(function (ServerRequestInterface $request, ResponseInterface $response) use ($database): ResponseInterface {
                $fortunes = $database->query('SELECT id, message FROM fortune')->fetchAll();
                $fortunes[] = ['id' => 0, 'message' => 'Additional fortune added at request time.'];

                usort($fortunes, static fn (array $a, array $b): int => $a['message'] <=> $b['message']);

                $html = "<!DOCTYPE html>\n<html>\n<head><title>Fortunes</title></head>\n<body>\n<table>\n<tr><th>id</th><th>message</th></tr>\n";

                foreach ($fortunes as $fortune) {
                    $html .= '<tr><td>' . (int) $fortune['id'] . '</td><td>'
                        . htmlspecialchars((string) $fortune['message'], ENT_QUOTES, 'UTF-8') . "</td></tr>\n";
                }

                $html .= "</table>\n</body>\n</html>\n";

                return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withBody(Stream::create($html));
            });
    }
}
