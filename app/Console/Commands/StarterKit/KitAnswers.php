<?php

declare(strict_types=1);

namespace App\Console\Commands\StarterKit;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The answers given to the install questions, and what they imply: which modules are active
 * and the values of the `__KIT_*__` placeholders.
 */
final readonly class KitAnswers {
    /**
     * Modules whose manifest has a `filament` variant, merged in when the panel is installed.
     */
    private const array FILAMENT_PLUGIN_MODULES = ['media-library', 'permission', 'settings', 'tags', 'translatable'];

    public const APP_TYPES = ['backoffice', 'site_with_backoffice', 'site', 'api'];

    public const PERMISSIONS = ['none', 'shield', 'permission'];

    public const TWO_FACTOR = ['none', 'optional', 'required'];

    /**
     * @param  list<string>  $allowed_email_domains
     * @param  list<string>  $locales
     * @param  list<string>  $modules  optional modules picked from the multiselect
     * @param  list<string>  $socialite_providers
     */
    public function __construct(
        public string $app_name,
        public string $app_slug,
        public string $vendor = 'simonecerruti',
        public string $license = 'MIT',
        public int $port = 8080,
        public string $timezone = 'Europe/Rome',
        public string $gh_repository = '',
        public string $app_type = 'site_with_backoffice',
        public string $panel_id = 'admin',
        public array $allowed_email_domains = [],
        public bool $force_password_change = false,
        public string $two_factor = 'none',
        public bool $mfa_reset = false,
        public bool $login_audit = false,
        public bool $multilingual = false,
        public array $locales = [],
        public string $default_locale = 'en',
        public bool $localized_routes = false,
        public bool $translatable = false,
        public string $permissions = 'none',
        public array $modules = [],
        public array $socialite_providers = [],
        public bool $socialite_registration = false,
        public bool $deploy = false,
        public bool $ai_tooling = false,
        public bool $codegraph = false,
        public string $gh_token = '',
        public string $context7_key = '',
        public bool $cloudflare = false,
        public bool $backup_google_drive = false,
    ) {
        $this->assertOneOf('app_type', $this->app_type, self::APP_TYPES);
        $this->assertOneOf('permissions', $this->permissions, self::PERMISSIONS);
        $this->assertOneOf('two_factor', $this->two_factor, self::TWO_FACTOR);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    public static function fromArray(array $answers): self {
        $app_name = (string) ($answers['app_name'] ?? throw new InvalidArgumentException('The answer [app_name] is required.'));
        $answers['app_slug'] ??= Str::slug($app_name);
        $answers['app_name'] = $app_name;

        $known = array_flip(array_map(fn (\ReflectionParameter $parameter): string => $parameter->getName(), new \ReflectionMethod(self::class, '__construct')->getParameters()));
        $unknown = array_diff_key($answers, $known);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown answers: '.implode(', ', array_keys($unknown)).'.');
        }

        return new self(...$answers);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return get_object_vars($this);
    }

    public function hasFilament(): bool {
        return \in_array($this->app_type, ['backoffice', 'site_with_backoffice'], true);
    }

    public function hasPublicSite(): bool {
        return \in_array($this->app_type, ['site_with_backoffice', 'site'], true);
    }

    /**
     * @return list<string>
     */
    public function activeModules(): array {
        $filament = $this->hasFilament();

        $modules = collect(['core'])
            ->when($filament, fn ($collection) => $collection->push('filament'))
            ->when($filament && $this->allowed_email_domains !== [], fn ($collection) => $collection->push('email-domain'))
            ->when($filament && $this->force_password_change, fn ($collection) => $collection->push('force-password-change'))
            ->when($filament && $this->two_factor !== 'none', fn ($collection) => $collection->push('filament-mfa'))
            ->when($filament && $this->two_factor !== 'none' && $this->mfa_reset, fn ($collection) => $collection->push('mfa-reset'))
            ->when($this->login_audit, fn ($collection) => $collection->push('login-audit'))
            ->when($this->multilingual, fn ($collection) => $collection->push('multilingual'))
            ->when($this->multilingual && $this->hasPublicSite() && $this->localized_routes, fn ($collection) => $collection->push('localized-routes'))
            ->when($this->multilingual && $this->hasPublicSite() && $this->translatable, fn ($collection) => $collection->push('translatable'))
            ->when($this->permissions !== 'none', fn ($collection) => $collection->push('permission'))
            ->when($this->permissions === 'shield' && $filament, fn ($collection) => $collection->push('shield'))
            ->when($this->socialite_providers !== [], fn ($collection) => $collection->push('socialite'))
            ->when($this->deploy, fn ($collection) => $collection->push('deploy'))
            ->when($this->ai_tooling, fn ($collection) => $collection->push('ai-tooling'))
            ->when($this->ai_tooling && $this->codegraph, fn ($collection) => $collection->push('codegraph'))
            ->when($this->cloudflare, fn ($collection) => $collection->push('cloudflare'))
            ->merge($this->modules);

        return $modules->unique()->values()->all();
    }

    /**
     * @return array<string, list<string>> module => variants to merge into its manifest
     */
    public function variants(): array {
        $variants = $this->socialite_providers === [] ? [] : ['socialite' => $this->socialite_providers];

        if ($this->backup_google_drive && \in_array('backup', $this->modules, true)) {
            $variants['backup'] = ['google-drive'];
        }

        if (! $this->hasFilament()) {
            return $variants;
        }

        foreach (array_intersect(self::FILAMENT_PLUGIN_MODULES, $this->activeModules()) as $module) {
            $variants[$module] = ['filament'];
        }

        return $variants;
    }

    /**
     * @return array<string, string>
     */
    public function placeholders(): array {
        $home_url = $this->hasFilament() ? '/'.$this->panel_id : '/';

        return [
            'KIT_APP_NAME' => $this->app_name,
            'KIT_APP_SLUG' => $this->app_slug,
            'KIT_VENDOR' => $this->vendor,
            'KIT_APP_LICENSE' => $this->license,
            'KIT_APP_PORT' => (string) $this->port,
            'KIT_APP_ACTUAL_TIMEZONE' => $this->timezone,
            'KIT_GH_REPOSITORY' => $this->gh_repository,
            'KIT_GH_TOKEN' => $this->gh_token,
            'KIT_CONTEXT7_API_KEY' => $this->context7_key,
            'KIT_PANEL_ID' => $this->panel_id,
            'KIT_PANEL_CLASS' => Str::studly($this->panel_id),
            'KIT_DEFAULT_LOCALE' => $this->default_locale,
            'KIT_DEFAULT_LOCALE_CASE' => Str::upper($this->default_locale),
            'KIT_LOCALE_CASES' => collect($this->locales)->map(fn (string $locale): string => '    case '.Str::upper($locale)." = '{$locale}';")->implode("\n"),
            'KIT_MFA_REQUIRED' => $this->two_factor === 'required' ? 'true' : 'false',
            'KIT_ALLOWED_EMAIL_DOMAINS' => implode(',', $this->allowed_email_domains),
            'KIT_SOCIALITE_PROVIDERS' => implode(',', $this->socialite_providers),
            'KIT_SOCIALITE_REGISTRATION' => $this->socialite_registration ? 'true' : 'false',
            'KIT_SOCIALITE_LOGIN_URL' => $this->hasFilament() ? $home_url.'/login' : '/login',
            'KIT_SOCIALITE_HOME_URL' => $home_url,
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function assertOneOf(string $name, string $value, array $allowed): void {
        if (! \in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("The answer [{$name}] must be one of: ".implode(', ', $allowed).'.');
        }
    }
}
