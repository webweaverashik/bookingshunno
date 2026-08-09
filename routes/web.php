<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/_maintenance/artisan', function () {
    $key = request('key');

    if (! $key || ! hash_equals(env('ARTISAN_WEB_KEY', ''), $key)) {
        abort(403);
    }

    $command = request('command', 'optimize:clear');

    $allowedCommands = [
        'optimize:clear',
        'config:clear',
        'cache:clear',
        'route:clear',
        'view:clear',
        'storage:link',
        'migrate',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:refresh --seed',
    ];

    if (! in_array($command, $allowedCommands, true)) {
        abort(403, 'Command not allowed.');
    }

    Artisan::call($command, [
        '--force' => true,
    ]);

    return response()->json([
        'success' => true,
        'command' => $command,
        'output'  => Artisan::output(),
    ]);
});
