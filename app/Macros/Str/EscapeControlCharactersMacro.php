<?php

declare(strict_types=1);

namespace App\Macros\Str;

use App\Macros\Contracts\Macro;
use Illuminate\Support\Str;

/**
 * Backslash-escapes ASCII control characters (0x00–0x1F), so user input logged or put in an exception
 * message cannot forge a new log entry.
 */
final class EscapeControlCharactersMacro extends Macro {
    protected static function macroable(): string {
        return Str::class;
    }

    protected static function name(): string {
        return 'escapeControlCharacters';
    }

    public static function handle(string $value): string {
        return addcslashes($value, "\0..\37");
    }
}
