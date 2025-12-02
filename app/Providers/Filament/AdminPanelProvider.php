<?php

namespace App\Providers\Filament;

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
            ->id('pxinadm')
            ->path('pxinadm')

            /*
            |--------------------------------------------------------------------------
            | Autenticação
            | Usa o guard "web" da aplicação (login próprio do app)
            | NÃO usa a página de login do Filament.
            |--------------------------------------------------------------------------
            */
            ->authGuard('web')
            // ->login() ❌ NÃO usar login do Filament

            /*
            |--------------------------------------------------------------------------
            | Branding e layout
            |--------------------------------------------------------------------------
            */
            ->brandName('TrustCash')
            ->colors([
                'primary' => Color::Green,
            ])
            ->maxContentWidth(Width::Full)
            ->topNavigation()

            /*
            |--------------------------------------------------------------------------
            | Descoberta automática (Resources, Pages, Widgets)
            |--------------------------------------------------------------------------
            */
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets'
            )
            ->pages([
                Pages\Dashboard::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | Middleware principal
            |--------------------------------------------------------------------------
            | Usa apenas "web", evitando duplicação de sessão.
            |--------------------------------------------------------------------------
            */
            ->middleware([
                'web',
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
                EnsureDashrashOne::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | Plugins (mantido vazio por segurança)
            |--------------------------------------------------------------------------
            */
            ->plugins([]);
    }
}
