import React, {
  useEffect,
  useState,
  useCallback,
  Suspense,
  lazy,
  useRef,
} from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import axios from "axios";

/* === Lazy load === */
const ExtratoHeader = lazy(() => import("@/Components/ExtratoHeader"));
const ExtratoTable = lazy(() => import("@/Components/ExtratoTable"));
const TransactionDetailModal = lazy(() =>
  import("@/Components/TransactionDetailModal")
);

/* === Skeleton === */
const SkeletonBlock = ({ height = 200 }) => (
  <div
    className="rounded-2xl border border-white/10 bg-[#0b0b0b]/80 animate-pulse shadow-inner"
    style={{ height }}
  />
);

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
  const CACHE_KEY = "extrato_cache_v1";
  const TABLE_CACHE_KEY = "extract_table_cache_v1";
  const debounceRef = useRef(null);

  /* =====================================================================================
     FUNÇÃO PRINCIPAL DE BUSCA (agora estável, sem recriação desnecessária)
  ===================================================================================== */
  const fetchExtrato = useCallback(
    async ({ force = false, page, statusFilter, searchTerm }) => {
      setLoadingTable(true);

      /** CACHE DE 1 MINUTO */
      if (!force) {
        const cached = sessionStorage.getItem(CACHE_KEY);
        if (cached) {
          try {
            const parsed = JSON.parse(cached);
            if (Date.now() - parsed.ts < 60000) {
              setSaldo(parsed.saldo);
              setEntradas(parsed.entradas);
              setSaidas(parsed.saidas);
              setTransactions(parsed.transactions);
              setTotalItems(parsed.totalItems);
              setLoadingTable(false);
              return;
            }
          } catch {
            sessionStorage.removeItem(CACHE_KEY);
          }
        }
      }

      try {
        const query = new URLSearchParams({
          page,
          perPage,
          status: statusFilter !== "all" ? statusFilter : "",
          search: searchTerm || "",
        }).toString();

        const [resBalance, resTx] = await Promise.all([
          axios.get("/api/balances"),
          axios.get(`/api/list/pix?${query}`),
        ]);

        const balanceData = resBalance?.data?.data || {};
        const saldoAtual = Number(balanceData.amount_available ?? 0);
        setSaldo(saldoAtual);

        const list = resTx?.data?.transactions || [];
        setTransactions(list);

        setTotalItems(resTx?.data?.totalItems ?? 0);

        let entradasTotal = 0;
        let saidasTotal = 0;

        for (const t of list) {
          const st = (t.status || "").toLowerCase();
          if (["paga", "paid", "approved", "confirmed", "completed"].includes(st)) {
            if (t.credit) entradasTotal += Number(t.amount);
            else saidasTotal += Number(t.amount);
          }
        }

        setEntradas(entradasTotal);
        setSaidas(saidasTotal);

        sessionStorage.setItem(
          CACHE_KEY,
          JSON.stringify({
            saldo: saldoAtual,
            entradas: entradasTotal,
            saidas: saidasTotal,
            transactions: list,
            totalItems: resTx?.data?.totalItems ?? 0,
            ts: Date.now(),
          })
        );
      } catch (err) {
        console.error("❌ Erro ao buscar extrato:", err);
      } finally {
        setLoadingTable(false);
      }
    },
    []
  );

  /* =====================================================================================
     AUTO UPDATE QUANDO FILTROS / BUSCA / PAGINA ALTERAM
  ===================================================================================== */
  useEffect(() => {
    sessionStorage.removeItem(CACHE_KEY);
    localStorage.removeItem(TABLE_CACHE_KEY);

    clearTimeout(debounceRef.current);

    if (searchTerm.trim() !== "") {
      debounceRef.current = setTimeout(
        () =>
          fetchExtrato({
            force: true,
            page,
            statusFilter,
            searchTerm,
          }),
        400
      );
    } else {
      fetchExtrato({
        force: true,
        page,
        statusFilter,
        searchTerm,
      });
    }

    return () => clearTimeout(debounceRef.current);
  }, [page, statusFilter, searchTerm]);

  /* =====================================================================================
     MODAL
  ===================================================================================== */
  const openModal = (tx) => {
    setSelectedTransaction(tx);
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
    setTimeout(() => setSelectedTransaction(null), 150);
  };

  /* =====================================================================================
     RENDER
  ===================================================================================== */
  return (
    <AuthenticatedLayout>
      <Head title="Extrato" />
      <div className="min-h-screen bg-[#0B0B0B] py-10 px-4 sm:px-6 lg:px-8 text-gray-100">
        <div className="max-w-6xl mx-auto space-y-8">
          {/* HEADER */}
          <Suspense fallback={<SkeletonBlock height={160} />}>
            <ExtratoHeader
              saldo={saldo}
              entradas={entradas}
              saidas={saidas}
              statusFilter={statusFilter}
              setStatusFilter={(val) => {
                setStatusFilter(val);
                setPage(1);
              }}
              searchTerm={searchTerm}
              setSearchTerm={setSearchTerm}
              refresh={() =>
                fetchExtrato({
                  force: true,
                  page: 1,
                  statusFilter,
                  searchTerm,
                })
              }
            />
          </Suspense>

          {/* TABLE */}
          <div
            className="min-h-[520px] transition-opacity duration-300"
            style={{ opacity: loadingTable ? 0.7 : 1 }}
          >
            <Suspense fallback={<SkeletonBlock height={520} />}>
              <ExtratoTable
                transactions={transactions}
                onView={openModal}
                page={page}
                setPage={setPage}
                totalItems={totalItems}
                perPage={perPage}
                loading={loadingTable}
                searchTerm={searchTerm}
                refresh={() =>
                  fetchExtrato({
                    force: true,
                    page,
                    statusFilter,
                    searchTerm,
                  })
                }
              />
            </Suspense>
          </div>
        </div>
      </div>

      {/* MODAL */}
      <Suspense fallback={null}>
        {selectedTransaction && (
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
        )}
      </Suspense>
    </AuthenticatedLayout>
  );
}
