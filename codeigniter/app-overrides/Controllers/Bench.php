<?php

namespace App\Controllers;

use Config\Database;

class Bench extends BaseController
{
    public function index()
    {
        return $this->response->setBody('OK');
    }

    public function json()
    {
        return $this->response
            ->setContentType('application/json')
            ->setBody(json_encode(['message' => 'Hello, World!']));
    }

    public function plaintext()
    {
        return $this->response
            ->setContentType('text/plain')
            ->setBody('Hello, World!');
    }

    public function db()
    {
        $db  = Database::connect();
        $row = $this->fetchRandomWorldRow($db);

        return $this->response
            ->setContentType('application/json')
            ->setBody(json_encode($row));
    }

    public function queries()
    {
        $n  = $this->clampQueries($this->request->getGet('queries'));
        $db = Database::connect();

        $results = [];
        for ($i = 0; $i < $n; $i++) {
            $results[] = $this->fetchRandomWorldRow($db);
        }

        return $this->response
            ->setContentType('application/json')
            ->setBody(json_encode($results));
    }

    public function updates()
    {
        $n  = $this->clampQueries($this->request->getGet('queries'));
        $db = Database::connect();

        $results = [];
        for ($i = 0; $i < $n; $i++) {
            $row              = $this->fetchRandomWorldRow($db);
            $row['randomNumber'] = random_int(1, 10000);

            $db->table('world')
                ->where('id', $row['id'])
                ->update(['randomNumber' => $row['randomNumber']]);

            $results[] = $row;
        }

        return $this->response
            ->setContentType('application/json')
            ->setBody(json_encode($results));
    }

    public function fortunes()
    {
        $db       = Database::connect();
        $fortunes = $db->table('fortune')->get()->getResultArray();

        $fortunes[] = [
            'id'      => 0,
            'message' => 'Additional fortune added at request time.',
        ];

        usort($fortunes, static fn (array $a, array $b): int => strcmp($a['message'], $b['message']));

        return $this->response
            ->setContentType('text/html')
            ->setBody(view('fortunes', ['fortunes' => $fortunes]));
    }

    private function fetchRandomWorldRow(\CodeIgniter\Database\BaseConnection $db): array
    {
        $id  = random_int(1, 10000);
        $row = $db->table('world')->where('id', $id)->get()->getRowArray();

        return [
            'id'           => (int) $row['id'],
            'randomNumber' => (int) $row['randomNumber'],
        ];
    }

    private function clampQueries(mixed $raw): int
    {
        $n = is_numeric($raw) ? (int) $raw : 1;

        if ($n < 1) {
            $n = 1;
        }

        if ($n > 500) {
            $n = 500;
        }

        return $n;
    }
}
