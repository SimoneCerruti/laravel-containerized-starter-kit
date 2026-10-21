<?php

/**
 * CHANGELOG.md handling (Keep a Changelog, with a '## [Unreleased]' section).
 *
 *   release <version> <notes_out>          move Unreleased into a new version section
 *   merge-driver <base> <ours> <theirs>    git merge driver used to sync staging after a hotfix
 *   check <base> <head> [--require-entry]  verify that a PR changes only the Unreleased section
 */

declare(strict_types=1);

require __DIR__ . '/shared.php';

/**
 * Return [prefix up to and including the Unreleased heading, Unreleased body, rest from the first version].
 *
 * @return array{string, string, string}
 */
function split(string $path): array
{
    $content = @file_get_contents($path);
    if ($content === false) {
        fail("{$path}: cannot read file");
    }
    $lines = preg_split('/(?<=\n)/', $content, -1, PREG_SPLIT_NO_EMPTY);

    $head = null;
    foreach ($lines as $i => $line) {
        if (preg_match('/^## \[Unreleased\]\s*$/i', $line)) {
            $head = $i;
            break;
        }
    }
    if ($head === null) {
        fail("{$path}: '## [Unreleased]' section not found");
    }

    $end = count($lines);
    for ($i = $head + 1; $i < count($lines); $i++) {
        if (str_starts_with($lines[$i], '## [')) {
            $end = $i;
            break;
        }
    }

    return [
        implode('', array_slice($lines, 0, $head + 1)),
        implode('', array_slice($lines, $head + 1, $end - $head - 1)),
        implode('', array_slice($lines, $end)),
    ];
}

function release(string $version, string $notesOut): void
{
    [$prefix, $body, $rest] = split('CHANGELOG.md');
    $notes = trim($body, "\n");
    if (trim($notes) === '') {
        fail('Unreleased section is empty: nothing to release');
    }
    $section = sprintf("## [%s] - %s\n\n%s\n", $version, gmdate('Y-m-d'), $notes);
    file_put_contents('CHANGELOG.md', $prefix . "\n" . $section . ($rest !== '' ? "\n" . $rest : ''));
    file_put_contents($notesOut, $notes . "\n");
}

function mergeDriver(string $base, string $ours, string $theirs): void
{
    // Merge of staging into the sync branch: ours = production after the hotfix release
    // (empty Unreleased, new version section), theirs = staging (unreleased entries).
    // Result: heading and versions from production, Unreleased from staging.
    [$basePre, , $baseRest] = split($base);
    [$oursPre, $oursBody, $oursRest] = split($ours);
    [$theirsPre, $theirsBody, $theirsRest] = split($theirs);
    if (trim($oursBody) !== '') {
        fail('Unreleased on production is not empty: manual resolution needed');
    }
    if ([$basePre, $baseRest] !== [$theirsPre, $theirsRest]) {
        fail('CHANGELOG.md on staging changed outside Unreleased: manual resolution needed');
    }
    file_put_contents($ours, $oursPre . $theirsBody . $oursRest);
}

function check(string $base, string $head, string ...$flags): void
{
    [$basePre, $baseBody, $baseRest] = split($base);
    [$headPre, $headBody, $headRest] = split($head);
    if ([$basePre, $baseRest] !== [$headPre, $headRest]) {
        fail("CHANGELOG.md: a PR may change only the '## [Unreleased]' section");
    }
    if (in_array('--require-entry', $flags, true) && (trim($headBody) === '' || $headBody === $baseBody)) {
        fail("CHANGELOG.md: missing entry under '## [Unreleased]'");
    }
}

$commands = ['release' => 'release', 'merge-driver' => 'mergeDriver', 'check' => 'check'];
$command = $argv[1] ?? '';
if (!isset($commands[$command])) {
    fail('Usage: changelog.php release|merge-driver|check <args>');
}
$commands[$command](...array_slice($argv, 2));
