import React, { useState, useEffect, useMemo } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import axios from "axios";
import SaqueHeader from "@/Components/SaqueHeader";
import SaqueTable from "@/Components/SaqueTable";
import FloatingWithdrawButton from "@/Components/FloatingWithdrawButton";
import ReceiptModal from "@/Components/ReceiptModal";

export default function Saque() {
  const [saques, setSaques] = useState([]);
  const [cards, setCards] = useState({});
  const [statusFilter, setStatusFilter] = useState("all");
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [openReceipt, setOpenReceipt] = useState(false);
  const [receiptData, setReceiptData] = useState(null);

  const fetchSaques = async () => {
    setLoading(true);
    try {
      const { data } = await axios.get("/api/withdraws");
      const meta = data.meta || {};
      const list = data.data || [];
      setSaques(list);
      setCards({
        total: meta.totals?.sum_all || 0,
        qtd: meta.totals?.count_all || list.length,
        proc: meta.totals?.count_processing || 0,
        ult: list.length ? list[0].created_at : null,
      });
    } catch (e) {
      console.error("Erro ao buscar saques:", e);
    } finally {
      setLoading(false);
      setIsRefreshing(false);
    }
  };

  useEffect(() => {
    fetchSaques();
  }, []);

  const handleRefresh = () => {
    if (!isRefreshing) {
      setIsRefreshing(true);
      fetchSaques();
    }
  };

  const filtered = useMemo(() => {
    let f = saques;
    if (statusFilter !== "all") f = f.filter((s) => String(s.status).toLowerCase() === statusFilter);
    if (query) {
      const q = query.toLowerCase();
      f = f.filter(
        (s) => String(s.id).includes(q) || String(s.pixkey || "").toLowerCase().includes(q)
      );
    }
    return f;
  }, [saques, statusFilter, query]);

  return (
    <AuthenticatedLayout>
      <Head title="Saques" />

      <div className="min-h-screen bg-[#0B0B0B] py-8 px-4 sm:px-6 lg:px-8 text-gray-100">
        <div className="max-w-6xl mx-auto space-y-8">
          <SaqueHeader cards={cards} onRefresh={handleRefresh} isRefreshing={isRefreshing} />
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
        </div>
      </div>

      <FloatingWithdrawButton />
      <ReceiptModal open={openReceipt} data={receiptData} onClose={() => setOpenReceipt(false)} />
    </AuthenticatedLayout>
  );
}
