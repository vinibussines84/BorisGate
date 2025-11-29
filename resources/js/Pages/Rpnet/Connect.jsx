// resources/js/Pages/Rpnet/Connect.jsx
'use client';

import React from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ShieldAlert, Clock, LogIn, Link as LinkIcon } from 'lucide-react';

export default function RpnetConnect() {
  const { props } = usePage();
  const status = props?.status;

  const { post, processing } = useForm({});

  const handleRelogin = () => {
    post(route('logout'));
  };

  const handleReauthRpnet = () => {
    post(route('rpnet.connect.post'));
  };

  return (
    <div className="min-h-screen bg-[#0B0B0B] text-gray-100 flex items-center justify-center px-4">
      <Head title="Sessão expirada — JcBank" />

      {/* ====== CSS local para a animação do cadeado ====== */}
      <style>{`
        @keyframes lock-rise { 
          0% { transform: translateY(16px); opacity: 0; } 
          60% { opacity: 1; } 
          100% { transform: translateY(0); opacity: 1; } 
        }
        @keyframes shackle-swing {
          0% { transform: rotate(-8deg); }
          50% { transform: rotate(6deg); }
          100% { transform: rotate(0deg); }
        }
        @keyframes stroke-draw {
          0% { stroke-dasharray: 0 200; }
          100% { stroke-dasharray: 200 0; }
        }
        .animate-lock-rise { animation: lock-rise .9s ease-out both; }
        .animate-shackle-swing { animation: shackle-swing .8s ease-out .25s both; transform-origin: center bottom; }
        .animate-stroke-draw { animation: stroke-draw 1.2s ease-out .1s both; }
      `}</style>

      {/* Card */}
      <div className="relative max-w-xl w-full">
        {/* Glow / Aura */}
        <div className="absolute -inset-6 bg-gradient-to-b from-rose-600/10 via-rose-500/5 to-transparent blur-2xl rounded-[2rem] pointer-events-none" />

        <div className="relative bg-[#111111] border border-white/10 rounded-3xl shadow-2xl overflow-hidden">
          {/* Top strip */}
          <div className="h-1 bg-gradient-to-r from-rose-500/70 via-rose-400/60 to-rose-500/70" />

          {/* Conteúdo */}
          <div className="p-8 sm:p-10">
            {/* Ícone + título */}
            <div className="flex items-center gap-3 mb-6">
              <div className="p-3 bg-rose-600/20 rounded-2xl border border-rose-600/30">
                <ShieldAlert className="w-6 h-6 text-rose-400" />
              </div>
              <div>
                <h1 className="text-2xl font-extrabold text-white">
                  Sessão protegida — token expirado
                </h1>
                <p className="text-gray-400 text-sm">
                  Por segurança, é necessário renovar sua autenticação.
                </p>
              </div>
            </div>

            {/* Animação do cadeado */}
            <div className="mx-auto mb-6 flex items-center justify-center">
              <svg
                width="140"
                height="140"
                viewBox="0 0 140 140"
                className="drop-shadow-[0_0_12px_rgba(244,63,94,0.25)]"
                aria-hidden
              >
                {/* contorno suave */}
                <defs>
                  <linearGradient id="lg" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stopColor="rgba(244,63,94,0.9)" />
                    <stop offset="100%" stopColor="rgba(244,114,182,0.9)" />
                  </linearGradient>
                </defs>

                {/* corpo */}
                <rect
                  x="32" y="58" rx="12" ry="12" width="76" height="66"
                  fill="url(#lg)" opacity="0.15"
                  className="animate-lock-rise"
                />
                <rect
                  x="32" y="58" rx="12" ry="12" width="76" height="66"
                  fill="none" stroke="url(#lg)" strokeWidth="2.5"
                  className="animate-stroke-draw"
                />

                {/* arco (haste) */}
                <path
                  d="M48,58 v-12 a22,22 0 1 1 44,0 v12"
                  fill="none" stroke="url(#lg)" strokeWidth="6" strokeLinecap="round"
                  className="animate-shackle-swing"
                />

                {/* cilindro/miolo */}
                <circle cx="70" cy="90" r="8" fill="url(#lg)" opacity="0.35" className="animate-lock-rise" />
                <rect x="66.5" y="90" width="7" height="16" rx="2" fill="url(#lg)" className="animate-lock-rise" />
              </svg>
            </div>

            {/* Mensagem de segurança */}
            <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4 mb-6">
              <div className="text-sm leading-relaxed text-gray-300">
                <p>
                  Para manter seus dados e saldo protegidos, encerramos sessões inativas ou com mudança de ambiente.
                  É rápido voltar: você pode <span className="text-white font-semibold">entrar novamente</span> ou apenas
                  <span className="text-white font-semibold"> reconectar RPNet</span>.
                </p>
                {status && (
                  <p className="mt-2 text-amber-300/90 bg-amber-500/10 border border-amber-500/30 rounded-lg px-3 py-2">
                    {status}
                  </p>
                )}
              </div>
            </div>

            {/* Ações */}
            <div className="flex flex-col sm:flex-row gap-3">
              <button
                type="button"
                onClick={handleRelogin}
                disabled={processing}
                className="group inline-flex items-center justify-center gap-2 rounded-2xl px-5 py-3
                           border border-rose-500/40 bg-rose-500/15 text-rose-100
                           hover:bg-rose-500/25 hover:border-rose-400 transition disabled:opacity-60 disabled:cursor-not-allowed"
              >
                <LogIn className="w-5 h-5" />
                <span className="font-semibold">Entrar novamente</span>
              </button>

              <button
                type="button"
                onClick={handleReauthRpnet}
                disabled={processing}
                className="group inline-flex items-center justify-center gap-2 rounded-2xl px-5 py-3
                           border border-gray-700 bg-white/[0.03] text-gray-100
                           hover:bg-white/[0.06] hover:border-gray-600 transition disabled:opacity-60 disabled:cursor-not-allowed"
              >
                <LinkIcon className="w-5 h-5" />
                <span className="font-semibold">Reconectar RPNet</span>
              </button>
            </div>

            {/* Diquinha discreta */}
            <p className="text-xs text-gray-500 mt-4 text-center">
              Se o problema persistir, limpe os cookies da página e recarregue.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
