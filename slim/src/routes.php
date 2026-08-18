<?php

declare(strict_types=1);

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

function clampedQueryCount(Request $request): int
{
    $raw = $request->getQueryParams()['queries'] ?? null;

    if ($raw === null || !is_numeric($raw)) {
        return 1;
    }

    $count = (int) $raw;

    if ($count < 1) {
        return 1;
    }

    if ($count > 500) {
        return 500;
    }

    return $count;
}

return function (App $app): void {
    $app->get('/json', function (Request $request, Response $response): Response {
        $response->getBody()->write(json_encode(['message' => 'Hello, World!'], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/plaintext', function (Request $request, Response $response): Response {
        $response->getBody()->write('Hello, World!');

        return $response->withHeader('Content-Type', 'text/plain');
    });

    $app->get('/db', function (Request $request, Response $response): Response {
        $pdo = Database::connection();

        $statement = $pdo->prepare('SELECT id, randomNumber FROM world WHERE id = ?');
        $statement->execute([random_int(1, 10000)]);
        $row = $statement->fetch();

        $response->getBody()->write(json_encode([
            'id' => (int) $row['id'],
            'randomNumber' => (int) $row['randomNumber'],
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/queries', function (Request $request, Response $response): Response {
        $pdo = Database::connection();
        $count = clampedQueryCount($request);

        $statement = $pdo->prepare('SELECT id, randomNumber FROM world WHERE id = ?');

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $statement->execute([random_int(1, 10000)]);
            $row = $statement->fetch();
            $results[] = [
                'id' => (int) $row['id'],
                'randomNumber' => (int) $row['randomNumber'],
            ];
        }

        $response->getBody()->write(json_encode($results, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/updates', function (Request $request, Response $response): Response {
        $pdo = Database::connection();
        $count = clampedQueryCount($request);

        $selectStatement = $pdo->prepare('SELECT id, randomNumber FROM world WHERE id = ?');
        $updateStatement = $pdo->prepare('UPDATE world SET randomNumber = ? WHERE id = ?');

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $selectStatement->execute([random_int(1, 10000)]);
            $row = $selectStatement->fetch();

            $id = (int) $row['id'];
            $newRandomNumber = random_int(1, 10000);

            $updateStatement->execute([$newRandomNumber, $id]);

            $results[] = [
                'id' => $id,
                'randomNumber' => $newRandomNumber,
            ];
        }

        $response->getBody()->write(json_encode($results, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/fortunes', function (Request $request, Response $response): Response {
        $pdo = Database::connection();

        $statement = $pdo->query('SELECT id, message FROM fortune');
        $fortunes = $statement->fetchAll();

        $fortunes[] = [
            'id' => 0,
            'message' => 'Additional fortune added at request time.',
        ];

        usort($fortunes, static fn (array $a, array $b): int => $a['message'] <=> $b['message']);

        ob_start();
        require __DIR__ . '/../templates/fortunes.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
};
