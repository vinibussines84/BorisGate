// resources/js/Pages/Cobranca/Index.jsx
import React, { useEffect, useState, useMemo } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
  PlusCircle,
  Receipt,
  RefreshCw,
  Clock,
  CheckCircle2,
  Copy,
  Check,
  Info,
} from "lucide-react";
import axios from "axios";
import NovaCobrancaModal from "@/Components/NovaCobrancaModal";

/* ================= Utils ================= */
const BRL = (v) =>
  Number(v || 0).toLocaleString("pt-BR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

function useCopy() {
  const [copiedId, setCopiedId] = useState(null);
  const copy = async (text, id = null) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopiedId(id ?? true);
      setTimeout(() => setCopiedId(null), 1400);
    } catch {}
  };
  return { copiedId, copy };
}

/* ================= Micro UI ================= */
function PageTitle({ icon: Icon, title, subtitle }) {
  return (
    <div className="flex items-center gap-3">
      <div className="p-2.5 rounded-2xl border border-zinc-800 bg-zinc-950">
        <Icon className="w-5 h-5 text-zinc-300" />
      </div>
      <div className="leading-tight">
        <h1 className="text-[20px] font-semibold text-zinc-100 tracking-tight">{title}</h1>
        <p className="text-[12px] text-zinc-500 font-light">{subtitle}</p>
      </div>
    </div>
  );
}

function GhostButton({ children, onClick, disabled }) {
  return (
    <button
      onClick={onClick}
      disabled={disabled}
      className="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium
                 text-zinc-200 border border-zinc-800 bg-zinc-950 hover:bg-zinc-900
                 transition disabled:opacity-60"
    >
      {children}
    </button>
  );
}

function AccentButton({ children, onClick }) {
  return (
    <button
      onClick={onClick}
      className="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold
                 border border-zinc-700/60 bg-zinc-900 text-zinc-100
                 hover:bg-zinc-800 hover:border-zinc-600 transition"
    >
      {children}
    </button>
  );
}

/* ================= Cards ================= */
function StatCard({ title, value, hint, icon: Icon, tone = "pending" }) {
  const tones = {
    pending: { ring: "border-amber-500/25", bg: "bg-amber-500/10", txt: "text-amber-200" },
    paid: { ring: "border-emerald-500/25", bg: "bg-emerald-500/10", txt: "text-emerald-200" },
  }[tone];

  return (
    <div
      className="relative rounded-2xl border border-zinc-800 bg-zinc-950/90 backdrop-blur p-4
                 hover:border-zinc-700 transition"
    >
      <div className="flex items-center justify-between">
        <div className="min-w-0">
          <p className="text-[11px] text-zinc-500 font-light">{title}</p>
          <p className="mt-1 text-xl font-semibold text-zinc-100">{value}</p>
          {hint ? <p className="mt-0.5 text-[11px] text-zinc-500 font-light">{hint}</p> : null}
        </div>
        <div className={`h-10 w-10 shrink-0 rounded-xl border ${tones.ring} ${tones.bg} grid place-items-center`}>
          <Icon size={18} className={tones.txt} />
        </div>
      </div>
    </div>
  );
}

/* ================= Tabela ================= */
function StatusBadge({ status }) {
  const s = String(status || "").toLowerCase();
  const map = {
    pending:  { bg: "bg-amber-500/10",   bd: "border-amber-500/25",   tx: "text-amber-200",  label: "Pendente" },
    paid:     { bg: "bg-emerald-500/10", bd: "border-emerald-500/25", tx: "text-emerald-200", label: "Paga" },
    failed:   { bg: "bg-red-500/10",     bd: "border-red-500/25",     tx: "text-red-200",     label: "Falhou" },
    refunded: { bg: "bg-sky-500/10",     bd: "border-sky-500/25",     tx: "text-sky-200",     label: "Estornada" },
  };
  const tone = map[s] ?? map.pending;

  return (
    <span
      className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] ${tone.bg} ${tone.bd} ${tone.tx}`}
    >
      {tone.label}
    </span>
  );
}

function ChargeRow({ item, onCopy, copied }) {
  const created = useMemo(() => {
    try {
      return new Date(item.created_at).toLocaleString("pt-BR");
    } catch {
      return item.created_at || "—";
    }
  }, [item.created_at]);

  const emv = item.qrcode || item.qr_code || item.emv || "";

  return (
    <tr className="border-b border-zinc-800 hover:bg-zinc-900/40">
      <td className="px-3 py-3 text-[12px] text-zinc-300 font-light">{created}</td>
      <td className="px-3 py-3 text-[12px] text-zinc-200 font-medium">R$ {BRL(item.amount)}</td>
      <td className="px-3 py-3"><StatusBadge status={item.status} /></td>
      <td className="px-3 py-3">
        {emv ? (
          <div className="flex items-center gap-2">
            <div
              className="max-w-[360px] truncate font-mono text-[11px] text-zinc-200"
              title={emv}
            >
              {emv}
            </div>
            <button
              onClick={() => onCopy(emv, item.id)}
              className="inline-flex items-center gap-1 rounded-md border border-zinc-800
                         bg-zinc-950 px-2 py-1 text-[11px] text-zinc-100 hover:bg-zinc-900 transition"
              title="Copiar 'copia e cola'"
            >
              {copied ? <Check size={12} /> : <Copy size={12} />}
              {copied ? "Copiado" : "Copiar"}
            </button>
          </div>
        ) : (
          <span className="text-[11px] text-zinc-500">—</span>
        )}
      </td>
      <td className="px-3 py-3 text-right">
        <span className="text-[11px] text-zinc-500">TXID:</span>{" "}
        <span className="text-[11px] text-zinc-300 font-mono">
          {item.txid || item.provider_transaction_id || "—"}
        </span>
      </td>
    </tr>
  );
}

/* ================= Página ================= */
export default function CobrancaIndex() {
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [stats, setStats] = useState({ pending: 0, paid: 0 });
  const [charges, setCharges] = useState([]);
  const [modalOpen, setModalOpen] = useState(false);
  const { copiedId, copy } = useCopy();

  const fetchStats = async () => {
    try {
      setIsRefreshing(true);
      const { data } = await axios.get("/api/charges/summary", { headers: { Accept: "application/json" } });
      if (data?.success) setStats({ pending: Number(data.pending || 0), paid: Number(data.paid || 0) });
      else setStats({ pending: 0, paid: 0 });
    } catch {
      setStats({ pending: 0, paid: 0 });
    } finally {
      setIsRefreshing(false);
    }
  };

  const fetchCharges = async () => {
    try {
      const { data } = await axios.get("/api/charges", { headers: { Accept: "application/json" } });
      if (Array.isArray(data?.data)) setCharges(data.data);
      else if (Array.isArray(data)) setCharges(data);
      else setCharges([]);
    } catch {
      setCharges([]);
    }
  };

  useEffect(() => {
    fetchStats();
    fetchCharges();
  }, []);

  const onModalSuccess = async () => {
    await Promise.all([fetchStats(), fetchCharges()]);
  };

  return (
    <AuthenticatedLayout>
      <Head title="Cobrança" />
      <div className="min-h-screen bg-[#0A0B0D] py-8 px-4 sm:px-6 lg:px-8 text-zinc-100">
        <div className="mx-auto w-full max-w-5xl space-y-6">
          {/* Header */}
          <div className="flex items-center justify-between">
            <PageTitle
              icon={Receipt}
              title="Cobrança (BETA)"
              subtitle="Crie cobranças Pix e compartilhe o “copia e cola”."
            />
            <div className="flex items-center gap-2">
              <GhostButton onClick={() => { fetchStats(); fetchCharges(); }} disabled={isRefreshing}>
                <RefreshCw size={14} className={isRefreshing ? "animate-spin" : ""} />
                {isRefreshing ? "Atualizando..." : "Atualizar"}
              </GhostButton>
              <AccentButton onClick={() => setModalOpen(true)}>
                <PlusCircle size={15} />
                Nova cobrança
              </AccentButton>
            </div>
          </div>

          {/* Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <StatCard title="Pendentes" value={stats.pending} hint="Aguardando pagamento" icon={Clock} tone="pending" />
            <StatCard title="Pagas" value={stats.paid} hint="Liquidadas" icon={CheckCircle2} tone="paid" />
          </div>

          {/* Lista */}
          <div className="rounded-3xl border border-zinc-800 bg-zinc-950/80 backdrop-blur p-5">
            <div className="mb-4 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="inline-block h-[3px] w-3 rounded-sm bg-zinc-300" />
                <h3 className="text-[15px] font-semibold text-zinc-100 tracking-tight">Minhas cobranças</h3>
              </div>
              <GhostButton onClick={() => { fetchStats(); fetchCharges(); }} disabled={isRefreshing}>
                <RefreshCw size={14} className={isRefreshing ? "animate-spin" : ""} />
                {isRefreshing ? "Atualizando..." : "Atualizar"}
              </GhostButton>
            </div>

            {charges.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-16 rounded-2xl border border-zinc-800 bg-zinc-900/40">
                <Info className="w-10 h-10 text-zinc-500 mb-3" />
                <p className="text-zinc-300 font-medium">Nenhuma cobrança listada</p>
                <p className="text-[12px] text-zinc-500 mt-1 font-light">
                  Clique em <span className="text-zinc-200 font-medium">Nova cobrança</span> para gerar o “copia e cola”.
                </p>
              </div>
            ) : (
              <div className="overflow-x-auto rounded-2xl border border-zinc-900">
                <table className="min-w-full text-left">
                  <thead>
                    <tr className="text-[11px] uppercase tracking-wider text-zinc-500 bg-zinc-950/60">
                      <th className="px-3 py-2 font-medium">Data</th>
                      <th className="px-3 py-2 font-medium">Valor</th>
                      <th className="px-3 py-2 font-medium">Status</th>
                      <th className="px-3 py-2 font-medium">Copia e cola (Pix)</th>
                      <th className="px-3 py-2 font-medium text-right">Ref.</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-zinc-900">
                    {charges.map((c) => (
                      <ChargeRow key={c.id} item={c} onCopy={copy} copied={copiedId === c.id} />
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Modal */}
      <NovaCobrancaModal open={modalOpen} onClose={() => setModalOpen(false)} onSuccess={onModalSuccess} />
    </AuthenticatedLayout>
  );
}
