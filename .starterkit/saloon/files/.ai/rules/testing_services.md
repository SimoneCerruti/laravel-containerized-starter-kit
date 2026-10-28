---
paths:
    - tests/App/Services/**
---

# Testing services

- Never hit the real API: register a Saloon `MockClient` (`MockClient::global()` or per-connector) with `MockResponse::make()` fixtures; stray requests are already blocked in `tests/Pest.php`.
- Assert the outgoing request, not only the response: `$mockClient->assertSent(MyRequest::class)` and, where it matters, the query, body and headers.
- Cover the failure paths: 4xx/5xx responses, timeouts, rate limit (429) and retries, and assert the Service's own exception, not Saloon's.
- Test the DTO mapping with a realistic provider payload stored in `tests/Fixtures/Saloon`.
