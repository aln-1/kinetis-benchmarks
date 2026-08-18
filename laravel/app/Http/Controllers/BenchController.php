<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class BenchController extends Controller
{
    public function json()
    {
        return response()->json(['message' => 'Hello, World!']);
    }

    public function plaintext()
    {
        return response('Hello, World!', 200, ['Content-Type' => 'text/plain']);
    }

    public function db()
    {
        $row = DB::table('world')->where('id', random_int(1, 10000))->first();

        return response()->json([
            'id' => (int) $row->id,
            'randomNumber' => (int) $row->randomNumber,
        ]);
    }

    public function queries()
    {
        $queries = $this->queryCount();

        $results = [];
        for ($i = 0; $i < $queries; $i++) {
            $row = DB::table('world')->where('id', random_int(1, 10000))->first();
            $results[] = [
                'id' => (int) $row->id,
                'randomNumber' => (int) $row->randomNumber,
            ];
        }

        return response()->json($results);
    }

    public function updates()
    {
        $queries = $this->queryCount();

        $results = [];
        for ($i = 0; $i < $queries; $i++) {
            $id = random_int(1, 10000);
            $row = DB::table('world')->where('id', $id)->first();

            $newRandomNumber = random_int(1, 10000);
            DB::table('world')->where('id', $id)->update(['randomNumber' => $newRandomNumber]);

            $results[] = [
                'id' => (int) $row->id,
                'randomNumber' => $newRandomNumber,
            ];
        }

        return response()->json($results);
    }

    public function fortunes()
    {
        $fortunes = DB::table('fortune')->get()->map(function ($row) {
            return (object) ['id' => (int) $row->id, 'message' => $row->message];
        })->all();

        $fortunes[] = (object) ['id' => 0, 'message' => 'Additional fortune added at request time.'];

        usort($fortunes, fn ($a, $b) => strcmp($a->message, $b->message));

        return response()->view('fortunes', ['fortunes' => $fortunes]);
    }

    private function queryCount(): int
    {
        $queries = request()->query('queries');

        if (! is_numeric($queries)) {
            return 1;
        }

        $queries = (int) $queries;

        return max(1, min(500, $queries));
    }
}
