import { useState, useEffect, useCallback } from "react";
import {
  Eye,
  EyeOff,
  RefreshCw,
  ReceiptText,
  SendHorizonal,
} from "lucide-react";
import axios from "axios";

/* Format helper */
const BRL = (v = 0) =>
  Number(v).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  });

/* Small animated progress bar */
function CardProgress({ visible }) {
  if (!visible) return null;
  return (
    <>
      <style>{`
        @keyframes cardSlide {
          0% { transform: translateX(-100%); opacity: .2; }
          100% { transform: translateX(250%); opacity: .8; }
        }
      `}</style>
      <div className="absolute inset-x-0 top-0 h-[2px] overflow-hidden">
        {/* Verde aplicado aqui */}
        <div className="h-full w-1/3 bg-[#02fb5c]/70 animate-[cardSlide_1.4s_linear_infinite]" />
      </div>
    </>
  );
}

/* Cache helper */
const CACHE_KEY = "balance_cache_v1";
const CACHE_TTL = 60000; // 60 seconds

export default function PaymentAccountCard({ minHeight = 80 }) {
  const [showBalance, setShowBalance] = useState(true);
  const [loading, setLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [balance, setBalance] = useState(0);
  const [blockedBalance, setBlockedBalance] = useState(0);
  const [error, setError] = useState(null);

  /* Load cache instantly */
  useEffect(() => {
    try {
      const cache = JSON.parse(localStorage.getItem(CACHE_KEY));
      if (cache && Date.now() - cache.timestamp < CACHE_TTL) {
        setBalance(cache.balance);
        setBlockedBalance(cache.blockedBalance);
        setLoading(false);
      }
    } catch {}
  }, []);

  /* Fetch function with cache write */
  const fetchBalance = useCallback(async (manual = false) => {
    try {
      if (manual) setIsRefreshing(true);
      const { data } = await axios.get("/api/balances");

      if (!data?.success) {
        setError(data?.message || "Error fetching balance.");
      } else {
        const newBalance = data.data?.amount_available ?? 0;
        const newBlocked = data.data?.blocked_amount ?? 0;

        setBalance((prev) => (prev !== newBalance ? newBalance : prev));
        setBlockedBalance((prev) =>
          prev !== newBlocked ? newBlocked : prev
        );
        setError(null);

        localStorage.setItem(
          CACHE_KEY,
          JSON.stringify({
            balance: newBalance,
            blockedBalance: newBlocked,
            timestamp: Date.now(),
          })
        );
      }
    } catch {
      setError("Communication failure.");
    } finally {
      setLoading(false);
      setIsRefreshing(false);
    }
  }, []);

  /* Load on mount + focus refresh */
  useEffect(() => {
    fetchBalance();

    const handleFocus = () => fetchBalance();
    window.addEventListener("focus", handleFocus);

    return () => window.removeEventListener("focus", handleFocus);
  }, [fetchBalance]);

  return (
    <section
      className={[
        "relative w-full rounded-2xl overflow-hidden",
        "bg-[#0D0E0F] border border-neutral-800",
        "transition duration-200",
        isRefreshing ? "opacity-95" : "",
      ].join(" ")}
      style={{ minHeight }}
    >
      {/* Progress line */}
      <CardProgress visible={loading || isRefreshing} />

      {/* Header */}
      <header className="flex items-center justify-between px-5 pt-3 pb-1">
        <div className="inline-flex items-center gap-2 rounded-full border border-neutral-700 bg-neutral-900/60 px-3 py-1.5">
          <span
            className={`h-2 w-2 rounded-full ${
              error ? "bg-red-500" : "bg-[#02fb5c]"
            }`}
          />
          <span className="text-[11px] text-neutral-300 tracking-wide">
            Payment Account
          </span>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => fetchBalance(true)}
            disabled={isRefreshing}
            className="h-8 w-8 flex items-center justify-center rounded-xl border border-neutral-700 bg-neutral-900/50 hover:bg-neutral-800/50 transition disabled:opacity-50"
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
            className="h-8 w-8 flex items-center justify-center rounded-xl border border-neutral-700 bg-neutral-900/50 hover:bg-neutral-800/50 transition"
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
            <div className="h-3 w-24 bg-neutral-700/30 rounded" />
            <div className="h-7 w-40 bg-neutral-700/30 rounded" />
          </div>
        ) : (
          <>
            <p className="text-[12px] text-neutral-400 mb-1">Available balance</p>

            <div className="flex items-end gap-2">
              <span className="text-white font-light tracking-tight text-[28px] sm:text-[30px] leading-none">
                {showBalance ? BRL(balance) : "•••••"}
              </span>

              {!error && (
                <span className="mb-1 inline-flex items-center rounded-full border border-neutral-700 bg-neutral-900/50 px-2 py-0.5 text-[11px] text-neutral-400">
                  Active
                </span>
              )}
            </div>

            <div className="mt-3 flex items-center gap-2">
              <span className="text-[11px] text-neutral-500">Blocked balance:</span>

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
          
          {/* Left Button (Statement) */}
          <button
            onClick={() => (window.location.href = "/extrato")}
            className="flex items-center justify-center gap-2 h-10 text-sm rounded-xl border border-neutral-700 bg-neutral-900/40 hover:bg-neutral-800/40 text-neutral-200 transition"
          >
            <ReceiptText size={16} />
            Statement
          </button>

          {/* Right Button (Send Pix) — agora VERDE */}
          <button
            onClick={() => (window.location.href = '/saques/solicitar')}
            className="flex items-center justify-center gap-2 h-10 text-sm rounded-xl 
            bg-[#02fb5c] hover:bg-[#00e756] text-neutral-900 font-semibold transition shadow-md shadow-[#02fb5c]/20"
          >
            <SendHorizonal size={16} className="text-neutral-900" />
            Send Pix
          </button>

        </div>
      </footer>
    </section>
  );
}
