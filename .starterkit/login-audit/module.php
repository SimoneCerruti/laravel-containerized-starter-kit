<?php

declare(strict_types=1);

/*
 * Logs sign-in, failed sign-in and sign-out to the activity log (`auth` log). Listeners are
 * auto-discovered from `app/Listeners`. With 2FA, failed challenges still need the custom
 * Filament login page (`App\Filament\Auth\Login`), which is not shipped yet.
 */
return [];
