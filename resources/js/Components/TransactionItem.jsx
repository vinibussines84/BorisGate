import React from "react";
import { Info } from "lucide-react";

export default function TransactionItem({
  id,
  description,
  type,
  amount,
  txid,
  status,
  rawStatus,
  date,
  formatCurrency,
  onClick,
}) {
  const fmt = (v) =>
    formatCurrency
      ? formatCurrency(v)
      : (Number(v) || 0).toLocaleString("pt-BR", {
          style: "currency",
          currency: "BRL",
          minimumFractionDigits: 2,
        });

  const statusClass = () => {
    const s = (status || "").toUpperCase();
    if (s === "EFETIVADO") {
      return "bg-emerald-500/10 text-emerald-300 border border-emerald-500/40";
    }
    if (s === "PENDENTE") {
      return "bg-amber-500/10 text-amber-300 border border-amber-500/40";
    }
    if (s === "FALHADO") {
      return "bg-red-500/10 text-red-300 border border-red-500/40";
    }
    return "bg-gray-700/40 text-gray-200 border border-gray-600";
  };

  return (
    <button
      onClick={onClick}
      className="w-full text-left rounded-2xl bg-[#080808] hover:bg-[#0b0b0b] border border-gray-900 px-4 py-3 sm:px-5 sm:py-4 flex items-center justify-between gap-4 transition"
    >
      <div className="flex items-center gap-4 min-w-0">
        <div className="w-9 h-9 rounded-2xl border border-blue-500/40 bg-black/40 flex items-center justify-center">
          <Info className="w-4 h-4 text-gray-300" />
        </div>

        <div className="flex flex-col gap-1 min-w-0">
          <div className="flex items-center gap-2 min-w-0">
            <span className="text-sm font-medium text-gray-100 truncate">
              {description}
            </span>
            <span className="text-[11px] text-gray-500 uppercase">
              {type}
            </span>
          </div>

          {/* ID / TXID / STATUS */}
          <div className="text-[11px] text-gray-500 flex flex-wrap gap-x-3 gap-y-1">
            <span className="truncate">ID #{id}</span>
            <span className="truncate">
              TXID: {txid ? txid : "—"}
            </span>
            <span className="truncate">
              Status: {(status || "").toUpperCase()}
              {rawStatus && (
                <span className="text-[10px] text-gray-600 ml-1">
                  ({rawStatus})
                </span>
              )}
            </span>
          </div>
        </div>
      </div>

      <div className="flex flex-col items-end gap-1 shrink-0">
        <span className="text-sm sm:text-base font-light text-gray-50">
          {fmt(amount)}
        </span>
        {date && (
          <span className="text-[11px] text-gray-500 whitespace-nowrap">
            {date}
          </span>
        )}
        <span
          className={
            "mt-1 inline-flex items-center px-3 py-1 rounded-full text-[11px] font-medium " +
            statusClass()
          }
        >
          {(status || "").toUpperCase()}
        </span>
      </div>
    </button>
  );
}
