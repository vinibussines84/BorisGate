import React from "react";
import { Eye, Search } from "lucide-react";
import StatusPill from "@/Components/StatusPill";
import OriginPill from "@/Components/OriginPill";

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
  { key: "canceled", label: "Cancelado" },
  { key: "failed", label: "Falhou" },
];

const SkeletonRow = () => (
  <tr className="border-b border-white/5">
    {Array.from({ length: 7 }).map((_, i) => (
      <td key={i} className="py-2 px-4">
        <div className="h-4 w-full max-w-[100px] bg-white/10 rounded animate-pulse" />
      </td>
    ))}
  </tr>
);

export default function SaqueTable({
  filtered,
  loading,
  statusFilter,
  setStatusFilter,
  query,
  setQuery,
  onOpenReceipt,
  setOpenReceipt,
}) {
  return (
    <div className="bg-[#0b0b0b]/95 border border-white/10 rounded-3xl p-5 backdrop-blur-sm shadow-[0_0_40px_-10px_rgba(0,0,0,0.8)] space-y-5 transition">
      {/* Tabs de Status */}
      <div className="flex overflow-x-auto no-scrollbar gap-2 p-1 rounded-xl border border-white/10 bg-[#050505]/80">
        {STATUS_TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => setStatusFilter(t.key)}
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

      {/* Campo de Busca */}
      <label className="relative block">
        <Search
          size={14}
          className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"
        />
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Buscar por ID, chave PIX ou end-to-end"
          className="w-full pl-9 pr-3 py-2 text-xs rounded-lg bg-[#0a0a0a]/70 border border-white/10 text-gray-200 placeholder:text-gray-500 focus:ring-2 focus:ring-[#02fb5c]/40 focus:border-[#02fb5c]/40 outline-none transition-all duration-200"
        />
      </label>

      {/* Tabela */}
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
              <th className="py-3 px-4 text-center">Ação</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              Array.from({ length: 6 }).map((_, i) => <SkeletonRow key={i} />)
            ) : filtered.length === 0 ? (
              <tr>
                <td
                  colSpan={7}
                  className="py-10 text-center text-gray-500 text-sm"
                >
                  Nenhum saque encontrado.
                </td>
              </tr>
            ) : (
              filtered.map((s) => (
                <tr
                  key={s.id}
                  className="border-b border-white/5 hover:bg-[#141414]/60 transition-colors"
                >
                  <td className="py-3 px-4 text-gray-300 font-mono text-xs">
                    #{s.id}
                  </td>
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
                  <td className="py-3 px-4 text-gray-400">
                    {fmtDate(s.created_at)}
                  </td>
                  <td className="py-3 px-4 text-center">
                    {s.status === "paid" ? (
                      <button
                        onClick={() => {
                          onOpenReceipt(s);
                          setOpenReceipt(true);
                        }}
                        className="inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg border border-[#02fb5c]/30 text-[#02fb5c] hover:bg-[#02fb5c]/10 hover:text-[#02fb5c] transition"
                      >
                        <Eye size={13} />
                        Ver
                      </button>
                    ) : (
                      <span className="inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg border border-white/10 text-gray-600 cursor-not-allowed opacity-60">
                        <Eye size={13} />
                        Ver
                      </span>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
