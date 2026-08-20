<?php

/**
 * Tolerant "package:discover" wrapper for Composer's post-autoload-dump hook.
 *
 * On some environments (notably shared cPanel hosts) the app may not be fully
 * bootable right after `composer install/update`, which makes the standard
 * `@php artisan package:discover --ansi` hook abort the whole install.
 *
 * Laravel's package manifest is built lazily on the first `php artisan` run
 * anyway, so a failure here is never fatal — we log and continue.
 */

try {
    require __DIR__.'/vendor/autoload.php';

    $app = require_once __DIR__.'/bootstrap/app.php';

    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    $input = new Symfony\Component\Console\Input\ArrayInput(['command' => 'package:discover']);
    $output = new Symfony\Component\Console\Output\ConsoleOutput();

    $status = $kernel->handle($input, $output);
    $kernel->terminate($input, $status);

    exit($status);
} catch (\Throwable $e) {
    fwrite(STDERR, '[cartping] package:discover skipped: '.$e->getMessage().PHP_EOL);
    exit(0);
}
