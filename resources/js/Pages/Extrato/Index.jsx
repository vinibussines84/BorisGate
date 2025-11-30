// resources/js/Pages/Extrato/Index.jsx
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

  /* ==========================================================
     REFRESH HANDLER
  ========================================================== */
  const refreshHandler = (isSearch = false) => {
    // 🔹 se for busca → volta pra página 1 e limpa cache
    if (isSearch) {
      setPage(1);
    }

    // 🔹 sempre limpar caches antes de refazer a requisição
    sessionStorage.removeItem(CACHE_KEY);
    localStorage.removeItem(TABLE_CACHE_KEY);

    fetchExtrato(false);
  };

  /* ==========================================================
     FETCH DATA (com cache leve)
  ========================================================== */
  const fetchExtrato = useCallback(
    async (useCache = false) => {
      setLoadingTable(true);

      if (useCache) {
        const cached = sessionStorage.getItem(CACHE_KEY);
        if (cached) {
          try {
            const { saldo, entradas, saidas, transactions, totalItems, ts } =
              JSON.parse(cached);

            if (Date.now() - ts < 60000) {
              setSaldo(saldo);
              setEntradas(entradas);
              setSaidas(saidas);
              setTransactions(transactions);
              setTotalItems(totalItems);
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

        /* BALANCE */
        const b = resBalance?.data?.data || {};
        const saldoAtual = Number(b.amount_available ?? 0);
        setSaldo(saldoAtual);

        /* LIST */
        const list = resTx?.data?.transactions || [];
        setTransactions(list);
        setTotalItems(resTx?.data?.total ?? 0);

        /* CREDIT / DEBIT SUM */
        let entradasTotal = 0;
        let saidasTotal = 0;

        for (const t of list) {
          const st = (t.status || "").toLowerCase();
          if (["paga", "paid", "approved"].includes(st)) {
            if (t.credit) entradasTotal += Number(t.amount);
            else saidasTotal += Number(t.amount);
          }
        }

        setEntradas(entradasTotal);
        setSaidas(saidasTotal);

        /* salva no cache */
        sessionStorage.setItem(
          CACHE_KEY,
          JSON.stringify({
            saldo: saldoAtual,
            entradas: entradasTotal,
            saidas: saidasTotal,
            transactions: list,
            totalItems: resTx?.data?.total ?? 0,
            ts: Date.now(),
          })
        );
      } catch (err) {
        console.error("❌ Erro ao buscar extrato:", err);
      } finally {
        setLoadingTable(false);
      }
    },
    [page, statusFilter, searchTerm]
  );

  /* ==========================================================
     FETCH AUTOMÁTICO (debounce para busca)
  ========================================================== */
  useEffect(() => {
    // 🔹 se for mudança de status → força reload imediato
    if (debounceRef.current) clearTimeout(debounceRef.current);

    if (searchTerm.trim() === "") {
      // ao mudar statusFilter, recarrega imediatamente
      fetchExtrato(false);
    } else {
      // busca → delay de 400ms
      debounceRef.current = setTimeout(() => {
        fetchExtrato(false);
      }, 400);
    }

    return () => clearTimeout(debounceRef.current);
  }, [searchTerm, statusFilter, page]);

  /* ==========================================================
     MODAL
  ========================================================== */
  const openModal = (tx) => {
    setSelectedTransaction(tx);
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
    setTimeout(() => setSelectedTransaction(null), 150);
  };

  /* ========================================================== */
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
                refreshHandler(false); // 🔥 força atualização ao trocar filtro
              }}
              searchTerm={searchTerm}
              setSearchTerm={setSearchTerm}
              refresh={(isSearch) => refreshHandler(isSearch)}
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
                refresh={(p) => fetchExtrato(p)}
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
