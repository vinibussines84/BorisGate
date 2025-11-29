// resources/js/Pages/Configuracoes/Index.jsx
import React from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { ShieldCheck, UserCog, KeyRound, ArrowRight } from "lucide-react";

export default function Configuracoes({ rpnet_user }) {
  return (
    <AuthenticatedLayout>
      <Head title="Configurações" />
      <div className="min-h-screen bg-[#0B0B0B] text-gray-100">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <h1 className="text-2xl font-semibold mb-6">Configurações</h1>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* ----- Conta (vai para /dados-conta) ----- */}
            <Link
              href="/dados-conta"
              aria-label="Abrir configurações da conta"
              className="group rounded-2xl border border-white/10 p-5 bg-white/[0.04] hover:bg-white/[0.06] transition shadow-[0_10px_30px_-12px_rgba(0,0,0,0.45)] focus:outline-none focus:ring-2 focus:ring-emerald-600/40"
            >
              <div className="flex items-center gap-3 mb-2">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl border border-white/10 bg-white/[0.06]">
                  <UserCog className="w-5 h-5" />
                </div>
                <h2 className="text-lg font-medium">Conta</h2>
                <ArrowRight className="ml-auto w-4 h-4 opacity-70 group-hover:translate-x-0.5 transition" />
              </div>
              <p className="text-sm text-gray-300">
                Ajuste informações básicas e dados de identificação.
              </p>
            </Link>

            {/* ----- Chaves de API (vai para /api) ----- */}
            <Link
              href="/api"
              aria-label="Abrir chaves de API"
              className="group rounded-2xl border border-white/10 p-5 bg-white/[0.04] hover:bg-white/[0.06] transition shadow-[0_10px_30px_-12px_rgba(0,0,0,0.45)] focus:outline-none focus:ring-2 focus:ring-emerald-600/40"
            >
              <div className="flex items-center gap-3 mb-2">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl border border-white/10 bg-white/[0.06]">
                  <KeyRound className="w-5 h-5" />
                </div>
                <h2 className="text-lg font-medium">Chaves de API</h2>
                <ArrowRight className="ml-auto w-4 h-4 opacity-70 group-hover:translate-x-0.5 transition" />
              </div>
              <p className="text-sm text-gray-300">
                Gere e visualize suas chaves para integrações.
              </p>
            </Link>
          </div>

          {/* ----- Banner de evolução ----- */}
          <div className="mt-8 rounded-2xl border border-emerald-700/30 bg-emerald-600/10 p-4 flex items-start gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-700/30 bg-emerald-600/15">
              <ShieldCheck className="w-5 h-5 text-emerald-400" />
            </div>
            <div className="min-w-0">
              <div className="text-sm font-medium text-emerald-300">
                Estamos sempre em evolução
              </div>
              <p className="text-sm text-emerald-200/90">
                Em breve: <span className="font-medium">transferência interna</span> e{" "}
                <span className="font-medium">conta digital própria</span>.
              </p>
            </div>
          </div>

          {/* (Opcional) detalhe técnico/ambiente */}
          {typeof rpnet_user !== "undefined" && (
            <div className="mt-8 text-xs text-white/60">
              Usuário RPNet: {rpnet_user?.name ?? "—"}
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
