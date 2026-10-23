## Nightwatch

Error tracking and monitoring with [Laravel Nightwatch](https://nightwatch.laravel.com/).

1. Create the application on nightwatch.laravel.com and copy its token.
2. Set `NIGHTWATCH_TOKEN` and `NIGHTWATCH_ENABLED=true` in the `.env` of each environment where you want it (it is `false` by default, so local and CI stay quiet).
3. The agent (`php artisan nightwatch:agent`) runs under supervisor in the container, only when `NIGHTWATCH_ENABLED` is `true`. Restart the container after changing the value.
4. In production, add the two keys to the `DOTENV_*` secret of the environment (see the deploy setup).
