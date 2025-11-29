// resources/js/Auth/SetupPin.jsx
import React, { useState } from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Shield, CheckCircle2, Loader2, ArrowLeft } from "lucide-react";
import axios from "axios";

export default function SetupPin() {
  const [pin, setPin] = useState("");
  const [pinConfirm, setPinConfirm] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");

  const onlyDigits = (v) => v.replace(/\D/g, "").slice(0, 6);
  const canSubmit = pin.length >= 4 && pin.length <= 6 && pin === pinConfirm;

  const postUrl = (() => {
    try {
      // usa a rota nomeada se o Ziggy estiver disponível
      return typeof route === "function" ? route("setup.pin.store") : "/setup/pin";
    } catch {
      return "/setup/pin";
    }
  })();

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!canSubmit || submitting) return;

    setSubmitting(true);
    setError("");

    try {
      await axios.post(postUrl, {
        pin,
        pin_confirmation: pinConfirm,
      });
      router.visit("/saques");
    } catch (err) {
      console.error(err);
      if (err?.response?.status === 422) {
        const errs = err.response.data?.errors || {};
        const msg =
          Object.values(errs).flat().join(" ") ||
          err.response.data?.message ||
          "Erro de validação. Verifique os campos.";
        setError(msg);
      } else if (err?.response?.status === 419) {
        setError("Sessão expirada. Faça login novamente.");
      } else if (err?.response?.status === 405) {
        setError("Método não permitido. Confirme se a rota POST /setup/pin existe.");
      } else {
        setError("Erro ao salvar PIN. Verifique os dados e tente novamente.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <AuthenticatedLayout>
      <Head title="Configurar PIN" />
      <div className="h-screen w-full flex items-center justify-center bg-[#0B0B0B] text-gray-100 overflow-hidden">
        <div className="w-full max-w-sm px-6 py-8 bg-[#101214]/80 border border-white/10 rounded-2xl backdrop-blur-md shadow-xl">
          {/* Header */}
          <div className="flex items-center gap-3 mb-6">
            <button
              type="button"
              onClick={() => router.visit("/saques")}
              className="p-2 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition"
            >
              <ArrowLeft size={18} />
            </button>
            <div>
              <h1 className="text-xl font-semibold text-white">Configurar PIN</h1>
              <p className="text-gray-400 text-sm">Defina um PIN para autorizar saques.</p>
            </div>
          </div>

          {/* Error */}
          {error && (
            <div className="text-xs text-rose-400 bg-rose-500/10 border border-rose-500/30 rounded-lg px-3 py-2 mb-4">
              {error}
            </div>
          )}

          {/* Form */}
          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label className="text-xs text-gray-400 flex items-center gap-1">
                <Shield size={12} /> PIN (4–6 dígitos)
              </label>
              <input
                type="password"
                inputMode="numeric"
                pattern="\d*"
                autoComplete="new-password"
                autoFocus
                value={pin}
                onChange={(e) => setPin(onlyDigits(e.target.value))}
                className="mt-1 w-full rounded-2xl bg-black/30 border border-white/10 px-3 py-3 text-base text-center tracking-widest text-white focus:border-emerald-500/40 outline-none"
                maxLength={6}
                placeholder="••••"
                required
              />
            </div>

            <div>
              <label className="text-xs text-gray-400 flex items-center gap-1">
                <Shield size={12} /> Confirmar PIN
              </label>
              <input
                type="password"
                inputMode="numeric"
                pattern="\d*"
                autoComplete="new-password"
                value={pinConfirm}
                onChange={(e) => setPinConfirm(onlyDigits(e.target.value))}
                className="mt-1 w-full rounded-2xl bg-black/30 border border-white/10 px-3 py-3 text-base text-center tracking-widest text-white focus:border-emerald-500/40 outline-none"
                maxLength={6}
                placeholder="••••"
                required
              />
            </div>

            <button
              type="submit"
              disabled={!canSubmit || submitting}
              className="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border border-emerald-500/30 bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 text-sm font-medium transition-all disabled:opacity-50"
            >
              {submitting ? (
                <Loader2 size={18} className="animate-spin" />
              ) : (
                <CheckCircle2 size={18} />
              )}
              {submitting ? "Salvando..." : "Salvar PIN"}
            </button>

            {/* Hint */}
            <p className="text-[11px] text-gray-500 text-center">
              O PIN é armazenado com hash (bcrypt) e nunca em texto puro.
            </p>
          </form>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
