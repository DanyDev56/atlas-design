<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

final class PlaygroundController extends Controller
{
    public function __invoke(): Response
    {
        if (! config('app.debug') && ! app()->environment('testing')) {
            abort(404);
        }

        return response()->view('playground');
    }
}
