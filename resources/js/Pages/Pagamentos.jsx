// resources/js/Pages/Saques.jsx
import React, { useMemo, useState, useEffect } from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
  ArrowDownCircle,
  History,
  ShieldCheck,
  Info,
  X,
  Loader2,
  Search,
  Filter,
  ChevronLeft,
  ChevronRight,
} from "lucide-react";
import axios from "axios";

/* ===============================================
   CONFIG
================================================= */
const ENABLE_MOCK = true; // troque para false ao ligar sua API
// Taxas padrão (exemplo): 1.99% + R$ 3,00
const DEFAULT_FEE_PERCENT = 1.99; // %
const DEFAULT_FEE_FIXED = 3.0;    // R$

/* ===============================================
   UTILS
================================================= */
const BRL = (v) =>
  (Number(v) || 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  });

const maskPixKey = (key = "") => {
  if (!key) return "-";
  const k = String(key);
  if (k.includes("@")) {
    // e-mail
    const [u, d] = k.split("@");
    return `${u.slice(0, 2)}***@${d}`;
  }
  const digits = k.replace(/\D/g, "").length;
  if (digits >= 11 && digits <= 14) {
    // CPF/CNPJ
    return `${k.slice(0, 3)}***${k.slice(-2)}`;
  }
  // telefone ou aleatória
  return `${k.slice(0, 3)}***${k.slice(-3)}`;
};

const statusColor = (s) => {
  switch ((s || "").toLowerCase()) {
    case "paid":
    case "approved":
    case "completed":
      return "bg-emerald-500/15 text-emerald-300 border-emerald-500/30";
    case "pending":
      return "bg-yellow-500/15 text-yellow-300 border-yellow-500/30";
    case "processing":
      return "bg-sky-500/15 text-sky-300 border-sky-500/30";
    case "failed":
    case "canceled":
      return "bg-rose-500/15 text-rose-300 border-rose-500/30";
    default:
      return "bg-zinc-700/20 text-zinc-300 border-zinc-600/30";
  }
};

/* ===============================================
   COMPONENTES PEQUENOS
================================================= */
const Badge = ({ children, className = "" }) => (
  <span className={`inline-flex items-center rounded-md border px-2 py-0.5 text-xs ${className}`}>
    {children}
  </span>
);

const StatusBadge = ({ status }) => (
  <Badge className={statusColor(status)}>{status || "-"}</Badge>
);

const KeyTypeBadge = ({ type }) => (
  <Badge className="border-emerald-500/20 bg-emerald-500/10 text-emerald-300">
    {type || "-"}
  </Badge>
);

/* ===============================================
   MODAL DE SAQUE
================================================= */
function WithdrawModal({
  open,
  onClose,
  onSubmit,
  loading,
  feePercent = DEFAULT_FEE_PERCENT,
  feeFixed = DEFAULT_FEE_FIXED,
}) {
  const [value, setValue] = useState("");
  const [keyType, setKeyType] = useState("aleatoria");
  const [pixKey, setPixKey] = useState("");
  const [note, setNote] = useState("");

  const numValue = useMemo(() => Number(String(value).replace(",", ".")) || 0, [value]);
  const fee = useMemo(() => numValue * (feePercent / 100) + feeFixed, [numValue, feePercent, feeFixed]);
  const net = useMemo(() => Math.max(0, numValue - fee), [numValue, fee]);

  useEffect(() => {
    if (!open) {
      setValue("");
      setPixKey("");
      setNote("");
      setKeyType("aleatoria");
    }
  }, [open]);

  if (!open) return null;

  const submit = () => {
    if (!numValue || !pixKey) return;
    onSubmit({
      amount: numValue,
      key_type: keyType,
      pix_key: pixKey,
      note,
      fee: Number(fee.toFixed(2)),
      net: Number(net.toFixed(2)),
    });
  };

  return (
    <>
      <div className="fixed inset-0 z-40 bg-black/50" onClick={onClose} />
      <div className="fixed inset-x-0 top-24 z-50 mx-auto w-full max-w-lg rounded-2xl border border-white/10 bg-[#151515] p-5 shadow-xl">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-lg font-medium text-white flex items-center gap-2">
            <ArrowDownCircle size={18} className="text-emerald-400" />
            Efetivar saque
          </h3>
          <button
            onClick={onClose}
            className="rounded-md p-1 text-gray-400 hover:text-gray-200 hover:bg-white/5"
            aria-label="Fechar"
          >
            <X size={18} />
          </button>
        </div>

        <div className="space-y-4">
          {/* Valor */}
          <div>
            <label className="mb-1 block text-sm text-gray-300">Valor</label>
            <input
              type="number"
              step="0.01"
              min="0"
              inputMode="decimal"
              value={value}
              onChange={(e) => setValue(e.target.value)}
              placeholder="0,00"
              className="w-full rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-white outline-none focus:border-emerald-500/50"
            />
          </div>

          {/* Tipo de chave */}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-sm text-gray-300">Tipo de chave PIX</label>
              <select
                value={keyType}
                onChange={(e) => setKeyType(e.target.value)}
                className="w-full rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-white outline-none focus:border-emerald-500/50"
              >
                <option value="aleatoria">Aleatória</option>
                <option value="cpf">CPF</option>
                <option value="cnpj">CNPJ</option>
                <option value="email">E-mail</option>
                <option value="telefone">Telefone</option>
              </select>
            </div>

            <div>
              <label className="mb-1 block text-sm text-gray-300">Chave PIX</label>
              <input
                value={pixKey}
                onChange={(e) => setPixKey(e.target.value)}
                placeholder="Sua chave PIX"
                className="w-full rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-white outline-none focus:border-emerald-500/50"
              />
            </div>
          </div>

          {/* Observação */}
          <div>
            <label className="mb-1 block text-sm text-gray-300">Observação (opcional)</label>
            <input
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder="Texto para controle interno"
              className="w-full rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-white outline-none focus:border-emerald-500/50"
            />
          </div>

          {/* Resumo de taxas */}
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-3">
            <div className="flex items-center justify-between text-sm text-gray-300">
              <span>Taxa (%)</span>
              <span>{feePercent.toFixed(2)}%</span>
            </div>
            <div className="mt-1 flex items-center justify-between text-sm text-gray-300">
              <span>Taxa fixa</span>
              <span>{BRL(feeFixed)}</span>
            </div>
            <div className="mt-2 h-px w-full bg-white/10" />
            <div className="mt-2 flex items-center justify-between text-sm text-gray-200">
              <span>Total de taxas</span>
              <span className="font-medium">{BRL(fee)}</span>
            </div>
            <div className="mt-1 flex items-center justify-between text-sm text-white">
              <span>Você receberá</span>
              <span className="font-semibold text-emerald-300">{BRL(net)}</span>
            </div>
          </div>

          {/* Ações */}
          <div className="flex items-center justify-end gap-2 pt-1">
            <button
              onClick={onClose}
              className="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-300 hover:bg-white/5"
            >
              Cancelar
            </button>
            <button
              disabled={loading || !numValue || !pixKey}
              onClick={submit}
              className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
            >
              {loading ? <Loader2 size={16} className="animate-spin" /> : <ArrowDownCircle size={16} />}
              Efetivar saque
            </button>
          </div>
        </div>
      </div>
    </>
  );
}

/* ===============================================
   PAGE
================================================= */
export default function Saques() {
  const [openModal, setOpenModal] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [rows, setRows] = useState([]);

  // ---- Busca / Filtros / Paginação ----
  const [query, setQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState("all"); // all | paid | pending | processing | failed | canceled | approved | completed
  const [keyTypeFilter, setKeyTypeFilter] = useState("all"); // all | aleatoria | cpf | cnpj | email | telefone
  const [perPage, setPerPage] = useState(10);
  const [page, setPage] = useState(1);

  // Mock inicial
  useEffect(() => {
    if (!ENABLE_MOCK) return;
    setRows([
      {
        id: "WDR-102938",
        created_at: "2025-10-30T16:22:00Z",
        amount: 1200,
        fee: 26.88, // 1.99% + 3.00
        net: 1173.12,
        status: "processing",
        key_type: "cpf",
        pix_key: "123.456.789-00",
      },
      {
        id: "WDR-102937",
        created_at: "2025-10-29T12:10:00Z",
        amount: 300,
        fee: 8.97,
        net: 291.03,
        status: "paid",
        key_type: "email",
        pix_key: "cliente@example.com",
      },
      {
        id: "WDR-102936",
        created_at: "2025-10-28T09:05:00Z",
        amount: 800,
        fee: 18.92,
        net: 781.08,
        status: "failed",
        key_type: "aleatoria",
        pix_key: "a1b2c3d4e5f6g7h8i9j0",
      },
      {
        id: "WDR-102935",
        created_at: "2025-10-27T12:18:00Z",
        amount: 540.5,
        fee: 13.75,
        net: 526.75,
        status: "pending",
        key_type: "telefone",
        pix_key: "+55 (11) 98888-7777",
      },
      {
        id: "WDR-102934",
        created_at: "2025-10-27T09:10:00Z",
        amount: 2100.0,
        fee: 44.79,
        net: 2055.21,
        status: "approved",
        key_type: "cnpj",
        pix_key: "12.345.678/0001-12",
      },
      {
        id: "WDR-102933",
        created_at: "2025-10-26T14:40:00Z",
        amount: 99.9,
        fee: 4.99,
        net: 94.91,
        status: "canceled",
        key_type: "aleatoria",
        pix_key: "z9y8x7w6v5u4t3s2r1q0",
      },
    ]);
  }, []);

  // API real (exemplo):
  // useEffect(() => {
  //   if (ENABLE_MOCK) return;
  //   axios
  //     .get("/api/withdrawals", { params: { page, perPage, q: query, status: statusFilter, key_type: keyTypeFilter } })
  //     .then(({ data }) => setRows(data?.data || data || []))
  //     .catch(() => setRows([]));
  // }, [page, perPage, query, statusFilter, keyTypeFilter]);

  // Resetar página quando filtrar/buscar
  useEffect(() => {
    setPage(1);
  }, [query, statusFilter, keyTypeFilter, perPage]);

  const filteredSorted = useMemo(() => {
    const q = query.trim().toLowerCase();

    return [...rows]
      .filter((r) => {
        // filtro por status
        if (statusFilter !== "all") {
          const k = (r.status || "").toLowerCase();
          if (k !== statusFilter) return false;
        }
        // filtro por tipo de chave
        if (keyTypeFilter !== "all") {
          const kt = (r.key_type || "").toLowerCase();
          if (kt !== keyTypeFilter) return false;
        }
        // busca
        if (q) {
          const hay =
            (r.id || "").toLowerCase() +
            " " +
            (r.pix_key || "").toLowerCase() +
            " " +
            (r.key_type || "").toLowerCase() +
            " " +
            (r.status || "").toLowerCase();
          if (!hay.includes(q)) return false;
        }
        return true;
      })
      .sort((a, b) => {
        // mais recente primeiro
        const da = new Date(a.created_at).getTime() || 0;
        const db = new Date(b.created_at).getTime() || 0;
        return db - da;
      });
  }, [rows, query, statusFilter, keyTypeFilter]);

  const total = filteredSorted.length;
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const pageSafe = Math.min(page, totalPages);
  const sliceStart = (pageSafe - 1) * perPage;
  const pageRows = filteredSorted.slice(sliceStart, sliceStart + perPage);

  const handleSubmit = async (payload) => {
    try {
      setSubmitting(true);
      if (ENABLE_MOCK) {
        // mock de sucesso
        const now = new Date().toISOString();
        const newRow = {
          id: `WDR-${Math.floor(Math.random() * 900000 + 100000)}`,
          created_at: now,
          amount: payload.amount,
          fee: payload.fee,
          net: payload.net,
          status: "processing",
          key_type: payload.key_type,
          pix_key: payload.pix_key,
          note: payload.note,
        };
        setRows((r) => [newRow, ...r]);
        setOpenModal(false);
      } else {
        // API real
        await axios.post("/api/withdrawals", {
          amount: payload.amount,
          key_type: payload.key_type,
          pix_key: payload.pix_key,
          note: payload.note,
        });
        // Em API real, recarregue sua lista aqui:
        // const { data } = await axios.get("/api/withdrawals", { params: { page, perPage, q: query, status: statusFilter, key_type: keyTypeFilter } });
        // setRows(data?.data || data || []);
        setOpenModal(false);
      }
    } catch (e) {
      console.error(e);
      alert("Não foi possível efetivar o saque. Tente novamente.");
    } finally {
      setSubmitting(false);
    }
  };

  const copyToClipboard = async (text) => {
    try {
      await navigator.clipboard.writeText(text || "");
    } catch {}
  };

  const StatusChip = ({ value, label }) => {
    const active = statusFilter === value;
    const base = "px-3 py-1.5 rounded-lg text-xs border transition";
    const classes = active
      ? "border-emerald-500/40 bg-emerald-500/10 text-emerald-300"
      : "border-white/10 bg-white/[0.03] text-gray-300 hover:bg-white/[0.06]";
    return (
      <button onClick={() => setStatusFilter(value)} className={`${base} ${classes}`}>
        {label}
      </button>
    );
  };

  const KeyTypeChip = ({ value, label }) => {
    const active = keyTypeFilter === value;
    const base = "px-3 py-1.5 rounded-lg text-xs border transition";
    const classes = active
      ? "border-sky-500/40 bg-sky-500/10 text-sky-300"
      : "border-white/10 bg-white/[0.03] text-gray-300 hover:bg-white/[0.06]";
    return (
      <button onClick={() => setKeyTypeFilter(value)} className={`${base} ${classes}`}>
        {label}
      </button>
    );
  };

  return (
    <AuthenticatedLayout>
      <Head title="Saques" />

      <div className="min-h-screen bg-[#0A0A0A] text-white py-10 px-4 sm:px-6 lg:px-8">
        <div className="mx-auto w-full max-w-5xl space-y-10">
          {/* Cabeçalho */}
          <header className="flex flex-col gap-6 pb-6">
            <div className="inline-flex items-center gap-2 self-start rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs text-gray-300">
              <ShieldCheck size={14} className="text-emerald-400" />
              Transferências seguras via PIX
            </div>

            <div>
              <h1 className="text-4xl font-extralight tracking-tight text-white sm:text-5xl">
                Efetivar <span className="font-light text-white/80">Saques</span>
              </h1>
              <p className="mt-3 max-w-2xl text-lg font-light text-gray-400">
                Envie seu saldo para uma conta via PIX com taxa transparente e liquidação rápida.
              </p>
            </div>

            {/* Ações rápidas */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <button
                type="button"
                onClick={() => setOpenModal(true)}
                className="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 transition hover:bg-white/[0.06] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30"
              >
                <div className="rounded-lg border border-emerald-600/20 bg-emerald-500/10 p-2">
                  <ArrowDownCircle size={18} className="text-emerald-400" />
                </div>
                <div className="text-left">
                  <p className="text-sm font-medium text-white">Efetivar saque</p>
                  <p className="text-xs text-gray-400">Informe valor e chave PIX</p>
                </div>
              </button>

              <div className="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3">
                <div className="rounded-lg border border-zinc-600/20 bg-zinc-500/10 p-2">
                  <Info size={18} className="text-zinc-300" />
                </div>
                <div className="text-left">
                  <p className="text-sm font-medium text-white">Taxas e prazos</p>
                  <p className="text-xs text-gray-400">
                    {DEFAULT_FEE_PERCENT}% + {BRL(DEFAULT_FEE_FIXED)} por saque
                  </p>
                </div>
              </div>
            </div>
          </header>

          {/* Aviso/Política */}
          <div className="flex items-start gap-3 rounded-xl border border-white/10 bg-white/[0.03] p-4">
            <div className="rounded-md border border-emerald-600/30 bg-emerald-500/10 p-1.5">
              <Info size={16} className="text-emerald-400" />
            </div>
            <p className="text-sm text-gray-300">
              Confira a chave, o valor e o resumo das taxas antes de confirmar. Saques podem estar
              sujeitos a verificações adicionais de segurança.
            </p>
          </div>

          {/* Últimos saques (com busca, filtros e paginação) */}
          <section className="space-y-4">
            <div className="flex items-center justify-between flex-wrap gap-3">
              <h2 className="text-2xl font-light text-white flex items-center gap-2">
                <History size={22} className="text-sky-400" />
                Últimos saques
              </h2>

              <div className="flex items-center gap-2">
                {/* Itens por página */}
                <select
                  value={perPage}
                  onChange={(e) => setPerPage(Number(e.target.value))}
                  className="rounded-lg border border-white/10 bg-white/[0.03] px-2 py-1.5 text-sm text-gray-200 focus:outline-none"
                  title="Itens por página"
                >
                  {[5, 10, 20, 50].map((n) => (
                    <option key={n} value={n}>{n}/página</option>
                  ))}
                </select>

                {/* Botão atualizar (mock / API) */}
                <button
                  onClick={() => window.location.reload()}
                  className="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/[0.03] px-3 py-1.5 text-sm text-gray-300 hover:bg-white/[0.06]"
                  title="Atualizar"
                >
                  <Loader2 size={16} className="animate-spin" />
                  Atualizar
                </button>
              </div>
            </div>

            {/* Barra de busca e filtros */}
            <div className="flex flex-col gap-3 rounded-2xl border border-white/10 bg-white/[0.02] p-3">
              <div className="flex items-center gap-2">
                <div className="relative flex-1">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
                  <input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Buscar por ID, chave PIX, tipo ou status…"
                    className="w-full rounded-lg border border-white/10 bg-[#0f0f0f] pl-9 pr-3 py-2 text-sm text-gray-200 placeholder-gray-500 focus:outline-none focus:border-emerald-500/40"
                  />
                </div>
                <div className="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-gray-300">
                  <Filter size={14} />
                  Filtros
                </div>
              </div>

              {/* Chips de filtros */}
              <div className="flex flex-wrap items-center gap-2">
                {/* Status */}
                <span className="text-xs text-gray-400 mr-1">Status:</span>
                <StatusChip value="all" label="Todos" />
                <StatusChip value="processing" label="Processing" />
                <StatusChip value="pending" label="Pending" />
                <StatusChip value="paid" label="Paid" />
                <StatusChip value="approved" label="Approved" />
                <StatusChip value="completed" label="Completed" />
                <StatusChip value="failed" label="Failed" />
                <StatusChip value="canceled" label="Canceled" />

                {/* separador */}
                <span className="mx-2 h-4 w-px bg-white/10" />

                {/* Tipo de chave */}
                <span className="text-xs text-gray-400 mr-1">Tipo de chave:</span>
                <KeyTypeChip value="all" label="Todas" />
                <KeyTypeChip value="aleatoria" label="Aleatória" />
                <KeyTypeChip value="cpf" label="CPF" />
                <KeyTypeChip value="cnpj" label="CNPJ" />
                <KeyTypeChip value="email" label="E-mail" />
                <KeyTypeChip value="telefone" label="Telefone" />
              </div>
            </div>

            {/* Tabela */}
            <div className="overflow-hidden rounded-2xl border border-white/10 shadow-[0_8px_30px_-12px_rgba(0,0,0,.6)]">
              <div className="max-h-[520px] overflow-auto">
                <table className="min-w-full text-sm">
                  {/* Cabeçalho fixo */}
                  <thead className="sticky top-0 z-10">
                    <tr className="bg-gradient-to-b from-white/[0.04] to-white/[0.02] text-[11px] uppercase tracking-wider text-gray-400">
                      <th className="px-4 py-3 text-left font-medium">Data</th>
                      <th className="px-4 py-3 text-right font-medium">Valor</th>
                      <th className="px-4 py-3 text-right font-medium">Taxa</th>
                      <th className="px-4 py-3 text-right font-medium">Líquido</th>
                      <th className="px-4 py-3 text-left font-medium">Status</th>
                      <th className="px-4 py-3 text-left font-medium">Tipo</th>
                      <th className="px-4 py-3 text-left font-medium">Chave PIX</th>
                      <th className="px-4 py-3 text-left font-medium">ID</th>
                    </tr>
                  </thead>

                  <tbody className="divide-y divide-white/5">
                    {pageRows.length === 0 && (
                      <tr>
                        <td colSpan={8} className="px-4 py-10 text-center text-gray-400">
                          Nenhum saque encontrado.
                        </td>
                      </tr>
                    )}

                    {pageRows.map((r, idx) => {
                      const d = new Date(r.created_at);
                      const when = isNaN(d.getTime())
                        ? "-"
                        : d.toLocaleString("pt-BR", {
                            day: "2-digit",
                            month: "2-digit",
                            year: "numeric",
                            hour: "2-digit",
                            minute: "2-digit",
                          });

                      return (
                        <tr
                          key={r.id}
                          className={[
                            "transition",
                            idx % 2 ? "bg-white/[0.015]" : "bg-transparent",
                            "hover:bg-white/[0.035]",
                          ].join(" ")}
                        >
                          <td className="px-4 py-3 text-gray-300">{when}</td>

                          <td className="px-4 py-3 text-right font-medium text-white">
                            {BRL(r.amount)}
                          </td>

                          <td className="px-4 py-3 text-right text-gray-300">{BRL(r.fee)}</td>

                          <td className="px-4 py-3 text-right font-semibold text-emerald-300">
                            {BRL(r.net)}
                          </td>

                          <td className="px-4 py-3">
                            <StatusBadge status={r.status} />
                          </td>

                          <td className="px-4 py-3">
                            <KeyTypeBadge type={(r.key_type || "-").toString().toUpperCase()} />
                          </td>

                          <td className="px-4 py-3">
                            <div className="flex items-center gap-2 max-w-[260px]">
                              <span
                                title={r.pix_key || "-"}
                                className="truncate text-xs font-mono text-gray-200"
                              >
                                {maskPixKey(r.pix_key)}
                              </span>
                              <button
                                onClick={() => copyToClipboard(r.pix_key)}
                                className="rounded-md border border-white/10 bg-white/5 px-1.5 py-0.5 text-[11px] text-gray-300 hover:bg-white/10"
                                title="Copiar chave"
                              >
                                Copiar
                              </button>
                            </div>
                          </td>

                          <td className="px-4 py-3 text-xs text-gray-400">{r.id}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>

              {/* Rodapé com paginação */}
              <div className="flex flex-col gap-2 bg-white/[0.02] px-4 py-2 md:flex-row md:items-center md:justify-between">
                <p className="text-xs text-gray-400">
                  Mostrando{" "}
                  <span className="text-gray-200">
                    {total === 0 ? 0 : sliceStart + 1} - {Math.min(sliceStart + perPage, total)}
                  </span>{" "}
                  de <span className="text-gray-200">{total}</span> saques
                </p>

                <div className="flex items-center gap-2">
                  <button
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                    disabled={pageSafe <= 1}
                    className="inline-flex items-center gap-1 rounded-lg border border-white/10 bg-white/[0.03] px-2 py-1.5 text-sm text-gray-300 hover:bg-white/[0.06] disabled:opacity-50"
                    title="Anterior"
                  >
                    <ChevronLeft size={16} />
                    Anterior
                  </button>

                  {/* índices simples (1 … N) */}
                  <div className="flex items-center gap-1">
                    {Array.from({ length: totalPages }).slice(0, 6).map((_, i) => {
                      const n = i + 1;
                      const active = n === pageSafe;
                      return (
                        <button
                          key={n}
                          onClick={() => setPage(n)}
                          className={[
                            "h-8 min-w-[2rem] rounded-md border text-sm",
                            active
                              ? "border-emerald-500/40 bg-emerald-500/10 text-emerald-300"
                              : "border-white/10 bg-white/[0.03] text-gray-300 hover:bg-white/[0.06]",
                          ].join(" ")}
                        >
                          {n}
                        </button>
                      );
                    })}
                    {totalPages > 6 && (
                      <span className="px-1 text-sm text-gray-400">… {totalPages}</span>
                    )}
                  </div>

                  <button
                    onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                    disabled={pageSafe >= totalPages}
                    className="inline-flex items-center gap-1 rounded-lg border border-white/10 bg-white/[0.03] px-2 py-1.5 text-sm text-gray-300 hover:bg-white/[0.06] disabled:opacity-50"
                    title="Próxima"
                  >
                    Próxima
                    <ChevronRight size={16} />
                  </button>
                </div>
              </div>
            </div>

            <p className="text-xs text-gray-500">
              Dica: “Processing” = transmitindo para o provedor PIX.
            </p>
          </section>
        </div>
      </div>

      {/* Modal */}
      <WithdrawModal
        open={openModal}
        onClose={() => setOpenModal(false)}
        onSubmit={handleSubmit}
        loading={submitting}
      />
    </AuthenticatedLayout>
  );
}
