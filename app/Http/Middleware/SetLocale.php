<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const LOCALES = ['en' => 'English', 'it' => 'Italiano', 'pt' => 'Português'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale')
            ?? $request->getPreferredLanguage(array_keys(self::LOCALES))
            ?? config('app.locale');

        app()->setLocale(array_key_exists($locale, self::LOCALES) ? $locale : 'en');

        return $next($request);
    }
}
