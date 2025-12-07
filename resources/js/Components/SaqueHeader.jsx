import React from "react";
import {
  Banknote,
  ArrowDownCircle,
  ListOrdered,
  Clock,
  CalendarDays,
  RefreshCw,
} from "lucide-react";

/* ============================
   HELPERS
============================ */

const BRL = (v) =>
  (Number(v) || 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
  });

const fmtDate = (iso) => {
  if (!iso) return "—";
  const d = new Date(iso);
  return d.toLocaleDateString("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};

/* ============================
   CARD COMPONENT
============================ */

const Card = ({ title, value, icon: Icon, color }) => {
  const colorMap = {
    verde:
      "text-[#02fb5c] border-[#02fb5c]/30 bg-[#02fb5c]/5 shadow-[0_0_10px_rgba(2,251,92,0.2)]",
    sky: "text-sky-400 border-sky-400/20 bg-sky-500/[0.06]",
    amber: "text-amber-400 border-amber-400/20 bg-amber-500/[0.06]",
    rose: "text-rose-400 border-rose-400/20 bg-rose-500/[0.06]",
  };

  return (
    <div className="rounded-2xl border border-white/10 bg-[#0b0b0b]/80 p-4 sm:p-5 flex items-center justify-between backdrop-blur-sm shadow min-h-[88px] transition-all">
      <div className="min-w-0">
        <p className="text-xs text-gray-400 leading-tight">{title}</p>
        <p className="mt-1 text-lg sm:text-xl font-semibold text-white break-words">
          {value}
        </p>
      </div>

      <div
        className={`h-8 w-8 sm:h-10 sm:w-10 flex-shrink-0 rounded-xl border flex items-center justify-center ${colorMap[color]}`}
      >
        <Icon size={16} className="sm:w-5 sm:h-5" />
      </div>
    </div>
  );
};

/* ============================
   MAIN COMPONENT
============================ */

export default function WithdrawHeader({ cards, onRefresh, isRefreshing }) {
  return (
    <div className="space-y-6">
      {/* HEADER WITH REFRESH */}
      <div className="bg-[#0b0b0b]/90 border border-white/10 rounded-3xl p-5 shadow-[0_0_40px_-10px_rgba(0,0,0,0.8)] backdrop-blur-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        
        {/* LEFT: ICON + TITLE */}
        <div className="flex items-center gap-3">
          <div className="p-3 rounded-2xl border border-[#02fb5c]/30 bg-[#02fb5c]/10 shadow-[0_0_10px_rgba(2,251,92,0.2)]">
            <Banknote className="w-6 h-6 text-[#02fb5c]" />
          </div>

          <div>
            <h1 className="text-xl sm:text-2xl font-bold text-white">
              Withdrawals Area
            </h1>
            <p className="text-sm text-gray-400">
              Track your PIX withdrawal requests and receipts.
            </p>
          </div>
        </div>

        {/* RIGHT: REFRESH BUTTON */}
        <button
          onClick={onRefresh}
          disabled={isRefreshing}
          className="inline-flex items-center gap-2 px-3 py-1.5 text-xs border border-[#02fb5c]/30 text-[#02fb5c] rounded-lg bg-[#02fb5c]/10 hover:bg-[#02fb5c]/20 transition-all disabled:opacity-50 self-start sm:self-auto"
        >
          <RefreshCw
            size={14}
            className={isRefreshing ? "animate-spin text-[#02fb5c]" : ""}
          />
          Refresh
        </button>
      </div>

      {/* CARDS GRID */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <Card
          title="Total Withdrawn"
          value={BRL(cards.total)}
          icon={ArrowDownCircle}
          color="verde"
        />
        <Card
          title="Quantity"
          value={cards.qtd || 0}
          icon={ListOrdered}
          color="sky"
        />
        <Card
          title="Processing"
          value={cards.proc || 0}
          icon={Clock}
          color="amber"
        />
        <Card
          title="Last Withdrawal"
          value={cards.ult ? fmtDate(cards.ult) : "—"}
          icon={CalendarDays}
          color="rose"
        />
      </div>
    </div>
  );
}
