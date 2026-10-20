<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

describe('freezeUuids', function (): void {
    test('every generated uuid is the same until the test ends', function (): void {
        $before = (string) Str::uuid();

        $frozen = freezeUuids();

        expect($before)->not->toBe($frozen)
            ->and((string) Str::uuid())->toBe($frozen)
            ->and((string) Str::uuid())->toBe($frozen);
    });

    test('the following test generates random uuids again', function (): void {
        expect((string) Str::uuid())->not->toBe((string) Str::uuid());
    });
});

describe('time', function (): void {
    test('now is frozen for the whole test', function (): void {
        $first = now();

        Sleep::assertNeverSlept();
        usleep(2000);

        expect(now()->equalTo($first))->toBeTrue();
    });
});

describe('stray processes', function (): void {
    test('running a process that was not faked throws', function (): void {
        expect(fn () => Process::run('echo stray'))
            ->toThrow(RuntimeException::class, 'without a matching fake');
    });

    test('a faked process runs', function (): void {
        Process::fake(['echo ok' => Process::result('ok')]);

        expect(Process::run('echo ok')->output())->toStartWith('ok');
    });
});

describe('model expectations', function (): void {
    test('toBe compares models by record, not by instance', function (): void {
        $user = User::factory()->create();

        expect(User::query()->findOrFail($user->getKey()))->toBe($user);
    });

    test('toBe fails for different records', function (): void {
        [$first, $second] = User::factory()->count(2)->create()->all();

        expect(fn () => expect($first)->toBe($second))
            ->toThrow(Exception::class, 'same record');
    });

    test('toEqual compares models by record', function (): void {
        $user = User::factory()->create();

        expect($user->fresh())->toEqual($user);
    });
});
