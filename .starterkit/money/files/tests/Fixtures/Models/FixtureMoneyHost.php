<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bare model handed to the `AsMoney` cast in tests: the cast never reads the database.
 */
final class FixtureMoneyHost extends Model {
    protected $table = 'fixture_money_hosts';
}
