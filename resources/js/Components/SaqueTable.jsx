import React, { useMemo, useCallback } from "react";
import { Search } from "lucide-react";
import OriginPill from "@/Components/OriginPill";

/* =======================================================
   🔧 Helpers
======================================================= */
const BRL = (v) =>
  (Number(v) || 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
  });

const fmtDate = (iso) => {
  if (!iso) return "—";
  const d = new Date(iso);
  return d.toLocaleString("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
  });
};

const STATUS_TABS = [
  { key: "all", label: "Todos" },
  { key: "pending", label: "Pendente" },
  { key: "processing", label: "Processando" },
  { key: "approved", label: "Aprovado" },
  { key: "paid", label: "Pago" },
  { key: "failed", label: "Falhou" },
  { key: "rejected", label: "Rejeitado" },
  { key: "canceled", label: "Cancelado" },
];

/* =======================================================
   🔵 Status Color Pill
======================================================= */
const StatusPill = ({ status }) => {
  const base =
    "px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center gap-1 border capitalize";
  const colorMap = {
    paid: "bg-emerald-500/10 text-emerald-400 border-emerald-500/30",
    approved: "bg-emerald-500/10 text-emerald-400 border-emerald-500/30",
    processing: "bg-sky-500/10 text-sky-400 border-sky-500/30",
    pending: "bg-amber-500/10 text-amber-400 border-amber-500/30",
    failed: "bg-rose-500/10 text-rose-400 border-rose-500/30",
    rejected: "bg-rose-500/10 text-rose-400 border-rose-500/30",
    canceled: "bg-gray-500/10 text-gray-400 border-gray-500/30",
  };
  const style = colorMap[status?.toLowerCase()] || "bg-white/5 text-gray-400 border-white/10";
  return <span className={`${base} ${style}`}>{status}</span>;
};

/* =======================================================
   Skeleton Row
======================================================= */
const SkeletonRow = React.memo(() => (
  <tr className="border-b border-white/5">
    {Array.from({ length: 6 }).map((_, i) => (
      <td key={i} className="py-2 px-4">
        <div className="h-4 w-full max-w-[100px] bg-white/10 rounded animate-pulse" />
      </td>
    ))}
  </tr>
));

/* =======================================================
   Main Component
======================================================= */
export default function SaqueTable({
  filtered,
  fullList,
  loading,
  statusFilter,
  setStatusFilter,
  query,
  setQuery,
  page,
  setPage,
  totalPages,
}) {
  const handleSearch = useCallback((e) => setQuery(e.target.value), [setQuery]);
  const handleStatusChange = useCallback(
    (key) => {
      setStatusFilter(key);
      setPage(1);
    },
    [setStatusFilter, setPage]
  );

  const renderedRows = useMemo(() => {
    if (loading)
      return Array.from({ length: 6 }).map((_, i) => <SkeletonRow key={i} />);
    if (!filtered?.length)
      return (
        <tr>
          <td colSpan={6} className="py-10 text-center text-gray-500 text-sm">
            Nenhum saque encontrado.
          </td>
        </tr>
      );

    return filtered.map((s) => (
      <tr
        key={s.id}
        className="border-b border-white/5 hover:bg-[#141414]/60 transition-colors"
      >
        <td className="py-3 px-4 text-gray-400 font-mono text-xs">#{s.id}</td>
        <td className="py-3 px-4 text-right text-white font-semibold">
          {BRL(s.amount)}
        </td>
        <td className="py-3 px-4 text-right text-gray-400">
          {BRL(s.fee_amount || 0)}
        </td>
        <td className="py-3 px-4">
          <OriginPill origin={s.origin} label={s.origin_label} />
        </td>
        <td className="py-3 px-4">
          <StatusPill status={s.status} />
        </td>
        <td className="py-3 px-4 text-gray-400">{fmtDate(s.created_at)}</td>
      </tr>
    ));
  }, [loading, filtered]);

  return (
    <div className="bg-[#0b0b0b]/95 border border-white/10 rounded-3xl p-5 backdrop-blur-sm shadow-[0_0_40px_-10px_rgba(0,0,0,0.8)] space-y-5 transition">
      {/* Status Tabs */}
      <div className="flex overflow-x-auto no-scrollbar gap-2 p-1 rounded-xl border border-white/10 bg-[#050505]/80">
        {STATUS_TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => handleStatusChange(t.key)}
            className={`flex-shrink-0 px-3 py-1.5 text-xs rounded-lg transition-all duration-200 ${
              statusFilter === t.key
                ? "bg-[#02fb5c]/10 text-[#02fb5c] border border-[#02fb5c]/30 shadow-[0_0_8px_rgba(2,251,92,0.2)]"
                : "text-gray-300 hover:text-white"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* Search */}
      <label className="relative block">
        <Search
          size={14}
          className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"
        />
        <input
          value={query}
          onChange={handleSearch}
          placeholder="Buscar por ID, chave PIX..."
          className="w-full pl-9 pr-3 py-2 text-xs rounded-lg bg-[#0a0a0a]/70 border border-white/10 text-gray-200 placeholder:text-gray-500 focus:ring-2 focus:ring-[#02fb5c]/40 focus:border-[#02fb5c]/40 outline-none transition-all duration-200"
        />
      </label>

      {/* Table */}
      <div className="overflow-x-auto rounded-2xl border border-white/10">
        <table className="min-w-full text-sm">
          <thead className="bg-[#0a0a0a]/95 border-b border-white/10 text-gray-400">
            <tr>
              <th className="py-3 px-4 text-left">ID</th>
              <th className="py-3 px-4 text-right">Valor</th>
              <th className="py-3 px-4 text-right">Taxa</th>
              <th className="py-3 px-4 text-left">Origem</th>
              <th className="py-3 px-4 text-left">Status</th>
              <th className="py-3 px-4 text-left">Data</th>
            </tr>
          </thead>
          <tbody>{renderedRows}</tbody>
        </table>
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-center gap-2 pt-4 text-xs text-gray-400">
          <button
            onClick={() => setPage((p) => Math.max(p - 1, 1))}
            disabled={page === 1}
            className="px-3 py-1 rounded-lg border border-white/10 bg-[#111]/60 hover:bg-[#222]/60 disabled:opacity-50"
          >
            ← Anterior
          </button>
          <span>
            Página {page} de {totalPages}
          </span>
          <button
            onClick={() => setPage((p) => Math.min(p + 1, totalPages))}
            disabled={page === totalPages}
            className="px-3 py-1 rounded-lg border border-white/10 bg-[#111]/60 hover:bg-[#222]/60 disabled:opacity-50"
          >
            Próxima →
          </button>
        </div>
      )}
    </div>
  );
}
