import React from "react";
import {
  FileText,
  ArrowUpRight,
  ArrowDownRight,
  Clock,
  Loader2,
  CheckCircle2,
  XCircle,
} from "lucide-react";

/* =====================================================================================
   FORMATADORES
===================================================================================== */
const formatCurrency = (value) =>
  (Number(value) || 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  });

const fmtDate = (iso) => {
  if (!iso) return "—";
  const d = new Date(iso);
  return isNaN(d.getTime())
    ? "—"
    : d.toLocaleString("pt-BR", { dateStyle: "short", timeStyle: "short" });
};

/* =====================================================================================
   STATUS
===================================================================================== */
const mapStatus = (s) => {
  const normalized = String(s || "").trim().toLowerCase();

  const groups = {
    EFETIVADO: ["paga", "paid", "approved", "completed"],
    FALHADO: ["falha", "failed", "erro", "error"],
    PENDENTE: ["pendente", "pending", "processing", "under_review"],
  };

  for (const [key, values] of Object.entries(groups)) {
    if (values.includes(normalized)) return key;
  }

  return normalized.toUpperCase();
};

const StatusPill = ({ status }) => {
  const s = String(status || "").toUpperCase();

  const map = {
    PENDENTE: {
      cls: "bg-amber-500/10 text-amber-500 border-amber-500/20",
      icon: Clock,
      label: "Pendente",
    },
    PROCESSANDO: {
      cls: "bg-sky-500/10 text-sky-500 border-sky-500/20",
      icon: Loader2,
      label: "Processando",
    },
    EFETIVADO: {
      cls: "bg-[#02fb5c]/10 text-[#02fb5c] border-[#02fb5c]/30",
      icon: CheckCircle2,
      label: "Efetivado",
    },
    FALHADO: {
      cls: "bg-[#ff3b5c]/10 text-[#ff3b5c] border-[#ff3b5c]/20",
      icon: XCircle,
      label: "Falhado",
    },
  };

  const cfg = map[s] || {
    cls: "bg-white/10 text-gray-300 border-white/20",
    icon: Clock,
    label: s || "—",
  };

  const Icon = cfg.icon;
  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] rounded-lg border font-medium ${cfg.cls}`}
    >
      <Icon size={12} className={s === "PROCESSANDO" ? "animate-spin" : ""} />
      {cfg.label}
    </span>
  );
};

/* =====================================================================================
   ORIGIN PILL
===================================================================================== */
const OriginPill = ({ type }) => {
  const credit = type === "PIX";
  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] rounded-lg border font-medium ${
        credit
          ? "bg-[#02fb5c]/10 text-[#02fb5c] border-[#02fb5c]/30"
          : "bg-[#ff3b5c]/10 text-[#ff3b5c] border-[#ff3b5c]/30"
      }`}
    >
      {credit ? <ArrowUpRight size={12} /> : <ArrowDownRight size={12} />}
      {credit ? "Entrada (PIX)" : "Débito (Saque)"}
    </span>
  );
};

/* =====================================================================================
   COMPONENTE PRINCIPAL — ExtratoTable
===================================================================================== */
export default function ExtratoTable({
  transactions = [],
  onView,
  page,
  setPage,
  perPage = 10,
  totalItems = 0,
  loading = false,
}) {
  const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
  const canPrev = page > 1;
  const canNext = page < totalPages;

  return (
    <div className="bg-[#0b0b0b]/95 border border-white/10 rounded-3xl p-6 backdrop-blur-sm min-h-[520px] flex flex-col justify-between transition-all duration-300">
      {/* HEADER */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-base font-semibold text-white">
            Histórico de Transações
          </h3>
          <span className="text-[11px] text-gray-400">
            {loading
              ? "Carregando..."
              : `${transactions.length} resultados nesta página (${totalItems} total)`}
          </span>
        </div>

        {/* TABELA */}
        <div className="overflow-x-auto rounded-2xl border border-white/10 min-h-[340px]">
          <table className="min-w-full text-sm">
            <thead className="sticky top-0 bg-[#0a0a0a]/95 backdrop-blur border-b border-white/10">
              <tr className="text-left text-gray-400">
                <th className="py-2.5 px-4">ID/Ref.</th>
                <th className="py-2.5 px-4">Tipo</th>
                <th className="py-2.5 px-4 text-right">Valor</th>
                <th className="py-2.5 px-4">Status</th>
                <th className="py-2.5 px-4">Data</th>
                <th className="py-2.5 px-4 text-center">Ação</th>
              </tr>
            </thead>

            <tbody>
              {loading ? (
                [...Array(perPage)].map((_, i) => (
                  <tr key={i} className="border-b border-white/5 animate-pulse">
                    <td colSpan={6} className="py-4 text-center text-gray-600">
                      &nbsp;
                    </td>
                  </tr>
                ))
              ) : transactions.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-12 text-center text-gray-400">
                    Nenhuma transação encontrada.
                  </td>
                </tr>
              ) : (
                transactions.map((t) => (
                  <tr
                    key={t.id}
                    className="border-b border-white/5 hover:bg-[#141414]/60 cursor-pointer transition-colors"
                    onClick={() => onView(t)}
                  >
                    <td className="py-2.5 px-4 font-mono text-xs text-gray-300">
                      #{t.id}
                    </td>
                    <td className="py-2.5 px-4">
                      <OriginPill type={t.credit ? "PIX" : "SAQUE"} />
                    </td>
                    <td className="py-2.5 px-4 text-right font-semibold tabular-nums text-gray-200">
                      {formatCurrency(t.amount)}
                    </td>
                    <td className="py-2.5 px-4">
                      <StatusPill status={mapStatus(t.status)} />
                    </td>
                    <td className="py-2.5 px-4 text-gray-400">
                      {fmtDate(t.paidAt || t.createdAt)}
                    </td>
                    <td className="py-2.5 px-4 text-center">
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          onView(t);
                        }}
                        className="inline-flex items-center gap-1 px-2 py-1 rounded-lg border text-xs border-white/10 text-gray-300 hover:bg-[#1a1a1a]"
                      >
                        <FileText size={13} /> Detalhes
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* PAGINAÇÃO */}
      <div className="mt-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <p className="text-xs text-gray-400">
          Página {page} de {totalPages}
        </p>

        <div className="flex items-center gap-2">
          <button
            disabled={!canPrev}
            onClick={() => canPrev && setPage(page - 1)}
            className={`px-3 py-2 text-xs rounded-lg border transition ${
              canPrev
                ? "border-white/10 text-gray-300 hover:bg-[#1a1a1a]"
                : "border-white/10 text-gray-600 cursor-not-allowed"
            }`}
          >
            ← Anterior
          </button>
          <button
            disabled={!canNext}
            onClick={() => canNext && setPage(page + 1)}
            className={`px-3 py-2 text-xs rounded-lg border transition ${
              canNext
                ? "border-white/10 text-gray-300 hover:bg-[#1a1a1a]"
                : "border-white/10 text-gray-600 cursor-not-allowed"
            }`}
          >
            Próxima →
          </button>
        </div>
      </div>
    </div>
  );
}
