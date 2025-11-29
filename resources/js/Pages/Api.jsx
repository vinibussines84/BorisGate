// resources/js/Pages/Api.jsx
import React, { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, usePage } from "@inertiajs/react";
import {
  KeyRound,
  Copy,
  CheckCircle2,
  Eye,
  EyeOff,
  ShieldCheck,
  Percent,
  Banknote,
  ToggleRight,
  Webhook as WebhookIcon,
  Link2,
  Clock,
  Power,
  BookOpen,
} from "lucide-react";

/* ================= Helpers ================= */
const toBRL = (v) =>
  (Number(v) || 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  });

const toPct = (v) =>
  `${(Number(v) || 0).toLocaleString("pt-BR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}%`;

function normalizePercent(pRaw) {
  const n = Number(pRaw) || 0;
  if (n <= 0) return { pct: 0 };
  if (n > 1) return { pct: n };
  return { pct: n * 100 };
}

function computeFee(enabled, fixed, percent) {
  const f = Number(fixed) || 0;
  const { pct } = normalizePercent(percent);

  if (!enabled) {
    return {
      enabled: false,
      type: "Desativada",
      valueStr: "—",
      badgeTone: "bg-zinc-900 text-zinc-500 border-zinc-700",
    };
  }
  if (pct > 0 && f > 0)
    return {
      enabled: true,
      type: "Misto",
      valueStr: `${toPct(pct)} + ${toBRL(f)}`,
      badgeTone: "bg-[#02fb5c]/10 text-[#02fb5c] border-[#02fb5c]/30",
    };
  if (pct > 0)
    return {
      enabled: true,
      type: "Percentual",
      valueStr: toPct(pct),
      badgeTone: "bg-[#02fb5c]/10 text-[#02fb5c] border-[#02fb5c]/30",
    };
  if (f > 0)
    return {
      enabled: true,
      type: "Fixo",
      valueStr: toBRL(f),
      badgeTone: "bg-[#02fb5c]/10 text-[#02fb5c] border-[#02fb5c]/30",
    };
  return {
    enabled: true,
    type: "Sem valor definido",
    valueStr: "—",
    badgeTone: "bg-[#1a1a1a] text-gray-400 border-zinc-700",
  };
}

/* ================= Components ================= */
const Card = ({ children, className = "" }) => (
  <div
    className={`bg-[#0b0b0b]/90 border border-white/10 rounded-3xl shadow-[0_0_30px_-10px_rgba(0,0,0,0.8)] ${className}`}
  >
    {children}
  </div>
);

const HeaderCapsule = ({ children }) => (
  <div className="w-full bg-[#0b0b0b]/95 border border-white/10 rounded-3xl shadow-[0_0_40px_-10px_rgba(0,0,0,0.8)] p-6 flex flex-col sm:flex-row items-center justify-between gap-3">
    {children}
  </div>
);

const SectionTitle = ({ icon: Icon, title }) => (
  <div className="flex items-center gap-3 mb-4">
    <div className="p-3 rounded-xl bg-[#111]/70 border border-white/10">
      <Icon size={18} className="text-[#02fb5c]" />
    </div>
    <h2 className="text-lg font-semibold text-white">{title}</h2>
  </div>
);

const ApiKeyCard = ({ label, value, hidden, copiedKey, copyWarningKey, onCopy }) => {
  const isCopied = copiedKey === label;
  const warning = copyWarningKey === label;
  const display = hidden ? "•••••••••••••••••••••" : value;

  return (
    <Card className="p-5">
      <h3 className="text-sm text-gray-400 mb-2">{label}</h3>
      <div className="flex items-center justify-between bg-[#111]/70 border border-white/10 rounded-xl px-4 py-3">
        <span className="font-mono text-white text-[13px] break-all">{display}</span>
        <button
          onClick={() => onCopy(value, label, hidden)}
          className={`p-2 rounded-lg transition ${
            isCopied
              ? "bg-[#02fb5c]/20 text-[#02fb5c] border border-[#02fb5c]/30"
              : warning
              ? "bg-yellow-500/20 text-yellow-300 border border-yellow-300/30"
              : "text-gray-300 hover:bg-white/10"
          }`}
        >
          {isCopied ? <CheckCircle2 size={16} /> : <Copy size={16} />}
        </button>
      </div>
    </Card>
  );
};

const FeeRow = ({ icon: Icon, titulo, fee }) => (
  <div className="flex items-center justify-between bg-[#111]/70 border border-white/10 rounded-xl p-4">
    <div className="flex items-center gap-3">
      <div className="p-2 rounded-lg bg-[#0b0b0b]/70 border border-white/10">
        <Icon size={18} className="text-[#02fb5c]" />
      </div>
      <div>
        <p className="text-white text-sm">{titulo}</p>
        <span
          className={`inline-flex items-center gap-2 px-2 py-1 mt-1 text-xs rounded-lg border ${fee.badgeTone}`}
        >
          <ToggleRight size={12} />
          {fee.type}
        </span>
      </div>
    </div>
    <span className="text-white text-sm font-medium">{fee.valueStr}</span>
  </div>
);

const WebhookRow = ({ label, url, copied, onCopy, enabled }) => {
  const hasUrl = !!url && url !== "—";
  const isCopied = copied === label;

  return (
    <div
      className={`flex items-center justify-between p-4 rounded-xl border ${
        enabled
          ? "border-white/10 bg-[#0b0b0b]/80"
          : "border-white/5 bg-white/[0.03] opacity-50"
      }`}
    >
      <div className="flex items-center gap-3 min-w-0">
        <div className="p-2 rounded-lg bg-[#111]/70 border border-white/10">
          <WebhookIcon size={18} className="text-[#02fb5c]" />
        </div>

        <div className="min-w-0">
          <p className="text-sm text-white">{label}</p>
          <div className="flex items-center gap-2 mt-1">
            <span className="px-2 py-0.5 bg-[#02fb5c]/10 rounded-md border border-[#02fb5c]/30 text-[11px] text-[#02fb5c] flex items-center gap-1">
              <Clock size={12} /> Assíncrono
            </span>
            {!enabled && (
              <span className="text-[11px] flex items-center gap-1 text-gray-500">
                <Power size={12} /> desativado
              </span>
            )}
          </div>
        </div>
      </div>

      <div className="flex items-center gap-2 min-w-0">
        <span className="hidden sm:flex text-gray-400 text-xs truncate max-w-[300px] items-center gap-1">
          <Link2 size={14} /> {hasUrl ? url : "—"}
        </span>
        <button
          disabled={!enabled}
          onClick={() => hasUrl && enabled && onCopy(url, label)}
          className={`p-2 rounded-lg transition ${
            isCopied
              ? "bg-[#02fb5c]/20 text-[#02fb5c] border border-[#02fb5c]/30"
              : "text-gray-300 hover:bg-white/10"
          }`}
        >
          {isCopied ? <CheckCircle2 size={16} /> : <Copy size={16} />}
        </button>
      </div>
    </div>
  );
};

/* =================== Página =================== */
export default function Api() {
  const { user } = usePage().props;
  const [hidden, setHidden] = useState(true);
  const [copiedKey, setCopiedKey] = useState(null);
  const [copiedHook, setCopiedHook] = useState(null);
  const [copyWarningKey, setCopyWarningKey] = useState(null);

  const keys = {
    AuthKey: user?.authkey ?? "—",
    SecretKey: user?.secretkey ?? "—",
  };

  const webhookEnabled = Boolean(user?.webhook_enabled);
  const webhooks = {
    in: user?.webhook_in_url ?? "—",
    out: user?.webhook_out_url ?? "—",
  };

  const feeIn = computeFee(user?.tax_in_enabled, user?.tax_in_fixed, user?.tax_in_percent);
  const feeOut = computeFee(user?.tax_out_enabled, user?.tax_out_fixed, user?.tax_out_percent);

  const copyToClipboard = (value, label, hidden) => {
    if (!value || value === "—") return;

    if (label === "AuthKey" || label === "SecretKey") {
      if (hidden) {
        setCopyWarningKey(label);
        setTimeout(() => setCopyWarningKey(null), 1800);
        return;
      }
      navigator.clipboard.writeText(value);
      setCopiedKey(label);
      setTimeout(() => setCopiedKey(null), 1500);
      return;
    }

    navigator.clipboard.writeText(value);
    setCopiedHook(label);
    setTimeout(() => setCopiedHook(null), 1500);
  };

  return (
    <AuthenticatedLayout>
      <Head title="API & Integração" />

      <div className="min-h-screen bg-[#0B0B0B] px-6 py-12 text-gray-100">
        <div className="max-w-5xl mx-auto space-y-10">
          {/* HEADER */}
          <HeaderCapsule>
            <div className="flex items-center gap-4">
              <div className="w-1 h-10 rounded-full bg-[#02fb5c]" />
              <div>
                <h1 className="text-2xl font-semibold text-white">Integração com API</h1>
                <p className="text-gray-400 text-sm">Gerencie suas credenciais e webhooks</p>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <button
                onClick={() => setHidden(!hidden)}
                className="flex items-center gap-2 px-5 py-2 rounded-full bg-[#02fb5c] hover:bg-[#29ff78] text-[#0b0b0b] font-semibold transition shadow-[0_0_15px_rgba(2,251,92,0.3)]"
              >
                {hidden ? <Eye size={16} /> : <EyeOff size={16} />}
                {hidden ? "Mostrar Chaves" : "Ocultar Chaves"}
              </button>

              <a
                href="/api/docs"
                className="flex items-center gap-2 px-5 py-2 rounded-full border border-[#02fb5c]/40 text-[#02fb5c] font-semibold hover:bg-[#02fb5c]/10 transition shadow-[0_0_10px_rgba(2,251,92,0.2)]"
              >
                <BookOpen size={16} />
                Documentação
              </a>
            </div>
          </HeaderCapsule>

          {/* CHAVES */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <ApiKeyCard
              label="AuthKey"
              value={keys.AuthKey}
              hidden={hidden}
              copiedKey={copiedKey}
              copyWarningKey={copyWarningKey}
              onCopy={copyToClipboard}
            />

            <ApiKeyCard
              label="SecretKey"
              value={keys.SecretKey}
              hidden={hidden}
              copiedKey={copiedKey}
              copyWarningKey={copyWarningKey}
              onCopy={copyToClipboard}
            />
          </div>

          {/* TAXAS */}
          <Card className="p-6">
            <SectionTitle icon={Percent} title="Configurações de Taxas" />
            <div className="space-y-3">
              <FeeRow icon={Percent} titulo="Cash In" fee={feeIn} />
              <FeeRow icon={Banknote} titulo="Cash Out" fee={feeOut} />
            </div>
          </Card>

          {/* WEBHOOKS */}
          <Card className="p-6">
            <SectionTitle icon={WebhookIcon} title="Webhooks" />
            <div className="mb-4 flex items-center gap-2 text-xs">
              <span className="px-2 py-1 rounded-md border border-[#02fb5c]/30 bg-[#02fb5c]/10 text-[#02fb5c] flex items-center gap-1">
                <Power size={12} />
                {webhookEnabled ? "Ativado" : "Desativado"}
              </span>
              <span className="text-gray-500">(Fila assíncrona)</span>
            </div>

            <div className="space-y-3">
              <WebhookRow
                label="Webhook (Cash In)"
                url={webhooks.in}
                copied={copiedHook}
                onCopy={copyToClipboard}
                enabled={webhookEnabled}
              />
              <WebhookRow
                label="Webhook (Cash Out)"
                url={webhooks.out}
                copied={copiedHook}
                onCopy={copyToClipboard}
                enabled={webhookEnabled}
              />
            </div>
          </Card>

          {/* RODAPÉ */}
          <div className="flex items-center gap-3 text-gray-500 text-xs">
            <ShieldCheck size={14} className="text-[#02fb5c]" />
            <p>Mantenha suas credenciais sempre em segurança.</p>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
