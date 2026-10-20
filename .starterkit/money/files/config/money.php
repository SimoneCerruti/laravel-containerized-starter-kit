<?php

declare(strict_types=1);

use Brick\Math\RoundingMode;

return [
    // Decimals kept by AsMoney columns (amount * 10^scale stored as integer). Changing it misreads stored amounts.
    'minor_unit_scale' => 4,

    // Scale of intermediate BigDecimal divisions (e.g. currency conversion).
    'calculation_scale' => 16,

    'rounding_mode' => RoundingMode::HalfUp,
];
