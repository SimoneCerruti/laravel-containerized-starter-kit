<?php

declare(strict_types=1);

use function Pest\Laravel\artisan;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\readlink;
use function Safe\symlink;

function aiHooksProject(): string {
    $project = sys_get_temp_dir().'/ai_hooks_'.uniqid();
    mkdir("{$project}/.ai/hooks", 0755, true);
    file_put_contents("{$project}/.ai/hooks/guard.sh", '#!/usr/bin/env bash');

    return $project;
}

test('links .ai/hooks into .claude, creating the folder', function (): void {
    $project = aiHooksProject();

    artisan('ai:link-hooks', ['--base-path' => $project])
        ->expectsOutputToContain('.claude/hooks -> .ai/hooks')
        ->assertSuccessful();

    expect(is_link("{$project}/.claude/hooks"))->toBeTrue()
        ->and(readlink("{$project}/.claude/hooks"))->toBe('../.ai/hooks')
        ->and(is_file("{$project}/.claude/hooks/guard.sh"))->toBeTrue()
        ->and(file_exists("{$project}/.codex"))->toBeFalse();
});

test('links .codex too when the folder already exists', function (): void {
    $project = aiHooksProject();
    mkdir("{$project}/.codex");

    artisan('ai:link-hooks', ['--base-path' => $project])->assertSuccessful();

    expect(is_link("{$project}/.codex/hooks"))->toBeTrue();
});

test('replaces a link that points somewhere else', function (): void {
    $project = aiHooksProject();
    mkdir("{$project}/.claude");
    symlink('../elsewhere', "{$project}/.claude/hooks");

    artisan('ai:link-hooks', ['--base-path' => $project])->assertSuccessful();

    expect(readlink("{$project}/.claude/hooks"))->toBe('../.ai/hooks');
});

test('refuses to replace a real folder', function (): void {
    $project = aiHooksProject();
    mkdir("{$project}/.claude/hooks", 0755, true);

    artisan('ai:link-hooks', ['--base-path' => $project])
        ->expectsOutputToContain('is a real folder')
        ->assertFailed();

    expect(is_link("{$project}/.claude/hooks"))->toBeFalse();
});

test('fails when there is nothing to link', function (): void {
    $project = sys_get_temp_dir().'/ai_hooks_'.uniqid();
    mkdir($project);

    artisan('ai:link-hooks', ['--base-path' => $project])
        ->expectsOutputToContain('There is no .ai/hooks folder to link.')
        ->assertFailed();
});
