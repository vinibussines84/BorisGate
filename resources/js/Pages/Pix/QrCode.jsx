// resources/js/Pages/Pix/QrCodePix.jsx
import React, { useRef, useState, useEffect } from "react";
import { Head, usePage, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
  QrCode,
  ArrowLeft,
  ChevronsUpDown,
  Check,
  KeyRound,
  Mail,
  Phone,
  IdCard,
  Fingerprint,
  Loader2,
  Download,
  Copy,
} from "lucide-react";

/* ==============================
   Endpoints conforme seu backend
============================== */
const PIX_KEYS_URL = `/api/stric/pix/keys`; // GET (419 quando sessão expira)
const STATIC_QR_URL = (accountId) =>
  `/api/accounts/${accountId}/pix/qrcode/static`;

/* ==============================
   Utils
============================== */
const iconForTranslatedType = (t = "") => {
  const T = t.toLowerCase();
  if (T.includes("mail")) return Mail;
  if (T.includes("telefone")) return Phone;
  if (T.includes("cpf")) return IdCard;
  if (T.includes("cnpj")) return Fingerprint;
  return KeyRound;
};

function parseAmountToNumber(v) {
  if (!v || String(v).trim() === "") return undefined;
  const normalized = String(v).replace(/\./g, "").replace(",", ".");
  const num = Number(normalized);
  if (Number.isNaN(num)) return undefined;
  return Math.round(num * 100) / 100;
}

/* ==============================
   Menu Horizontal (QRCode e Minhas chaves)
============================== */
function HorizontalMenu() {
  const { url } = usePage();
  const items = [
    { label: "QRCode", href: "/pix/qrcode", icon: QrCode },
    // 🔗 Agora navega para /pix/chaves
    { label: "Minhas chaves", href: "/pix/chaves", icon: KeyRound },
  ];

  return (
    <nav className="sticky -top-px z-10 rounded-2xl border border-white/10 bg-[#0C0C0E]/80 backdrop-blur supports-[backdrop-filter]:bg-[#0C0C0E]/60">
      <div className="overflow-x-auto no-scrollbar">
        <ul className="flex items-center gap-2 px-3 py-2 min-w-max">
          {items.map(({ label, href, icon: Icon }) => {
            const isActive = url?.startsWith?.(href);
            return (
              <li key={href}>
                <Link
                  href={href}
                  className={[
                    "group inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm transition-colors border",
                    isActive
                      ? "bg-emerald-600/90 border-emerald-500 text-white"
                      : "bg-white/[0.04] border-white/10 text-zinc-300 hover:bg-white/[0.08]",
                  ].join(" ")}
                >
                  <Icon
                    size={16}
                    className={isActive ? "opacity-100" : "opacity-80"}
                  />
                  <span className="whitespace-nowrap">{label}</span>
                </Link>
              </li>
            );
          })}
        </ul>
      </div>
    </nav>
  );
}

export default function QrCodePix() {
  const { props } = usePage();
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

  // ⚠️ accountId vindo do Inertia (conforme seu web.php)
  const accountId = props.account_id || props.accountId || "";

  // form state
  const [key, setKey] = useState("");
  const [amount, setAmount] = useState("");
  const [description, setDescription] = useState("");

  // resultados
  const [qrImg, setQrImg] = useState("");
  const [qrText, setQrText] = useState("");

  // ui state
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");
  const [copied, setCopied] = useState(false);

  // chaves (popover inline)
  const [showKeys, setShowKeys] = useState(false);
  const [keysLoading, setKeysLoading] = useState(false);
  const [keysError, setKeysError] = useState("");
  const [pixKeys, setPixKeys] = useState([]);
  const [highlight, setHighlight] = useState(0);

  const canSubmit = !!accountId && !!key && !loading;

  /* ==============================
     Buscar chaves ao abrir popover
  ============================== */
  async function ensureKeysLoaded() {
    if (keysLoading || pixKeys.length > 0) return;
    setKeysError("");
    setKeysLoading(true);
    try {
      const res = await fetch(PIX_KEYS_URL, {
        headers: { "X-CSRF-TOKEN": csrf || "", Accept: "application/json" },
      });

      if (res.status === 419) {
        const data = await res.json().catch(() => ({}));
        window.location.href = data?.redirect || "/login";
        return;
      }

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        setKeysError(data?.message || "Falha ao carregar suas chaves Pix.");
        return;
      }

      const data = await res.json();
      const list = Array.isArray(data?.keys) ? data.keys : [];
      setPixKeys(list);
      setHighlight(0);
    } catch {
      setKeysError("Erro inesperado ao carregar chaves.");
    } finally {
      setKeysLoading(false);
    }
  }

  /* ==============================
     Gerar QR estático
  ============================== */
  async function handleGenerate(e) {
    e.preventDefault();
    if (!canSubmit) return;

    setLoading(true);
    setErr("");
    setQrImg("");
    setQrText("");

    const amountNumber = parseAmountToNumber(amount);

    try {
      const res = await fetch(STATIC_QR_URL(accountId), {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrf || "",
          Accept: "application/json",
        },
        body: JSON.stringify({
          key: (key || "").trim(),
          amount: amountNumber ?? null,
          description: description || "",
        }),
      });

      if (res.status === 401 && res.headers.get("X-Auth-Expired") === "1") {
        const data = await res.json().catch(() => ({}));
        window.location.href = data?.redirect || "/login";
        return;
      }

      if (res.status === 429) {
        const retryAfter = res.headers.get("Retry-After") || "20";
        setErr(`Muitas solicitações. Tente novamente em ~${retryAfter}s.`);
        return;
      }

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        setErr(data?.message || "Falha ao gerar o QR Code.");
        return;
      }

      const data = await res.json();
      const img = data?.qrCode?.image
        ? `data:image/png;base64,${data.qrCode.image}`
        : "";
      const txt = data?.qrCode?.text || "";

      setQrImg(img);
      setQrText(txt);
    } catch {
      setErr("Erro inesperado. Verifique sua conexão e tente novamente.");
    } finally {
      setLoading(false);
    }
  }

  /* ==============================
     Auxiliares (copiar / baixar)
  ============================== */
  async function copyText() {
    if (!qrText) return;
    try {
      await navigator.clipboard.writeText(qrText);
      setCopied(true);
      setTimeout(() => setCopied(false), 1200);
    } catch {
      const ta = document.createElement("textarea");
      ta.value = qrText;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      document.body.removeChild(ta);
      setCopied(true);
      setTimeout(() => setCopied(false), 1200);
    }
  }

  function downloadQr() {
    if (!qrImg) return;
    const a = document.createElement("a");
    a.href = qrImg;
    a.download = `qrcode-pix-${Date.now()}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  // Fecha popover ao clicar fora
  const popRef = useRef(null);
  useEffect(() => {
    function onClickOutside(ev) {
      if (!popRef.current) return;
      if (!popRef.current.contains(ev.target)) setShowKeys(false);
    }
    if (showKeys) document.addEventListener("mousedown", onClickOutside);
    return () => document.removeEventListener("mousedown", onClickOutside);
  }, [showKeys]);

  // Navegação por teclado no popover
  function onKeyDownList(e) {
    if (!showKeys || pixKeys.length === 0) return;
    if (e.key === "ArrowDown") {
      e.preventDefault();
      setHighlight((h) => Math.min(h + 1, pixKeys.length - 1));
    }
    if (e.key === "ArrowUp") {
      e.preventDefault();
      setHighlight((h) => Math.max(h - 1, 0));
    }
    if (e.key === "Enter") {
      e.preventDefault();
      const chosen = pixKeys[highlight];
      if (chosen) {
        setKey(chosen.value || "");
        setShowKeys(false);
      }
    }
  }

  return (
    <AuthenticatedLayout>
      <Head title="Pix — QRCode" />
      <div className="min-h-screen bg-[#0B0B0B] text-white px-4 py-6">
        <div className="max-w-5xl mx-auto space-y-6">
          {/* Topo */}
          <div className="flex items-center justify-between">
            <button
              onClick={() => history.back()}
              className="inline-flex items-center gap-2 text-zinc-400 hover:text-white"
            >
              <ArrowLeft size={18} /> Voltar
            </button>
          </div>

          {/* Header + Menu */}
          <div className="space-y-4">
            <header className="flex items-center gap-3">
              <div className="h-12 w-12 rounded-2xl border border-white/10 bg-white/[0.06] flex items-center justify-center backdrop-blur">
                <QrCode size={22} className="text-emerald-400" />
              </div>
              <div>
                <h1 className="text-3xl font-light tracking-tight">
                  Pix <span className="text-white/80">QRCode</span>
                </h1>
                <p className="text-sm text-zinc-400">
                  Gere um QR <span className="text-white/80">estático</span> por
                  chave Pix. Valor e descrição são opcionais.
                </p>
              </div>
            </header>

            {/* Menu Horizontal */}
            <HorizontalMenu />
          </div>

          {/* Card do formulário */}
          <form
            onSubmit={handleGenerate}
            className="rounded-2xl border border-white/10 bg-gradient-to-br from-white/[0.05] to-white/[0.02] p-5 sm:p-6 space-y-5 shadow-[0_10px_40px_-15px_rgba(16,185,129,0.25)]"
          >
            {/* Aviso se faltar accountId */}
            {!accountId && (
              <div className="text-amber-300 text-xs -mt-2">
                ⚠️ <span className="font-semibold">account_id</span> não veio nas
                props. Configure a rota Inertia para enviar
                <code className="ml-1">account_id</code> (ex.:
                <code className="ml-1">session('stric_account_id')</code>).
              </div>
            )}

            {/* CHAVE PIX com popover */}
            <div className="relative" ref={popRef}>
              <label className="block text-sm text-zinc-300 mb-1">
                Chave Pix (obrigatório)
              </label>

              <div className="flex items-stretch gap-2">
                <div className="relative flex-1">
                  <input
                    value={key}
                    onChange={(e) => setKey(e.target.value)}
                    onFocus={() => {
                      setShowKeys(true);
                      ensureKeysLoaded();
                    }}
                    onKeyDown={onKeyDownList}
                    placeholder="E-mail, telefone, CPF/CNPJ ou chave aleatória (EVP)"
                    className="w-full rounded-xl bg-[#0F1011] border border-white/10 px-3 py-3 outline-none focus:border-emerald-500/60 pr-10 transition-colors"
                    required
                  />
                  <button
                    type="button"
                    onClick={() => {
                      const s = !showKeys;
                      setShowKeys(s);
                      if (s) ensureKeysLoaded();
                    }}
                    className="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-zinc-400 hover:text-white hover:bg-white/5"
                    aria-label="Minhas chaves (popover)"
                    title="Minhas chaves (popover)"
                  >
                    <ChevronsUpDown size={16} />
                  </button>
                </div>

                {/* Botão abre o popover inline para escolher uma chave rapidamente */}
                <button
                  type="button"
                  onClick={() => {
                    setShowKeys((v) => !v);
                    ensureKeysLoaded();
                  }}
                  className="inline-flex items-center gap-2 px-4 py-3 rounded-xl border border-white/10 bg-white/[0.05] hover:bg-white/[0.09] text-sm transition-colors"
                >
                  <KeyRound size={16} />
                  Minhas chaves
                </button>
              </div>

              {/* Popover de chaves */}
              {showKeys && (
                <div className="absolute z-20 mt-2 w-full rounded-xl border border-white/10 bg-[#0f0f10] shadow-2xl">
                  <div className="p-2">
                    {keysLoading && (
                      <div className="flex items-center gap-2 px-3 py-2 text-zinc-400 text-sm">
                        <Loader2 className="animate-spin" size={16} />
                        Carregando suas chaves...
                      </div>
                    )}

                    {!!keysError && (
                      <div className="px-3 py-2 text-rose-300 text-sm">
                        {keysError}
                      </div>
                    )}

                    {!keysLoading && !keysError && pixKeys.length === 0 && (
                      <div className="px-3 py-2 text-zinc-400 text-sm">
                        Nenhuma chave encontrada para esta conta.
                      </div>
                    )}

                    {!keysLoading && pixKeys.length > 0 && (
                      <ul className="max-h-56 overflow-auto">
                        {pixKeys.map((k, idx) => {
                          const Icon = iconForTranslatedType(k.type || "");
                          const active = highlight === idx;
                          return (
                            <li key={`${k.value}-${idx}`}>
                              <button
                                type="button"
                                className={`w-full text-left px-3 py-2 rounded-lg flex items-center gap-2 ${
                                  active ? "bg-white/10" : "hover:bg-white/5"
                                }`}
                                onMouseEnter={() => setHighlight(idx)}
                                onClick={() => {
                                  setKey(k.value || "");
                                  setShowKeys(false);
                                }}
                              >
                                <Icon
                                  size={16}
                                  className="text-emerald-400 shrink-0"
                                />
                                <div className="min-w-0">
                                  <div className="text-sm text-white/90 truncate">
                                    {k.value || "—"}
                                  </div>
                                  <div className="text-[11px] text-zinc-400 uppercase tracking-wide">
                                    {k.type || "Aleatória"}{" "}
                                    {k.status ? `• ${k.status}` : ""}
                                  </div>
                                </div>
                                {key === (k.value || "") && (
                                  <Check
                                    size={16}
                                    className="ml-auto text-emerald-400"
                                  />
                                )}
                              </button>
                            </li>
                          );
                        })}
                      </ul>
                    )}
                  </div>
                </div>
              )}
            </div>

            {/* VALOR + DESCRIÇÃO */}
            <div className="grid sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm text-zinc-300 mb-1">
                  Valor (opcional)
                </label>
                <input
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  placeholder="Ex.: 49,90"
                  inputMode="decimal"
                  className="w-full rounded-xl bg-[#0F1011] border border-white/10 px-3 py-3 outline-none focus:border-emerald-500/60 transition-colors"
                />
                <p className="text-[11px] text-zinc-500 mt-1">
                  Todos os valores são passados pelo {" "}
                  <span className="text-zinc-300">JcBank.</span>.
                </p>
              </div>

              <div>
                <label className="block text-sm text-zinc-300 mb-1">
                  Descrição (opcional)
                </label>
                <input
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Ex.: Café da manhã"
                  className="w-full rounded-xl bg-[#0F1011] border border-white/10 px-3 py-3 outline-none focus:border-emerald-500/60 transition-colors"
                />
              </div>
            </div>

            {/* Erro */}
            {err && <div className="text-rose-300 text-sm">{err}</div>}

            {/* Ações */}
            <div className="flex flex-col sm:flex-row gap-3 pt-1">
              <button
                type="submit"
                disabled={!canSubmit}
                className={`flex-1 inline-flex items-center justify-center gap-2 py-3 rounded-xl text-white text-sm font-medium transition-colors
                ${
                  canSubmit
                    ? "bg-emerald-600 hover:bg-emerald-500"
                    : "bg-emerald-900/40 cursor-not-allowed"
                }`}
              >
                {loading ? (
                  <Loader2 className="animate-spin" size={16} />
                ) : (
                  <QrCode size={16} />
                )}
                {loading ? "Gerando..." : "Gerar QRCode"}
              </button>

              <button
                type="button"
                onClick={copyText}
                disabled={!qrText}
                className={`flex-1 inline-flex items-center justify-center gap-2 py-3 rounded-xl border border-white/10 text-white text-sm font-medium transition-colors
                ${
                  qrText
                    ? copied
                      ? "bg-emerald-900/20 border-emerald-600/40"
                      : "bg-white/[0.06] hover:bg-white/[0.1]"
                    : "bg-white/[0.02] cursor-not-allowed"
                }`}
              >
                <Copy size={16} />
                {copied ? "Copiado!" : 'Copiar "Pix Copia e Cola"'}
              </button>

              <button
                type="button"
                onClick={downloadQr}
                disabled={!qrImg}
                className={`flex-1 inline-flex items-center justify-center gap-2 py-3 rounded-xl border border-white/10 text-white text-sm font-medium transition-colors
                ${
                  qrImg
                    ? "bg-white/[0.06] hover:bg-white/[0.1]"
                    : "bg-white/[0.02] cursor-not-allowed"
                }`}
              >
                <Download size={16} />
                Baixar QRCode (PNG)
              </button>
            </div>

            {/* Preview */}
            <div className="flex items-center justify-center py-6">
              {qrImg ? (
                <img
                  src={qrImg}
                  alt="QR Pix estático"
                  className="h-56 w-56 rounded-2xl border border-white/10 bg-white/[0.06] object-contain shadow-lg shadow-black/30"
                />
              ) : (
                <div className="h-56 w-56 rounded-2xl border border-white/10 bg-white/[0.04] grid place-content-center">
                  <QrCode size={96} className="text-white/60" />
                </div>
              )}
            </div>

            {qrText && (
              <div className="text-xs text-zinc-400 break-all select-all rounded-xl border border-white/10 bg-white/[0.03] p-3">
                {qrText}
              </div>
            )}
          </form>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
