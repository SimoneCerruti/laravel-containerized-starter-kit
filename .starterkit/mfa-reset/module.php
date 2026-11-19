<?php

declare(strict_types=1);

/*
 * `users:reset-mfa {email}` for users who lost their second factor. Needs the `filament-mfa` columns.
 * With `login-audit` the reset is also written to the activity log (`mfa_reset` event, caused by nobody
 * since it runs from the CLI, performed on the user).
 */
return [
    'with' => [
        'login-audit' => [
            'fragments' => [
                'app/Enums/ActivityEvent.php' => [
                    'activity-events' => "case MFA_RESET = 'mfa_reset';",
                ],
                'tests/App/Enums/ActivityEventTest.php' => [
                    'activity-event-values' => "'mfa_reset',",
                ],
                'app/Actions/ResetUserMultiFactorAuthentication.php' => [
                    'mfa-reset-imports' => 'use App\Enums\ActivityEvent;',
                    'mfa-reset-audit' => "activity('auth')\n    ->performedOn(\$user)\n    ->event(ActivityEvent::MFA_RESET->value)\n    ->log('Multi-factor authentication reset');",
                ],
                'tests/App/Actions/ResetUserMultiFactorAuthenticationTest.php' => [
                    'mfa-reset-test-imports' => "use App\\Enums\\ActivityEvent;\nuse Spatie\\Activitylog\\Models\\Activity;",
                    'mfa-reset-audit-tests' => <<<'PHP'
it('records the reset in the activity log', function (): void {
    $user = User::factory()->withEmailAuthentication()->create();

    expect(Activity::query()->where('event', ActivityEvent::MFA_RESET->value)->exists())->toBeFalse();

    ResetUserMultiFactorAuthentication::run($user);

    $activity = Activity::query()->where('event', ActivityEvent::MFA_RESET->value)->firstOrFail();

    expect($activity->subject_id)->toBe($user->id)
        ->and($activity->log_name)->toBe('auth');
});
PHP,
                ],
            ],
        ],
    ],
];
