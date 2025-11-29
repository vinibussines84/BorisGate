// resources/js/Pages/Errors/NotFound.jsx
import React from "react";
import { Head, router } from "@inertiajs/react";

export default function NotFound({ status = 404, url = "" }) {
  const currentUrl = url || (typeof window !== "undefined" ? window.location.href : "");

  return (
    <>
      <Head title="Página não encontrada" />

      {/* Keyframes locais para a animação */}
      <style>{`
        @keyframes cable-wiggle {
          0%, 100% { transform: translateX(0px) rotate(0deg); }
          25% { transform: translateX(-4px) rotate(-1.2deg); }
          50% { transform: translateX(3px) rotate(1deg); }
          75% { transform: translateX(-2px) rotate(-0.8deg); }
        }
        @keyframes plug-peek {
          0%   { transform: translateX(-18px); }
          45%  { transform: translateX(0px); }
          60%  { transform: translateX(4px); }   /* encosta e dá choque */
          75%  { transform: translateX(-6px); }
          100% { transform: translateX(-18px); }
        }
        @keyframes spark-flicker {
          0%, 100% { opacity: 0; transform: scale(0.6) translateY(0px); }
          45% { opacity: 0; transform: scale(0.6) translateY(0px); }
          60% { opacity: 1; transform: scale(1) translateY(-3px); }
          75% { opacity: .2; transform: scale(.85) translateY(-6px); }
          90% { opacity: 0; transform: scale(.6) translateY(-8px); }
        }
        @keyframes socket-glow {
          0%, 100% { filter: drop-shadow(0 0 0 rgba(16, 185, 129, 0)); }
          60% { filter: drop-shadow(0 0 10px rgba(16, 185, 129, .7)); }
          75% { filter: drop-shadow(0 0 3px rgba(16, 185, 129, .2)); }
        }
        .anim-wiggle { animation: cable-wiggle 2.2s ease-in-out infinite; transform-origin: 0% 50%; }
        .anim-plug   { animation: plug-peek 2.2s ease-in-out infinite; }
        .anim-spark  { animation: spark-flicker 2.2s ease-in-out infinite; }
        .anim-glow   { animation: socket-glow 2.2s ease-in-out infinite; }
      `}</style>

      <div className="min-h-screen bg-[#0B0B0B] text-gray-200 flex items-center justify-center p-6">
        <div className="max-w-lg w-full text-center">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/5 border border-white/10 mb-6">
            <span className="text-xl font-bold">{status}</span>
          </div>

          {/* Ilustração: cabo x tomada com faíscas */}
          <div className="relative mx-auto mb-8 w-full max-w-[520px]">
            <svg
              viewBox="0 0 520 180"
              className="w-full h-auto"
              aria-hidden="true"
              role="img"
            >
              {/* Cabo serpenteando (esquerda -> tomada) */}
              <path
                d="M10,120 C80,100 120,150 180,130 C240,110 260,140 300,130 C340,120 360,145 395,140"
                fill="none"
                stroke="rgba(255,255,255,0.25)"
                strokeWidth="6"
                className="anim-wiggle"
                strokeLinecap="round"
              />

              {/* Plug (movimenta tentando conectar) */}
              <g className="anim-plug" transform="translate(330,96)">
                {/* Corpo do plug */}
                <rect x="0" y="0" width="46" height="30" rx="6" fill="rgba(255,255,255,0.06)" stroke="rgba(255,255,255,0.35)" />
                {/* Pinos */}
                <rect x="44" y="7" width="10" height="5" rx="1" fill="#9ca3af" />
                <rect x="44" y="18" width="10" height="5" rx="1" fill="#9ca3af" />
                {/* Alívio do cabo */}
                <path d="M0,15 C-8,15 -12,15 -18,15" stroke="rgba(255,255,255,0.35)" strokeWidth="4" fill="none" strokeLinecap="round" />
                {/* Sombra suave */}
                <ellipse cx="20" cy="34" rx="22" ry="5" fill="rgba(0,0,0,0.35)" />
              </g>

              {/* Tomada na parede (lado direito) */}
              <g transform="translate(430,70)" className="anim-glow">
                <rect x="0" y="0" width="70" height="60" rx="10" fill="rgba(255,255,255,0.06)" stroke="rgba(255,255,255,0.2)" />
                {/* Furos */}
                <circle cx="26" cy="22" r="4" fill="#9ca3af" />
                <circle cx="26" cy="38" r="4" fill="#9ca3af" />
                <circle cx="44" cy="30" r="4" fill="#9ca3af" />
              </g>

              {/* Faíscas (aparecem quando encosta) */}
              <g transform="translate(420,95)">
                {/* Conjunto 1 */}
                <g className="anim-spark">
                  <path d="M0 0 L6 -4 L3 2 L9 5 L2 4 L0 10 L-2 4 L-9 5 L-3 2 L-6 -4 Z"
                        fill="rgba(16,185,129,0.9)" />
                </g>
                {/* Conjunto 2 (com leve offset) */}
                <g className="anim-spark" style={{ animationDelay: "0.05s" }} transform="translate(12,-4) rotate(15)">
                  <path d="M0 0 L6 -4 L3 2 L9 5 L2 4 L0 10 L-2 4 L-9 5 L-3 2 L-6 -4 Z"
                        fill="rgba(16,185,129,0.8)" />
                </g>
                {/* Conjunto 3 (offset diferente) */}
                <g className="anim-spark" style={{ animationDelay: "0.1s" }} transform="translate(-10,6) rotate(-12)">
                  <path d="M0 0 L6 -4 L3 2 L9 5 L2 4 L0 10 L-2 4 L-9 5 L-3 2 L-6 -4 Z"
                        fill="rgba(52,211,153,0.8)" />
                </g>
              </g>
            </svg>
          </div>

          <h1 className="text-2xl md:text-3xl font-semibold text-white">Página não encontrada</h1>
          <p className="mt-2 text-gray-400">
            O endereço <span className="text-gray-300 break-all">{currentUrl}</span> não existe.
          </p>

          <div className="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
            <button
              onClick={() => router.visit("/dashboard")}
              className="px-5 py-2 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-sm"
            >
              Ir para o Dashboard
            </button>
            <button
              onClick={() => window.history.back()}
              className="px-5 py-2 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-sm"
            >
              Voltar
            </button>
          </div>

          <p className="mt-6 text-xs text-gray-500">Código de erro: {status}</p>
        </div>
      </div>
    </>
  );
}
