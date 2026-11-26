<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class SetLocaleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales', ['ar', 'en']);

        $requestedLocale = $request->query('lang');
        if (is_string($requestedLocale) && in_array($requestedLocale, $supported, true)) {
            if ($request->hasSession()) {
                $request->session()->put('locale', $requestedLocale);
            }
            $locale = $requestedLocale;
        } elseif ($request->hasSession() && is_string($request->session()->get('locale')) && in_array($request->session()->get('locale'), $supported, true)) {
            $locale = (string) $request->session()->get('locale');
        } else {
            $locale = (string) config('app.locale', 'ar');
        }

        if (! in_array($locale, $supported, true)) {
            $locale = 'ar';
        }

        App::setLocale($locale);
        View::share('currentLocale', $locale);
        View::share('textDirection', $locale === 'ar' ? 'rtl' : 'ltr');

        return $next($request);
    }
}
