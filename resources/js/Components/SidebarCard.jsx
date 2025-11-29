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
import CalendarPicker from "@/Components/CalendarPicker";

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
        {/* icon */}
        <div className="flex items-center gap-3.5 flex-1 min-w-0">
          <div className="flex h-10 w-10 items-center justify-center rounded-xl ring-1 ring-inset ring-zinc-800 bg-zinc-900/80">
            {it.credit ? (
              <ArrowDownRight className="h-5 w-5 text-emerald-400" />
            ) : (
              <ArrowUpRight className="h-5 w-5 text-red-400" />
            )}
          </div>

          {/* name */}
          <div className="min-w-0 flex-1">
            <p className="truncate font-medium text-[15px] text-zinc-100">
              {it.kind === "SAQUE" ? "Withdrawal" : "EquitPay"}
            </p>
            <p className="text-[12px] text-zinc-500 truncate mt-0.5">
              {`E2E: ${it.e2e || "—"}`}
            </p>
          </div>
        </div>

        {/* values */}
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

      {/* DETAILS */}
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
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [isCalendarOpen, setIsCalendarOpen] = useState(false);
  const calendarContainerRef = useRef(null);

  const CACHE_KEY = "paid_feed_cache_v1";
  const CACHE_TTL = 60 * 1000; // 1 minute

  /* Close calendar when clicking outside */
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (
        isCalendarOpen &&
        calendarContainerRef.current &&
        !calendarContainerRef.current.contains(e.target)
      ) {
        setIsCalendarOpen(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [isCalendarOpen]);

  /* Load transactions with cache */
  const load = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const cache = JSON.parse(localStorage.getItem(CACHE_KEY));
      if (cache && Date.now() - cache.timestamp < CACHE_TTL) {
        setItems(cache.data || []);
        setLoading(false);
        return;
      }

      const res = await fetch("/api/metrics/paid-feed?limit=30", {
        headers: { Accept: "application/json" },
        credentials: "include",
      });

      const text = await res.text();
      let json = {};
      try {
        json = JSON.parse(text);
      } catch {
        throw new Error(
          "The server response is not valid JSON.\nCheck authentication or if API returned an HTML error."
        );
      }

      if (!res.ok) throw new Error(json?.message || "Failed to load transactions.");

      const arr = Array.isArray(json?.data) ? json.data : [];
      setItems(arr);

      localStorage.setItem(
        CACHE_KEY,
        JSON.stringify({ data: arr, timestamp: Date.now() })
      );
    } catch (e) {
      console.error("Error fetching /api/metrics/paid-feed:", e);
      setError(e?.message || "Failed to connect to server.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
    const handleFocus = () => load();
    window.addEventListener("focus", handleFocus);
    return () => window.removeEventListener("focus", handleFocus);
  }, [load]);

  /* Sort and select handlers */
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
    <div className="relative flex flex-col h-[720px] rounded-2xl border border-zinc-800/80 bg-gradient-to-br from-zinc-950/90 via-zinc-900/90 to-zinc-950/80 shadow-[0_0_30px_-8px_rgba(16,185,129,0.3)] overflow-hidden backdrop-blur-lg">
      {/* Header */}
      <div className="sticky top-0 z-20 bg-zinc-950/80 backdrop-blur-xl border-b border-zinc-800/70">
        <div className="flex items-center justify-between p-4">
          <div>
            <h3 className="text-base md:text-lg font-semibold text-white tracking-tight flex items-center gap-2">
              <span className="inline-block w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.7)]" />
              Paid Transactions
            </h3>
            <p className="text-[12px] text-zinc-500 mt-0.5">
              Received PIX and completed withdrawals
            </p>
          </div>

          <div className="relative" ref={calendarContainerRef}>
            <button
              onClick={() => setIsCalendarOpen((p) => !p)}
              className="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium border border-zinc-800 bg-zinc-900/60 text-zinc-300 hover:border-emerald-500/40 hover:text-emerald-300 hover:shadow-[0_0_10px_rgba(16,185,129,0.25)] transition-all"
            >
              <Calendar size={16} />
              Period
            </button>
            {isCalendarOpen && (
              <div className="absolute right-0 mt-2 z-30">
                <CalendarPicker
                  isOpen={isCalendarOpen}
                  onClose={() => setIsCalendarOpen(false)}
                />
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto pr-1 custom-scrollbar">
        {loading ? (
          <div className="grid grid-cols-1 gap-3 p-4">
            {Array.from({ length: 7 }).map((_, i) => (
              <Skeleton key={i} className="h-[64px]" />
            ))}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center gap-3 text-center h-full px-6">
            <BadgeInfo className="h-8 w-8 text-zinc-400" />
            <p className="text-sm text-zinc-300 leading-relaxed max-w-sm whitespace-pre-line">
              {error}
            </p>
          </div>
        ) : sortedItems.length === 0 ? (
          <div className="flex flex-col items-center justify-center gap-3 text-center h-full px-6">
            <div className="h-14 w-14 rounded-xl border border-zinc-800 bg-zinc-900 flex items-center justify-center">
              <Info className="h-6 w-6 text-zinc-500" />
            </div>
            <p className="text-zinc-300 font-medium">No records found</p>
            <p className="text-[12px] text-zinc-500 mt-1">
              Please wait for processing or adjust the selected period.
            </p>
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
