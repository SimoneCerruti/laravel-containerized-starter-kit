<?php

/**
 * Helpers shared by the CI scripts (changelog.php, version.php).
 */

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
