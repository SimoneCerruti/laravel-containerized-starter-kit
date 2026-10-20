@php($providers = config('socialite.providers'))

@if ($providers !== [])
    <div class="space-y-2">
        @if (session('socialite.error'))
            <p role="alert" class="text-sm text-red-600">{{ session('socialite.error') }}</p>
        @endif

        @foreach ($providers as $provider)
            <a href="{{ route('socialite.redirect', $provider) }}" class="block rounded-md border px-4 py-2 text-center text-sm font-medium">
                {{ __('Continue with :provider', ['provider' => ucfirst($provider)]) }}
            </a>
        @endforeach
    </div>
@endif
