---
paths:
    - app/Services/**
---

# Services

- Implement every Service using Saloon (`saloonphp/saloon`) and its `saloonphp/laravel-plugin` extension — use the `/saloon-development` skill.
- Wrap each integration behind a Service: the rest of the app depends on the Service, not on Saloon, so the client stays swappable.
- Use typed request/response classes, DTO mapping, built-in retry / timeout / rate-limit.
- Set explicit timeouts and retries on every outbound call; respect provider rate limits.
- Set the retry / timeout / rate-limit property dynamically, delegating to a config files for their values.
- Always use Artisan commands to generate SaloonPHP classes: `php artisan saloon:connector`, `php artisan saloon:request`, `php artisan saloon:response`, `php artisan saloon:plugin`, `php artisan saloon:auth`
