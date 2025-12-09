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
  (Number(v) || 0).toLocaleString("en-US", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
  });

const toPct = (v) =>
  `${(Number(v) || 0).toLocaleString("en-US", {
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
      type: "Disabled",
      valueStr: "—",
      badgeTone: "bg-zinc-900 text-zinc-500 border-zinc-700",
    };
  }
  if (pct > 0 && f > 0)
    return {
      enabled: true,
      type: "Mixed",
      valueStr: `${toPct(pct)} + ${toBRL(f)}`,
      badgeTone: "bg-[#02fb5c]/10 text-[#02fb5c] border-[#02fb5c]/30",
    };
  if (pct > 0)
    return {
      enabled: true,
      type: "Percentage",
      valueStr: toPct(pct),
      badgeTone: "bg-[#02fb5c]/10 text-[#02fb5c] border-[#02fb5c]/30",
    };
  if (f > 0)
    return {
      enabled: true,
      type: "Fixed",
      valueStr: toBRL(f),
      badgeTone: "bg-[#02fb5c]/10 text-[#02fb5c] border-[#02fb5c]/30",
    };
  return {
    enabled: true,
    type: "No value defined",
    valueStr: "—",
    badgeTone: "bg-[#1a1a1a] text-gray-400 border-zinc-700",
  };
}

/* ================= Components ================= */
const Card = ({ children, className = "" }) => (
  <div
    className={`bg-[#0b0b0b]/90 border border-white/10 rounded-3xl shadow-lg shadow-black/10 ${className}`}
  >
    {children}
  </div>
);

const HeaderCapsule = ({ children }) => (
  <div className="w-full bg-[#0b0b0b]/95 border border-white/10 rounded-3xl shadow-md shadow-black/10 p-6 flex flex-col md:flex-row items-center justify-between gap-4">
    {children}
  </div>
);

const SectionTitle = ({ icon: Icon, title }) => (
  <div className="flex items-center gap-3 mb-4">
    <div className="p-3 rounded-xl bg-[#111]/70 border border-white/10 shadow-sm shadow-black/10">
      <Icon size={18} className="text-[#02fb5c]" />
    </div>
    <h2 className="text-lg font-semibold text-white">{title}</h2>
  </div>
);

const ApiKeyCard = ({
  label,
  value,
  hidden,
  copiedKey,
  copyWarningKey,
  onCopy,
}) => {
  const isCopied = copiedKey === label;
  const warning = copyWarningKey === label;
  const display = hidden ? "•••••••••••••••••••••" : value;

  return (
    <Card className="p-5 flex flex-col justify-between">
      <h3 className="text-sm text-gray-400 mb-2">{label}</h3>
      <div className="flex flex-wrap items-center justify-between gap-3 bg-[#111]/70 border border-white/10 rounded-xl px-4 py-3">
        <span className="font-mono text-white text-[13px] break-all max-w-full">
          {display}
        </span>

        <button
          onClick={() => onCopy(value, label, hidden)}
          className={`p-2 rounded-lg transition shadow-sm ${
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

const FeeRow = ({ icon: Icon, title, fee }) => (
  <div className="flex flex-col md:flex-row md:items-center justify-between bg-[#111]/70 border border-white/10 rounded-xl p-4 shadow-sm shadow-black/10 gap-3">
    <div className="flex items-center gap-3">
      <div className="p-2 rounded-lg bg-[#0b0b0b]/70 border border-white/10 shadow-sm shadow-black/10">
        <Icon size={18} className="text-[#02fb5c]" />
      </div>

      <div>
        <p className="text-white text-sm">{title}</p>
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
      className={`flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 rounded-xl border shadow-sm shadow-black/10 ${
        enabled
          ? "border-white/10 bg-[#0b0b0b]/80"
          : "border-white/5 bg-white/[0.03] opacity-50"
      }`}
    >
      <div className="flex items-center gap-3 min-w-0">
        <div className="p-2 rounded-lg bg-[#111]/70 border border-white/10 shadow-sm shadow-black/10">
          <WebhookIcon size={18} className="text-[#02fb5c]" />
        </div>

        <div className="min-w-0">
          <p className="text-sm text-white">{label}</p>

          <div className="flex items-center flex-wrap gap-2 mt-1">
            <span className="px-2 py-0.5 bg-[#02fb5c]/10 rounded-md border border-[#02fb5c]/30 text-[11px] text-[#02fb5c] flex items-center gap-1">
              <Clock size={12} /> Async
            </span>

            {!enabled && (
              <span className="text-[11px] flex items-center gap-1 text-gray-500">
                <Power size={12} /> disabled
              </span>
            )}
          </div>
        </div>
      </div>

      <div className="flex items-center gap-2 min-w-0">
        <span className="hidden md:flex text-gray-400 text-xs truncate max-w-[300px] items-center gap-1">
          <Link2 size={14} /> {hasUrl ? url : "—"}
        </span>

        <button
          disabled={!enabled}
          onClick={() => hasUrl && enabled && onCopy(url, label)}
          className={`p-2 rounded-lg transition shadow-sm ${
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

/* =================== Page =================== */
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

  const feeIn = computeFee(
    user?.tax_in_enabled,
    user?.tax_in_fixed,
    user?.tax_in_percent
  );
  const feeOut = computeFee(
    user?.tax_out_enabled,
    user?.tax_out_fixed,
    user?.tax_out_percent
  );

  const copyToClipboard = (value, label, hidden) => {
    if (!value || value === "—") return;

    if (hidden && (label === "AuthKey" || label === "SecretKey")) {
      setCopyWarningKey(label);
      setTimeout(() => setCopyWarningKey(null), 1800);
      return;
    }

    navigator.clipboard.writeText(value);

    if (label === "AuthKey" || label === "SecretKey") {
      setCopiedKey(label);
      setTimeout(() => setCopiedKey(null), 1500);
      return;
    }

    setCopiedHook(label);
    setTimeout(() => setCopiedHook(null), 1500);
  };

  return (
    <AuthenticatedLayout>
      <Head title="API & Integration" />

      <div className="min-h-screen bg-[#0B0B0B] px-4 sm:px-6 py-10 text-gray-100">
        <div className="max-w-5xl mx-auto space-y-10">

          {/* HEADER */}
          <HeaderCapsule>
            <div className="flex items-center gap-4 w-full md:w-auto">
              <div className="w-1 h-10 rounded-full bg-[#02fb5c]" />

              <div>
                <h1 className="text-2xl font-semibold text-white">API Integration</h1>
                <p className="text-gray-400 text-sm">
                  Manage your credentials and webhooks
                </p>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <button
                onClick={() => setHidden(!hidden)}
                className="flex items-center gap-2 px-5 py-2 rounded-full bg-[#02fb5c] hover:bg-[#00e756] text-[#0b0b0b] font-semibold transition"
              >
                {hidden ? <Eye size={16} /> : <EyeOff size={16} />}
                {hidden ? "Show Keys" : "Hide Keys"}
              </button>

              <a
                href="/api/docs"
                className="flex items-center gap-2 px-5 py-2 rounded-full border border-[#02fb5c]/40 text-[#02fb5c] font-semibold hover:bg-[#02fb5c]/10 transition"
              >
                <BookOpen size={16} />
                Documentation
              </a>
            </div>
          </HeaderCapsule>

          {/* KEYS */}
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

          {/* FEES */}
          <Card className="p-6">
            <SectionTitle icon={Percent} title="Fee Settings" />

            <div className="space-y-3">
              <FeeRow icon={Percent} title="Cash In" fee={feeIn} />
              <FeeRow icon={Banknote} title="Cash Out" fee={feeOut} />
            </div>
          </Card>

          {/* WEBHOOKS */}
          <Card className="p-6">
            <SectionTitle icon={WebhookIcon} title="Webhooks" />

            <div className="mb-4 flex items-center gap-2 text-xs flex-wrap">
              <span className="px-2 py-1 rounded-md border border-[#02fb5c]/30 bg-[#02fb5c]/10 text-[#02fb5c] flex items-center gap-1">
                <Power size={12} />
                {webhookEnabled ? "Enabled" : "Disabled"}
              </span>

              <span className="text-gray-500">(Asynchronous queue)</span>
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

          {/* FOOTER */}
          <div className="flex items-center gap-3 text-gray-500 text-xs">
            <ShieldCheck size={14} className="text-[#02fb5c]" />
            <p>Keep your credentials secure at all times.</p>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
