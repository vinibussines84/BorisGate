import React, { useEffect, useState, useCallback } from "react";
import {
  ArrowUpRight,
  ArrowDownRight,
  ListChecks,
  CalendarDays,
  Zap,
  DollarSign,
  Hash,
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

const todayDate = new Date().toLocaleDateString("pt-BR", {
  day: "numeric",
  month: "long",
  year: "numeric",
});

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

  const fetchData = useCallback(async () => {
    setLoading(true);
    setErr(null);

    try {
      const res = await fetch("/api/metrics/day", {
        headers: { Accept: "application/json" },
        credentials: "include",
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const json = await res.json();
      setData(json?.data ?? {});
    } catch (e) {
      setErr(e?.message || "Falha ao carregar métricas.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const incoming = BRL(toNumber(data?.valorLiquidoDia) || 0);
  const outgoing = BRL(toNumber(data?.saidasLiquidoDia) || 0);
  const qtdPagas = data?.qtdPagasDia ?? 0;
  const qtdSaques = data?.qtdSaquesDia ?? 0;
  const pixVolume = BRL(toNumber(data?.volumePixMes) || 0);
  const period = data?.periodo ?? todayDate;

  return (
    <section className="w-full mx-auto max-w-5xl">
      {/* HEADER */}
      <div className="mb-4 flex items-center justify-between">
        <h3 className="text-sm sm:text-base font-semibold text-neutral-100">
          Daily Indicators
        </h3>
      </div>

      {/* PERIOD */}
      <span className="flex items-center text-[11px] text-neutral-400 mb-3 capitalize">
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
            label="Incoming (Net)"
            value={incoming}
            hint="Total received via PIX (after fees)"
            iconColor="text-green-500"
          />

          <KpiTile
            icon={ArrowDownRight}
            label="Withdrawals (Net)"
            value={outgoing}
            hint="Total withdrawals made today"
            iconColor="text-red-500"
          />

          <KpiTile
            icon={Zap}
            label="Monthly PIX Volume"
            value={pixVolume}
            hint="Total PIX processed this month"
            iconColor="text-sky-400"
          />

          <KpiTile
            icon={ListChecks}
            label="Paid Transactions"
            value={String(qtdPagas)}
            hint="Number of PIX paid today"
            iconColor="text-emerald-400"
            className="md:col-span-2 md:col-start-1"
          />

          <KpiTile
            icon={Hash}
            label="Withdrawals Made"
            value={String(qtdSaques)}
            hint="Number of withdrawals completed today"
            iconColor="text-yellow-400"
            className="md:col-span-1"
          />
        </div>
      )}
    </section>
  );
}
