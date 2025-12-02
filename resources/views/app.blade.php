<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <meta name="app-name" content="{{ config('app.name', 'Trust Gate') }}">
    <title inertia>{{ config('app.name', 'PixionPay') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <script>
        // Use uma query mais robusta para garantir que o token seja encontrado
        // O selector deve ser `meta[name="csrf-token"]`
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            window.csrfToken = csrfMeta.getAttribute('content');
        } else {
            console.error('CSRF token meta tag not found.');
        }
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />

    <link rel="icon" type="image/png" sizes="192x192" href="/web-app-manifest-192x192.png" />
    <link rel="icon" type="image/png" sizes="512x512" href="/web-app-manifest-512x512.png" />

    <link rel="manifest" href="/site.webmanifest" />
    <meta name="theme-color" content="#3b36d1">

    {{-- Habilite APENAS se usar route() dentro do React --}}
    {{-- @routes --}}

    @viteReactRefresh
    @vite([
        'resources/js/app.jsx',
        "resources/js/Pages/{$page['component']}.jsx"
    ])

    @inertiaHead
</head>

<body class="font-sans antialiased bg-gray-950 text-gray-100 selection:bg-emerald-500/20">

    @inertia

    {{-- O script de CSRF foi movido para o <head> --}}

</body>
</html>