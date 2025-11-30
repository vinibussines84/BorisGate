import React, { useRef, useCallback, useEffect, useState } from "react";
import {
  FileText,
  Filter,
  RefreshCw,
  Search,
  XCircle,
  CreditCard,
  ArrowUpRight,
  ArrowDownRight,
} from "lucide-react";

const STATUS_TABS = [
  { key: "all", label: "All" },
  { key: "EFETIVADO", label: "Completed" },
  { key: "PENDENTE", label: "Pending" },
  { key: "FALHADO", label: "Failed" },
];

export default function ExtratoHeader({
  saldo = 0,
  entradas = 0,
  saidas = 0,
  statusFilter,
  setStatusFilter,
  searchTerm,
  setSearchTerm,
  refresh,
}) {
  const [pillStyle, setPillStyle] = useState({ left: 0, width: 0 });
  const tabRefs = useRef([]);

  /* ==========================================================
     ATUALIZA A “PÍLULA” DO STATUS SELECIONADO
  ========================================================== */
  const calcPill = useCallback(() => {
    const idx = STATUS_TABS.findIndex((s) => s.key === statusFilter);
    const el = tabRefs.current[idx];
    if (el) setPillStyle({ left: el.offsetLeft, width: el.offsetWidth });
  }, [statusFilter]);

  useEffect(() => {
    const t = setTimeout(calcPill, 80);
    return () => clearTimeout(t);
  }, [calcPill]);

  /* ==========================================================
     FORMATADORES
  ========================================================== */
  const formatCurrency = (value) =>
    (Number(value) || 0).toLocaleString("pt-BR", {
      style: "currency",
      currency: "BRL",
      minimumFractionDigits: 2,
    });

  const onKeyPress = (e) => {
    if (e.key === "Enter") refresh(true);
  };

  /* ==========================================================
     RESETAR FILTROS
  ========================================================== */
  const handleResetFilters = () => {
    setSearchTerm("");
    setStatusFilter("all");
    refresh(true); // 🔥 força recarregar do zero
  };

  /* ==========================================================
     RENDER
  ========================================================== */
  return (
    <div className="relative overflow-hidden rounded-3xl border border-white/10 bg-[#0b0b0b]/95 p-6 sm:p-7 backdrop-blur-sm min-h-[180px] transition-all duration-300">
      {/* HEADER SUPERIOR */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="p-3 rounded-2xl border border-white/10 bg-[#0a0a0a]/90 shrink-0">
            <FileText className="w-5 h-5 text-[#02fb5c]" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-white leading-none">
              Statement
            </h1>
            <p className="text-gray-400 text-sm mt-0.5">
              Overview of account activity
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => refresh(false)}
            className="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium 
              text-gray-200 border border-white/10 rounded-lg bg-[#0a0a0a]/90 hover:bg-[#141414] transition-colors"
          >
            <RefreshCw size={14} className="text-gray-300" />
            Refresh
          </button>

          {/* RESET FILTERS */}
          <button
            onClick={handleResetFilters}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium 
              text-[#ff3b5c] border border-[#ff3b5c]/40 rounded-lg bg-[#2b0000]/40 hover:bg-[#3a0000]/60 transition-colors"
          >
            <XCircle size={13} /> Reset
          </button>
        </div>
      </div>

      {/* SALDOS */}
      <div className="mt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-6">
        <div className="flex-1 rounded-2xl bg-[#0a0a0a]/95 border border-white/10 px-6 py-5 min-h-[110px] flex flex-col justify-between shadow-[0_0_25px_rgba(0,0,0,0.4)]">
          <div className="flex items-center gap-2 text-[11px] uppercase tracking-wide text-gray-400">
            <CreditCard size={12} className="text-gray-500" />
            Available Balance
          </div>
          <p className="text-4xl font-semibold text-white tabular-nums leading-none transition-opacity duration-300">
            {formatCurrency(saldo)}
          </p>
        </div>

        <div className="flex sm:flex-row flex-col gap-3 shrink-0">
          {/* Entradas */}
          <div className="flex items-center justify-between sm:justify-start gap-3 rounded-2xl px-5 py-3 bg-[#0a0a0a]/95 border border-[#1b1b1b] shadow-inner w-[180px]">
            <div className="flex items-center justify-center w-8 h-8 rounded-lg bg-[#02fb5c]/20 border border-[#02fb5c]/40">
              <ArrowUpRight size={15} className="text-[#02fb5c]" />
            </div>
            <div className="flex flex-col">
              <span className="text-[12px] text-gray-400">Credits</span>
              <span className="text-sm font-medium text-white tabular-nums">
                {formatCurrency(entradas)}
              </span>
            </div>
          </div>

          {/* Saídas */}
          <div className="flex items-center justify-between sm:justify-start gap-3 rounded-2xl px-5 py-3 bg-[#0a0a0a]/95 border border-[#1b1b1b] shadow-inner w-[180px]">
            <div className="flex items-center justify-center w-8 h-8 rounded-lg bg-[#2b0000]/40 border border-[#ff3b5c]/40">
              <ArrowDownRight size={15} className="text-[#ff3b5c]" />
            </div>
            <div className="flex flex-col">
              <span className="text-[12px] text-gray-400">Debits</span>
              <span className="text-sm font-medium text-white tabular-nums">
                {formatCurrency(saidas)}
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* FILTROS */}
      <div className="mt-8 flex flex-col lg:flex-row gap-3 items-center justify-between">
        {/* STATUS FILTER */}
        <div className="flex items-center gap-2 w-full lg:w-auto">
          <span className="text-[11px] text-gray-400 flex items-center gap-1.5 shrink-0">
            <Filter size={12} /> Status:
          </span>

          <div className="relative flex items-center overflow-x-auto no-scrollbar p-1 rounded-xl bg-[#050505]/80 border border-[#1a1a1a] max-w-full scroll-smooth min-h-[32px]">
            {/* PILL */}
            <div
              className="absolute h-[28px] rounded-lg bg-[#02fb5c]/10 border border-[#02fb5c]/40 transition-all duration-300 ease-out"
              style={{ width: pillStyle.width, left: pillStyle.left }}
            />
            <div className="flex flex-nowrap space-x-1">
              {STATUS_TABS.map((s, i) => (
                <button
                  key={s.key}
                  ref={(el) => (tabRefs.current[i] = el)}
                  onClick={() => {
                    if (s.key !== statusFilter) {
                      setStatusFilter(s.key);
                      refresh(false);
                    }
                  }}
                  className={`relative z-10 px-4 py-1 text-[11px] rounded-lg whitespace-nowrap transition-colors ${
                    statusFilter === s.key
                      ? "text-[#02fb5c]"
                      : "text-gray-300 hover:text-white"
                  }`}
                >
                  {s.label}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* SEARCH */}
        <div className="relative flex items-center gap-2 w-full sm:max-w-[380px]">
          <div className="relative flex-1">
            <Search
              size={14}
              className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none"
            />
            <input
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              onKeyDown={onKeyPress}
              placeholder="Search by E2E, Amount..."
              className="w-full pl-9 pr-3 py-2 text-xs rounded-lg bg-[#050505]/80 border border-[#1a1a1a]
                text-gray-200 placeholder:text-gray-500 focus:ring-2 focus:ring-[#02fb5c]/40 transition-all duration-200"
            />
          </div>

          {/* BOTÃO SEARCH */}
          <button
            onClick={() => refresh(true)}
            className="px-4 py-2 text-xs font-medium rounded-lg bg-[#02fb5c]/20 border border-[#02fb5c]/40 text-[#02fb5c] hover:bg-[#02fb5c]/30 transition-all"
          >
            Search
          </button>
        </div>
      </div>
    </div>
  );
}
