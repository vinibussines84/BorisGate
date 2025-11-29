import React, { useMemo, useState } from "react";
import {
  KeyRound,
  Wallet,
  Info,
  Zap,
  Copy,
  Check,
  Shield,
  Building2,
  ArrowRight,
} from "lucide-react";

export const PixKeyForm = ({
  pixKey,
  setPixKey,
  loading,
  error,
  pixData,
  handlePixKeyCheck,
  onProceed, // segue à etapa de valor
}) => {
  const [copied, setCopied] = useState(null);

  const keyHint = useMemo(() => {
    if (!pixKey) return "Ex: 123.456.789-00, email@exemplo.com ou +55 11 99999-9999";
    if (/@/.test(pixKey)) return "Detectamos e-mail. Continue para validar.";
    if (pixKey.replace(/\D/g, "").length >= 11) return "Detectamos CPF/CNPJ. Continue para validar.";
    return "Informe CPF, CNPJ, e-mail ou telefone.";
  }, [pixKey]);

  const copy = async (text, key) => {
    try {
      if (!navigator?.clipboard) throw new Error("Sem suporte ao clipboard");
      await navigator.clipboard.writeText(text || "");
      setCopied(key);
      setTimeout(() => setCopied(null), 1200);
    } catch {
      // fallback simples
      const ta = document.createElement("textarea");
      ta.value = text || "";
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand("copy");
        setCopied(key);
        setTimeout(() => setCopied(null), 1200);
      } finally {
        document.body.removeChild(ta);
      }
    }
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-[1fr_1px_0.8fr] gap-0">
      {/* Coluna esquerda (form) */}
      <div className="p-6 sm:p-8 lg:p-9">
        <div className="mb-8">
          <h2 className="text-4xl font-extralight text-white tracking-tight">
            Digite a <span className="font-semibold text-emerald-400">Chave Pix</span>
          </h2>
          <p className="mt-2 text-sm text-zinc-400">
            Validação instantânea, com segurança avançada.
          </p>
        </div>

        <form onSubmit={handlePixKeyCheck} className="max-w-xl" noValidate>
          <label
            htmlFor="pix-key-input"
            className="block text-[11px] uppercase tracking-wide text-zinc-400 mb-2"
          >
            Chave Pix
          </label>

          <div className="relative group">
            <div
              className="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-r
                         from-emerald-500/0 via-emerald-500/0 to-emerald-500/0
                         group-focus-within:via-emerald-500/10 transition-all"
            />
            <KeyRound
              size={18}
              className="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-500"
              aria-hidden
            />
            <input
              id="pix-key-input"
              type="text"
              autoFocus
              placeholder="Ex: 123.456.789-00 ou email@exemplo.com"
              value={pixKey}
              onChange={(e) => setPixKey(e.target.value)}
              className={`w-full pl-11 pr-12 py-3.5 rounded-2xl bg-[#141414]
                         border ${error ? "border-rose-500/40" : "border-white/10"}
                         focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20
                         text-white placeholder-zinc-600 shadow-sm outline-none transition`}
              aria-invalid={!!error}
              aria-describedby="pixkey-hint"
            />
            <span id="pixkey-hint" className="absolute -bottom-6 left-0 text-xs text-zinc-500">
              {keyHint}
            </span>
          </div>

          <div className="mt-10 flex flex-wrap items-center gap-3">
            <button
              type="submit"
              disabled={loading || !pixKey.trim()}
              className="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-2xl
                         bg-emerald-600 hover:bg-emerald-500 disabled:bg-emerald-700/40
                         text-white font-semibold border border-emerald-400/20
                         transition shadow-[0_8px_24px_-10px_rgba(16,185,129,0.45)]"
              aria-busy={loading}
            >
              {loading ? (
                <svg
                  className="animate-spin h-5 w-5"
                  viewBox="0 0 24 24"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="2" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
              ) : (
                <>
                  <Zap size={18} /> Validar chave
                </>
              )}
            </button>

            <button
              type="button"
              onClick={onProceed}
              disabled={!pixData}
              className="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-2xl
                         bg-white/[0.06] hover:bg-white/[0.1] text-white font-semibold
                         border border-white/10 disabled:opacity-50 transition"
              title={!pixData ? "Valide uma chave antes de prosseguir" : "Prosseguir"}
            >
              <ArrowRight size={18} /> Prosseguir
            </button>

            <div className="flex items-center gap-2 text-xs text-zinc-400">
              <Shield size={16} className="text-emerald-400" />
              <span>Protegido por verificação antifraude.</span>
            </div>
          </div>

          {!!error && (
            <div
              className="mt-6 p-3.5 rounded-xl border border-rose-700/50 bg-rose-900/20 text-rose-200 text-sm"
              role="alert"
              aria-live="polite"
            >
              {error}
            </div>
          )}
        </form>

        {/* Resultado da validação */}
        {pixData && (
          <div className="mt-10 max-w-xl rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <div className="flex items-center justify-between border-b border-white/10 pb-3 mb-4">
              <div className="flex items-center gap-3 min-w-0">
                <div className="h-10 w-10 rounded-xl bg-emerald-500/15 grid place-items-center text-emerald-300 font-semibold flex-shrink-0">
                  {String(pixData?.name || "?").slice(0, 1)}
                </div>
                <div className="min-w-0">
                  <p className="text-xs text-zinc-400">Destinatário encontrado</p>
                  <p className="text-sm font-medium text-white truncate">{pixData.name}</p>
                </div>
              </div>
              <span className="inline-flex items-center gap-1.5 text-[11px] px-2.5 py-1 rounded-full
                               border border-emerald-600/40 bg-emerald-600/10 text-emerald-300">
                <Building2 size={14} /> {pixData?.bankName || "Banco"}
              </span>
            </div>

            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div className="rounded-xl bg-white/[0.02] border border-white/10 p-3">
                <dt className="text-zinc-500">Documento</dt>
                <dd className="mt-0.5 text-white/90">{pixData.document || "—"}</dd>
              </div>
              <div className="rounded-xl bg-white/[0.02] border border-white/10 p-3">
                <dt className="text-zinc-500">Código ISPB</dt>
                <dd className="mt-0.5 text-white/90">{pixData.ispb || "—"}</dd>
              </div>
              <div className="rounded-xl bg-white/[0.02] border border-white/10 p-3">
                <dt className="text-zinc-500">ID da Chave</dt>
                <dd className="mt-0.5 flex items-center gap-2 text-white/90">
                  <span className="truncate" title={pixData.id}>
                    {pixData.id || "—"}
                  </span>
                  {!!pixData.id && (
                    <button
                      type="button"
                      onClick={() => copy(pixData.id, "id")}
                      className="p-1.5 rounded-lg hover:bg-white/5 border border-white/10"
                      title="Copiar"
                    >
                      {copied === "id" ? (
                        <Check size={16} className="text-emerald-400" />
                      ) : (
                        <Copy size={16} className="text-zinc-400" />
                      )}
                    </button>
                  )}
                </dd>
              </div>
              <div className="rounded-xl bg-white/[0.02] border border-white/10 p-3">
                <dt className="text-zinc-500">EndToEnd ID</dt>
                <dd className="mt-0.5 flex items-center gap-2 text-white/90">
                  <span className="truncate" title={pixData.endToEndId}>
                    {pixData.endToEndId || "—"}
                  </span>
                  {!!pixData.endToEndId && (
                    <button
                      type="button"
                      onClick={() => copy(pixData.endToEndId, "e2e")}
                      className="p-1.5 rounded-lg hover:bg-white/5 border border-white/10"
                      title="Copiar"
                    >
                      {copied === "e2e" ? (
                        <Check size={16} className="text-emerald-400" />
                      ) : (
                        <Copy size={16} className="text-zinc-400" />
                      )}
                    </button>
                  )}
                </dd>
              </div>
            </dl>
          </div>
        )}
      </div>

      {/* Divisor vertical */}
      <div className="hidden lg:block w-px bg-gradient-to-b from-transparent via-white/10 to-transparent" />

      {/* Aside (conteúdo informativo) */}
      <aside className="p-6 sm:p-8 lg:p-9 bg-transparent">
        <div className="flex items-center gap-3 mb-5">
          <Info size={20} className="text-sky-400" />
          <h3 className="text-base font-semibold text-white tracking-tight">
            Segurança e Agilidade
          </h3>
        </div>

        <p className="text-zinc-400 text-sm leading-relaxed mb-5" style={{ textAlign: "justify" }}>
          O <span className="text-emerald-400 font-semibold">Pix</span> é a forma mais rápida e
          segura de transferir dinheiro, disponível 24h por dia.
        </p>

        <ul className="text-sm space-y-3 text-zinc-300">
          <li className="flex items-start gap-2">
            <Zap size={16} className="text-emerald-400 mt-0.5" />
            <span>
              <strong>Velocidade:</strong> transferência concluída em segundos.
            </span>
          </li>
          <li className="flex items-start gap-2">
            <KeyRound size={16} className="text-emerald-400 mt-0.5" />
            <span>
              <strong>Simplicidade:</strong> só a chave já basta.
            </span>
          </li>
          <li className="flex items-start gap-2">
            <Wallet size={16} className="text-emerald-400 mt-0.5" />
            <span>
              <strong>Custo Zero:</strong> transações grátis para PF.
            </span>
          </li>
        </ul>

        <div className="mt-8 rounded-2xl border border-emerald-700/40 bg-emerald-800/10 p-4">
          <div className="flex items-center gap-2 text-emerald-300 text-sm">
            <span className="inline-block h-2 w-2 rounded-full bg-emerald-400 animate-pulse" />
            Serviço de consulta de chaves <span className="font-medium">ativo</span> e funcional.
          </div>
        </div>
      </aside>
    </div>
  );
};
