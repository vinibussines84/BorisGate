// resources/js/Pages/Transferencia.jsx
import React from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { AlertTriangle, ArrowLeft } from "lucide-react";

export default function Transferencia() {
  return (
    <AuthenticatedLayout>
      <Head title="Transferência" />

      {/* OBS: não use min-h-screen aqui; o layout já provê padding e fluxo */}
      <section className="relative w-full text-white">
        {/* --- BACKGROUND LAYERS (mais clean) --- */}
        <div className="pointer-events-none absolute inset-0 -z-10">
          {/* glows bem sutis e menores */}
          <div className="absolute -top-24 -left-24 h-64 w-64 rounded-full bg-pink-500/10 blur-2xl" />
          <div className="absolute -bottom-24 -right-24 h-64 w-64 rounded-full bg-purple-500/10 blur-2xl" />
          {/* grade bem leve */}
          <div
            aria-hidden
            className="absolute inset-0 opacity-[0.04] [background:radial-gradient(60rem_60rem_at_50%_-15%,#fff_0,transparent_60%)]"
          />
          <div
            aria-hidden
            className="absolute inset-0 bg-[linear-gradient(to_right,transparent_0,transparent_49%,rgba(255,255,255,.05)_50%,transparent_51%),linear-gradient(to_bottom,transparent_0,transparent_49%,rgba(255,255,255,.05)_50%,transparent_51%)] bg-[length:40px_40px]"
          />
        </div>

        {/* --- CONTENT --- */}
        {/* use apenas uma coluna central com espaçamento normal */}
        <div className="mx-auto max-w-3xl px-1 sm:px-2 lg:px-4">
          <div className="mx-auto max-w-xl rounded-2xl border border-white/10 bg-white/[0.04] p-6 sm:p-8 shadow-none">
            {/* Badge */}
            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs text-zinc-300">
              <span className="inline-block h-2 w-2 animate-pulse rounded-full bg-pink-400" />
              Em desenvolvimento
            </div>

            {/* Icon */}
            <div className="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-xl border border-pink-500/30 bg-pink-500/10">
              <AlertTriangle size={24} className="text-pink-400" />
            </div>

            {/* Title */}
            <h1 className="text-center text-3xl sm:text-4xl font-light tracking-tight text-white">
              Transferência{" "}
              <span className="font-normal text-pink-300/90">em breve</span>
            </h1>

            {/* Subtitle */}
            <p className="mx-auto mt-4 max-w-md text-center text-sm leading-relaxed text-zinc-400">
              Estamos finalizando esta funcionalidade para garantir a melhor
              experiência e segurança. Enquanto isso, continue acompanhando seu
              saldo e seus pagamentos pelo painel.
            </p>

            {/* Actions */}
            <div className="mt-8 flex flex-col-reverse items-center justify-center gap-3 sm:flex-row">
              <button
                onClick={() => router.get("/dashboard")}
                className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-5 py-3 text-sm font-medium text-white transition hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-500/30"
              >
                <ArrowLeft size={18} />
                Voltar para o Início
              </button>

              <button
                disabled
                className="inline-flex cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-pink-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-70"
                title="Função em breve"
              >
                Transferir (breve)
              </button>
            </div>
          </div>
        </div>

        {/* particulas discretas */}
        <div className="pointer-events-none absolute inset-0 -z-10">
          <span className="absolute left-[15%] top-[22%] h-1.5 w-1.5 animate-ping rounded-full bg-white/25" />
          <span className="absolute left-[70%] top-[38%] h-1.5 w-1.5 animate-ping rounded-full bg-pink-300/50 [animation-delay:400ms]" />
          <span className="absolute left-[42%] top-[68%] h-1.5 w-1.5 animate-ping rounded-full bg-white/15 [animation-delay:700ms]" />
        </div>
      </section>
    </AuthenticatedLayout>
  );
}
