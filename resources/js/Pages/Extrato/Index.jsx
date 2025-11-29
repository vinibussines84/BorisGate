import React, { useEffect, useState, useCallback } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import axios from "axios";
import ExtratoHeader from "@/Components/ExtratoHeader";
import ExtratoTable from "@/Components/ExtratoTable";
import TransactionDetailModal from "@/Components/TransactionDetailModal";

/* =====================================================================================
   COMPONENTE PRINCIPAL — Extrato Page
===================================================================================== */
export default function Extrato() {
  const [saldo, setSaldo] = useState(0);
  const [entradas, setEntradas] = useState(0);
  const [saidas, setSaidas] = useState(0);
  const [transactions, setTransactions] = useState([]);
  const [totalItems, setTotalItems] = useState(0);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState("all");
  const [searchTerm, setSearchTerm] = useState("");
  const [loadingTable, setLoadingTable] = useState(true);
  const [selectedTransaction, setSelectedTransaction] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);

  const perPage = 10;

  /* =====================================================================================
     FETCH DATA
  ====================================================================================== */
  const fetchExtrato = useCallback(async () => {
    setLoadingTable(true);

    try {
      const query = new URLSearchParams({
        page,
        perPage,
        status: statusFilter !== "all" ? statusFilter : "",
        search: searchTerm || "",
      }).toString();

      // executa as duas requisições em paralelo
      const [resBalance, resTx] = await Promise.all([
        axios.get("/api/balances"),
        axios.get(`/api/list/pix?${query}`),
      ]);

      const b = resBalance?.data?.data || {};
      setSaldo(Number(b.amount_available ?? 0));

      const list = resTx?.data?.transactions || [];
      setTransactions(list);
      setTotalItems(resTx?.data?.total ?? 0);

      // somatório de entradas/saídas (somente pagos/aprovados)
      let entradasTotal = 0;
      let saidasTotal = 0;

      for (const t of list) {
        const status = (t.status || "").toLowerCase();
        if (["paga", "paid", "approved"].includes(status)) {
          if (t.credit) entradasTotal += Number(t.amount);
          else saidasTotal += Number(t.amount);
        }
      }

      setEntradas(entradasTotal);
      setSaidas(saidasTotal);
    } catch (err) {
      console.error("Erro ao buscar extrato:", err);
    } finally {
      setLoadingTable(false);
    }
  }, [page, statusFilter, searchTerm]);

  useEffect(() => {
    fetchExtrato();
  }, [fetchExtrato]);

  /* =====================================================================================
     MODAL HANDLERS
  ====================================================================================== */
  const openModal = (tx) => {
    setSelectedTransaction(tx);
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
    // pequeno delay para não causar shift ao esconder modal
    setTimeout(() => setSelectedTransaction(null), 200);
  };

  /* =====================================================================================
     RENDER
  ====================================================================================== */
  return (
    <AuthenticatedLayout>
      <Head title="Extrato" />

      {/* Container principal fixo e estável */}
      <div className="min-h-screen bg-[#0B0B0B] py-10 px-4 sm:px-6 lg:px-8 text-gray-100">
        <div className="max-w-6xl mx-auto space-y-8">
          {/* HEADER */}
          <div className="min-h-[180px]">
            <ExtratoHeader
              saldo={saldo}
              entradas={entradas}
              saidas={saidas}
              statusFilter={statusFilter}
              setStatusFilter={setStatusFilter}
              searchTerm={searchTerm}
              setSearchTerm={setSearchTerm}
              refresh={fetchExtrato}
            />
          </div>

          {/* TABLE */}
          <div className="min-h-[520px] transition-opacity duration-300" style={{ opacity: loadingTable ? 0.7 : 1 }}>
            <ExtratoTable
              transactions={transactions}
              onView={openModal}
              page={page}
              setPage={setPage}
              totalItems={totalItems}
              perPage={perPage}
              loading={loadingTable}
            />
          </div>
        </div>
      </div>

      {/* MODAL DETALHE — mantido montado, visibilidade controlada */}
      <div className={`${isModalOpen ? "block" : "hidden"}`}>
        <TransactionDetailModal
          transaction={selectedTransaction}
          details={selectedTransaction}
          isOpen={isModalOpen}
          onClose={closeModal}
          formatCurrency={(v) =>
            (Number(v) || 0).toLocaleString("pt-BR", {
              style: "currency",
              currency: "BRL",
            })
          }
        />
      </div>
    </AuthenticatedLayout>
  );
}
