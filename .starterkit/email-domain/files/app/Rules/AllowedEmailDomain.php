<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use App\Actions\CheckAllowedEmailForAdminPanel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class AllowedEmailDomain implements ValidationRule {
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if (! \is_string($value)) {
            $fail('The :attribute must be a valid email address.')->translate();

            return;
        }

        if (!CheckAllowedEmailForAdminPanel::run($value)) {
            $fail('The :attribute domain is not authorised for panel access.')->translate();
        }
    }
}
