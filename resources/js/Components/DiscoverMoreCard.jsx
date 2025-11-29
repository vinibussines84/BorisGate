// resources/js/Components/DiscoverMoreCard.jsx
import React, { useEffect, useState } from "react";
import {
  ArrowUpRight,
  ArrowDownRight,
  ListChecks,
  CalendarDays,
  AlertTriangle,
  Zap,
} from "lucide-react";

/* Utils */
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

/* Tile (preto fixo) */
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
        "ring-1 ring-inset ring-neutral-900",
        "shadow-sm",
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

export default function DiscoverMoreCard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState(null);

  const routeOr = (name, fallback) =>
    typeof window !== "undefined" && window.route
      ? window.route(name)
      : fallback;

  const fetchMonth = async () => {
    setLoading(true);
    setErr(null);
    try {
      const url = routeOr("api.metrics.month", "/api/metrics/month");
      const res = await fetch(url, { headers: { Accept: "application/json" } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      const d = json?.data ?? {};
      setData(d);
    } catch (e) {
      setErr(e?.message || "Falha ao carregar indicadores.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMonth();
  }, []);

  const entradaMes = toNumber(data?.entradaMes) || 0;
  const saidaMes = toNumber(data?.saidaMes) || 0;
  const pendentes = Number.isFinite(toNumber(data?.pendentes))
    ? toNumber(data?.pendentes)
    : 0;
  const chargebacksMes = Number.isFinite(toNumber(data?.chargebacksMes))
    ? toNumber(data?.chargebacksMes)
    : 0;
  const volumePix = Number.isFinite(toNumber(data?.volumePix))
    ? toNumber(data?.volumePix)
    : 0;
  const periodo = data?.periodo ?? "Este mês";

  const entradaFmt = BRL(entradaMes);
  const saidaFmt = BRL(saidaMes);
  const volumePixFmt = BRL(volumePix);

  return (
    <section className="w-full mx-auto max-w-5xl">
      {/* Cabeçalho */}
      <div className="mb-3 flex items-center justify-between">
        <h3 className="text-sm sm:text-base font-semibold text-neutral-100">
          <span className="relative inline-block">
            <span
              aria-hidden
              className="absolute -top-1 left-0 h-[3px] w-3 bg-neutral-300 rounded-sm"
            />
            Indicadores do mês
          </span>
        </h3>
        <span className="hidden sm:flex items-center text-[11px] text-neutral-400">
          <CalendarDays size={12} className="inline mr-1 opacity-80" />
          {periodo}
        </span>
      </div>

      {err && <div className="mb-3 text-sm text-red-300">{err}</div>}

      {/* Loader */}
      {loading ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div className="h-20 rounded-2xl overflow-hidden border border-neutral-800 ring-1 ring-inset ring-neutral-900 bg-neutral-900 animate-pulse" />
          <div className="h-20 rounded-2xl overflow-hidden border border-neutral-800 ring-1 ring-inset ring-neutral-900 bg-neutral-900 animate-pulse" />
          <div className="h-20 rounded-2xl overflow-hidden border border-neutral-800 ring-1 ring-inset ring-neutral-900 bg-neutral-900 animate-pulse" />
        </div>
      ) : !data ? (
        <div className="text-center text-neutral-400 text-sm py-4">
          Não foi possível carregar os indicadores.
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 auto-rows-[1fr]">
          <KpiTile
            icon={ArrowUpRight}
            label="Entradas do mês"
            value={entradaFmt}
            iconColor="text-green-500"
          />
          <KpiTile
            icon={ArrowDownRight}
            label="Saídas do mês"
            value={saidaFmt}
            iconColor="text-red-500"
          />
          <KpiTile
            icon={Zap}
            label="Volume total Pix"
            value={volumePixFmt}
            hint="Volume total processado via Pix"
            iconColor="text-sky-400"
          />

          <KpiTile
            icon={AlertTriangle}
            label="Chargebacks do mês"
            value={String(chargebacksMes)}
            hint={`${chargebacksMes} chargebacks no período`}
            iconColor="text-yellow-400"
          />

          <KpiTile
            icon={ListChecks}
            label="Pendentes"
            value={String(pendentes)}
            className="md:col-span-2 md:col-start-2"
            hint="Transações aguardando confirmação"
          />
        </div>
      )}
    </section>
  );
}
