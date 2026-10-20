<?php

declare(strict_types=1);

use App\Console\Commands\StarterKit\EnvWriter;

describe('apply', function (): void {
    test('replaces an existing entry in place', function (): void {
        $patched = (new EnvWriter)->apply("A=1\nAPP_PORT=80\nB=2\n", ['APP_PORT' => '8080']);

        expect($patched)->toBe("A=1\nAPP_PORT=8080\nB=2\n");
    });

    test('adds a missing entry at the end of the file', function (): void {
        $patched = (new EnvWriter)->apply('A=1', ['B' => '2']);

        expect($patched)->toBe("A=1\nB=2\n");
    });

    test('adds a missing entry before the marker so other modules can follow', function (): void {
        $patched = (new EnvWriter)->apply("A=1\n# @kit:env\nZ=9\n", ['B' => '2', 'C' => '3']);

        expect($patched)->toBe("A=1\nB=2\nC=3\n# @kit:env\nZ=9\n");
    });

    test('quotes only the values that need it', function (): void {
        $patched = (new EnvWriter)->apply('', ['PLAIN' => 'abc', 'SPACED' => 'my app', 'EMPTY' => '', 'DOLLAR' => 'a$b "c"']);

        expect($patched)->toBe("PLAIN=abc\nSPACED=\"my app\"\nEMPTY=\nDOLLAR=\"a\\\$b \\\"c\\\"\"\n");
    });

    test('does not treat a commented entry as the existing one', function (): void {
        $patched = (new EnvWriter)->apply("# APP_PORT=80\n", ['APP_PORT' => '8080']);

        expect($patched)->toBe("# APP_PORT=80\nAPP_PORT=8080\n");
    });
});
