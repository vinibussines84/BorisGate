// resources/js/Components/DiscoverMoreCard.jsx
import React, { useEffect, useState, useCallback, useRef } from "react";
import {
  ArrowUpRight,
  ArrowDownRight,
  ListChecks,
  CalendarDays,
  AlertTriangle,
  Zap,
  Filter,
} from "lucide-react";

/* =============== Utils =============== */
const toNumber = (v) => {
  if (typeof v === "number") return v;
  if (typeof v === "string") {
    const n = Number(v.replace(/\./g, "").replace(",", "."));
    return Number.isFinite(n) ? n : NaN;
  }
  return NaN;
};

const BRL = (v) => {
  const n = toNumber(v);
  if (!Number.isFinite(n)) return "—";
  return n.toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  });
};

/* =============== KPI Tile =============== */
function KpiTile({
  icon: Icon,
  label,
  value,
  hint,
  className = "",
  iconColor = "text-neutral-200",
}) {
  return (
    <div
      className={[
        "relative isolate rounded-2xl overflow-hidden",
        "border border-neutral-800 bg-neutral-950",
        "ring-1 ring-inset ring-neutral-900 shadow-sm",
        "p-4 sm:p-5",
        className,
      ].join(" ")}
    >
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <span className="inline-flex h-6 w-6 items-center justify-center rounded-md border border-neutral-800 bg-neutral-900">
            <Icon size={14} className={iconColor} />
          </span>
          <span className="text-xs text-neutral-400">{label}</span>
        </div>
        <span className="text-sm font-semibold text-neutral-100 whitespace-nowrap">
          {value}
        </span>
      </div>
      {hint && (
        <div className="mt-2 text-[11px] leading-tight text-neutral-400">
          {hint}
        </div>
      )}
    </div>
  );
}

/* =============== Component =============== */
export default function DiscoverMoreCard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState(null);
  const [filter, setFilter] = useState("month");
  const [openFilter, setOpenFilter] = useState(false);

  const filterRef = useRef();

  const routeOr = (name, fallback) =>
    typeof window !== "undefined" && window.route
      ? window.route(name)
      : fallback;

  /* =============== Close dropdown on outside click =============== */
  useEffect(() => {
    const handler = (e) => {
      if (filterRef.current && !filterRef.current.contains(e.target)) {
        setOpenFilter(false);
      }
    };
    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);

  /* =============== Build API URL based on filter =============== */
  const buildQueryURL = () => {
    const base = routeOr("api.metrics.month", "/api/metrics/month");

    const makeDate = (offsetDays = 0) => {
      const d = new Date();
      d.setDate(d.getDate() - offsetDays);
      return d.toISOString().slice(0, 10);
    };

    if (filter === "today") return `${base}?day=${makeDate(0)}`;
    if (filter === "yesterday") return `${base}?day=${makeDate(1)}`;
    if (filter === "5days") return `${base}?day=${makeDate(5)}`;
    if (filter === "7days") return `${base}?day=${makeDate(7)}`;

    return base; // monthly default
  };

  /* =============== Fetch metrics =============== */
  const fetchData = useCallback(async () => {
    setLoading(true);
    setErr(null);

    try {
      const url = buildQueryURL();

      const res = await fetch(url, {
        headers: { Accept: "application/json" },
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const json = await res.json();
      setData(json?.data ?? {});
    } catch (e) {
      setErr(e?.message || "Failed to load metrics.");
    } finally {
      setLoading(false);
    }
  }, [filter]);

  /* =============== Auto fetch =============== */
  useEffect(() => {
    fetchData();
  }, [fetchData]);

  /* =============== Derived values =============== */
  const incoming = BRL(toNumber(data?.entradaMes) || 0);
  const outgoing = BRL(toNumber(data?.saidaMes) || 0);
  const pending = toNumber(data?.pendentes) || 0;
  const chargebacks = toNumber(data?.chargebacksMes) || 0;
  const pixVolume = BRL(toNumber(data?.volumePix) || 0);
  const period = data?.periodo ?? "Period";

  /* =============== Filter labels =============== */
  const filterLabel = {
    month: "Monthly",
    today: "Today",
    yesterday: "Yesterday",
    "5days": "5 days ago",
    "7days": "7 days ago",
  }[filter];

  /* =============== Render =============== */
  return (
    <section className="w-full mx-auto max-w-5xl">

      {/* HEADER */}
      <div className="mb-4 flex items-center justify-between">
        <h3 className="text-sm sm:text-base font-semibold text-neutral-100">
          Indicators
        </h3>

        {/* FILTER DROPDOWN */}
        <div className="relative" ref={filterRef}>
          <button
            onClick={() => setOpenFilter(!openFilter)}
            className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg 
                       bg-neutral-900 border border-neutral-700 text-neutral-300 
                       hover:border-neutral-500 transition"
          >
            <Filter size={14} />
            {filterLabel}
          </button>

          {openFilter && (
            <div
              className="absolute right-0 mt-2 w-44 rounded-xl overflow-hidden 
                         bg-neutral-900 border border-neutral-700 shadow-lg z-20"
            >
              {[
                ["today", "Today"],
                ["yesterday", "Yesterday"],
                ["5days", "5 days ago"],
                ["7days", "7 days ago"],
                ["month", "Monthly"],
              ].map(([key, label]) => (
                <button
                  key={key}
                  onClick={() => {
                    setFilter(key);
                    setOpenFilter(false);
                  }}
                  className={`w-full text-left px-3 py-2 text-sm 
                              hover:bg-neutral-800 transition 
                              ${filter === key ? "bg-neutral-800 text-white" : "text-neutral-300"}`}
                >
                  {label}
                </button>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* PERIOD */}
      <span className="flex items-center text-[11px] text-neutral-400 mb-3">
        <CalendarDays size={12} className="inline mr-1 opacity-80" />
        {period}
      </span>

      {/* ERR */}
      {err && (
        <div className="mb-3 text-sm text-red-300 border border-red-800/50 rounded-lg p-2 bg-red-950/30">
          {err}
        </div>
      )}

      {/* LOADER */}
      {loading ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          {[1, 2, 3].map((i) => (
            <div
              key={i}
              className="h-20 rounded-2xl border border-neutral-800 bg-neutral-900 animate-pulse"
            />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 auto-rows-[1fr]">

          <KpiTile
            icon={ArrowUpRight}
            label="Incoming"
            value={incoming}
            iconColor="text-green-500"
          />

          <KpiTile
            icon={ArrowDownRight}
            label="Outgoing"
            value={outgoing}
            iconColor="text-red-500"
          />

          <KpiTile
            icon={Zap}
            label="Pix Volume"
            value={pixVolume}
            hint="Total Pix processed"
            iconColor="text-sky-400"
          />

          <KpiTile
            icon={AlertTriangle}
            label="Chargebacks"
            value={String(chargebacks)}
            iconColor="text-yellow-400"
          />

          <KpiTile
            icon={ListChecks}
            label="Pending"
            value={String(pending)}
            className="md:col-span-2 md:col-start-2"
          />
        </div>
      )}
    </section>
  );
}
