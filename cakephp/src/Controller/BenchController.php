<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

class BenchController extends AppController
{
    public function json(): Response
    {
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['message' => 'Hello, World!'], JSON_THROW_ON_ERROR));
    }

    public function plaintext(): Response
    {
        return $this->response
            ->withType('text/plain')
            ->withStringBody('Hello, World!');
    }

    public function db(): Response
    {
        $world = $this->fetchTable('World')->get(random_int(1, 10000));

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'id' => $world->get('id'),
                'randomNumber' => $world->get('randomNumber'),
            ], JSON_THROW_ON_ERROR));
    }

    public function queries(): Response
    {
        $table = $this->fetchTable('World');
        $count = $this->queryCount();

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $world = $table->get(random_int(1, 10000));
            $results[] = [
                'id' => $world->get('id'),
                'randomNumber' => $world->get('randomNumber'),
            ];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($results, JSON_THROW_ON_ERROR));
    }

    public function updates(): Response
    {
        $table = $this->fetchTable('World');
        $count = $this->queryCount();

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $world = $table->get(random_int(1, 10000));
            $newRandomNumber = random_int(1, 10000);

            $table->updateAll(
                ['randomNumber' => $newRandomNumber],
                ['id' => $world->get('id')],
            );

            $results[] = [
                'id' => $world->get('id'),
                'randomNumber' => $newRandomNumber,
            ];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($results, JSON_THROW_ON_ERROR));
    }

    public function fortunes(): Response
    {
        $fortunes = $this->fetchTable('Fortune')
            ->find()
            ->select(['id', 'message'])
            ->all()
            ->map(fn ($fortune) => ['id' => $fortune->get('id'), 'message' => $fortune->get('message')])
            ->toList();

        $fortunes[] = ['id' => 0, 'message' => 'Additional fortune added at request time.'];

        usort($fortunes, fn ($a, $b) => $a['message'] <=> $b['message']);

        $this->set('fortunes', $fortunes);
        $this->viewBuilder()->disableAutoLayout();

        return $this->render()->withType('text/html');
    }

    private function queryCount(): int
    {
        $raw = $this->getRequest()->getQuery('queries');

        if (!is_numeric($raw)) {
            return 1;
        }

        $count = (int)$raw;

        return max(1, min(500, $count));
    }
}
