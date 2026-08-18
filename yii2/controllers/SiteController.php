<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class SiteController extends Controller
{
    public $layout = false;

    public function actionJson()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return ['message' => 'Hello, World!'];
    }

    public function actionPlaintext()
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'text/plain');

        return 'Hello, World!';
    }

    public function actionDb()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->fetchRandomWorld();
    }

    public function actionQueries()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $count = $this->parseQueryCount();
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->fetchRandomWorld();
        }

        return $result;
    }

    public function actionUpdates()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $count = $this->parseQueryCount();
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $row = $this->fetchRandomWorld();
            $row['randomNumber'] = random_int(1, 10000);

            Yii::$app->db->createCommand(
                'UPDATE world SET randomNumber = :rn WHERE id = :id',
                [':rn' => $row['randomNumber'], ':id' => $row['id']]
            )->execute();

            $result[] = $row;
        }

        return $result;
    }

    public function actionFortunes()
    {
        $fortunes = Yii::$app->db->createCommand('SELECT id, message FROM fortune')->queryAll();
        $fortunes[] = ['id' => 0, 'message' => 'Additional fortune added at request time.'];

        usort($fortunes, static function ($a, $b) {
            return strcmp($a['message'], $b['message']);
        });

        return $this->render('fortunes', ['fortunes' => $fortunes]);
    }

    private function fetchRandomWorld(): array
    {
        $id = random_int(1, 10000);
        $row = Yii::$app->db->createCommand(
            'SELECT id, randomNumber FROM world WHERE id = :id',
            [':id' => $id]
        )->queryOne();

        return ['id' => (int) $row['id'], 'randomNumber' => (int) $row['randomNumber']];
    }

    private function parseQueryCount(): int
    {
        $count = Yii::$app->request->get('queries', 1);

        if (!is_numeric($count)) {
            $count = 1;
        }

        $count = (int) $count;

        if ($count < 1) {
            $count = 1;
        }

        if ($count > 500) {
            $count = 500;
        }

        return $count;
    }
}
