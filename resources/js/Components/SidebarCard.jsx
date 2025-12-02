// resources/js/Components/SidebarCard.jsx
import React, {
  useState,
  useEffect,
  useRef,
  useCallback,
  useMemo,
} from "react";
import {
  Calendar,
  ArrowUpRight,
  ArrowDownRight,
  Info,
  Clock,
  BadgeInfo,
} from "lucide-react";

/* ---------- Utils ---------- */
const currencyBRL = (v = 0) =>
  Number(v || 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  });

const fmtDateTime = (iso) =>
  iso
    ? new Date(iso).toLocaleString("pt-BR", {
        dateStyle: "short",
        timeStyle: "short",
      })
    : "—";

const norm = (s) => String(s || "").trim().toLowerCase();

const normalizeStatusKey = (s) => {
  const v = norm(s);
  if (["paga", "paid", "completed", "confirmed", "settled"].includes(v))
    return "paid";
  if (["pending", "pendente", "processing", "authorized", "created"].includes(v))
    return "pending";
  if (["failed", "falha", "denied", "canceled", "cancelled"].includes(v))
    return "failed";
  if (["erro", "error"].includes(v)) return "failed";
  if (["med", "mediacao", "mediation", "analise", "em_analise"].includes(v))
    return "mediation";
  return "pending";
};

const statusMap = {
  paid: {
    label: "Paid",
    color: "bg-emerald-500/10 text-emerald-300 border-emerald-500/20",
  },
  pending: {
    label: "Pending",
    color: "bg-amber-500/10 text-amber-300 border-amber-500/20",
  },
  failed: {
    label: "Failed",
    color: "bg-red-600/10 text-red-300 border-red-600/20",
  },
  canceled: {
    label: "Canceled",
    color: "bg-zinc-700/20 text-zinc-400 border-zinc-600/30",
  },
  mediation: {
    label: "Under Review",
    color: "bg-sky-500/10 text-sky-300 border-sky-500/20",
  },
};

/* Small Components */
const DetailLine = React.memo(({ label, value }) => (
  <div className="flex flex-col gap-1 py-2 border-b border-zinc-800/60 last:border-none">
    <span className="text-[11px] uppercase tracking-wide text-zinc-500">
      {label}
    </span>
    <span className="text-[13px] text-zinc-100 break-words leading-relaxed">
      {value || "—"}
    </span>
  </div>
));

const Skeleton = ({ className = "" }) => (
  <div className={`animate-pulse rounded-xl bg-zinc-800/50 ${className}`} />
);

/* ------------------------------
    TRANSACTION ITEM
------------------------------- */
const FeedItem = React.memo(function FeedItem({ it, selected, onSelect }) {
  const badgeKey = normalizeStatusKey(it.status || "paid");
  const statusCfg =
    statusMap[badgeKey] || {
      label: it.statusLabel || it.status || "",
      color: "bg-zinc-700/30 text-zinc-300 border-zinc-700/40",
    };

  return (
    <button
      onClick={() => onSelect(it)}
      className={`group w-full text-left rounded-2xl border border-zinc-800/60 bg-zinc-950/60 hover:bg-zinc-900/80 hover:border-emerald-500/20 transition-all duration-300 p-3.5 shadow-[0_0_8px_rgba(16,185,129,0.05)] ${
        selected ? "ring-1 ring-emerald-500/30" : ""
      }`}
    >
      <div className="flex items-center justify-between gap-4">
        <div className="flex items-center gap-3.5 flex-1 min-w-0">
          <div className="flex h-10 w-10 items-center justify-center rounded-xl ring-1 ring-inset ring-zinc-800 bg-zinc-900/80">
            {it.credit ? (
              <ArrowDownRight className="h-5 w-5 text-emerald-400" />
            ) : (
              <ArrowUpRight className="h-5 w-5 text-red-400" />
            )}
          </div>

          <div className="min-w-0 flex-1">
            <p className="truncate font-medium text-[15px] text-zinc-100">
              {it.kind === "SAQUE" ? "Withdrawal" : "PixionPay"}
            </p>
            <p className="text-[12px] text-zinc-500 truncate mt-0.5">
              {`E2E: ${it.e2e || "—"}`}
            </p>
          </div>
        </div>

        <div className="text-right flex-shrink-0">
          <span
            className={`inline-flex items-center justify-end gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium border ${statusCfg.color} mb-1`}
          >
            <Clock size={11} />
            {statusCfg.label}
          </span>
          <p
            className={`font-semibold whitespace-nowrap ${
              it.credit ? "text-emerald-400" : "text-zinc-100"
            }`}
          >
            {`${it.credit ? "+" : "−"} ${currencyBRL(it.amount)}`}
          </p>
          <p className="text-[12px] text-zinc-500">
            {fmtDateTime(it.paidAt || it.createdAt)}
          </p>
        </div>
      </div>

      {selected && (
        <div className="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3 text-zinc-200 animate-fadeIn">
          <DetailLine label="Type" value={it.kind} />
          <DetailLine label="Gross Amount" value={currencyBRL(it.amount)} />
          <DetailLine label="Fee" value={currencyBRL(it.fee)} />
          <DetailLine label="Net Amount" value={currencyBRL(it.net)} />
          <DetailLine label="Paid At" value={fmtDateTime(it.paidAt)} />
          <DetailLine label="E2E" value={it.e2e || "—"} />
        </div>
      )}
    </button>
  );
});

/* ------------------------------
            MAIN CARD
------------------------------- */
export default function SidebarCard() {
  const [selectedItem, setSelectedItem] = useState(null);
  const [items, setItems] = useState([]);

  const [kpis, setKpis] = useState({
    qtdPagasDia: 0,
    valorBrutoDia: 0,
    valorLiquidoDia: 0,
    volumePixMes: 0,
    periodo: "",
  });

  const [loadingFeed, setLoadingFeed] = useState(true);
  const [loadingKpis, setLoadingKpis] = useState(true);

  const [errorFeed, setErrorFeed] = useState(null);
  const [errorKpis, setErrorKpis] = useState(null);

  /* -------------------------------
     LOAD KPIS
  ------------------------------- */
  const loadKpis = useCallback(async () => {
    setLoadingKpis(true);
    setErrorKpis(null);

    try {
      const res = await fetch("/api/metrics/day", {
        headers: { Accept: "application/json" },
        credentials: "include",
      });

      const json = await res.json();

      if (!res.ok) throw new Error(json?.message || "Failed to load KPIs.");

      setKpis(json.data);
    } catch (e) {
      setErrorKpis(e.message);
    } finally {
      setLoadingKpis(false);
    }
  }, []);

  /* -------------------------------
     LOAD FEED
  ------------------------------- */
  const loadFeed = useCallback(async () => {
    setLoadingFeed(true);
    setErrorFeed(null);

    try {
      const res = await fetch("/api/metrics/paid-feed?limit=30", {
        headers: { Accept: "application/json" },
        credentials: "include",
      });

      const json = await res.json();

      if (!res.ok) throw new Error(json?.message || "Failed to load feed.");

      setItems(Array.isArray(json.data) ? json.data : []);
    } catch (e) {
      setErrorFeed(e.message);
    } finally {
      setLoadingFeed(false);
    }
  }, []);

  /* INITIAL LOAD + AUTOREFRESH */
  useEffect(() => {
    loadKpis();
    loadFeed();

    const interval = setInterval(() => {
      loadKpis();
      loadFeed();
    }, 60000);

    return () => clearInterval(interval);
  }, []);

  const sortedItems = useMemo(
    () =>
      [...items].sort(
        (a, b) =>
          new Date(b.paidAt || b.createdAt) - new Date(a.paidAt || a.createdAt)
      ),
    [items]
  );

  const handleSelect = useCallback(
    (it) => setSelectedItem((prev) => (prev?.id === it.id ? null : it)),
    []
  );

  return (
    <div className="relative flex flex-col h-[760px] rounded-2xl border border-zinc-800/80 bg-gradient-to-br from-zinc-950/90 via-zinc-900/90 to-zinc-950/80 shadow-[0_0_30px_-8px_rgba(16,185,129,0.3)] overflow-hidden backdrop-blur-lg">

      {/* HEADER */}
      <div className="sticky top-0 z-20 bg-zinc-950/80 backdrop-blur-xl border-b border-zinc-800/70 p-4">
        <h3 className="text-base md:text-lg font-semibold text-white tracking-tight">
          Daily Performance
        </h3>
        <p className="text-[12px] text-zinc-500">{kpis.periodo}</p>

        {/* KPIs BLOCK */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
          <div className="rounded-xl border border-zinc-800 bg-zinc-900/60 p-3">
            <p className="text-[12px] text-zinc-400">Paid Today</p>
            <p className="text-lg font-semibold text-emerald-400">
              {kpis.qtdPagasDia}
            </p>
          </div>

          <div className="rounded-xl border border-zinc-800 bg-zinc-900/60 p-3">
            <p className="text-[12px] text-zinc-400">Gross Today</p>
            <p className="text-lg font-semibold text-zinc-100">
              {currencyBRL(kpis.valorBrutoDia)}
            </p>
          </div>

          <div className="rounded-xl border border-zinc-800 bg-zinc-900/60 p-3">
            <p className="text-[12px] text-zinc-400">Net Today</p>
            <p className="text-lg font-semibold text-emerald-400">
              {currencyBRL(kpis.valorLiquidoDia)}
            </p>
          </div>

          <div className="rounded-xl border border-zinc-800 bg-zinc-900/60 p-3">
            <p className="text-[12px] text-zinc-400">Pix Volume (Month)</p>
            <p className="text-lg font-semibold text-sky-400">
              {currencyBRL(kpis.volumePixMes)}
            </p>
          </div>
        </div>
      </div>

      {/* FEED */}
      <div className="flex-1 overflow-y-auto pr-1 custom-scrollbar">
        {loadingFeed ? (
          <div className="grid grid-cols-1 gap-3 p-4">
            {Array.from({ length: 7 }).map((_, i) => (
              <Skeleton key={i} className="h-[64px]" />
            ))}
          </div>
        ) : errorFeed ? (
          <div className="flex flex-col items-center justify-center gap-3 text-center h-full px-6">
            <BadgeInfo className="h-8 w-8 text-zinc-400" />
            <p className="text-sm text-zinc-300">{errorFeed}</p>
          </div>
        ) : sortedItems.length === 0 ? (
          <div className="flex flex-col items-center justify-center gap-3 text-center h-full px-6">
            <div className="h-14 w-14 rounded-xl border border-zinc-800 bg-zinc-900 flex items-center justify-center">
              <Info className="h-6 w-6 text-zinc-500" />
            </div>
            <p className="text-zinc-300 font-medium">No records found</p>
          </div>
        ) : (
          <div className="space-y-3 p-4">
            {sortedItems.map((it) => (
              <FeedItem
                key={`${it.kind}-${it.id}`}
                it={it}
                selected={selectedItem?.id === it.id}
                onSelect={handleSelect}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
