<?php

declare(strict_types=1);

namespace App\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

final class CheckAllowedEmailForAdminPanel {
    use AsAction;

    public function handle(string $email): bool {
        $domain = str($email)->afterLast('@')->lower()->toString();

        return \in_array($domain, config()->array('app.allowed_admin_email_domains'), true);
    }
}
