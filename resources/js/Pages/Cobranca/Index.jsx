// resources/js/Pages/Cobranca/Index.jsx
import React from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
  Receipt,
  Info,
} from "lucide-react";

// Removidas todas as importações e componentes auxiliares não utilizados.

/* ================= Micro UI ================= */
function PageTitle({ icon: Icon, title, subtitle }) {
  return (
    <div className="flex items-center gap-3">
      <div className="p-2.5 rounded-2xl border border-zinc-800 bg-zinc-950">
        <Icon className="w-5 h-5 text-zinc-300" />
      </div>
      <div className="leading-tight">
        <h1 className="text-[20px] font-semibold text-zinc-100 tracking-tight">{title}</h1>
        <p className="text-[12px] text-zinc-500 font-light">{subtitle}</p>
      </div>
    </div>
  );
}

/* ================= Página Modificada ================= */
export default function CobrancaIndex() {
  // A funcionalidade está desabilitada, a lógica foi removida.

  return (
    <AuthenticatedLayout>
      <Head title="Billing" />
      
      {/* Container principal sem rolagem desnecessária */}
      <div className="flex flex-col items-center justify-center h-full w-full py-20 px-4 sm:px-6 lg:px-8 text-zinc-100">
        <div className="mx-auto w-full max-w-5xl space-y-6">
          
          {/* Header simples (mantido para contexto no layout) */}
          <div className="flex items-center justify-start">
            <PageTitle
              icon={Receipt}
              title="Billing"
              subtitle="Generate Pix charges and share 'copy and paste'."
            />
          </div>

          {/* Aviso Centralizado Elegante */}
          <div className="flex flex-col items-center justify-center py-20 rounded-3xl border border-zinc-800 bg-zinc-950/80 backdrop-blur">
            <Info className="w-12 h-12 text-zinc-500 mb-4" />
            <p className="text-xl text-zinc-300 font-semibold mb-2">
              Billing is currently disabled.
            </p>
            <p className="text-sm text-zinc-500 max-w-md text-center">
              This feature is temporarily unavailable or under maintenance. We apologize for the inconvenience.
            </p>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}