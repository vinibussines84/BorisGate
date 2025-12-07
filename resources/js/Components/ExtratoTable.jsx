import React, { useMemo, useEffect } from "react";
import {
  FileText,
  ArrowUpRight,
  Clock,
  Loader2,
  CheckCircle2,
  XCircle,
} from "lucide-react";

/* =====================================================================================
   FORMATTERS
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
   STATUS MAP
===================================================================================== */
const mapStatus = (s) => {
  const normalized = String(s || "").trim().toLowerCase();

  const groups = {
    COMPLETED: ["paga", "paid", "approved", "completed"],
    FAILED: ["falha", "failed", "erro", "error", "cancelada"],
    PENDING: ["pendente", "pending", "processing", "under_review"],
  };

  for (const [key, values] of Object.entries(groups)) {
    if (values.includes(normalized)) return key;
  }

  return normalized.toUpperCase();
};

const StatusPill = React.memo(({ status }) => {
  const s = String(status || "").toUpperCase();

  const map = {
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
      cls: "bg-[#02fb5c]/10 text-[#02fb5c] border-[#02fb5c]/30",
      icon: CheckCircle2,
      label: "Completed",
    },
    FAILED: {
      cls: "bg-[#ff3b5c]/10 text-[#ff3b5c] border-[#ff3b5c]/20",
      icon: XCircle,
      label: "Failed",
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
      <Icon size={12} className={s === "PROCESSING" ? "animate-spin" : ""} />
      {cfg.label}
    </span>
  );
});
StatusPill.displayName = "StatusPill";

/* =====================================================================================
   ORIGIN PILL (AGORA SOMENTE PIX)
===================================================================================== */
const OriginPill = React.memo(() => {
  return (
    <span
      className={
        "inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] rounded-lg border font-medium " +
        "bg-[#02fb5c]/10 text-[#02fb5c] border-[#02fb5c]/30"
      }
    >
      <ArrowUpRight size={12} />
      Credit (PIX)
    </span>
  );
});
OriginPill.displayName = "OriginPill";

/* =====================================================================================
   CONFIG
===================================================================================== */
const CACHE_KEY = "extract_table_cache_v1";
const CACHE_TTL = 15 * 1000;

/* =====================================================================================
   MAIN COMPONENT
===================================================================================== */
export default function ExtractTable({
  transactions = [],
  onView,
  page,
  setPage,
  perPage = 10,
  totalItems = 0,
  loading = false,
  refresh,
  searchTerm = "",
}) {
  const totalPages = useMemo(
    () => Math.max(1, Math.ceil(totalItems / perPage)),
    [totalItems, perPage]
  );

  const canPrev = page > 1;
  const canNext = page < totalPages;
  const isSearching = searchTerm.trim() !== "";

  /* ------------------------------------------------------------------
     CACHE — só quando NÃO estiver pesquisando
  ------------------------------------------------------------------ */
  useEffect(() => {
    if (isSearching || loading) return;
    if (transactions.length > 0) {
      localStorage.setItem(
        CACHE_KEY,
        JSON.stringify({
          transactions,
          page,
          totalItems,
          timestamp: Date.now(),
        })
      );
    }
  }, [transactions, loading, page, totalItems, isSearching]);

  useEffect(() => {
    if (isSearching) localStorage.removeItem(CACHE_KEY);
  }, [isSearching]);

  useEffect(() => {
    if (isSearching) return;
    const cache = localStorage.getItem(CACHE_KEY);
    if (!cache) return;
    const parsed = JSON.parse(cache);
    if (Date.now() - parsed.timestamp > CACHE_TTL) refresh?.(true);
  }, [page, isSearching]);

  const cached = useMemo(() => {
    if (isSearching) return null;
    try {
      const c = localStorage.getItem(CACHE_KEY);
      if (!c) return null;
      const parsed = JSON.parse(c);
      return Date.now() - parsed.timestamp < CACHE_TTL ? parsed : null;
    } catch {
      return null;
    }
  }, [isSearching]);

  const activeTransactions =
    isSearching || !cached?.transactions?.length
      ? transactions
      : cached.transactions;

  /* =====================================================================================
     RENDER
  ===================================================================================== */
  return (
    <div className="bg-[#0b0b0b]/95 border border-white/10 rounded-3xl p-6 backdrop-blur-sm min-h-[520px] flex flex-col justify-between">
      {/* HEADER */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-base font-semibold text-white">
            Transaction History
          </h3>
          <span className="text-[11px] text-gray-400">
            {loading
              ? "Loading..."
              : `${activeTransactions.length} results on this page (${totalItems} total)`}
          </span>
        </div>

        {/* TABLE */}
        <div className="overflow-x-auto rounded-2xl border border-white/10 min-h-[340px]">
          <table className="min-w-full text-sm">
            <thead className="sticky top-0 bg-[#0a0a0a]/95 border-b border-white/10">
              <tr className="text-left text-gray-400">
                <th className="py-2.5 px-4">ID</th>
                <th className="py-2.5 px-4">Type</th>
                <th className="py-2.5 px-4 text-right">Amount</th>
                <th className="py-2.5 px-4">Status</th>
                <th className="py-2.5 px-4">E2E</th>
                <th className="py-2.5 px-4">Date</th>
                <th className="py-2.5 px-4 text-center">Action</th>
              </tr>
            </thead>

            <tbody>
              {loading ? (
                [...Array(perPage)].map((_, i) => (
                  <tr key={i} className="border-b border-white/5 animate-pulse">
                    <td colSpan={7} className="py-4" />
                  </tr>
                ))
              ) : activeTransactions.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-gray-400">
                    No transactions found.
                  </td>
                </tr>
              ) : (
                activeTransactions.map((t) => (
                  <tr
                    key={t.id}
                    className="border-b border-white/5 hover:bg-[#141414]/60 cursor-pointer"
                    onClick={() => onView?.(t)}
                  >
                    <td className="py-2.5 px-4 font-mono text-xs text-gray-300">
                      #{t.id}
                    </td>

                    <td className="py-2.5 px-4">
                      <OriginPill />
                    </td>

                    <td className="py-2.5 px-4 text-right font-semibold text-gray-200">
                      {formatCurrency(t.amount)}
                    </td>

                    <td className="py-2.5 px-4">
                      <StatusPill status={mapStatus(t.status)} />
                    </td>

                    <td className="py-2.5 px-4 font-mono text-xs text-gray-400">
                      {t.e2e || "—"}
                    </td>

                    <td className="py-2.5 px-4 text-gray-400">
                      {fmtDate(t.paidAt || t.createdAt)}
                    </td>

                    <td className="py-2.5 px-4 text-center">
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          onView?.(t);
                        }}
                        className="inline-flex items-center gap-1 px-2 py-1 rounded-lg border text-xs border-white/10 text-gray-300 hover:bg-[#1a1a1a]"
                      >
                        <FileText size={13} /> Details
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* PAGINATION */}
      <div className="mt-5 flex flex-col sm:flex-row sm:items-center justify-between">
        <p className="text-xs text-gray-400">
          Page {page} of {totalPages}
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
            ← Previous
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
            Next →
          </button>
        </div>
      </div>
    </div>
  );
}
