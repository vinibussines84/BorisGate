{{-- resources/views/errors/user-hold.blade.php --}}
@php
    /** Props opcionais
     *  $title  (string) — título
     *  $reason (string) — motivo do bloqueio
     *  $until  (string) — previsão de liberação
     */
    $title = $title ?? 'Conta bloqueada';
@endphp

<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <meta name="color-scheme" content="dark light" />
    <meta name="theme-color" content="#0a0f14" />
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])

    <style>
        /* ======= Ornamentos de fundo (full viewport) ======= */
        .grid-dots {
            background-image: radial-gradient(currentColor 1px, transparent 1.2px);
            background-size: 18px 18px;
            opacity: .08;
            mask-image: radial-gradient(ellipse at center, #000 40%, transparent 75%);
        }
        @keyframes floaty {
            0%   { transform: translateY(0) translateX(0) scale(1);    opacity:.35; }
            50%  { transform: translateY(-10px) translateX(6px) scale(1.03); opacity:.55; }
            100% { transform: translateY(0) translateX(0) scale(1);    opacity:.35; }
        }
        .glow { filter: blur(56px); animation: floaty 9s ease-in-out infinite; }
        .shine {
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.12), transparent);
            background-size: 200% 100%;
            animation: shine 2.3s ease-in-out infinite;
        }
        @keyframes shine { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

        /* Dá um “peso” no vidro para cobrir totalmente o fundo */
        .glass-bg { background: linear-gradient(180deg, rgba(22, 34, 28, .55), rgba(11, 17, 19, .55)); }
    </style>
</head>

<body class="min-h-dvh bg-[#0a0f14] text-zinc-100 antialiased selection:bg-emerald-500/30 selection:text-emerald-100">
    {{-- ======= Camadas de fundo (full screen) ======= --}}
    <div class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(1200px_600px_at_10%_-10%,rgba(16,185,129,.10),transparent),radial-gradient(1000px_500px_at_100%_110%,rgba(34,211,238,.08),transparent)]"></div>
        <div class="absolute -top-24 -left-24 w-[28rem] h-[28rem] rounded-full bg-emerald-500/15 glow"></div>
        <div class="absolute -bottom-32 -right-16 w-[30rem] h-[30rem] rounded-full bg-cyan-500/12 glow" style="animation-delay:.5s"></div>
        <div class="grid-dots absolute inset-0 text-white"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-black/10 via-black/0 to-black/30"></div>
    </div>

    {{-- ======= Conteúdo 100% da viewport ======= --}}
    <main class="relative z-10 min-h-dvh flex items-center justify-center px-6 py-10">
        <section class="w-full max-w-3xl">
            {{-- Card com vidro e borda iluminada --}}
            <div class="group relative rounded-3xl">
                <div class="absolute -inset-[1px] rounded-3xl bg-gradient-to-br from-emerald-500/35 via-emerald-400/10 to-cyan-400/35 opacity-80 blur-[10px] transition-opacity duration-500 group-hover:opacity-100"></div>

                <div class="relative rounded-3xl border border-white/10 bg-white/5 glass-bg backdrop-blur-2xl shadow-2xl">
                    {{-- Header --}}
                    <header class="p-7 sm:p-10">
                        <div class="flex items-start gap-5">
                            <div class="shrink-0 grid place-items-center w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/25">
                                <svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 2l9 4v6c0 5-3.8 9.7-9 10-5.2-.3-9-5-9-10V6l9-4z" fill="currentColor" class="text-emerald-400/75"/>
                                    <path d="M10.2 9.8a1.8 1.8 0 113.6 0v3.6a1.8 1.8 0 11-3.6 0V9.8z" fill="#0a0f14"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight">{{ $title }}</h1>
                                <p class="mt-2 text-sm sm:text-base text-zinc-400">
                                    Sua conta está em análise por política interna. Isso é temporário.
                                </p>
                            </div>
                        </div>
                    </header>

                    <div class="h-px w-full bg-gradient-to-r from-transparent via-white/15 to-transparent"></div>

                    {{-- Corpo --}}
                    <div class="p-7 sm:p-10 pt-6 sm:pt-8 space-y-6">
                        <p class="text-zinc-300 leading-relaxed text-[15px] sm:text-[16px]">
                            Por segurança, restringimos o acesso até a revisão ser concluída. Tente novamente mais tarde.
                        </p>

                        <ul class="space-y-3">
                            @isset($reason)
                                <li class="flex items-start gap-3">
                                    <span class="mt-1 h-2 w-2 rounded-full bg-emerald-400/80 ring-1 ring-emerald-300/40"></span>
                                    <span class="text-sm text-zinc-300">
                                        <span class="text-zinc-400">Motivo:</span>
                                        <span class="ml-1">{{ $reason }}</span>
                                    </span>
                                </li>
                            @endisset

                            @isset($until)
                                <li class="flex items-start gap-3">
                                    <span class="mt-1 h-2 w-2 rounded-full bg-cyan-400/80 ring-1 ring-cyan-300/40"></span>
                                    <span class="text-sm text-zinc-300">
                                        <span class="text-zinc-400">Previsão de liberação:</span>
                                        <time class="ml-1">{{ $until }}</time>
                                    </span>
                                </li>
                            @endisset
                        </ul>

                        <div class="rounded-xl border border-yellow-400/25 bg-yellow-400/10 p-3.5 text-[13px] text-yellow-100/90">
                            ⏳ Em alguns casos, a análise pode solicitar informações adicionais.
                        </div>
                    </div>

                    {{-- Ações --}}
                    <footer class="p-7 sm:p-10 pt-4 sm:pt-6">
                        <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                            <a href="{{ url()->previous() }}"
                               class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium
                                      bg-white/5 hover:bg-white/10 border border-white/10 transition
                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50">
                                <svg width="18" height="18" viewBox="0 0 24 24" class="opacity-80" aria-hidden="true">
                                    <path fill="currentColor" d="M10 19l-7-7 7-7v4h8v6h-8v4z"/>
                                </svg>
                                Voltar
                            </a>

                            <a href="{{ url('/') }}"
                               class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold
                                      bg-emerald-500/90 hover:bg-emerald-500 text-emerald-50
                                      shadow-sm shadow-emerald-900/20 transition
                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/60">
                                <svg width="18" height="18" viewBox="0 0 24 24" class="opacity-90" aria-hidden="true">
                                    <path fill="currentColor" d="M10 17l5-5-5-5v3H3v4h7v3zm9 4H12v-2h7V5h-7V3h7a2 2 0 012 2v14a2 2 0 01-2 2z"/>
                                </svg>
                                Início
                            </a>

                            <div class="sm:ml-auto"></div>

                            <p class="text-[12px] text-zinc-500">Laravel · Segurança</p>
                        </div>

                        <div class="mt-6 h-px w-full shine opacity-15 rounded-full"></div>
                    </footer>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
