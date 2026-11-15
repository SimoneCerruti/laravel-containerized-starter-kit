---
paths:
    - app/Exceptions/**
---

# Exceptions

- Every domain has one root exception named `<Domain>Exception`, extending `RuntimeException` (eg `PaymentException`, `SocialLoginException`). Every other exception of that domain extends the root, so a single `catch (PaymentException $exception)` covers the whole domain.
- Name every sub-exception after its domain: `<Domain><Case>Exception` (eg `PaymentDeclinedException`).
- Never extend the root exception of another domain.
- Exceptions are plain classes: not `final`, not `abstract`, no marker interface. This is the one exception to "classes are `final` by default". The root can be thrown directly when no sub-exception fits.
- Exceptions live flat in `app/Exceptions`, with no domain sub-folder.
- Each case of a sub-exception is a public static named constructor returning `new self(...)`. The typed parameters document what the message needs.
- Add a further sub-exception only when the parent covers more than one domain area, so callers can catch one area apart or the area carries its own data. When only the message differs, add a named constructor instead.

```php
class PaymentDeclinedException extends PaymentException {
    public static function cardExpired(string $card_brand): self {
        return new self(\sprintf('The %s card has expired.', Str::escapeControlCharacters($card_brand)));
    }
}
```
