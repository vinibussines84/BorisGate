// resources/js/Components/NovaCobrancaModal.jsx
import React, { useEffect, useState } from "react";
import { QrCode, PlusCircle, RefreshCw, X, Copy, Check, Info } from "lucide-react";
import axios from "axios";

/* ===================== Utils ===================== */
const BRL = (v) =>
  Number(v || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function isPixEmv(s = "") {
  const v = String(s).trim();
  if (v.length < 60) return false;
  const hasHeader = /^000201/.test(v);
  const hasDomain = /BR\.GOV\.BCB\.PIX/i.test(v);
  const hasCrc = /6304[0-9A-Fa-f]{4}$/.test(v);
  return hasHeader && hasDomain && hasCrc;
}

/** Extrai SOMENTE EMV do payload do provedor. */
function extractEmvFromResponse(data) {
  const candidates = [
    data?.cobranca?.qrcode,
    data?.cobranca?.qr_code_emv,
    data?.data?.qrCodeResponse?.qrcode,
    data?.data?.qr?.qrcode,
    data?.data?.qr_code,
    data?.qrcode,
    data?.emv,
  ].filter(Boolean);
  return candidates.find(isPixEmv) || null;
}

/* ===================== Bits de UI ===================== */
function FancyCheck({ checked, onChange, label, id }) {
  return (
    <button
      type="button"
      onClick={() => onChange(!checked)}
      className="group flex w-full items-start gap-3 text-left"
      aria-pressed={checked}
      aria-controls={id}
    >
      <span
        className={[
          "mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-md border transition",
          checked
            ? "bg-neutral-800 border-neutral-500 ring-1 ring-neutral-500/40 shadow-[0_0_0_3px_rgba(120,120,120,0.12)]"
            : "bg-[#0b0d12] border-white/15",
        ].join(" ")}
      >
        {checked ? <Check size={14} className="text-neutral-200" /> : null}
      </span>
      <span className="text-sm text-neutral-200 leading-relaxed">
        {label}
        <a
          href="#"
          onClick={(e) => e.preventDefault()}
          className="text-neutral-300 underline-offset-4 hover:underline ml-1"
        >
          termos de cobrança
        </a>
        .
      </span>
    </button>
  );
}

function MoneyInput({ label = "Valor (BRL)", value, onChange, autoFocus = false, id = "amount" }) {
  const formatMoneyInput = (raw) => {
    const digits = String(raw ?? "").replace(/[^\d]/g, "");
    if (!digits) return "0,00";
    const n = (Number(digits) / 100).toFixed(2);
    return n.replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  };
  const handleChange = (e) => onChange(formatMoneyInput(e.target.value));
  const moveCaretToEnd = (el) => {
    requestAnimationFrame(() => {
      const len = el.value.length;
      el.setSelectionRange(len, len);
    });
  };

  return (
    <div className="w-full">
      <label htmlFor={id} className="text-[12px] text-neutral-400">
        {label}
      </label>
      <div
        className={[
          "group mt-1.5 flex items-stretch rounded-2xl border bg-gradient-to-b",
          "from-[#0e1014] to-[#0b0d12] border-neutral-800/70",
          "shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]",
          "focus-within:border-neutral-600 focus-within:shadow-[0_0_0_3px_rgba(140,140,140,0.15)]",
          "transition",
        ].join(" ")}
      >
        <div className="pl-3 pr-2 flex items-center">
          <span className="text-neutral-300 text-sm select-none">R$</span>
        </div>

        <input
          id={id}
          autoFocus={autoFocus}
          value={value || "0,00"}
          onChange={handleChange}
          onFocus={(e) => moveCaretToEnd(e.target)}
          inputMode="decimal"
          autoComplete="off"
          spellCheck="false"
          className={[
            "flex-1 rounded-r-2xl bg-transparent px-3 py-2.5",
            "text-[20px] leading-none text-neutral-100 font-semibold tracking-wide tabular-nums",
            "placeholder:text-neutral-500 outline-none ring-0 focus:outline-none",
          ].join(" ")}
          aria-label="Valor da cobrança em reais"
        />
      </div>
      <p className="mt-1 text-[11px] text-neutral-500">Ex.: 25,90 • Use apenas números</p>
    </div>
  );
}

/* ===================== Modal ===================== */
export default function NovaCobrancaModal({ open, onClose, onSuccess }) {
  const [step, setStep] = useState("form"); // 'form' | 'success'
  const [sending, setSending] = useState(false);
  const [amount, setAmount] = useState("0,00");
  const [accept, setAccept] = useState(false);
  const [error, setError] = useState(null);

  const [emv, setEmv] = useState(null);
  const [txAmount, setTxAmount] = useState(0);
  const [copied, setCopied] = useState(false);
  const [progress, setProgress] = useState(0);

  // ESC / Enter
  useEffect(() => {
    if (!open) return;
    const onKey = (e) => {
      if (e.key === "Escape") onClose?.();
      if (e.key === "Enter" && step === "form" && !sending) {
        const btn = document.getElementById("btn-gerar-cobranca");
        btn?.click();
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, step, sending, onClose]);

  // reset ao abrir
  useEffect(() => {
    if (!open) return;
    setStep("form");
    setSending(false);
    setAmount("0,00");
    setAccept(false);
    setError(null);
    setEmv(null);
    setTxAmount(0);
    setCopied(false);
    setProgress(0);
  }, [open]);

  // Barra de progresso monocromática
  useEffect(() => {
    if (!sending) return;
    setProgress(12);
    const id = setInterval(() => {
      setProgress((p) => (p < 88 ? Math.min(88, p + 9) : p));
    }, 180);
    return () => clearInterval(id);
  }, [sending]);

  // parse "1.234,56" -> 1234.56
  const parseMoneyToFloat = (s) => {
    if (!s) return 0;
    const cleaned = String(s).replace(/\./g, "").replace(",", ".");
    return Number(cleaned || 0);
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    setError(null);

    const value = parseMoneyToFloat(amount);
    if (!value || value <= 0) {
      setError("Informe um valor válido.");
      return;
    }
    if (!accept) {
      setError("Você precisa aceitar os termos para continuar.");
      return;
    }

    try {
      setSending(true);
      const { data } = await axios.post(
        "/cobranca",
        { amount: value, acceptTerms: true },
        { headers: { Accept: "application/json" } }
      );

      if (!data?.success) {
        setError(data?.message || "Não foi possível criar a cobrança.");
        setSending(false);
        return;
      }

      const emvStr = extractEmvFromResponse(data);
      if (!emvStr) {
        setError("O provedor não retornou o texto Pix (copia e cola/EMV).");
        setSending(false);
        return;
      }

      setEmv(emvStr);
      setTxAmount(data?.cobranca?.amount ?? value);

      setProgress(100);
      setTimeout(() => {
        setStep("success");
        setError(null);
        onSuccess?.();
        setSending(false);
      }, 240);
    } catch (err) {
      setError(err?.response?.data?.message || err?.message || "Erro inesperado ao criar a cobrança.");
      setSending(false);
    }
  };

  const handleCopy = async () => {
    if (!emv) return;
    try {
      await navigator.clipboard.writeText(emv);
      setCopied(true);
      setTimeout(() => setCopied(false), 1600);
    } catch {}
  };

  if (!open) return null;

  return (
    <>
      {/* Overlay */}
      <div className="fixed inset-0 z-[80] bg-black/70 backdrop-blur-sm" />

      <div role="dialog" aria-modal="true" className="fixed inset-0 z-[81] flex items-center justify-center px-4">
        <div className="w-full max-w-lg overflow-hidden rounded-2xl border border-neutral-800/70 bg-gradient-to-b from-[#0d0f13] to-[#0a0b0e] shadow-[0_30px_120px_-20px_rgba(0,0,0,0.75)]">
          {/* Progress (monocromático) */}
          <div
            className="h-1 bg-gradient-to-r from-neutral-400 via-neutral-300 to-neutral-500 transition-all duration-300 ease-out"
            style={{ width: sending ? `${progress}%` : "0%" }}
          />

          {/* Header */}
          <div className="flex items-center justify-between px-5 py-4 border-b border-neutral-800/70">
            <div className="flex items-center gap-3">
              <div className="h-10 w-10 rounded-xl bg-neutral-900/70 border border-neutral-700 grid place-items-center shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]">
                <QrCode size={18} className="text-neutral-300" />
              </div>
              <div>
                <h3 className="text-[15px] font-semibold text-neutral-100 tracking-wide">
                  {step === "form" ? "Nova cobrança Pix" : "Cópia Pix gerada"}
                </h3>
                <p className="text-[11px] text-neutral-400">Compartilhe o texto “copia e cola”.</p>
              </div>
            </div>
            <button
              onClick={onClose}
              className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-neutral-800/70 hover:bg-white/5 transition"
              aria-label="Fechar"
            >
              <X size={16} className="text-neutral-300" />
            </button>
          </div>

          {/* Conteúdo */}
          {step === "form" ? (
            <form noValidate onSubmit={onSubmit} className="px-5 py-5 space-y-5">
              {error && (
                <div className="flex items-start gap-2 rounded-lg border border-red-500/30 bg-red-500/10 p-2.5 text-red-200 text-xs">
                  <Info size={14} className="mt-0.5 shrink-0" />
                  <p className="leading-snug">{error}</p>
                </div>
              )}

              <MoneyInput value={amount} onChange={setAmount} autoFocus />
              <FancyCheck checked={accept} onChange={setAccept} id="terms" label="Li e aceito os" />

              <div className="flex items-center justify-end gap-2 pt-1">
                <button
                  type="button"
                  onClick={onClose}
                  className="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs text-neutral-200 border border-neutral-800/70 hover:bg-white/5 transition"
                >
                  Cancelar
                </button>
                <button
                  id="btn-gerar-cobranca"
                  type="submit"
                  disabled={sending || !accept}
                  className={[
                    "relative overflow-hidden inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold",
                    "border border-neutral-700 bg-neutral-900 text-neutral-100",
                    "hover:bg-neutral-800 active:scale-[0.98] transition disabled:opacity-60",
                    "shadow-[0_10px_30px_-12px_rgba(120,120,120,0.28)]",
                  ].join(" ")}
                >
                  <span
                    className={[
                      "pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r",
                      "from-transparent via-white/10 to-transparent",
                      sending ? "animate-[shimmer_1.8s_linear_infinite]" : "",
                    ].join(" ")}
                  />
                  {sending ? (
                    <>
                      <RefreshCw size={14} className="animate-spin" />
                      Gerando…
                    </>
                  ) : (
                    <>
                      <PlusCircle size={15} />
                      Gerar cobrança
                    </>
                  )}
                </button>
              </div>
            </form>
          ) : (
            <div className="px-5 py-6 animate-[fadeIn_300ms_ease-out]">
              {!!error && (
                <div className="mb-3 flex items-start gap-2 rounded-lg border border-red-500/30 bg-red-500/10 p-2.5 text-red-200 text-xs">
                  <Info size={14} className="mt-0.5 shrink-0" />
                  <p className="leading-snug">{error}</p>
                </div>
              )}

              {/* Valor */}
              <div className="text-center pb-4 border-b border-white/5 mb-6">
                <p className="text-sm text-neutral-300 font-medium tracking-wider uppercase">Valor da Cobrança</p>
                <p className="text-4xl font-extrabold text-neutral-100 mt-1 tabular-nums">R$ {BRL(txAmount)}</p>
              </div>

              {/* EMV */}
              <div>
                <label className="text-[12px] text-neutral-400">Copia e cola (Pix)</label>
                <div className="mt-1.5 flex">
                  <textarea
                    readOnly
                    value={emv || ""}
                    rows={4}
                    className="w-full rounded-l-lg border border-neutral-800/70 bg-[#101216] px-3 py-2 text-[12px] text-neutral-100 font-mono"
                  />
                  <button
                    type="button"
                    onClick={handleCopy}
                    disabled={!emv}
                    className={[
                      "inline-flex items-center gap-2 rounded-r-lg px-4 py-2 text-sm font-semibold",
                      "bg-neutral-200 hover:bg-white text-black",
                      "shadow-[0_12px_30px_-10px_rgba(180,180,180,0.35)] transition disabled:opacity-50 disabled:shadow-none",
                    ].join(" ")}
                    title={emv ? "Copiar" : "Sem EMV disponível"}
                  >
                    {copied ? <Check size={16} /> : <Copy size={16} />}
                    {copied ? "EMV Copiado!" : "Copiar EMV"}
                  </button>
                </div>
                {!emv && <p className="mt-1 text-[11px] text-red-300">Não foi possível extrair o EMV desta cobrança.</p>}
              </div>

              <div className="mt-6 flex items-center justify-end">
                <button
                  type="button"
                  onClick={onClose}
                  className="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs text-neutral-200 border border-neutral-800/70 hover:bg-white/5 transition"
                >
                  Fechar
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Keyframes utilitárias */}
      <style>{`
        @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
      `}</style>
    </>
  );
}
