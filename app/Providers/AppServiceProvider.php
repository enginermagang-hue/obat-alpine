<?php

namespace App\Providers;

use App\Models\User;
use App\Models\UserPreference;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('id');

        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole('super_admin') ? true : null;
        });

        // Auto-discovery Laravel tidak menemukan policy untuk model di luar
        // namespace App\Models (mis. Spatie), jadi daftarkan eksplisit.
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();

            if ($user === null) {
                return;
            }

            $user->loadMissing('preferences');

            $view->with([
                'navSections' => $this->navigation($user),
                'prefs' => $user->preferences ?? new UserPreference(UserPreference::defaults()),
            ]);
        });
    }

    /**
     * Filter menu berdasarkan route yang tersedia dan role user.
     *
     * @return array<int, array{label: string|null, items: array<int, array<string, mixed>>}>
     */
    private function navigation(User $user): array
    {
        $isSuperAdmin = $user->hasRole('super_admin');

        return collect(config('navigation'))
            ->map(function (array $section) use ($user, $isSuperAdmin): array {
                $items = collect($section['items'])
                    ->filter(fn (array $item): bool => Route::has($item['route']))
                    ->filter(fn (array $item): bool => $isSuperAdmin
                        || $item['roles'] === []
                        || $user->hasAnyRole($item['roles']))
                    ->values()
                    ->all();

                return ['label' => $section['label'], 'items' => $items];
            })
            ->filter(fn (array $section): bool => $section['items'] !== [])
            ->values()
            ->all();
    }
}
