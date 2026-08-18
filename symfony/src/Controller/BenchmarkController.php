<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class BenchmarkController
{
    public function __construct(
        private readonly Connection $db,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/json', name: 'bench_json', methods: ['GET'])]
    public function json(): JsonResponse
    {
        return new JsonResponse(['message' => 'Hello, World!']);
    }

    #[Route('/plaintext', name: 'bench_plaintext', methods: ['GET'])]
    public function plaintext(): Response
    {
        return new Response('Hello, World!', 200, ['Content-Type' => 'text/plain']);
    }

    #[Route('/db', name: 'bench_db', methods: ['GET'])]
    public function db(): JsonResponse
    {
        return new JsonResponse($this->fetchRandomWorld());
    }

    #[Route('/queries', name: 'bench_queries', methods: ['GET'])]
    public function queries(Request $request): JsonResponse
    {
        $count = $this->parseQueryCount($request);

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = $this->fetchRandomWorld();
        }

        return new JsonResponse($rows);
    }

    #[Route('/updates', name: 'bench_updates', methods: ['GET'])]
    public function updates(Request $request): JsonResponse
    {
        $count = $this->parseQueryCount($request);

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $row = $this->fetchRandomWorld();
            $row['randomNumber'] = random_int(1, 10000);

            $this->db->executeStatement(
                'UPDATE world SET randomNumber = ? WHERE id = ?',
                [$row['randomNumber'], $row['id']],
            );

            $rows[] = $row;
        }

        return new JsonResponse($rows);
    }

    #[Route('/fortunes', name: 'bench_fortunes', methods: ['GET'])]
    public function fortunes(): Response
    {
        $fortunes = $this->db->fetchAllAssociative('SELECT id, message FROM fortune');
        $fortunes[] = ['id' => 0, 'message' => 'Additional fortune added at request time.'];

        usort($fortunes, static fn (array $a, array $b): int => $a['message'] <=> $b['message']);

        $html = $this->twig->render('fortunes.html.twig', ['fortunes' => $fortunes]);

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    private function fetchRandomWorld(): array
    {
        $id = random_int(1, 10000);
        $row = $this->db->fetchAssociative('SELECT id, randomNumber FROM world WHERE id = ?', [$id]);

        return [
            'id' => (int) $row['id'],
            'randomNumber' => (int) $row['randomNumber'],
        ];
    }

    private function parseQueryCount(Request $request): int
    {
        $raw = $request->query->get('queries');

        if ($raw === null || !is_numeric($raw)) {
            return 1;
        }

        return max(1, min(500, (int) $raw));
    }
}
