@php
    $locale = $currentLocale ?? app()->getLocale();
    $dir = $textDirection ?? ($locale === 'ar' ? 'rtl' : 'ltr');
    $theme = $currentTheme ?? session('theme', 'light');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}" class="{{ $theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('ui.login_title') }} — {{ __('ui.app_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col items-center justify-center bg-slate-100 p-4 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="w-full max-w-md space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-700 font-bold text-white shadow-sm">RB</span>
                <div>
                    <h1 class="text-base font-bold text-slate-900 dark:text-white">{{ __('ui.app_name') }}</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('ui.app_subtitle') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('preferences.locale', ['locale' => $locale === 'ar' ? 'en' : 'ar']) }}" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    {{ __('ui.locale_switch_label') }}
                </a>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.login_title') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.login_subtitle') }}</p>

            @if ($errors->any())
                <div data-testid="login-error" class="mt-4 rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-200">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.attempt') }}" method="POST" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('ui.email') }}</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        data-testid="login-email"
                        class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-sky-600 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('ui.password') }}</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        data-testid="login-password"
                        class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-sky-600 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    >
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                    <span>{{ __('ui.remember_me') }}</span>
                </label>

                <button
                    type="submit"
                    data-testid="login-submit"
                    class="w-full rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-600"
                >
                    {{ __('ui.sign_in') }}
                </button>
            </form>
        </div>
    </div>
</body>
</html>
