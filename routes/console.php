<?php

use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console routes (shortcuts)
|--------------------------------------------------------------------------
*/

Artisan::command('cartping:demo', function () {
    $this->comment('CartPing is ready. Point a browser at your dashboard and start the onboarding wizard.');
})->purpose('Show a CartPing ready message');
