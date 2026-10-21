<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;

/**
 * Makes the `/up` health route fail (HTTP 500) when the database is unreachable.
 */
final class CheckDatabaseConnection {
    public function handle(DiagnosingHealth $event): void {
        DB::select('select 1');
    }
}
