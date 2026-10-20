<?php

declare(strict_types=1);

use App\Enums\Concerns\HasSelectableOptions;
use Filament\Support\Contracts\HasLabel;

enum SelectableOptionsFixture: string implements HasLabel {
    use HasSelectableOptions;

    case ACTIVE = 'active';
    case LEGACY = 'legacy';

    public function getLabel(): string {
        return ucfirst($this->value);
    }

    /**
     * @return list<self>
     */
    public static function excludedFromSelectableOptions(): array {
        return [self::LEGACY];
    }
}

test('leaves out the excluded cases', function (): void {
    expect(SelectableOptionsFixture::selectableOptions())->toBe(['active' => 'Active']);
});

test('keeps an excluded case when it is the current value', function (): void {
    expect(SelectableOptionsFixture::selectableOptions(SelectableOptionsFixture::LEGACY))->toBe(['active' => 'Active', 'legacy' => 'Legacy']);
});
