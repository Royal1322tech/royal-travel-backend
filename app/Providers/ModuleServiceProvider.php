<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (glob(app_path('Modules/*/Providers/*ServiceProvider.php')) ?: [] as $file) {
            $relative = substr($file, strlen(app_path()) + 1);
            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $relative);
            if (class_exists($class)) {
                $this->app->register($class);
            }
        }
    }
}
