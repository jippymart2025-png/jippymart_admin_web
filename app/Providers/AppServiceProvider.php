<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;  // <--- important
use Illuminate\Support\Facades\URL;  // <--- important



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Firebase cookies
        setcookie('XSRF-TOKEN-AK', bin2hex(env('FIREBASE_APIKEY')), time() + 3600, "/");
        setcookie('XSRF-TOKEN-AD', bin2hex(env('FIREBASE_AUTH_DOMAIN')), time() + 3600, "/");
        setcookie('XSRF-TOKEN-DU', bin2hex(env('FIREBASE_DATABASE_URL')), time() + 3600, "/");
        setcookie('XSRF-TOKEN-PI', bin2hex(env('FIREBASE_PROJECT_ID')), time() + 3600, "/");
        setcookie('XSRF-TOKEN-SB', bin2hex(env('FIREBASE_STORAGE_BUCKET')), time() + 3600, "/");
        setcookie('XSRF-TOKEN-MS', bin2hex(env('FIREBASE_MESSAAGING_SENDER_ID')), time() + 3600, "/");
        setcookie('XSRF-TOKEN-AI', bin2hex(env('FIREBASE_APP_ID')), time() + 3600, "/");
        setcookie('XSRF-TOKEN-MI', bin2hex(env('FIREBASE_MEASUREMENT_ID')), time() + 3600, "/");

        // Countries JSON loader
        $countries_data = [];
        $get_countries_json = file_get_contents(public_path('countriesdata.json'));

        if ($get_countries_json != '') {
            $countries_data = json_decode($get_countries_json);
        }

        view()->composer('*', function ($view) use ($countries_data) {
            $view->with('countries_data', $countries_data);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Disable logging
        Log::setDefaultDriver('null');

        // Force HTTPS if behind a proxy (like AWS, Cloudflare)
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $this->app['request']->server->set('HTTPS', 'on');
            URL::forceScheme('https');
        }

        // Load environment-specific impersonation config
        $environment = app()->environment();
        $configFile = "impersonation.{$environment}";

        if (file_exists(config_path("{$configFile}.php"))) {
            config([$configFile => require config_path("{$configFile}.php")]);
        }

        // --------------------------------------------------
        // 🚀 HOSTINGER STORAGE FIX (NO SYMLINK ALLOWED)
        // --------------------------------------------------

        $publicStorage = public_path('storage');
        $storagePath   = storage_path('app/public');

        // Create public/storage folder if not exists
        if (! File::exists($publicStorage)) {
            File::makeDirectory($publicStorage, 0755, true);
        }

        // Copy all storage/app/public → public/storage
        if (File::isDirectory($storagePath)) {
            File::copyDirectory($storagePath, $publicStorage);
        }
    }
}
