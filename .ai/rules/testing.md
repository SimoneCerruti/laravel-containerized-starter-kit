---
paths:
    - tests/**
---

# Testing

- Use PestPHP and the `/pest-testing` skill.

## Where things live

- Match source code directories for test files. If testing app/Providers/AppServiceProvider.php, the tests should live in tests/App/Providers/AppServiceProviderTest.php
- Large or shared datasets live in `tests/Datasets`.
- Browser tests live in `tests/Browser`.
- Database tests live in `tests/Database`.
- Domain-specific testing rules (actions, console commands, enums, macros, HTTP, database, browser and the optional modules) are listed in `.ai/rules/index.md`: read the ones matching the test's path.

## `tests/Pest.php`

- Shared setup / helpers / config live in `tests/Pest.php`, never in the test file.
- If while developing a test, a pattern is recurring, abstract a helper function in `tests/Pest.php`.
- Write custom interceptions / expectations that beautify and simplify a test in `tests/Pest.php`.

## Helper conventions

- Helper function names use `camelCase`.  
- Use the helpers Pest packages provide instead of `$this` in tests.
- Helpers should always start with a verb. Helper functions that perform expectations/assertions should have a name beginning with "assert"/"expect". Use full, unambiguous names: the reader should understand what is happening just by looking at the call, without needing to open the definition.

## Models and factories in tests

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- If while developing a test, an inline model state is recurring, abstract the state in its factory.
- Make use of `Illuminate\Database\Eloquent\Factories\Sequence`, `Model::factory()->has()`, `Model::factory()->state()`, `Model::factory()->for()`, `Model::factory()->hasAttached()` for cleaner code in tests.

## Faking and mocking

- Use the `::fake()` methods of facades in tests (`Mail::fake()`, `Notification::fake()`, etc.). Put them in the `tests/Pest.php` shared setup so they aren't forgotten.
- When using Services in a test, use Saloon's MockClient (or `Http::fake()` if not on Saloon) instead of hitting a live API.

## Structure and rigor

- Group related tests with `describe()`. Nesting `describe` blocks is allowed.
- Test both the happy and bad scenario. Eg: a numeric value validation should be tested both by passing a numeric, thus valid, value and a non-numeric, invalid, value. Do not explicitly use "bad path"/"happy path" in the test name.
- When asserting/expecting an exception, also match its message (even partially) instead of only the exception type, to avoid false positives from another exception of the same type.
- Before/After: before verifying that an action has occurred, explicitly verify that it had not already occurred (pre-condition state).
  Eg:
  ```php
  test('marks the email as verified', function (): void {
    $user = User::factory()->unverified()->create();

    expect($user->hasVerifiedEmail())->toBeFalse() // checking the pre-condition state: email was unverified. This check ensures that the change/action actually happened and that has not always been true.
        ->and($user->markEmailAsVerified())->toBeTrue() // change/action
        ->and($user->hasVerifiedEmail())->toBeTrue() // actual test assertions
        ->and($user->fresh()?->email_verified_at)->not->toBeNull();
  });
  ```
- Ask for approval before deleting a test.
