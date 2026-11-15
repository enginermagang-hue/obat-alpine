<?php

namespace App\Http\Middleware;

use App\Models\UserPreference;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $bahasa = UserPreference::query()
                ->where('user_id', $user->id)
                ->value('bahasa');

            if (is_string($bahasa) && $bahasa !== '' && $bahasa !== app()->getLocale()) {
                app()->setLocale($bahasa);
            }
        }

        return $next($request);
    }
}
