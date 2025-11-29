<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureUserActive;
use App\Http\Middleware\EnsureDashrashOne;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth as Width;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            /*
            |--------------------------------------------------------------------------
            | Identificação e caminho do painel
            |--------------------------------------------------------------------------
            */
            ->default()
            ->id('equitadm')
            ->path('equitadm')

            /*
            |--------------------------------------------------------------------------
            | Autenticação
            | Usa o mesmo guard da aplicação (web)
            | NÃO usa o login nativo do Filament.
            |--------------------------------------------------------------------------
            */
            ->authGuard('web')
            // ->login() // ❌ não habilitar login próprio

            /*
            |--------------------------------------------------------------------------
            | Branding e layout
            |--------------------------------------------------------------------------
            */
            ->brandName('TrustCash')
            ->colors(['primary' => Color::Green])
            ->maxContentWidth(Width::Full)
            ->topNavigation()

            /*
            |--------------------------------------------------------------------------
            | Descoberta automática de recursos, páginas e widgets
            |--------------------------------------------------------------------------
            */
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->pages([
                Pages\Dashboard::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | Middleware da aplicação
            |--------------------------------------------------------------------------
            | Usa o grupo "web" que já inclui:
            | - EncryptCookies
            | - StartSession
            | - ShareErrorsFromSession
            | - VerifyCsrfToken
            | - SubstituteBindings
            |
            | Assim, evita múltiplos StartSession e perda de sessão.
            |--------------------------------------------------------------------------
            */
            ->middleware([
                'web', // ✅ usa o mesmo stack do app
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | Middleware de autenticação e regras extras
            |--------------------------------------------------------------------------
            */
            ->authMiddleware([
                \Illuminate\Auth\Middleware\Authenticate::class . ':web',
                //EnsureUserActive::class,
                EnsureDashrashOne::class,
            ]);
    }
}
