import { useState, useEffect, useRef } from "react";
import {
  Calendar,
  X,
  ArrowUpRight,
  ArrowDownRight,
  Info,
  Filter,
  Clock,
  User2,
  Building2,
  BadgeInfo,
} from "lucide-react";
import CalendarPicker from "@/Components/CalendarPicker";

/* ---------- Utils ---------- */
const currencyBRL = (v = 0) =>
  Number(v || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2 });

const statusMap = {
  DONE: { label: "Concluída", color: "bg-emerald-600/10 text-emerald-300 border-emerald-600/20" },
  SUCCESS: { label: "Concluída", color: "bg-emerald-600/10 text-emerald-300 border-emerald-600/20" },
  PENDING: { label: "Pendente", color: "bg-amber-600/10 text-amber-300 border-amber-600/20" },
  PROCESSING: { label: "Processando", color: "bg-zinc-700/20 text-zinc-300 border-zinc-600/30" },
  FAILED: { label: "Falhou", color: "bg-red-600/10 text-red-300 border-red-600/20" },
  CANCELED: { label: "Cancelada", color: "bg-zinc-700/20 text-zinc-400 border-zinc-600/30" },
};
const translateStatus = (s) => statusMap[s]?.label || s;

/* ---------- Subcomponents ---------- */
const DetailLine = ({ label, value }) => (
  <div className="flex flex-col gap-1 py-2 border-b border-zinc-800 last:border-none">
    <span className="text-[12px] uppercase tracking-wide text-zinc-500">{label}</span>
    <span className="text-[13px] text-zinc-100 break-words leading-relaxed">{value || "—"}</span>
  </div>
);

const Skeleton = ({ className = "" }) => (
  <div className={`animate-pulse rounded-xl bg-zinc-800/60 ${className}`} />
);

/* ---------- Main ---------- */
export default function SidebarCard() {
  const [selectedTransaction, setSelectedTransaction] = useState(null);
  const [transactions, setTransactions] = useState([]);
  const [filtered, setFiltered] = useState([]);
  const [transactionDetails, setTransactionDetails] = useState(null);
  const [loadingDetails, setLoadingDetails] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [isCalendarOpen, setIsCalendarOpen] = useState(false);
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [activeFilters, setActiveFilters] = useState([]);
  const [search, setSearch] = useState("");
  const calendarContainerRef = useRef(null);

  /* ---------- UI Handlers ---------- */
  const toggleFilter = (filterName) => {
    setActiveFilters((prev) =>
      prev.includes(filterName)
        ? prev.filter((f) => f !== filterName)
        : [...prev, filterName]
    );
  };

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (isCalendarOpen && calendarContainerRef.current && !calendarContainerRef.current.contains(e.target)) {
        setIsCalendarOpen(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [isCalendarOpen]);

  /* ---------- Fetch principal ---------- */
  useEffect(() => {
    const fetchTransactions = async () => {
      try {
        const res = await fetch("/transactionlist", { credentials: "include" });
        const data = await res.json();

        if (res.status === 401) {
          setError("Sessão expirada. Faça login novamente.");
          setTimeout(() => (window.location.href = "/login"), 1500);
          return;
        }
        if (!res.ok || data.success === false) {
          setError(data.message || "Erro ao buscar transações.");
          return;
        }

        const formatted = (data.transactions || []).map((tx) => ({
          ...tx,
          createdAtFormatted: new Date(tx.createdAt).toLocaleString("pt-BR", {
            dateStyle: "short",
            timeStyle: "short",
          }),
        }));
        setTransactions(formatted);
        setFiltered(formatted);
      } catch (err) {
        console.error("Erro ao buscar transações:", err);
        setError("Falha na comunicação com o servidor.");
      } finally {
        setLoading(false);
      }
    };

    fetchTransactions();
  }, []);

  /* ---------- Filtro e busca ---------- */
  useEffect(() => {
    const s = (search || "").toLowerCase();
    let current = transactions;

    if (s) {
      current = transactions.filter((t) => {
        const fields = [
          t.category,
          t.beneficiaryName,
          t.id,
          t.status,
          t.type,
          t.amount?.toString(),
          t.createdAtFormatted,
        ]
          .filter(Boolean)
          .map((x) => x.toString().toLowerCase())
          .join(" ");
        return fields.includes(s);
      });
    }

    if (activeFilters.length > 0) {
      current = current.filter((t) => {
        const categoryMap = {
          "Vendas Diretas": ["Sale", "Direct Sale", "Payment"],
          "Pix Efetivados": ["Pix Received", "Pix Sent"],
          Outros: ["Fee", "Transfer"],
        };
        return activeFilters.some((filter) =>
          (categoryMap[filter] || []).includes(t.category)
        );
      });
    }

    setFiltered(current);
  }, [search, transactions, activeFilters]);

  /* ---------- Ações ---------- */
  const formatAmount = (value, credit) => {
    const sign = credit ? "+" : "−";
    return `${sign} ${currencyBRL(value)}`;
  };

  const handleSelectTransaction = async (tx) => {
    setSelectedTransaction(tx);
    setLoadingDetails(true);
    setTransactionDetails(null);

    try {
      const res = await fetch(`/transactionlist/${tx.id}`, { credentials: "include" });
      const data = await res.json();
      if (!res.ok || !data.success)
        throw new Error(data.message || "Erro ao buscar detalhes.");
      setTransactionDetails(data);
    } catch (err) {
      console.error("Erro ao buscar detalhes:", err);
      setTransactionDetails(null);
    } finally {
      setLoadingDetails(false);
    }
  };

  /* ---------- UI ---------- */
  return (
    <div className="relative flex flex-col h-[720px] rounded-2xl border border-zinc-800 bg-zinc-950 overflow-hidden">
      {/* Header */}
      <div className="sticky top-0 z-20 bg-zinc-950/90 backdrop-blur border-b border-zinc-900">
        <div className="flex items-center justify-between gap-3 p-4">
          <div className="flex-1 min-w-0 pr-2">
            <h3 className="text-base md:text-lg font-semibold tracking-tight text-zinc-100 truncate">
              Histórico de Transações
            </h3>
            <p className="text-[12px] text-zinc-500 mt-0.5 truncate">
              Filtre e visualize transações
            </p>
          </div>

          <div className="flex items-center gap-2" ref={calendarContainerRef}>
            <button
              onClick={() => setIsCalendarOpen((p) => !p)}
              className="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium border border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-850 hover:border-zinc-700 transition"
            >
              <Calendar size={16} /> Período
            </button>
            <button
              onClick={() => {
                setIsFilterOpen((p) => !p);
                setIsCalendarOpen(false);
              }}
              className="hidden md:inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium border border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-850 hover:border-zinc-700 transition"
            >
              <Filter size={16} /> Filtros
            </button>
          </div>
        </div>

        {/* Filtros */}
        {isFilterOpen && (
          <div className="px-4 pb-3">
            <div className="flex flex-wrap gap-2">
              {["Vendas Diretas", "Pix Efetivados", "Outros"].map((f) => (
                <button
                  key={f}
                  onClick={() => toggleFilter(f)}
                  className={`px-3 py-1.5 rounded-full text-sm font-medium transition ${
                    activeFilters.includes(f)
                      ? "bg-zinc-800 border border-zinc-700 text-zinc-100"
                      : "bg-zinc-900 border border-zinc-800 text-zinc-300 hover:bg-zinc-850"
                  }`}
                >
                  {f}
                </button>
              ))}
            </div>
          </div>
        )}

        {isCalendarOpen && (
          <div className="px-4 pb-3">
            <CalendarPicker isOpen={isCalendarOpen} onClose={() => setIsCalendarOpen(false)} />
          </div>
        )}
      </div>

      {/* Conteúdo */}
      <div className="flex-1 overflow-y-auto pr-1">
        {loading ? (
          <div className="grid grid-cols-1 gap-3 p-4">
            {Array.from({ length: 7 }).map((_, i) => (
              <Skeleton key={i} className="h-[64px]" />
            ))}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center gap-3 text-center h-full px-6">
            <BadgeInfo className="h-8 w-8 text-zinc-400" />
            <p className="text-sm text-zinc-300 leading-relaxed max-w-sm">{error}</p>
          </div>
        ) : filtered.length === 0 ? (
          <div className="flex flex-col items-center justify-center gap-3 text-center h-full px-6">
            <div className="h-14 w-14 rounded-xl border border-zinc-800 bg-zinc-900 flex items-center justify-center">
              <Info className="h-6 w-6 text-zinc-500" />
            </div>
            <div className="max-w-sm">
              <p className="text-zinc-300 font-medium">Nenhuma transação encontrada</p>
              <p className="text-[12px] text-zinc-500 mt-1">
                Tente ajustar a busca, filtros ou o período.
              </p>
            </div>
          </div>
        ) : (
          <div className="space-y-2 p-4">
            {filtered.map((tx) => {
              const isCredit = !!tx.credit;
              const statusCfg =
                statusMap[tx.status] ||
                { label: tx.status, color: "bg-zinc-700/30 text-zinc-300 border-zinc-700/40" };

              return (
                <button
                  key={tx.id}
                  onClick={() => handleSelectTransaction(tx)}
                  className="group w-full text-left rounded-xl border border-zinc-800 bg-zinc-950 hover:bg-zinc-900 hover:border-zinc-700 transition-colors p-3.5"
                >
                  <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3.5 flex-1 min-w-0">
                      <div className="flex h-10 w-10 items-center justify-center rounded-xl ring-1 ring-inset ring-zinc-800 bg-zinc-900">
                        {isCredit ? (
                          <ArrowDownRight className="h-5 w-5 text-zinc-300" />
                        ) : (
                          <ArrowUpRight className="h-5 w-5 text-zinc-300" />
                        )}
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className="truncate font-medium text-[15px] text-zinc-100">
                          {tx.category || "Transação"}
                        </p>
                        <p className="text-[12px] text-zinc-500 truncate mt-0.5">
                          {tx.beneficiaryName || "Favorecido não informado"}
                        </p>
                      </div>
                    </div>

                    <div className="text-right flex-shrink-0">
                      <span
                        className={`inline-flex items-center justify-end gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-medium border ${statusCfg.color} mb-1`}
                      >
                        <Clock size={11} />
                        {translateStatus(tx.status)}
                      </span>

                      <p className="font-semibold text-zinc-100 whitespace-nowrap">
                        {formatAmount(tx.amount, isCredit)}
                      </p>
                      <p className="text-[12px] text-zinc-500">{tx.createdAtFormatted}</p>
                    </div>
                  </div>
                </button>
              );
            })}
          </div>
        )}
      </div>

      {/* Painel lateral (detalhes) */}
      <div
        className={`fixed top-0 right-0 h-full w-full md:w-[420px] transition-transform duration-250 z-50
        bg-zinc-950 border-l border-zinc-800
        ${selectedTransaction ? "translate-x-0" : "translate-x-full"}`}
      >
        {selectedTransaction && (
          <div className="flex flex-col h-full">
            <div className="sticky top-0 z-10 px-4 pt-4 pb-3 bg-zinc-950/90 backdrop-blur border-b border-zinc-900">
              <div className="flex items-center justify-between">
                <div className="max-w-[70%]">
                  <h4 className="text-zinc-100 font-medium leading-snug">
                    Detalhes da Transação
                  </h4>
                  <p className="text-[12px] text-zinc-500">ID: {selectedTransaction.id}</p>
                </div>
                <button
                  onClick={() => setSelectedTransaction(null)}
                  className="p-2 rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-850 hover:border-zinc-700 transition"
                >
                  <X size={18} />
                </button>
              </div>

              <div className="mt-2">
                <span
                  className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium border ${
                    (statusMap[selectedTransaction.status] || {}).color ||
                    "bg-zinc-700/30 text-zinc-300 border-zinc-700/40"
                  }`}
                >
                  <Clock size={12} />
                  {translateStatus(selectedTransaction.status)}
                </span>
              </div>
            </div>

            {/* Corpo do painel */}
            <div className="px-4 pt-3 pb-4 border-b border-zinc-900">
              <p className="text-sm text-zinc-500">
                {selectedTransaction.credit ? "Recebido de:" : "Enviado para:"}
              </p>
              <p className="text-lg font-semibold text-zinc-100 leading-snug break-words mt-1">
                {selectedTransaction.beneficiaryName || "Nome Não Informado"}
              </p>

              <div className="flex justify-between items-end mt-4">
                <div>
                  <p className="text-[12px] text-zinc-500">
                    <Clock size={12} className="inline mr-1 -mt-0.5" /> Data e hora
                  </p>
                  <p className="text-sm font-medium text-zinc-300 mt-0.5">
                    {new Date(selectedTransaction.createdAt).toLocaleString("pt-BR", {
                      dateStyle: "long",
                      timeStyle: "medium",
                    })}
                  </p>
                </div>

                <div className="text-right">
                  <p className="text-[12px] text-zinc-500">Valor</p>
                  <p className="text-xl font-semibold tracking-tight mt-0.5 text-zinc-100">
                    R$ {currencyBRL(selectedTransaction.amount)}
                  </p>
                </div>
              </div>
            </div>

            {/* Detalhes técnicos */}
            <div className="flex-1 overflow-y-auto p-4 space-y-4">
              {loadingDetails ? (
                <div className="space-y-3">
                  <Skeleton className="h-6 w-40" />
                  <Skeleton className="h-20" />
                  <Skeleton className="h-6 w-48" />
                  <Skeleton className="h-40" />
                </div>
              ) : (
                <>
                  <div className="rounded-xl border border-zinc-800 bg-zinc-950 p-4">
                    <div className="grid grid-cols-2 gap-3">
                      <DetailLine label="Status" value={translateStatus(selectedTransaction.status)} />
                      <DetailLine label="Tipo" value={selectedTransaction.type || "—"} />
                      <DetailLine label="Categoria" value={selectedTransaction.category || "—"} />
                      <DetailLine label="Identificador" value={selectedTransaction.id} />
                    </div>
                  </div>

                  {transactionDetails?.data?.payer && (
                    <div className="rounded-xl border border-zinc-800 bg-zinc-950 p-4">
                      <div className="flex items-center gap-2 mb-2 text-zinc-300">
                        <User2 size={16} /> <span className="text-sm font-semibold">Pagador</span>
                      </div>
                      <DetailLine label="Nome" value={transactionDetails.data.payer.name} />
                      <DetailLine label="Documento" value={transactionDetails.data.payer.document} />
                      <DetailLine label="Banco" value={transactionDetails.data.payer.bank} />
                    </div>
                  )}

                  {transactionDetails?.data?.beneficiary && (
                    <div className="rounded-xl border border-zinc-800 bg-zinc-950 p-4">
                      <div className="flex items-center gap-2 mb-2 text-zinc-300">
                        <Building2 size={16} /> <span className="text-sm font-semibold">Beneficiário</span>
                      </div>
                      <DetailLine label="Nome" value={transactionDetails.data.beneficiary.name} />
                      <DetailLine label="Documento" value={transactionDetails.data.beneficiary.document} />
                      <DetailLine label="Banco" value={transactionDetails.data.beneficiary.bank} />
                    </div>
                  )}
                </>
              )}
            </div>
          </div>
        )}
      </div>

      {selectedTransaction && (
        <div onClick={() => setSelectedTransaction(null)} className="fixed inset-0 bg-black/40 z-40" />
      )}
    </div>
  );
}
