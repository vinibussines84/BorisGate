// resources/js/Pages/Saque/Index.jsx
import React, { useState, useEffect, useMemo, Suspense, lazy } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import axios from "axios";

/* === Lazy components === */
const SaqueHeader = lazy(() => import("@/Components/SaqueHeader"));
const SaqueTable = lazy(() => import("@/Components/SaqueTable"));
const FloatingWithdrawButton = lazy(() => import("@/Components/FloatingWithdrawButton"));
const ReceiptModal = lazy(() => import("@/Components/ReceiptModal"));

/* === Simple fallback skeleton === */
const SkeletonBlock = ({ height = 180 }) => (
  <div
    className="rounded-2xl border border-white/10 bg-[#0b0b0b]/80 animate-pulse shadow-inner"
    style={{ height }}
  />
);

export default function Saque() {
  const [saques, setSaques] = useState([]);
  const [cards, setCards] = useState({});
  const [statusFilter, setStatusFilter] = useState("all");
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [openReceipt, setOpenReceipt] = useState(false);
  const [receiptData, setReceiptData] = useState(null);
  const CACHE_KEY = "saques_cache_v1";

  /* ============================================
     🔁 Busca / Cache / Atualização
  ============================================ */
  const fetchSaques = async (useCache = false) => {
    if (useCache) {
      const cached = sessionStorage.getItem(CACHE_KEY);
      if (cached) {
        try {
          const parsed = JSON.parse(cached);
          setSaques(parsed.data || []);
          setCards(parsed.cards || {});
          setLoading(false);
          return;
        } catch {
          sessionStorage.removeItem(CACHE_KEY);
        }
      }
    }

    setLoading(true);
    try {
      const { data } = await axios.get("/api/withdraws");
      const meta = data.meta || {};
      const list = data.data || [];

      const cardsData = {
        total: meta.totals?.sum_all || 0,
        qtd: meta.totals?.count_all || list.length,
        proc: meta.totals?.count_processing || 0,
        ult: list.length ? list[0].created_at : null,
      };

      setSaques(list);
      setCards(cardsData);

      // Cache resultado (expira após 2min)
      sessionStorage.setItem(
        CACHE_KEY,
        JSON.stringify({ data: list, cards: cardsData, ts: Date.now() })
      );
    } catch (e) {
      console.error("❌ Error fetching withdrawals:", e);
    } finally {
      setLoading(false);
      setIsRefreshing(false);
    }
  };

  useEffect(() => {
    const cache = sessionStorage.getItem(CACHE_KEY);
    if (cache) {
      const parsed = JSON.parse(cache);
      if (Date.now() - (parsed.ts || 0) < 120000) {
        // usa cache válido (menos de 2min)
        setSaques(parsed.data || []);
        setCards(parsed.cards || {});
        setLoading(false);
        return;
      }
    }
    fetchSaques(true);
  }, []);

  const handleRefresh = () => {
    if (!isRefreshing) {
      setIsRefreshing(true);
      fetchSaques();
    }
  };

  /* ============================================
     🔍 Filtro e busca
  ============================================ */
  const filtered = useMemo(() => {
    let f = saques;
    if (statusFilter !== "all") {
      f = f.filter((s) => String(s.status).toLowerCase() === statusFilter);
    }
    if (query) {
      const q = query.toLowerCase();
      f = f.filter(
        (s) =>
          String(s.id).includes(q) ||
          String(s.pixkey || "").toLowerCase().includes(q) ||
          String(s.endtoend || "").toLowerCase().includes(q)
      );
    }
    return f;
  }, [saques, statusFilter, query]);

  /* ============================================
     ⚙️ Render principal
  ============================================ */
  return (
    <AuthenticatedLayout>
      <Head title="Withdrawals" />

      <div className="min-h-screen bg-[#0B0B0B] py-8 px-4 sm:px-6 lg:px-8 text-gray-100">
        <div className="max-w-6xl mx-auto space-y-8">
          <Suspense fallback={<SkeletonBlock height={120} />}>
            <SaqueHeader
              cards={cards}
              onRefresh={handleRefresh}
              isRefreshing={isRefreshing}
            />
          </Suspense>

          <Suspense fallback={<SkeletonBlock height={420} />}>
            <SaqueTable
              loading={loading}
              filtered={filtered}
              statusFilter={statusFilter}
              setStatusFilter={setStatusFilter}
              query={query}
              setQuery={setQuery}
              onOpenReceipt={setReceiptData}
              setOpenReceipt={setOpenReceipt}
            />
          </Suspense>
        </div>
      </div>

      {/* === Floating button + receipt modal === */}
      <Suspense>
        <FloatingWithdrawButton />
      </Suspense>

      <Suspense fallback={null}>
        <ReceiptModal
          open={openReceipt}
          data={receiptData}
          onClose={() => setOpenReceipt(false)}
        />
      </Suspense>
    </AuthenticatedLayout>
  );
}
