<?php

declare(strict_types=1);

use Illuminate\Support\Str;

test('registers the escapeControlCharacters macro on the Str facade', function (): void {
    expect(Str::hasMacro('escapeControlCharacters'))->toBeTrue();
});
