// resources/js/Components/PaymentAccountCard.jsx
import { useState, useEffect } from "react";
import { Eye, EyeOff, RefreshCw, ReceiptText, SendHorizonal } from "lucide-react";
import axios from "axios";

/* Subtle progress line */
function CardProgress({ visible }) {
  if (!visible) return null;
  return (
    <>
      <style>{`
        @keyframes cp {
          0% { transform: translateX(-80%); opacity: .25; }
          100% { transform: translateX(180%); opacity: .8; }
        }
      `}</style>
      <div className="absolute inset-x-0 top-0 z-20 h-[2px] overflow-hidden">
        <div className="h-full w-[45%] bg-gradient-to-r from-transparent via-[#02fb5c]/70 to-transparent animate-[cp_1.6s_linear_infinite]" />
      </div>
    </>
  );
}

export default function PaymentAccountCard({ minHeight = 80 }) {
  const [showBalance, setShowBalance] = useState(true);
  const [loading, setLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [balance, setBalance] = useState(0);
  const [blockedBalance, setBlockedBalance] = useState(0);
  const [error, setError] = useState(null);

  /* === Keep BRL formatting === */
  const BRL = (v = 0) =>
    Number(v).toLocaleString("pt-BR", {
      style: "currency",
      currency: "BRL",
      minimumFractionDigits: 2,
    });

  const fetchBalance = async (initial = false) => {
    try {
      if (!initial) setIsRefreshing(true);
      const { data } = await axios.get("/api/balances");

      if (!data?.success) {
        setError(data?.message || "Error fetching balance.");
      } else {
        setBalance(data.data?.amount_available ?? 0);
        setBlockedBalance(data.data?.blocked_amount ?? 0);
        setError(null);
      }
    } catch {
      setError("Communication failure.");
    } finally {
      setLoading(false);
      setIsRefreshing(false);
    }
  };

  useEffect(() => {
    fetchBalance(true);
    const id = setInterval(() => fetchBalance(false), 30000);
    return () => clearInterval(id);
  }, []);

  return (
    <section
      className={[
        "relative w-full isolate overflow-hidden rounded-2xl",
        "bg-[#0D0E0F] border border-neutral-800",
        "shadow-[0px_0px_40px_-10px_rgba(0,0,0,0.8)]",
        "backdrop-blur-xl transition duration-200",
        isRefreshing ? "opacity-[.92]" : "",
      ].join(" ")}
      style={{ minHeight }}
    >
      <CardProgress visible={loading || isRefreshing} />

      {/* Header */}
      <header className="relative z-10 flex items-center justify-between px-5 pt-3 pb-1">
        <div className="inline-flex items-center gap-2 rounded-full border border-neutral-700 bg-neutral-900/60 px-3 py-1.5 backdrop-blur">
          <span
            className={`h-2 w-2 rounded-full ${
              error ? "bg-red-500" : "bg-emerald-400"
            }`}
          />
          <span className="text-[11px] text-neutral-300 tracking-wide">
            Payment Account
          </span>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => fetchBalance(false)}
            disabled={isRefreshing}
            className="h-8 w-8 rounded-xl border border-neutral-700 bg-neutral-900/50 hover:bg-neutral-800/50 transition flex items-center justify-center disabled:opacity-60"
          >
            <RefreshCw
              size={15}
              className={
                isRefreshing ? "animate-spin text-[#02fb5c]" : "text-neutral-300"
              }
            />
          </button>

          <button
            onClick={() => setShowBalance((v) => !v)}
            className="h-8 w-8 rounded-xl border border-neutral-700 bg-neutral-900/50 hover:bg-neutral-800/50 transition flex items-center justify-center"
          >
            {showBalance ? (
              <Eye size={16} className="text-neutral-300" />
            ) : (
              <EyeOff size={16} className="text-neutral-300" />
            )}
          </button>
        </div>
      </header>

      {/* Body */}
      <div className="px-5 py-3">
        {loading ? (
          <div className="space-y-3">
            <div className="h-3 w-24 bg-neutral-700/40 rounded animate-pulse" />
            <div className="h-7 w-40 bg-neutral-700/40 rounded animate-pulse" />
          </div>
        ) : (
          <>
            <p className="text-[12px] text-neutral-400 mb-1">Available balance</p>

            <div className="flex items-end gap-2">
              <span
                className={[
                  "font-light tracking-tight tabular-nums",
                  "leading-none text-white",
                  "text-[28px] sm:text-[30px] md:text-[32px]",
                ].join(" ")}
              >
                {showBalance ? BRL(balance) : "•••••"}
              </span>

              {!loading && !error && (
                <span className="mb-1 inline-flex items-center rounded-full border border-neutral-700 bg-neutral-900/50 px-2 py-0.5 text-[11px] text-neutral-400">
                  Active
                </span>
              )}
            </div>

            <div className="mt-3 flex items-center gap-2">
              <span className="text-[11px] text-neutral-500">
                Blocked balance:
              </span>

              <span className="px-3 py-1 rounded-lg border border-neutral-700 bg-neutral-900/40 text-xs text-neutral-300">
                {showBalance ? BRL(blockedBalance) : "•••••"}
              </span>
            </div>
          </>
        )}
      </div>

      <hr className="border-neutral-800 mx-5" />

      {/* Footer */}
      <footer className="px-5 py-3">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <button
            onClick={() => (window.location.href = "/extrato")}
            className="flex items-center justify-center gap-2 h-10 text-sm rounded-xl border border-neutral-700 bg-neutral-900/40 hover:bg-neutral-800/40 text-neutral-200 transition"
          >
            <ReceiptText size={16} />
            Statement
          </button>

          {/* Main PIX Send Button (neon green) */}
          <button
            onClick={() => (window.location.href = "/saques/solicitar")}
            className="flex items-center justify-center gap-2 h-10 text-sm rounded-xl bg-[#02fb5c] hover:bg-[#29ff78] text-neutral-900 font-semibold transition shadow-[0_0_20px_rgba(2,251,92,0.4)] hover:shadow-[0_0_25px_rgba(2,251,92,0.55)]"
          >
            <SendHorizonal size={16} />
            Send Pix
          </button>
        </div>
      </footer>
    </section>
  );
}
