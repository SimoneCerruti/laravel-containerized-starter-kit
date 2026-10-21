<?php

/**
 * Next release version (Semantic Versioning, bump inferred from Conventional Commits).
 *
 *   <last_tag> <bump>    print 'version=X.Y.Z' and 'tag=vX.Y.Z' for $GITHUB_OUTPUT
 *
 * last_tag is the latest vX.Y.Z tag ('' if none); bump is auto|major|minor|patch.
 * stdin is the commit log since last_tag (git log --format='%s%n%b'), used with 'auto'.
 */

declare(strict_types=1);

require __DIR__ . '/shared.php';

/**
 * Infer the bump from the commit log: breaking change -> major, feat -> minor, anything else -> patch.
 */
function inferBump(string $log): string
{
    if (preg_match('/^[a-z]+(\([^)]*\))?!:|^BREAKING[ -]CHANGE:/m', $log)) {
        return 'major';
    }
    if (preg_match('/^feat(\([^)]*\))?:/m', $log)) {
        return 'minor';
    }

    return 'patch';
}

$last = $argv[1] ?? '';
$bump = $argv[2] ?? '';
if ($last !== '' && !preg_match('/^v(\d+)\.(\d+)\.(\d+)$/', $last)) {
    fail("invalid last tag: {$last}");
}

// always drain stdin: an unread pipe would kill git log with SIGPIPE and fail the step under pipefail
$log = stream_get_contents(STDIN);
if ($bump === 'auto') {
    $bump = inferBump($log);
}

[$major, $minor, $patch] = array_map('intval', explode('.', substr($last ?: 'v0.0.0', 1)));
[$major, $minor, $patch] = match ($bump) {
    'major' => [$major + 1, 0, 0],
    'minor' => [$major, $minor + 1, 0],
    'patch' => [$major, $minor, $patch + 1],
    default => fail("invalid bump: {$bump}"),
};
$version = "{$major}.{$minor}.{$patch}";

// stdout goes to $GITHUB_OUTPUT: the log line goes to stderr
fwrite(STDERR, 'Last tag: ' . ($last ?: 'none') . " -> v{$version} ({$bump})" . PHP_EOL);
echo "version={$version}\n";
echo "tag=v{$version}\n";
