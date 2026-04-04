<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'theme_preference' => $request->user()->theme_preference,
                    'passkeys' => fn () => $request->user()->passkeys()
                        ->get()
                        ->map(fn ($key) => $key->only(['id', 'name', 'last_used_at'])),
                ] : null,
            ],
            'appSettings' => fn () => Schema::hasTable('app_settings') ? [
                'app_name' => AppSetting::get('app_name', config('app.name', 'IPBlock')),
                'logo_light' => AppSetting::get('logo_light') ? '/storage/' . AppSetting::get('logo_light') : null,
                'logo_dark' => AppSetting::get('logo_dark') ? '/storage/' . AppSetting::get('logo_dark') : null,
            ] : [
                'app_name' => config('app.name', 'IPBlock'),
                'logo_light' => null,
                'logo_dark' => null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'telegram_token' => fn () => $request->session()->get('telegram_token'),
            ],
        ];
    }
}
