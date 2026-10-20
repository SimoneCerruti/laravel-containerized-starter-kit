<?php

declare(strict_types=1);

namespace Tests\Fixtures\Enums;

use App\Enums\Concerns\HasAutoLabel;

enum FixtureLabelEnum: string {
    use HasAutoLabel;

    case FIRST_CASE = 'first_case';
    case SECOND_CASE = 'second_case';
    case SINGLE_WORD = 'single';
    case MULTI_WORD = 'multi_word_case';
}
