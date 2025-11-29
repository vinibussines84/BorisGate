// resources/js/Pages/DadosConta.jsx
import React, { useEffect, useState, useMemo } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, usePage } from "@inertiajs/react";
import {
  User,
  Copy,
  Check,
  AtSign,
  Fingerprint,
  CalendarClock,
  IdCard,
} from "lucide-react";

/* ===========================
   Helpers
=========================== */
const onlyDigits = (s = "") => String(s || "").replace(/\D/g, "");
const cpfMask = (v) => {
  const d = onlyDigits(v).slice(0, 11);
  if (!d) return "•••.•••.•••-••";
  return d
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
};
const initials = (name = "", fallback = "?") => {
  const parts = String(name).trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return fallback;
  const first = parts[0]?.[0] || "";
  const last = parts.length > 1 ? parts[parts.length - 1]?.[0] : "";
  return (first + last).toUpperCase();
};
const classNames = (...c) => c.filter(Boolean).join(" ");
const formatDateTime = (raw) => {
  if (!raw) return "—";
  const d = new Date(raw);
  if (Number.isNaN(d.getTime())) return "—";
  return d.toLocaleString("pt-BR", { dateStyle: "short", timeStyle: "short" });
};

/* ===========================
   UI Primitives (rounded & pretty)
=========================== */
const Shell = ({ children, className = "" }) => (
  <div
    className={classNames(
      "rounded-2xl p-[1px]",
      "bg-[linear-gradient(135deg,rgba(255,255,255,0.18),rgba(255,255,255,0.04))]",
      className
    )}
  >
    <div className="rounded-2xl bg-[rgba(17,18,22,0.78)] backdrop-blur-md shadow-[0_12px_40px_-18px_rgba(0,0,0,0.6)] border border-white/10">
      {children}
    </div>
  </div>
);

const Pill = ({ children, className = "" }) => (
  <div
    className={classNames(
      "inline-flex items-center gap-2 rounded-full",
      "px-3 py-1.5 text-sm",
      "border border-white/10 bg-white/[0.03]",
      className
    )}
  >
    {children}
  </div>
);

function Copyable({ text, masked, mono = false, className = "" }) {
  const [copied, setCopied] = useState(false);
  const value = masked ?? text;

  const doCopy = async () => {
    try {
      await navigator.clipboard.writeText(String(text || ""));
      setCopied(true);
      setTimeout(() => setCopied(false), 1100);
    } catch {}
  };

  return (
    <div
      className={classNames(
        "group inline-flex items-center gap-2 rounded-2xl border border-white/10 px-3 py-2",
        "bg-white/[0.03] hover:bg-white/[0.06] transition",
        className
      )}
    >
      <span
        className={classNames(
          "text-sm text-zinc-100 truncate",
          mono && "font-mono tabular-nums"
        )}
        title={String(text || "")}
      >
        {value ?? "—"}
      </span>
      <button
        type="button"
        onClick={doCopy}
        className="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-xl border border-white/10 hover:bg-white/10 transition"
        aria-label="Copiar"
        title="Copiar"
      >
        {copied ? <Check size={16} /> : <Copy size={16} />}
      </button>
    </div>
  );
}

const InfoChip = ({ icon: Icon, label, children }) => (
  <div className="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/[0.025] p-3.5">
    <div className="mt-0.5 flex h-9 w-9 items-center justify-center rounded-xl border border-white/10 bg-white/[0.04]">
      <Icon size={16} />
    </div>
    <div className="min-w-0">
      <div className="text-[11px] uppercase tracking-wide text-white/45">{label}</div>
      <div className="mt-0.5">{children}</div>
    </div>
  </div>
);

const Skeleton = ({ className = "" }) => (
  <div className={classNames("animate-pulse rounded-xl bg-white/10", className)} />
);

/* ===========================
   Page
=========================== */
export default function DadosConta() {
  const { props } = usePage();
  const inertiaUser = props?.auth?.user ?? null;

  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);

  // Busca /api/me/summary e usa inertia como fallback
  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const res = await fetch("/api/me/summary", { credentials: "include" });
        const data = await res.json().catch(() => ({}));
        if (alive && data?.ok) setProfile(data.data);
        else if (alive) setProfile(null);
      } catch {
        if (alive) setProfile(null);
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => {
      alive = false;
    };
  }, []);

  const id = profile?.id ?? inertiaUser?.id ?? "—";
  const nome = profile?.nome ?? inertiaUser?.nome_completo ?? inertiaUser?.name ?? "";
  const email = profile?.email ?? inertiaUser?.email ?? "";
  const cpfRaw = profile?.cpf_cnpj ?? inertiaUser?.cpf_cnpj ?? inertiaUser?.cpf;
  const cpfMasked = cpfMask(cpfRaw);
  const createdAt = formatDateTime(profile?.created_at ?? inertiaUser?.created_at);

  const avatar = initials(nome || email, "U");

  return (
    <AuthenticatedLayout>
      <Head title="Identificação" />

      <div className="p-4 md:p-6 space-y-5">
        {/* Header arredondado, elegante */}
        <Shell>
          <div className="p-5">
            {/* Responsividade aprimorada aqui */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div className="flex items-center gap-3 min-w-0">
                <div className="relative">
                  <div className="absolute inset-0 rounded-2xl bg-gradient-to-br from-emerald-400/30 via-emerald-500/20 to-transparent blur-md opacity-50" />
                  <div className="relative flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.05] font-semibold text-lg">
                    {loading ? <Skeleton className="h-6 w-10" /> : avatar}
                  </div>
                </div>
                <div className="min-w-0">
                  <div className="text-xs text-white/60">Identificação</div>
                  {loading ? (
                    <Skeleton className="mt-1 h-6 w-44" />
                  ) : (
                    <h1 className="truncate text-2xl font-medium text-zinc-100">{nome || email || "—"}</h1>
                  )}
                </div>
              </div>

              {/* Pills responsivas: Coluna única -> 2 colunas (xs) -> 3 colunas (sm) -> Linha Flex (lg) */}
              <div className="grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-3 lg:flex gap-2 w-full">
                <Pill className="justify-between">
                  <span className="text-[11px] uppercase text-white/55">Usuário</span>
                  {loading ? (
                    <Skeleton className="h-4 w-20" />
                  ) : (
                    <span className="font-mono text-sm">{id}</span>
                  )}
                </Pill>
                <Pill className="justify-between">
                  <span className="text-[11px] uppercase text-white/55">Criado em</span>
                  {loading ? (
                    <Skeleton className="h-4 w-28" />
                  ) : (
                    <span className="text-sm">{createdAt}</span>
                  )}
                </Pill>
                {/* Esta pill pode quebrar linha mais facilmente para manter o layout */}
                <Pill className="gap-2 sm:col-span-1">
                  <span className="h-2 w-2 rounded-full bg-emerald-400" />
                  <span className="text-sm">Ativo</span>
                </Pill>
              </div>
            </div>
          </div>
        </Shell>

        {/* Grid de informações (rounded) */}
        {/* O layout de 2 colunas (md:grid-cols-2) está OK, mas adicionamos uma 'Shell' em cada coluna para consistência */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
          <Shell>
            <div className="p-5">
              <div className="mb-3 flex items-center gap-2">
                <div className="h-9 w-9 rounded-xl border border-white/10 bg-white/[0.04] flex items-center justify-center">
                  <User size={16} className="text-emerald-400" />
                </div>
                <h2 className="text-sm font-semibold text-zinc-200">Dados básicos</h2>
              </div>

              <div className="grid gap-3.5">
                <InfoChip icon={IdCard} label="ID do usuário">
                  {loading ? <Skeleton className="h-8 w-40" /> : <Copyable text={id} mono />}
                </InfoChip>

                <InfoChip icon={User} label="Nome">
                  {loading ? <Skeleton className="h-8 w-64" /> : <Copyable text={nome || "—"} />}
                </InfoChip>

                <InfoChip icon={AtSign} label="E-mail">
                  {loading ? <Skeleton className="h-8 w-64" /> : <Copyable text={email} />}
                </InfoChip>
              </div>
            </div>
          </Shell>

          <Shell>
            <div className="p-5">
              <div className="mb-3 flex items-center gap-2">
                <div className="h-9 w-9 rounded-xl border border-white/10 bg-white/[0.04] flex items-center justify-center">
                  <Fingerprint size={16} className="text-emerald-400" />
                </div>
                <h2 className="text-sm font-semibold text-zinc-200">Documento</h2>
              </div>

              <div className="grid gap-3.5">
                <InfoChip icon={Fingerprint} label="CPF">
                  {loading ? (
                    <Skeleton className="h-8 w-44" />
                  ) : (
                    <Copyable text={onlyDigits(cpfRaw)} masked={cpfMasked} mono />
                  )}
                </InfoChip>

                <InfoChip icon={CalendarClock} label="Criado em">
                  {loading ? <Skeleton className="h-8 w-56" /> : <Copyable text={createdAt} />}
                </InfoChip>
              </div>
            </div>
          </Shell>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}