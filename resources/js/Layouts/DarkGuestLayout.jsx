// resources/js/Layouts/DarkGuestLayout.jsx
import React from "react";

/**
 * Shell de autenticação escuro, sem logo e sem cartão branco padrão.
 * Usa gradientes suaves e glows esmeralda.
 */
export default function DarkGuestLayout({ children }) {
  return (
    <div className="min-h-screen relative bg-[#0A0A0A] text-gray-100 overflow-hidden">
      {/* Gradiente de topo bem suave */}
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(1000px_500px_at_50%_-200px,rgba(16,185,129,0.12),transparent_60%)]" />
      {/* Glow lateral */}
      <div className="pointer-events-none absolute -right-40 top-1/3 w-[520px] h-[520px] rounded-full blur-3xl opacity-25 bg-emerald-500/20" />
      {/* Glow lateral oposto */}
      <div className="pointer-events-none absolute -left-40 bottom-1/4 w-[420px] h-[420px] rounded-full blur-3xl opacity-15 bg-white/10" />

      {/* Conteúdo centralizado */}
      <div className="relative z-10 mx-auto w-full max-w-xl px-4 py-16 md:py-24">
        {children}
      </div>
    </div>
  );
}
