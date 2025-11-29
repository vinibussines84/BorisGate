import React, { useEffect, useState, useRef } from "react";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";

/* =======================
   Helpers
======================= */
const BRL = (v) =>
  (Number(v) || 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  });

const compactBRL = (v) => {
  const n = Number(v) || 0;
  if (Math.abs(n) >= 1_000_000) return `R$ ${(n / 1_000_000).toFixed(1)}M`;
  if (Math.abs(n) >= 1_000) return `R$ ${(n / 1_000).toFixed(1)}k`;
  return BRL(n);
};

const CustomTooltip = React.memo(({ active, payload, label }) => {
  if (!active || !payload?.length) return null;
  const valor = payload[0]?.value;

  return (
    <div
      style={{
        background: "rgba(11, 11, 11, 0.92)",
        border: "1px solid rgba(255,255,255,0.06)",
        borderRadius: 14,
        padding: "10px 12px",
        boxShadow: "0 14px 36px rgba(16,185,129,0.18)",
        backdropFilter: "blur(8px)",
        color: "#fff",
        minWidth: 150,
      }}
    >
      <div
        style={{
          display: "inline-flex",
          alignItems: "center",
          gap: 8,
          fontSize: 11,
          color: "rgba(255,255,255,0.7)",
          marginBottom: 6,
          letterSpacing: 0.35,
          textTransform: "uppercase",
        }}
      >
        <span
          style={{
            width: 8,
            height: 8,
            borderRadius: 9999,
            background: "#22c55e",
            boxShadow: "0 0 0 3px rgba(34,197,94,0.15)",
          }}
        />
        {label}
      </div>

      <div
        style={{
          fontSize: 18,
          fontWeight: 800,
          color: "#22c55e",
          lineHeight: 1.1,
          marginBottom: 2,
        }}
      >
        {BRL(valor)}
      </div>

      <div style={{ fontSize: 11, color: "rgba(255,255,255,0.65)" }}>
        Entradas líquidas do dia
      </div>
    </div>
  );
});

function Skeleton({ height }) {
  return (
    <div className="w-full animate-pulse" style={{ height }}>
      <div className="h-full w-full rounded-xl bg-white/5" />
    </div>
  );
}

function EmptyState({ message = "Sem dados para o período selecionado.", height }) {
  return (
    <div
      className="flex items-center justify-center rounded-xl border border-white/10 bg-black/20"
      style={{ height }}
    >
      <div className="text-center space-y-1">
        <div className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500/10 border border-emerald-500/20">
          <span className="block h-1.5 w-1.5 rounded-full bg-emerald-400" />
        </div>
        <p className="text-xs text-white/60">{message}</p>
      </div>
    </div>
  );
}

/* =======================
   Componente Principal
======================= */
export default function DashboardChart({
  data: dataProp = null,
  days = 7,
  title = "Fluxo Diário",
  subtitle = "",
  endpoint = "/api/dashboard/daily-flow",
  className = "",
  height = 210,
}) {
  const [data, setData] = useState(dataProp || []);
  const [loading, setLoading] = useState(!dataProp);
  const [error, setError] = useState(null);
  const [ready, setReady] = useState(false); // ✅ renderiza gráfico só quando tiver tamanho válido

  const containerRef = useRef(null);
  const gradientIdRef = useRef(`grad-${Math.random().toString(36).slice(2)}`);
  const gradientId = gradientIdRef.current;

  /* =======================
     Fetch
  ======================= */
  useEffect(() => {
    if (dataProp) return;

    const controller = new AbortController();
    const signal = controller.signal;

    setLoading(true);
    setError(null);

    fetch(`${endpoint}?days=${days}`, { signal })
      .then((res) => res.json())
      .then((json) => {
        if (json?.data) {
          setData(Array.isArray(json.data) ? json.data : []);
        } else {
          setData([]);
        }
      })
      .catch((e) => {
        if (e.name === "AbortError") return;
        setError("Erro ao carregar dados.");
        setData([]);
      })
      .finally(() => setLoading(false));

    return () => controller.abort();
  }, [endpoint, days, dataProp]);

  /* =======================
     Observa tamanho real
  ======================= */
  useEffect(() => {
    const node = containerRef.current;
    if (!node) return;

    const checkReady = (width, height) => {
      if (width > 0 && height > 0) {
        setReady(true);
      }
    };

    const resizeObserver = new ResizeObserver((entries) => {
      const { width, height } = entries[0].contentRect;
      checkReady(width, height);
    });

    resizeObserver.observe(node);

    return () => resizeObserver.disconnect();
  }, []);

  const subtitleText = subtitle || `Últimos ${days} dias`;

  /* =======================
     Render
  ======================= */
  return (
    <div
      ref={containerRef}
      className={[
        "w-full rounded-2xl border border-white/10 bg-[#0b0b0c]/60 p-4 backdrop-blur-md",
        "shadow-[0_0_24px_-12px_rgba(16,185,129,0.24)]",
        className,
      ].join(" ")}
    >
      {/* Header */}
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-white font-semibold text-base flex items-center gap-2">
          <span className="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400 ring-4 ring-emerald-500/10" />
          {title}
        </h2>
        <span className="text-[10px] text-white/45 uppercase tracking-wide">
          {subtitleText}
        </span>
      </div>

      {/* Corpo */}
      {loading ? (
        <Skeleton height={height} />
      ) : error ? (
        <EmptyState message={error} height={height} />
      ) : !data.length ? (
        <EmptyState height={height} />
      ) : !ready ? (
        // enquanto o ResizeObserver ainda não detectar tamanho válido
        <Skeleton height={height} />
      ) : (
        <div className="w-full" style={{ height, minWidth: 0, minHeight: 0 }}>
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data} margin={{ top: 6, right: 16, bottom: 0, left: -6 }}>
              <defs>
                <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#10b981" stopOpacity={0.95} />
                  <stop offset="100%" stopColor="#10b981" stopOpacity={0.05} />
                </linearGradient>
              </defs>

              <CartesianGrid
                strokeDasharray="2 4"
                stroke="rgba(255,255,255,0.05)"
                vertical={false}
              />

              <XAxis
                dataKey="name"
                tick={{ fill: "#9ca3af", fontSize: 11 }}
                axisLine={false}
                tickLine={false}
                padding={{ left: 6, right: 6 }}
                height={24}
              />

              <YAxis
                tick={{ fill: "#9ca3af", fontSize: 11 }}
                axisLine={false}
                tickLine={false}
                tickFormatter={compactBRL}
                width={52}
              />

              <Tooltip
                cursor={{ stroke: "rgba(16,185,129,0.35)", strokeWidth: 1 }}
                content={<CustomTooltip />}
              />

              <Line
                type="monotone"
                dataKey="valor"
                stroke={`url(#${gradientId})`}
                strokeWidth={2}
                dot={false}
                activeDot={{ r: 3.8, fill: "#10b981" }}
                isAnimationActive={true}
                animationDuration={500}
                animationEasing="ease-out"
              />
            </LineChart>
          </ResponsiveContainer>
        </div>
      )}
    </div>
  );
}
