import React from "react";
import { FileText, ArrowDownRight, Clock, Loader2, CheckCircle2, XCircle } from "lucide-react";

const formatCurrency = (value) =>
  (Number(value) || 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  });

const fmtDate = (iso) => {
  if (!iso) return "—";
  const d = new Date(iso);
  return d.toLocaleString("pt-BR", { dateStyle: "short", timeStyle: "short" });
};

const mapStatus = (raw) => {
  const s = String(raw || "").toLowerCase();

  if (["paid", "approved", "completed"].includes(s)) return "COMPLETED";
  if (["pending", "processing"].includes(s)) return "PENDING";
  if (["failed", "error", "denied", "rejected"].includes(s)) return "FAILED";

  return "PENDING";
};

const StatusPill = ({ status }) => {
  const s = mapStatus(status);

  const cfg = {
    PENDING: {
      cls: "bg-amber-500/10 text-amber-500 border-amber-500/20",
      icon: Clock,
      label: "Pending",
    },
    PROCESSING: {
      cls: "bg-sky-500/10 text-sky-500 border-sky-500/20",
      icon: Loader2,
      label: "Processing",
    },
    COMPLETED: {
      cls: "bg-emerald-500/10 text-emerald-400 border-emerald-500/20",
      icon: CheckCircle2,
      label: "Completed",
    },
    FAILED: {
      cls: "bg-red-500/10 text-red-400 border-red-500/20",
      icon: XCircle,
      label: "Failed",
    },
  }[s];

  const Icon = cfg.icon;

  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 text-[11px] border rounded-md ${cfg.cls}`}>
      <Icon size={12} />
      {cfg.label}
    </span>
  );
};

export default function SaqueTable({
  loading,
  filtered,
  fullList,
  statusFilter,
  setStatusFilter,
  query,
  setQuery,
  page,
  setPage,
  totalPages,
}) {
  return (
    <div className="rounded-2xl bg-[#0b0b0b]/90 p-6 border border-white/10">
      {/* HEADER */}
      <div className="flex justify-between mb-4">
        <h3 className="text-lg font-semibold text-white">Withdrawals</h3>
        <span className="text-xs text-gray-400">
          Page {page} of {totalPages}
        </span>
      </div>

      {/* TABLE */}
      <div className="overflow-x-auto rounded-xl border border-white/10">
        <table className="min-w-full text-sm">
          <thead className="bg-[#141414] text-gray-400 border-b border-white/10">
            <tr>
              <th className="px-4 py-3">ID</th>
              <th className="px-4 py-3">Amount</th>
              <th className="px-4 py-3">Fee</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">E2E</th>
              <th className="px-4 py-3">Requested At</th>
              <th className="px-4 py-3 text-center">Action</th>
            </tr>
          </thead>

          <tbody>
            {loading ? (
              [...Array(10)].map((_, i) => (
                <tr key={i} className="animate-pulse border-b border-white/5">
                  <td className="py-4 px-4 bg-white/5" colSpan={7}></td>
                </tr>
              ))
            ) : filtered.length === 0 ? (
              <tr>
                <td colSpan={7} className="py-10 text-center text-gray-500">
                  No withdrawals found.
                </td>
              </tr>
            ) : (
              filtered.map((s) => (
                <tr
                  key={s.id}
                  className="hover:bg-[#1a1a1a] border-b border-white/5 transition cursor-pointer"
                >
                  <td className="px-4 py-3 text-gray-300 font-mono text-xs">#{s.id}</td>

                  <td className="px-4 py-3 font-semibold text-gray-200">
                    {formatCurrency(s.amount)}
                  </td>

                  <td className="px-4 py-3 text-gray-400">{formatCurrency(s.fee)}</td>

                  <td className="px-4 py-3">
                    <StatusPill status={s.status} />
                  </td>

                  <td className="px-4 py-3 font-mono text-xs text-gray-400">{s.e2e || "—"}</td>

                  <td className="px-4 py-3 text-gray-400">{fmtDate(s.created_at)}</td>

                  <td className="px-4 py-3 text-center">
                    <button className="px-2 py-1 text-xs border border-white/20 rounded-md hover:bg-white/10 flex items-center gap-1 text-gray-300">
                      <FileText size={13} /> Details
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* PAGINATION */}
      <div className="flex justify-between mt-5">
        <button
          disabled={page <= 1}
          onClick={() => page > 1 && setPage(page - 1)}
          className={`px-3 py-2 rounded-lg text-xs border ${
            page > 1
              ? "border-white/20 text-gray-200 hover:bg-[#1a1a1a]"
              : "border-white/10 text-gray-500 cursor-not-allowed"
          }`}
        >
          ← Previous
        </button>

        <button
          disabled={page >= totalPages}
          onClick={() => page < totalPages && setPage(page + 1)}
          className={`px-3 py-2 rounded-lg text-xs border ${
            page < totalPages
              ? "border-white/20 text-gray-200 hover:bg-[#1a1a1a]"
              : "border-white/10 text-gray-500 cursor-not-allowed"
          }`}
        >
          Next →
        </button>
      </div>
    </div>
  );
}
