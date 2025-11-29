import React, { useMemo, useState, useRef, useEffect, useCallback } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import {
  ArrowLeft,
  CheckCircle2,
  Loader2,
  Eye,
  EyeOff,
  ShieldCheck,
  Info,
  User2,
  KeyRound,
  Hash,
  Copy,
  Printer,
  Check,
  X,
  Landmark,
  XCircle, // ⬅️ novo
} from "lucide-react";

/* ---------------- Utils ---------------- */
const onlyDigits = (v = "") => v.replace(/\D+/g, "");
const maskCPF = (v = "") => {
  const d = onlyDigits(v).slice(0, 11);
  return d
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
};
const formatBRL = (n) =>
  new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(
    isNaN(n) ? 0 : n
  );
const parseBRL = (str) => {
  if (typeof str === "number") return str;
  if (!str) return 0;
  const normalized = str
    .toString()
    .replace(/\./g, "")
    .replace(/,/g, ".")
    .replace(/[^0-9.\-]/g, "");
  const n = parseFloat(normalized);
  return isNaN(n) ? 0 : n;
};
const formatDateTime = (d = new Date()) =>
  new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "medium",
  }).format(d);

/* ---------------- PinInput ---------------- */
const PinInput = ({ length = 4, value, onChange, onComplete, disabled = false, showValue = false }) => {
  const LENGTH = length;
  const inputRefs = useRef([]);
  const [focusedIndex, setFocusedIndex] = useState(0);
  const pinArray = value.padEnd(LENGTH, " ").split("").slice(0, LENGTH);

  const handlePinChange = (e) => {
    const input = e.target.value;
    const digit = onlyDigits(input).slice(-1);
    if (digit && focusedIndex < LENGTH) {
      const newValue = value.slice(0, focusedIndex) + digit + value.slice(focusedIndex + 1);
      onChange(newValue.slice(0, LENGTH));
      if (focusedIndex < LENGTH - 1) {
        setFocusedIndex(focusedIndex + 1);
        inputRefs.current[focusedIndex + 1]?.focus();
      } else if (focusedIndex === LENGTH - 1) {
        inputRefs.current[focusedIndex]?.blur();
        if (newValue.length === LENGTH && onComplete) onComplete(newValue);
      }
    }
  };

  const handleKeyDown = (e, index) => {
    if (e.key === "Backspace") {
      e.preventDefault();
      const newPin = value.slice(0, index).padEnd(LENGTH, " ");
      onChange(newPin.slice(0, LENGTH).trim());
      if (index > 0) {
        setFocusedIndex(index - 1);
        inputRefs.current[index - 1]?.focus();
      } else {
        setFocusedIndex(0);
        inputRefs.current[0]?.focus();
      }
    }
  };

  useEffect(() => {
    inputRefs.current[0]?.focus();
  }, []);

  useEffect(() => {
    const nextIndex = value.length < LENGTH ? value.length : LENGTH - 1;
    setFocusedIndex(nextIndex);
  }, [value, LENGTH, disabled]);

  const focusCorrectInput = useCallback(() => {
    if (!disabled) {
      const indexToFocus = value.length < LENGTH ? value.length : LENGTH - 1;
      setFocusedIndex(indexToFocus);
      inputRefs.current[indexToFocus]?.focus();
    }
  }, [value.length, LENGTH, disabled]);

  return (
    <div
      className={`flex justify-center gap-2 sm:gap-3 p-2 rounded-2xl bg-[#0F0F10] border border-white/10 transition ${
        disabled ? "opacity-60" : "hover:border-emerald-500/50"
      }`}
      onClick={focusCorrectInput}
    >
      {pinArray.map((digit, index) => (
        <div
          key={index}
          className={`h-12 w-10 sm:w-12 overflow-hidden rounded-xl relative transition duration-150 ease-out border border-white/10 grid place-content-center bg-gradient-to-b from-white/5 to-white/[0.03] ${
            focusedIndex === index && !disabled ? "ring-2 ring-emerald-500/60 shadow-[0_0_20px_0_rgba(16,185,129,0.25)]" : ""
          }`}
        >
          <input
            ref={(el) => (inputRefs.current[index] = el)}
            type="tel"
            maxLength={1}
            value=""
            onChange={handlePinChange}
            onKeyDown={(e) => handleKeyDown(e, index)}
            onFocus={() => setFocusedIndex(index)}
            onBlur={() => setFocusedIndex(value.length)}
            disabled={disabled}
            className="absolute inset-0 opacity-0 cursor-default"
            inputMode="numeric"
            autoComplete="off"
            tabIndex={index + 1}
            aria-label={`Dígito ${index + 1} do PIN`}
          />
          <div className="text-3xl font-extrabold text-white select-none">
            {digit !== " " ? (showValue ? digit : "•") : <span className="text-zinc-700">—</span>}
          </div>
        </div>
      ))}
    </div>
  );
};

/* ---------------- Page (2 etapas + Saldo disponível) ---------------- */
export default function Amount() {
  const {
    key,
    name,
    document,
    keyInfoId,
    account_id,
    tenantId,
    bankName: bankNameProp,
    institutionName,
    bank,
    institution,
    ispb,
    bankIspb,
    bankCode,
    endToEndId: endToEndIdProp,
  } = usePage().props;

  const bankName = bankNameProp || institutionName || bank || institution || null;
  const bankISPB = bankIspb || ispb || bankCode || null;

  const [phase, setPhase] = useState("amount"); // amount | pin
  const [balanceLoading, setBalanceLoading] = useState(true);
  const [availableBalance, setAvailableBalance] = useState(0);

  const [amountInput, setAmountInput] = useState("");
  const amount = useMemo(() => parseBRL(amountInput), [amountInput]);
  const quick = [10, 25, 50, 100, 250, 500];

  const [pin, setPin] = useState("");
  const [showPin, setShowPin] = useState(false);

  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(null);
  const [error, setError] = useState(null);
  const [showReceipt, setShowReceipt] = useState(false);
  const [copied, setCopied] = useState(false);

  // ⬇️ novo estado do modal de cancelamento
  const [showCancelModal, setShowCancelModal] = useState(false);

  useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        setBalanceLoading(true);
        const res = await fetch("/api/rpnet/balance", {
          method: "GET",
          headers: { Accept: "application/json" },
          credentials: "include",
        });
        const data = await res.json();
        const bal = Number(data?.balance ?? 0);
        if (mounted) setAvailableBalance(bal);
      } catch {
        if (mounted) setAvailableBalance(0);
      } finally {
        if (mounted) setBalanceLoading(false);
      }
    })();
    return () => {
      mounted = false;
    };
  }, []);

  const getCsrfToken = () => {
    if (typeof window !== "undefined") {
      return (
        window.csrfToken ||
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
        ""
      );
    }
    return "";
  };

  const handleQuickAmount = (v) => setAmountInput(formatBRL(v));

  const handleConfirmAmount = (e) => {
    e.preventDefault();
    setError(null);
    if (!account_id) return setError("Conta bancária não encontrada na sessão.");
    if (!amount || amount <= 0) return setError("Informe um valor válido para o PIX.");
    if (amount > availableBalance) return setError("Valor acima do seu saldo disponível.");
    setPhase("pin");
  };

  const handleEditAmount = () => {
    setPhase("amount");
    setPin("");
    setError(null);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (phase !== "pin") return;
    setLoading(true);
    setError(null);
    setSuccess(null);

    try {
      const csrfToken = getCsrfToken();
      if (!account_id) throw new Error("Conta bancária não encontrada na sessão.");
      if (!amount || amount <= 0) throw new Error("Informe um valor válido para o PIX.");
      if (amount > availableBalance) throw new Error("Valor acima do seu saldo disponível.");
      if (pin.length < 4) throw new Error("PIN inválido. Informe todos os 4 dígitos.");

      const headers = {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN": csrfToken,
        "X-Requested-With": "XMLHttpRequest",
      };
      if (tenantId) headers["X-Tenant-Id"] = tenantId;

      const response = await fetch(`/api/stric/pix/payment/${account_id}`, {
        method: "POST",
        headers,
        credentials: "include",
        body: JSON.stringify({
          keyInfoId,
          amount: Number(amount.toFixed(2)),
          pin,
        }),
      });

      const data = await response.json().catch(() => {
        throw new Error("Resposta inválida do servidor.");
      });

      if (!response.ok || !data?.success) {
        throw new Error(data?.message || "Falha ao enviar o PIX.");
      }

      setSuccess(data.transaction);
      setShowReceipt(true);
    } catch (err) {
      setError(err.message || "Erro de conexão com o servidor.");
    } finally {
      setLoading(false);
    }
  };

  /* --------- Comprovante helpers --------- */
  const endToEnd = success?.endToEndId || success?.e2eId || endToEndIdProp || "—";
  const txId = success?.id || success?.transaction_id || "—";
  const txRef = success?.reference || success?.ref || null;
  const txAmount = success?.amount ?? amount;
  const txStatus = (success?.status || "paid").toString().toLowerCase();
  const txCreatedAt =
    success?.created_at || success?.createdAt || success?.date || new Date().toISOString();

  const copyEndToEnd = async () => {
    try {
      await navigator.clipboard.writeText(String(endToEnd));
      setCopied(true);
      setTimeout(() => setCopied(false), 1200);
    } catch {}
  };
  const printReceipt = () => window.print();

  // ⬇️ ação de confirmar cancelamento
  const confirmCancel = () => {
    setShowCancelModal(false);
    router.visit("/dashboard");
  };

  return (
    <>
      <Head title="Definir Valor do PIX" />

      {/* local styles for subtle animations */}
      <style>{`
        @keyframes floatUp { from { transform: translateY(8px); opacity: .0 } to { transform: translateY(0); opacity: 1 } }
        .animate-floatUp { animation: floatUp .5s ease-out both }
        .btn-gradient {
          background-image: linear-gradient(90deg, rgba(16,185,129,1) 0%, rgba(5,150,105,1) 100%);
        }
        .btn-gradient:hover { filter: brightness(1.05); }
        .glow-card { box-shadow: 0 18px 50px -20px rgba(0,0,0,.6), 0 0 0 1px rgba(255,255,255,.06) inset; }
        .chip:hover { box-shadow: 0 0 22px rgba(16,185,129,.18); }
      `}</style>

      <div className="relative min-h-screen bg-[#0A0A0A] text-white">
        {/* Glows */}
        <div className="pointer-events-none absolute -top-20 -left-16 h-72 w-72 rounded-full bg-emerald-500/10 blur-3xl" />
        <div className="pointer-events-none absolute -bottom-24 -right-12 h-72 w-72 rounded-full bg-sky-500/10 blur-3xl" />

        {/* Header */}
        <div className="sticky top-0 z-30 backdrop-blur supports-[backdrop-filter]:bg-white/5 bg-white/0 border-b border-white/10">
          <div className="container mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center gap-3">
            <button
              onClick={() => router.visit("/pix/send")}
              className="p-2 rounded-xl text-zinc-300 hover:text-white hover:bg-white/10 transition"
            >
              <ArrowLeft size={20} />
            </button>
            <div className="h-9 w-9 rounded-xl border border-white/10 bg-white/[0.06] grid place-items-center">
              <CheckCircle2 size={18} className="text-emerald-400" />
            </div>
            <div className="flex-1">
              <h1 className="text-lg sm:text-xl font-semibold tracking-tight">
                {phase === "amount" ? "Definir valor do PIX" : "Confirme com seu PIN"}
              </h1>
              <p className="text-xs text-zinc-400">
                {phase === "amount"
                  ? "Informe o valor. Você confirmará com PIN na próxima etapa."
                  : "Digite seu PIN para autorizar o envio do PIX."}
              </p>
            </div>

            {/* Botão Cancelar no header (opcional) */}
            <button
              type="button"
              onClick={() => setShowCancelModal(true)}
              className="hidden md:inline-flex items-center gap-2 h-9 px-3 rounded-xl border border-rose-700/40 text-rose-300 hover:bg-rose-900/20 transition"
            >
              <XCircle size={16} /> Cancelar transferência
            </button>

            <div className="hidden md:block text-right">
              <div className="text-[11px] text-zinc-400">Saldo disponível</div>
              <div className="text-sm font-semibold">
                {balanceLoading ? (
                  <span className="inline-block h-4 w-24 bg-white/10 animate-pulse rounded" />
                ) : (
                  formatBRL(availableBalance)
                )}
              </div>
            </div>
          </div>
          <div className="h-[2px] w-full bg-gradient-to-r from-transparent via-emerald-500/40 to-transparent" />
        </div>

        {/* Content */}
        <div className="container mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-16">
          <div className="grid gap-6 md:grid-cols-[2fr_1fr] items-start">
            {/* Main card */}
            <div className="rounded-2xl border border-white/10 bg-white/[0.04] backdrop-blur-md glow-card p-6 md:p-8 animate-floatUp">
              {/* Top */}
              <div className="flex items-center justify-between mb-6">
                <div className="flex items-center gap-3 text-xs text-emerald-300 bg-emerald-900/20 border border-emerald-700/30 px-3 py-1 rounded-full">
                  <ShieldCheck size={14} /> Sessão protegida por PIN
                </div>
                <div className="text-right md:hidden">
                  <div className="text-[11px] text-zinc-400">Saldo disponível</div>
                  <div className="text-sm font-semibold">
                    {balanceLoading ? (
                      <span className="inline-block h-4 w-24 bg-white/10 animate-pulse rounded" />
                    ) : (
                      formatBRL(availableBalance)
                    )}
                  </div>
                </div>
              </div>

              {/* Destinatário */}
              <div className="flex items-center gap-4 p-4 rounded-xl bg-white/[0.03] border border-white/10 mb-6">
                <div className="h-12 w-12 rounded-xl bg-emerald-600/20 grid place-content-center">
                  <User2 size={22} className="text-emerald-300" />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="text-xs text-zinc-400">Enviar PIX para</div>
                  <div className="text-lg font-medium truncate">{name || "Destinatário"}</div>
                  <div className="flex items-center gap-2 text-xs text-zinc-400 mt-1">
                    <Hash size={14} /> {document ? maskCPF(document) : "Documento não informado"}
                  </div>
                  <div className="flex items-center gap-2 text-xs text-zinc-400 mt-1">
                    <Landmark size={14} /> {bankName || "Banco não identificado"}
                    {bankISPB && <span className="text-zinc-500">• ISPB {bankISPB}</span>}
                  </div>
                  <div className="flex items-center gap-2 text-xs text-zinc-400 mt-1">
                    <KeyRound size={14} /> ID da Chave: {keyInfoId || "—"}
                  </div>
                </div>
                <div className="hidden sm:flex items-center gap-2 text-xs text-zinc-400">
                  <KeyRound size={14} /> {key || "chave"}
                </div>
              </div>

              {/* FORM */}
              <form onSubmit={phase === "amount" ? handleConfirmAmount : handleSubmit} className="space-y-6" noValidate>
                {/* Valor */}
                <div>
                  <label htmlFor="pix-amount" className="block text-sm text-zinc-400 mb-2">
                    Valor do PIX
                  </label>
                  <div className="relative">
                    <input
                      id="pix-amount"
                      inputMode="decimal"
                      placeholder="R$ 0,00"
                      value={amountInput}
                      onChange={(e) => setAmountInput(e.target.value)}
                      onBlur={() => setAmountInput(formatBRL(amount))}
                      disabled={phase === "pin"}
                      className="w-full rounded-2xl bg-[#111214] border border-white/10 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 text-3xl font-semibold text-center px-4 py-4 outline-none transition disabled:opacity-60"
                      aria-describedby="amount-hint"
                    />
                    <div className="pointer-events-none absolute inset-0 rounded-2xl ring-1 ring-white/5" />
                  </div>

                  <div className="mt-3 grid grid-cols-3 sm:grid-cols-6 gap-2" id="amount-hint">
                    {quick.map((v) => (
                      <button
                        key={v}
                        type="button"
                        onClick={() => handleQuickAmount(v)}
                        disabled={phase === "pin"}
                        className="chip text-sm px-3 py-2 rounded-xl bg-[#121315] border border-white/10 hover:border-emerald-500/40 hover:bg-emerald-900/10 transition disabled:opacity-50"
                      >
                        {formatBRL(v)}
                      </button>
                    ))}
                  </div>
                </div>

                {/* PIN (fase 2) */}
                {phase === "pin" && (
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <label htmlFor="pix-pin" className="block text-sm text-zinc-400">
                        PIN transacional ({pin.length} de 4 dígitos)
                      </label>
                      <button
                        type="button"
                        onClick={handleEditAmount}
                        className="text-xs text-emerald-300 hover:text-emerald-200 transition"
                      >
                        Alterar valor
                      </button>
                    </div>

                    <div className="relative flex items-center justify-center">
                      <button
                        type="button"
                        onClick={() => setShowPin((s) => !s)}
                        className="absolute right-0 top-1/2 -translate-y-1/2 z-10 p-3 text-zinc-400 hover:text-zinc-200"
                        aria-label={showPin ? "Ocultar PIN" : "Mostrar PIN"}
                      >
                        {showPin ? <EyeOff size={20} /> : <Eye size={20} />}
                      </button>

                      <PinInput
                        length={4}
                        value={pin}
                        onChange={setPin}
                        disabled={loading}
                        showValue={showPin}
                      />
                    </div>

                    <p className="text-xs text-zinc-500 mt-2 flex items-start gap-2">
                      <Info size={14} className="mt-0.5" /> O PIN valida sua autorização de envio. Não compartilhe com ninguém.
                    </p>
                  </div>
                )}

                {/* CTAs */}
                {phase === "amount" ? (
                  <div className="grid gap-2">
                    <button
                      type="submit"
                      disabled={!amount || amount <= 0 || balanceLoading}
                      className="btn-gradient w-full h-12 rounded-2xl text-base font-semibold text-white focus:outline-none focus:ring-4 focus:ring-emerald-500/30 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      Confirmar valor
                    </button>

                    {/* ⬇️ botão Cancelar transferência */}
                    <button
                      type="button"
                      onClick={() => setShowCancelModal(true)}
                      className="w-full h-11 rounded-2xl text-sm font-medium border border-rose-700/40 text-rose-300 hover:bg-rose-900/20 transition"
                    >
                      Cancelar transferência
                    </button>
                  </div>
                ) : (
                  <div className="grid gap-2">
                    <button
                      type="submit"
                      disabled={loading || pin.length < 4}
                      className="btn-gradient w-full h-12 rounded-2xl text-base font-semibold text-white transition flex items-center justify-center gap-2 focus:outline-none focus:ring-4 focus:ring-emerald-500/30 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? (
                        <>
                          <Loader2 size={18} className="animate-spin" /> Processando…
                        </>
                      ) : (
                        <>
                          <CheckCircle2 size={18} /> Enviar PIX
                        </>
                      )}
                    </button>

                    {/* ⬇️ botão Cancelar transferência também na fase do PIN */}
                    <button
                      type="button"
                      onClick={() => setShowCancelModal(true)}
                      className="w-full h-11 rounded-2xl text-sm font-medium border border-rose-700/40 text-rose-300 hover:bg-rose-900/20 transition"
                    >
                      Cancelar transferência
                    </button>
                  </div>
                )}

                {/* Mensagens */}
                {error && (
                  <div
                    className="p-3 rounded-xl border border-rose-700/40 bg-rose-900/20 text-rose-200 text-sm flex items-start gap-2"
                    role="alert"
                    aria-live="polite"
                  >
                    <Info size={16} className="mt-0.5" /> {error}
                  </div>
                )}

                {success && !showReceipt && (
                  <div className="p-4 rounded-xl border border-emerald-700/40 bg-emerald-900/20 text-emerald-300 text-sm space-y-2">
                    <div className="font-medium">PIX enviado com sucesso!</div>
                    <div className="text-emerald-200/90">ID: {txId}</div>
                    <div className="text-emerald-200/90">Status: {txStatus}</div>
                    <div className="text-emerald-200/90">Valor: {formatBRL(txAmount)}</div>
                    <button
                      type="button"
                      onClick={() => setShowReceipt(true)}
                      className="mt-2 inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-emerald-700/40 hover:bg-emerald-900/30 transition text-emerald-200"
                    >
                      <CheckCircle2 size={16} /> Ver comprovante
                    </button>
                  </div>
                )}
              </form>
            </div>

            {/* Sidebar */}
            <div className="space-y-6 animate-floatUp" style={{ animationDelay: ".05s" }}>
              {/* Integração */}
              <div className="rounded-2xl bg-white/[0.04] border border-white/10 p-5 glow-card">
                <div className="flex items-center justify-between mb-3">
                  <div className="text-sm text-zinc-400">Integração</div>
                  <span className="text-[11px] px-2 py-0.5 rounded-full bg-emerald-900/25 border border-emerald-700/30 text-emerald-300">
                    Funcionando
                  </span>
                </div>
                <div className="flex items-center gap-3">
                  <div className="h-10 w-10 rounded-xl bg-emerald-700/20 grid place-content-center">
                    <ShieldCheck size={18} className="text-emerald-300" />
                  </div>
                  <p className="text-sm text-zinc-300 leading-5">
                    Sessão ativa e protegida. Transações exigem confirmação por PIN e respeitam limites de segurança.
                  </p>
                </div>
              </div>

              {/* Resumo */}
              <div className="rounded-2xl bg-white/[0.04] border border-white/10 p-5 glow-card">
                <div className="text-sm text-zinc-400 mb-3">Resumo</div>
                <ul className="text-sm space-y-2">
                  <li className="flex items-center justify-between">
                    <span className="text-zinc-400">Destinatário</span>
                    <span className="text-zinc-200 max-w-[60%] truncate text-right">
                      {name || "—"}
                    </span>
                  </li>
                  <li className="flex items-center justify-between">
                    <span className="text-zinc-400">Documento</span>
                    <span className="text-zinc-200">{document ? maskCPF(document) : "—"}</span>
                  </li>
                  <li className="flex items-center justify-between">
                    <span className="text-zinc-400">Banco</span>
                    <span className="text-zinc-200 max-w-[60%] truncate text-right">
                      {bankName || "—"}
                      {bankISPB ? ` • ISPB ${bankISPB}` : ""}
                    </span>
                  </li>
                  <li className="flex items-center justify-between">
                    <span className="text-zinc-400">ID da Chave</span>
                    <span className="text-zinc-200 max-w-[60%] truncate text-right">
                      {keyInfoId || "—"}
                    </span>
                  </li>
                  <li className="flex items-center justify-between">
                    <span className="text-zinc-400">Chave</span>
                    <span className="text-zinc-200 max-w-[60%] truncate text-right">{key || "—"}</span>
                  </li>
                  <li className="flex items-center justify-between">
                    <span className="text-zinc-400">Valor</span>
                    <span className="text-zinc-200">{amount > 0 ? formatBRL(amount) : "—"}</span>
                  </li>
                  <li className="flex items-center justify-between">
                    <span className="text-zinc-400">Taxas</span>
                    <span className="text-zinc-200">{amount > 0 ? formatBRL(0) : "—"}</span>
                  </li>
                  <li className="flex items-center justify-between border-t border-white/10 pt-2 mt-1">
                    <span className="text-zinc-300 font-medium">Total</span>
                    <span className="text-white font-semibold">
                      {amount > 0 ? formatBRL(amount) : "—"}
                    </span>
                  </li>
                </ul>
              </div>

              {/* Dicas */}
              <div className="rounded-2xl bg-white/[0.04] border border-white/10 p-5 glow-card">
                <div className="text-sm text-zinc-400 mb-3">Dicas de segurança</div>
                <ul className="text-sm text-zinc-300 space-y-2 list-disc pl-5">
                  <li>Confira nome e documento do destinatário antes de enviar.</li>
                  <li>Nunca compartilhe seu PIN ou códigos de confirmação.</li>
                  <li>Em caso de erro, entre em contato com o suporte imediatamente.</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        {/* Modal Comprovante */}
        {showReceipt && (
          <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div
              className="absolute inset-0 bg-black/70 backdrop-blur-sm"
              onClick={() => setShowReceipt(false)}
            />
            <div className="relative z-[61] w-full max-w-lg rounded-2xl border border-emerald-700/30 bg-[#0F1112] text-white shadow-2xl print:w-full print:max-w-none print:rounded-none print:border-0">
              <style>{`
                @media print {
                  body * { visibility: hidden; }
                  .printable, .printable * { visibility: visible; }
                  .printable { position: fixed; inset: 0; padding: 24px; background: #fff; color: #111; }
                }
              `}</style>

              <div className="printable p-6">
                {/* Header */}
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center gap-3">
                    <div className="h-10 w-10 rounded-xl bg-emerald-600/15 grid place-content-center">
                      <CheckCircle2 size={22} className="text-emerald-400" />
                    </div>
                    <div>
                      <div className="text-lg font-semibold">Comprovante de PIX</div>
                      <div className="text-xs text-zinc-400">
                        {formatDateTime(new Date(txCreatedAt))}
                      </div>
                    </div>
                  </div>

                  <span className="text-[11px] px-2 py-0.5 rounded-full bg-emerald-900/25 border-emerald-700/30 border text-emerald-300 capitalize">
                    {txStatus}
                  </span>
                </div>

                {/* Valor */}
                <div className="mb-5">
                  <div className="text-sm text-zinc-400">Valor</div>
                  <div className="text-3xl font-bold">{formatBRL(txAmount)}</div>
                </div>

                {/* Infos */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                  <div className="space-y-1">
                    <div className="text-zinc-400">Destinatário</div>
                    <div className="text-zinc-100">{name || "—"}</div>
                  </div>

                  <div className="space-y-1">
                    <div className="text-zinc-400">Documento</div>
                    <div className="text-zinc-100">
                      {document ? maskCPF(document) : "—"}
                    </div>
                  </div>

                  <div className="space-y-1">
                    <div className="text-zinc-400">Banco</div>
                    <div className="text-zinc-100">
                      {bankName || "—"} {bankISPB ? `• ISPB ${bankISPB}` : ""}
                    </div>
                  </div>

                  <div className="space-y-1">
                    <div className="text-zinc-400">Chave / ID da Chave</div>
                    <div className="text-zinc-100 break-all">
                      {key || "—"}{keyInfoId ? ` • ${keyInfoId}` : ""}
                    </div>
                  </div>

                  <div className="space-y-1 sm:col-span-2">
                    <div className="text-zinc-400">EndToEndId</div>
                    <div className="flex items-center gap-2">
                      <span className="text-zinc-100 break-all">{endToEnd}</span>
                      <button
                        type="button"
                        onClick={copyEndToEnd}
                        className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-md border border-zinc-700 hover:bg-zinc-800 transition"
                      >
                        {copied ? <Check size={14} /> : <Copy size={14} />} {copied ? "Copiado" : "Copiar"}
                      </button>
                    </div>
                  </div>

                  <div className="space-y-1">
                    <div className="text-zinc-400">ID da Transação</div>
                    <div className="text-zinc-100 break-all">{txId}</div>
                  </div>

                  <div className="space-y-1">
                    <div className="text-zinc-400">Referência</div>
                    <div className="text-zinc-100 break-all">{txRef || "—"}</div>
                  </div>
                </div>

                {/* Ações */}
                <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
                  <button
                    type="button"
                    onClick={printReceipt}
                    className="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-zinc-700 hover:bg-zinc-800 transition"
                  >
                    <Printer size={16} /> Imprimir
                  </button>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setShowReceipt(false)}
                      className="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-zinc-700 hover:bg-zinc-800 transition"
                    >
                      <X size={16} /> Fechar
                    </button>
                    <button
                      type="button"
                      onClick={() => router.visit("/pix")}
                      className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 transition"
                    >
                      Fazer outro PIX
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* ⬇️ Modal de Cancelamento */}
        {showCancelModal && (
          <div className="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div
              className="absolute inset-0 bg-black/70 backdrop-blur-sm"
              onClick={() => setShowCancelModal(false)}
            />
            <div className="relative z-[71] w-full max-w-md rounded-2xl border border-rose-700/30 bg-[#101012] text-white shadow-2xl">
              <div className="p-6">
                <div className="flex items-start gap-3">
                  <div className="h-10 w-10 rounded-xl bg-rose-600/15 grid place-content-center shrink-0">
                    <XCircle size={22} className="text-rose-400" />
                  </div>
                  <div className="flex-1">
                    <h3 className="text-lg font-semibold">Cancelar transferência?</h3>
                    <p className="mt-1 text-sm text-zinc-400">
                      Você está prestes a cancelar esta operação. Nenhum valor será enviado.
                    </p>

                    <div className="mt-4 rounded-xl bg-white/[0.03] border border-white/10 p-3 text-sm space-y-1">
                      <div className="flex justify-between">
                        <span className="text-zinc-400">Destinatário</span>
                        <span className="text-zinc-200 max-w-[60%] truncate text-right">{name || "—"}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-zinc-400">Valor</span>
                        <span className="text-zinc-200">{amount > 0 ? formatBRL(amount) : "—"}</span>
                      </div>
                    </div>

                    <div className="mt-5 flex flex-col sm:flex-row gap-2">
                      <button
                        type="button"
                        onClick={() => setShowCancelModal(false)}
                        className="h-11 w-full rounded-xl border border-white/10 hover:bg-white/10 transition"
                      >
                        Voltar
                      </button>
                      <button
                        type="button"
                        onClick={confirmCancel}
                        className="h-11 w-full rounded-xl bg-rose-600 hover:bg-rose-700 transition font-semibold"
                      >
                        Confirmar cancelamento
                      </button>
                    </div>
                  </div>
                  <button
                    onClick={() => setShowCancelModal(false)}
                    className="p-2 -m-2 text-zinc-400 hover:text-white"
                    aria-label="Fechar"
                  >
                    <X size={18} />
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
        {/* /Modal de Cancelamento */}
      </div>
    </>
  );
}
