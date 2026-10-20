<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Tests\Fixtures\Enums\FixtureLabelEnum;

describe('getLabel', function (): void {
    test('humanises the snake_case case value', function (FixtureLabelEnum $case, string $expected_label): void {
        expect($case->getLabel())->toBe($expected_label);
    })->with([
        'no underscore' => [FixtureLabelEnum::SINGLE_WORD, 'Single'],
        'one underscore' => [FixtureLabelEnum::FIRST_CASE, 'First case'],
        'several underscores' => [FixtureLabelEnum::MULTI_WORD, 'Multi word case'],
    ]);

    test('falls back to the humanised case value when no translation is registered', function (): void {
        expect(FixtureLabelEnum::FIRST_CASE->getLabel())->toBe('First case');
    });

    test('derives a distinct label per case, from that case value', function (): void {
        expect(FixtureLabelEnum::FIRST_CASE->getLabel())
            ->not->toBe(FixtureLabelEnum::SECOND_CASE->getLabel());
    });

    test('resolves the translation registered for the case value in the active locale', function (): void {
        Lang::addLines(['*.First case' => 'Prima fixture'], 'it');
        app()->setLocale('it');

        expect(FixtureLabelEnum::FIRST_CASE->getLabel())->toBe('Prima fixture');
    });
});
