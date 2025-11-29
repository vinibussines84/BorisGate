import React, { useMemo, useState } from "react";
import { Head, useForm } from "@inertiajs/react";
import { Lock, EnvelopeSimple, ArrowLeft } from "@phosphor-icons/react";

/* === Glow helper (brilhos verdes) === */
const Glow = ({ className = "" }) => (
  <div className={`pointer-events-none absolute rounded-full blur-3xl ${className}`} />
);

/* === Partículas (“estrelas”) verdes suaves === */
const Sparkles = () => {
  const dots = useMemo(
    () =>
      Array.from({ length: 26 }).map((_, i) => ({
        left: `${Math.random() * 100}%`,
        top: `${Math.random() * 100}%`,
        size: Math.random() * 2 + 1,
        delay: Math.random() * 3,
        duration: 2.5 + Math.random() * 2,
        opacity: 0.18 + Math.random() * 0.25,
      })),
    []
  );

  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden">
      {dots.map((d, idx) => (
        <span
          key={idx}
          className="absolute rounded-full bg-emerald-400/80 animate-pulse"
          style={{
            left: d.left,
            top: d.top,
            width: d.size,
            height: d.size,
            opacity: d.opacity,
            animationDuration: `${d.duration}s`,
            animationDelay: `${d.delay}s`,
          }}
        />
      ))}
    </div>
  );
};

export default function ForgotPassword({ status }) {
  const { data, setData, post, processing, errors } = useForm({ email: "" });
  const [sent, setSent] = useState(false);

  const submit = (e) => {
    e.preventDefault();

    post("/forgot-password", {
      onSuccess: () => setSent(true),
    });
  };

  return (
    <div className="min-h-screen w-full relative overflow-hidden bg-black text-white">
      <Head title="Recuperar senha — JcBank" />

      {/* Fundo com brilhos */}
      <Glow className="w-[36rem] h-[36rem] bg-emerald-500/15 -top-40 -left-40" />
      <Glow className="w-[28rem] h-[28rem] bg-emerald-400/10 top-1/4 -right-24" />
      <Glow className="w-[30rem] h-[30rem] bg-emerald-600/10 -bottom-36 left-1/3" />
      <Sparkles />

      {/* Grade */}
      <div className="pointer-events-none absolute inset-0 opacity-[0.06]">
        <div
          className="absolute inset-0"
          style={{
            backgroundImage:
              "linear-gradient(to right, #16a34a 1px, transparent 1px), linear-gradient(to bottom, #16a34a 1px, transparent 1px)",
            backgroundSize: "80px 80px",
          }}
        />
      </div>

      {/* Conteúdo */}
      <div className="min-h-screen w-full flex items-center justify-center p-4 relative">
        <div className="w-full max-w-[480px]">
          {/* Cabeçalho */}
          <div className="mb-6">
            <div className="flex items-center gap-2">
              <div className="size-9 rounded-xl bg-white/5 border border-emerald-400/20 flex items-center justify-center shadow-[0_0_30px_-12px_rgba(16,185,129,0.45)]">
                <Lock size={18} className="text-white" />
              </div>
              <span className="text-sm text-emerald-200/80">Área segura .</span>
            </div>

            <h1 className="mt-3 text-[30px] leading-tight font-black">Recuperar acesso</h1>
            <p className="text-[15px] text-white/70">
              Informe seu e-mail para enviarmos um link de redefinição de senha.
            </p>
          </div>

          {/* Card */}
          <div className="rounded-2xl border border-emerald-400/15 bg-white/[0.03] backdrop-blur-md shadow-[0_0_1px_0_rgba(255,255,255,0.2),0_20px_80px_-30px_rgba(16,185,129,0.45)] p-5">
            {(status || sent) && (
              <div className="mb-4 rounded-lg border border-emerald-400/30 bg-emerald-500/10 text-emerald-200 text-sm px-3 py-2">
                {status || "Se existir uma conta com este e-mail, enviaremos o link em instantes."}
              </div>
            )}

            <form onSubmit={submit} className="space-y-5">
              {/* Email */}
              <div>
                <label htmlFor="email" className="block text-xs mb-1.5 text-white/70">
                  E-mail
                </label>

                <div
                  className={`relative rounded-xl border bg-[#0e0e0e] transition-all focus-within:ring-2 focus-within:ring-emerald-400/50 ${
                    errors.email ? "border-red-500/70" : "border-white/10"
                  }`}
                >
                  <div className="absolute inset-y-0 left-3 flex items-center">
                    <EnvelopeSimple size={18} className="text-white/50" />
                  </div>

                  <input
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    onChange={(e) => setData("email", e.target.value)}
                    placeholder="seuemail@exemplo.com"
                    required
                    className="w-full rounded-xl bg-transparent pl-10 pr-3 py-3 text-[15px] placeholder-white/40 outline-none"
                    autoFocus
                  />
                </div>

                {errors.email && (
                  <p className="text-red-400 text-xs mt-1.5">{errors.email}</p>
                )}
              </div>

              {/* AÇÕES */}
              <div className="flex items-center justify-between gap-3">
                <a
                  href="/login"
                  className="inline-flex items-center gap-2 px-3 h-11 rounded-xl border border-white/10 bg-white/[0.04] hover:bg-white/[0.07] text-sm transition-colors"
                >
                  <ArrowLeft size={16} /> Voltar ao login
                </a>

                <button
                  type="submit"
                  disabled={processing}
                  className="group relative h-11 px-4 rounded-xl border border-emerald-400/20 bg-emerald-500/10 hover:bg-emerald-500/15 transition-all font-semibold disabled:opacity-60"
                >
                  <span className="relative z-10">
                    {processing ? "Enviando..." : "Enviar link de redefinição"}
                  </span>

                  <span className="absolute inset-0 rounded-xl bg-gradient-to-r from-emerald-400/0 via-emerald-400/10 to-emerald-400/0 opacity-0 group-hover:opacity-100 transition-opacity" />
                </button>
              </div>
            </form>

            <p className="mt-4 text-[12px] text-white/55">
              Dica: verifique também a caixa de spam/lixo eletrônico.
            </p>
          </div>

          {/* Rodapé */}
          <p className="mt-6 text-center text-[12px] text-white/45">
            Precisa de ajuda?{" "}
            <a href="/support" className="text-emerald-300 underline underline-offset-2">
              Fale com o suporte
            </a>.
          </p>
        </div>
      </div>
    </div>
  );
}
