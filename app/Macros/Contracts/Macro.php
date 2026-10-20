<?php

declare(strict_types=1);

namespace App\Macros\Contracts;

/**
 * A subclass registers itself as a macro on `macroable()` under `name()`,
 * backed by its own public static `handle()` method — not declared here,
 * since each macro's signature (and `@template`) differs. Larastan only
 * preserves a macro's generic through a real, reflectable method, never a
 * closure built at runtime.
 */
abstract class Macro {
    /** @return class-string */
    abstract protected static function macroable(): string;

    /** @return non-empty-string */
    abstract protected static function name(): string;

    public static function register(): void {
        static::macroable()::macro(static::name(), [static::class, 'handle']);
    }
}
