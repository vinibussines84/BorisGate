// resources/js/Components/NewDashboardCard.jsx
import { useEffect, useState } from "react";
import { Eye, EyeOff, Lock, ShieldAlert, RefreshCw } from "lucide-react";

/* --- Animated top line (same as PaymentAccountCard, emerald tone) --- */
function CardProgress({ visible }) {
  if (!visible) return null;

  return (
    <>
      <style>{`
        @keyframes ndcCardProgress {
          0%   { transform: translateX(-80%); opacity: .3; }
          100% { transform: translateX(180%); opacity: .7; }
        }
      `}</style>

      <div className="absolute inset-x-0 top-0 z-30 h-[2px] overflow-hidden">
        <div
          className="h-full w-[45%] bg-gradient-to-r from-transparent via-emerald-500/60 to-transparent animate-[ndcCardProgress_1.6s_linear_infinite]"
          style={{ willChange: "transform" }}
        />
      </div>
    </>
  );
}

/* =============================
   Main Card (same color style)
============================= */
export default function NewDashboardCard({ minHeight = 80, initialBalances = {} }) {
  const [showBalance, setShowBalance] = useState(true);
  const [loading, setLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [retainedBalance, setRetainedBalance] = useState(Number(initialBalances.amount_retained ?? 0));
  const [securityBlock, setSecurityBlock] = useState(Number(initialBalances.blocked_amount ?? 0));
  const [error, setError] = useState(null);

  /* ======= Helpers ======= */
  const BRL = (v = 0) =>
    Number(v).toLocaleString("pt-BR", {
      style: "currency",
      currency: "BRL",
      minimumFractionDigits: 2,
    });

  const Value = ({ v, dim = false }) => (
    <span
      className={[
        "whitespace-nowrap tabular-nums text-base sm:text-lg font-semibold",
        "text-neutral-100 transition-opacity duration-300",
        dim ? "opacity-70" : "opacity-100",
      ].join(" ")}
      aria-live="polite"
      aria-atomic="true"
    >
      {showBalance ? BRL(v) : "•••••"}
    </span>
  );

  /* ======= Fetch ======= */
  const loadBalances = async ({ initial = false } = {}) => {
    try {
      if (initial) setLoading(true);
      else setIsRefreshing(true);

      setError(null);
      const res = await fetch("/api/balances", { headers: { Accept: "application/json" } });
      const json = await res.json();

      if (!res.ok || !json?.success) throw new Error(json?.message || "Error fetching balances.");

      const data = json.data || {};
      setRetainedBalance(Number(data.amount_retained ?? 0));
      setSecurityBlock(Number(data.blocked_amount ?? 0));
    } catch (err) {
      setError(err?.message || "Failed to load balances.");
    } finally {
      setLoading(false);
      setIsRefreshing(false);
    }
  };

  /* ======= Auto refresh ======= */
  useEffect(() => {
    loadBalances({ initial: true });
    const interval = setInterval(() => loadBalances({ initial: false }), 30000);
    return () => clearInterval(interval);
  }, []);

  /* =============================
     Render
  ============================== */
  return (
    <section
      className={[
        "relative isolate w-full overflow-hidden rounded-2xl",
        "bg-[#0D0E0F] border border-neutral-800",
        "backdrop-blur-xl shadow-[0_0_40px_-10px_rgba(0,0,0,0.8)]",
        "flex flex-col transition-all duration-300",
        isRefreshing ? "opacity-[.92]" : "",
      ].join(" ")}
      style={{ minHeight }}
    >
      <CardProgress visible={loading || isRefreshing} />

      {/* Header */}
      <header className="relative z-10 flex items-center justify-between px-5 pt-3 pb-1">
        <div className="inline-flex items-center gap-2 rounded-full border border-neutral-700 bg-neutral-900/60 px-3 py-1.5">
          <span className="inline-flex items-center gap-1.5">
            <span className="inline-block h-2 w-2 rounded-full bg-emerald-400" />
            <span className="text-[11px] font-medium text-neutral-300 tracking-wide">
              {error ? "Error updating" : "Balance Restrictions"}
            </span>
          </span>
        </div>

        <div className="flex items-center gap-2">
          {/* Refresh */}
          <button
            type="button"
            onClick={() => loadBalances({ initial: false })}
            disabled={isRefreshing}
            className="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-neutral-700 bg-neutral-900/50 hover:bg-neutral-800/50 text-neutral-200 transition disabled:opacity-60"
            title="Refresh"
            aria-busy={isRefreshing}
          >
            <RefreshCw size={15} className={isRefreshing ? "animate-spin text-emerald-400" : ""} />
          </button>

          {/* Show / Hide */}
          <button
            type="button"
            onClick={() => setShowBalance((v) => !v)}
            className="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-neutral-700 bg-neutral-900/50 hover:bg-neutral-800/50 text-neutral-200 transition"
            title={showBalance ? "Hide values" : "Show values"}
            aria-label={showBalance ? "Hide values" : "Show values"}
          >
            {showBalance ? <Eye size={16} /> : <EyeOff size={16} />}
          </button>
        </div>
      </header>

      {/* Error */}
      {error && (
        <div className="mx-5 mt-3 flex items-start gap-2 rounded-lg border border-red-900/50 bg-red-950/40 p-2.5 text-red-300 shadow-sm text-sm">
          {error}
        </div>
      )}

      {/* Body */}
      <div className="relative z-10 flex-1 px-5 py-3 space-y-3">

        {/* Retained Balance */}
        <div className="group rounded-xl border border-neutral-800 bg-neutral-900/40 hover:bg-neutral-800/40 transition-all duration-200 px-3 py-3">
          <div className="flex items-start justify-between">
            <h3 className="text-sm font-medium text-neutral-100 leading-tight">
              <span className="inline-flex items-center gap-1.5">
                <span className="inline-block h-3 w-0.5 rounded bg-emerald-500/60" />
                Retained Balance
              </span>
            </h3>

            {loading ? (
              <span className="inline-block h-4 w-24 rounded-md bg-white/10 animate-pulse" />
            ) : (
              <Value v={retainedBalance} dim={isRefreshing} />
            )}
          </div>

          <p className="mt-1 text-[11px] text-neutral-400 flex items-center gap-1 leading-tight">
            <Lock size={12} className="text-neutral-300" /> Temporarily unavailable
          </p>
        </div>

        {/* Security Block */}
        <div className="group rounded-xl border border-neutral-800 bg-neutral-900/40 hover:bg-neutral-800/40 transition-all duration-200 px-3 py-3">
          <div className="flex items-start justify-between">
            <h3 className="text-sm font-medium text-neutral-100 leading-tight">
              <span className="inline-flex items-center gap-1.5">
                <span className="inline-block h-3 w-0.5 rounded bg-emerald-500/60" />
                Security Block
              </span>
            </h3>

            {loading ? (
              <span className="inline-block h-4 w-24 rounded-md bg-white/10 animate-pulse" />
            ) : (
              <Value v={securityBlock} dim={isRefreshing} />
            )}
          </div>

          <p className="mt-1 text-[11px] text-neutral-400 flex items-center gap-1 leading-tight">
            <ShieldAlert size={12} className="text-neutral-300" /> Security Analysis /{" "}
            <span className="text-red-400 font-semibold">Risk</span>
          </p>
        </div>
      </div>
    </section>
  );
}
