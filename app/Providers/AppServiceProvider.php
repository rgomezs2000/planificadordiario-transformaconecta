<?php

namespace App\Providers;

use App\Notificaciones\BuzonDual;
use App\Notificaciones\Contratos\Buzon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El buzón de la aplicación escribe en MySQL y en Firestore. Quien pida
        // el contrato Buzon recibe el doble; el panel NoSQL usa BuzonFirestore
        // directamente para leer el espejo.
        $this->app->bind(Buzon::class, BuzonDual::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
