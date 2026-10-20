<?php

declare(strict_types=1);

use App\Macros\Str\EscapeControlCharactersMacro;
use Illuminate\Support\Str;

describe('macroable()', function (): void {
    test('registers on the Str facade', function (): void {
        $macroable = (new ReflectionMethod(EscapeControlCharactersMacro::class, 'macroable'))->invoke(null);

        expect($macroable)->toBe(Str::class);
    });
});

describe('name()', function (): void {
    test('registers under "escapeControlCharacters"', function (): void {
        $name = (new ReflectionMethod(EscapeControlCharactersMacro::class, 'name'))->invoke(null);

        expect($name)->toBe('escapeControlCharacters');
    });
});

describe('handle()', function (): void {
    test('escapes newlines, carriage returns, tabs and null bytes', function (): void {
        expect(EscapeControlCharactersMacro::handle("a\nb\rc\td\0e"))->toBe('a\nb\rc\td\000e');
    });

    test('forges no extra log line from a submitted newline', function (): void {
        expect(EscapeControlCharactersMacro::handle("ok\n[ERROR] forged"))->not->toContain("\n");
    });

    test('leaves a string without control characters unchanged', function (): void {
        expect(EscapeControlCharactersMacro::handle('plain value 123'))->toBe('plain value 123');
    });
});
