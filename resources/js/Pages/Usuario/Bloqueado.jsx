// resources/js/Pages/Usuario/Bloqueado.jsx
import React from "react";
import { Head, router } from "@inertiajs/react";

export default function Bloqueado() {
  return (
    <>
      <Head title="Indisponibilidade Global de Rede" />

      {/* Estilos de animação vazios (mantidos para estrutura) */}
      <style>{`
        /* Animações removidas para manter a página estática */
      `}</style>

      <div className="min-h-screen bg-[#0B0B0B] text-gray-200 flex items-center justify-center p-6">
        <div className="max-w-lg w-full text-center"> 

          {/* BADGE: Status Inativo (centralizado automaticamente por text-center) */}
          {/* Removido: mb-4 */}
          <span className="inline-flex items-center px-3 py-1 mb-2 rounded-full text-xs font-medium bg-red-900/40 text-red-300 border border-red-800/60">
            Status: Inativo
          </span>

          {/* REMOVIDO: O bloco do Ícone (martelinho/chave de boca) */}
          {/*
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-white/5 border border-white/10 mb-6">
            <svg ... >
              <path ... />
              <circle ... />
            </svg>
          </div>
          */}

          <h1 className="text-3xl font-semibold text-white">
            Indisponibilidade Global de Rede
          </h1>

          <p className="mt-3 text-gray-400 text-lg">
            Devido a falhas temporárias na Cloudflare e em grandes provedores de DNS, muitos sites estão fora do ar. Estamos aguardando a estabilização da infraestrutura de rede para garantir o funcionamento do nosso sistema.
          </p>
          
          {/* NOVO AVISO DE INSTABILIDADE */}
          <div className="mt-4 p-4 rounded-xl bg-red-900/40 border border-red-800/60 text-sm text-red-300">
              ⚠️ Atenção: Devido à instabilidade externa, evite fazer novas transações ou saques neste momento. Qualquer tentativa pode resultar em erros ou falhas no processamento.
          </div>

          <div className="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            {/* 🔹 Corrigido: usar POST em vez de GET */}
            <button
              onClick={() => router.post("/logout")}
              className="px-5 py-2 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-sm"
            >
              Sair da conta
            </button>

            <button
              onClick={() => router.visit("/")}
              className="px-5 py-2 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-sm"
            >
              Tentar novamente
            </button>
          </div>

          <p className="mt-6 text-xs text-gray-500">
            Aguardando a resolução do incidente na infraestrutura externa
          </p>
        </div>
      </div>
    </>
  );
}