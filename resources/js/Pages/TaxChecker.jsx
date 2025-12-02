import React, { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, router } from "@inertiajs/react";
import {
  Calculator,
  CheckCircle,
  ArrowDownCircle,
  Banknote,
  TrendingUp,
  PiggyBank,
} from "lucide-react";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from "recharts";

/* ==========================
   Helpers
========================== */
const BRL = (v) =>
  (Number(v) || 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  });

const fmtDateTime = (iso) => {
  if (!iso) return "—";
  const d = new Date(iso);
  if (isNaN(d.getTime())) return "—";
  return d.toLocaleString("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
  });
};

/* ==========================
   Página Principal
========================== */
export default function TaxChecker({
  transactions,
  stats,
  users,
  selected_user_id,
  date_range,
}) {
  const [selectedUser, setSelectedUser] = useState(selected_user_id || "");
  const [startDate, setStartDate] = useState(
    date_range?.start?.substring(0, 10) || ""
  );
  const [endDate, setEndDate] = useState(
    date_range?.end?.substring(0, 10) || ""
  );

  const data = transactions?.data ?? [];
  const links = transactions?.links ?? [];

  const handleFilterChange = () => {
    const params = {
      ...(selectedUser && { user_id: selectedUser }),
      ...(startDate && { start_date: startDate }),
      ...(endDate && { end_date: endDate }),
    };
    router.get(route("tax-checker.index"), params, {
      preserveScroll: true,
      preserveState: false,
    });
  };

  const handlePagination = (url) => {
    if (!url) return;
    router.get(url, {}, { preserveScroll: true });
  };

  // ==========================
  // Gráfico — Bruto × Líquido × Lucro
  // ==========================
  const chartData = [
    {
      name: "Valores (R$)",
      Bruto: stats?.total_bruto ?? 0,
      Liquidante: stats?.valor_liquido_liquidante ?? 0,
      Lucro: stats?.lucro ?? 0,
    },
  ];

  return (
    <AuthenticatedLayout>
      <Head title="Validador de Taxas" />

      <div className="min-h-screen bg-[#0B0B0B] py-10 px-4 sm:px-6 lg:px-8 text-gray-100">
        <div className="max-w-7xl mx-auto space-y-10">
          {/* HEADER */}
          <div className="bg-[#0b0b0b]/90 border border-white/10 rounded-3xl p-6 shadow-[0_0_40px_-10px_rgba(0,0,0,0.7)] flex items-center gap-5 backdrop-blur">
            <div className="h-full w-1.5 rounded-full bg-[#02fb5c]" />
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-2xl border border-white/10 bg-black/40">
                <Calculator className="w-6 h-6 text-[#02fb5c]" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-white">
                  Validador de Taxas
                </h1>
                <p className="text-zinc-400 text-sm mt-1">
                  Acompanhe transações, taxas da liquidante e lucro do período.
                </p>
              </div>
            </div>
          </div>

          {/* FILTROS */}
          <div className="bg-[#0b0b0b]/80 border border-white/10 rounded-3xl p-5 flex flex-col sm:flex-row sm:items-end gap-4 justify-between">
            <div className="flex flex-col gap-2">
              <label className="block text-xs text-zinc-400 mb-1">
                Filtrar por Usuário
              </label>
              <select
                value={selectedUser}
                onChange={(e) => setSelectedUser(e.target.value)}
                className="bg-black/40 border border-white/10 rounded-xl text-sm text-white px-4 py-2 focus:outline-none focus:border-[#02fb5c]/40"
              >
                <option value="">Todos os usuários</option>
                {users?.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.nome_completo || u.email}
                  </option>
                ))}
              </select>
            </div>

            <div className="flex items-end gap-3">
              <div>
                <label className="block text-xs text-zinc-400 mb-1">
                  Data inicial
                </label>
                <input
                  type="date"
                  value={startDate}
                  onChange={(e) => setStartDate(e.target.value)}
                  className="bg-black/40 border border-white/10 rounded-xl text-sm text-white px-3 py-2 focus:outline-none focus:border-[#02fb5c]/40"
                />
              </div>
              <div>
                <label className="block text-xs text-zinc-400 mb-1">
                  Data final
                </label>
                <input
                  type="date"
                  value={endDate}
                  onChange={(e) => setEndDate(e.target.value)}
                  className="bg-black/40 border border-white/10 rounded-xl text-sm text-white px-3 py-2 focus:outline-none focus:border-[#02fb5c]/40"
                />
              </div>

              <button
                onClick={handleFilterChange}
                className="px-4 py-2 rounded-xl bg-[#02fb5c]/10 border border-[#02fb5c]/20 text-[#02fb5c] text-sm font-semibold hover:bg-[#02fb5c]/20 transition"
              >
                Aplicar
              </button>
            </div>
          </div>

          {/* CARDS FINANCEIROS */}
          <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <Card
              title="Total Bruto de Entradas"
              value={BRL(stats?.total_bruto ?? 0)}
              color="text-blue-400"
              icon={Banknote}
            />
            <Card
              title="Líquido da Liquidante"
              value={BRL(stats?.valor_liquido_liquidante ?? 0)}
              color="text-[#02fb5c]"
              icon={TrendingUp}
            />
            <Card
              title="Lucro do Período"
              value={BRL(stats?.lucro ?? 0)}
              color="text-emerald-400"
              icon={PiggyBank}
            />
            <Card
              title="Pedidos Pagos"
              value={stats?.paid_orders_count ?? 0}
              color="text-amber-400"
              icon={CheckCircle}
            />
          </div>

          {/* GRÁFICO COMPARATIVO */}
          <div className="bg-[#0b0b0b]/80 border border-white/10 rounded-3xl p-6 backdrop-blur-sm">
            <h3 className="text-white text-base font-semibold mb-4">
              Comparativo Financeiro (Bruto x Líquido x Lucro)
            </h3>
            <div className="h-72">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={chartData} barSize={60}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#1f1f1f" />
                  <XAxis dataKey="name" stroke="#aaa" />
                  <YAxis stroke="#aaa" />
                  <Tooltip
                    cursor={{ fill: "#222" }}
                    formatter={(v) => BRL(v)}
                    labelStyle={{ color: "#02fb5c" }}
                  />
                  <Legend />
                  <Bar dataKey="Bruto" fill="#3b82f6" radius={[8, 8, 0, 0]} />
                  <Bar
                    dataKey="Liquidante"
                    fill="#02fb5c"
                    radius={[8, 8, 0, 0]}
                  />
                  <Bar dataKey="Lucro" fill="#10b981" radius={[8, 8, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>

          {/* SAQUES */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Card
              title="Saques Pagos"
              value={stats?.withdraw_count ?? 0}
              color="text-[#02fb5c]"
              icon={ArrowDownCircle}
            />
            <Card
              title="Valor Total Sacado"
              value={BRL(stats?.withdraw_total ?? 0)}
              color="text-emerald-400"
              icon={Banknote}
            />
            <Card
              title="Taxas de Saques (R$0,10 por pago)"
              value={BRL(stats?.taxa_liquidante_saques ?? 0)}
              color="text-gray-400"
              icon={Calculator}
            />
          </div>

          {/* TABELA */}
          <div className="bg-[#0b0b0b]/90 backdrop-blur-sm rounded-3xl border border-white/10 shadow-lg p-6">
            <h3 className="text-base font-semibold text-white mb-4">
              Transações ({transactions.total})
            </h3>
            <div className="overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="text-left text-gray-400 border-b border-white/10">
                    <th className="py-2">ID</th>
                    <th className="py-2">Usuário</th>
                    <th className="py-2">Valor Bruto</th>
                    <th className="py-2">Líquido Liquidante</th>
                    <th className="py-2">Líquido Cliente</th>
                    <th className="py-2">Lucro</th>
                    <th className="py-2">Status</th>
                    <th className="py-2">Data</th>
                  </tr>
                </thead>
                <tbody>
                  {data.length > 0 ? (
                    data.map((t) => (
                      <tr
                        key={t.id}
                        className="border-b border-white/5 hover:bg-white/5 transition"
                      >
                        <td className="py-3 text-gray-300 font-mono">#{t.id}</td>
                        <td className="py-3 text-gray-300">
                          {t.user?.nome_completo || t.user?.email || "—"}
                        </td>
                        <td className="py-3 text-white font-semibold">
                          {BRL(t.amount)}
                        </td>
                        <td className="py-3 text-[#02fb5c] font-semibold">
                          {BRL(t.expected_liquid)}
                        </td>
                        <td className="py-3 text-amber-400">
                          {BRL(t.expected_client)}
                        </td>
                        <td className="py-3 text-emerald-400 font-bold">
                          {BRL(t.expected_profit)}
                        </td>
                        <td className="py-3 text-gray-300">{t.status_label}</td>
                        <td className="py-3 text-gray-400 font-mono text-xs">
                          {fmtDateTime(t.created_at)}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan="8" className="text-center py-6 text-zinc-500">
                        Nenhuma transação encontrada para o período.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            {/* Paginação */}
            <div className="flex justify-center mt-6 gap-3">
              {links
                .filter((l) => l.url !== null)
                .map((link, i) => (
                  <button
                    key={i}
                    onClick={() => handlePagination(link.url)}
                    className={`px-3 py-1 rounded-lg text-sm ${
                      link.active
                        ? "bg-[#02fb5c]/20 text-[#02fb5c] border border-[#02fb5c]/30"
                        : "text-gray-400 border border-white/10 hover:bg-white/5"
                    }`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

/* =====================
   Subcomponente — Card
===================== */
function Card({ title, value, icon: Icon, color }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-[#121315]/70 backdrop-blur-sm p-5 flex items-center justify-between shadow">
      <div>
        <p className="text-xs text-gray-500">{title}</p>
        <p className="mt-1 text-xl font-bold text-white">{value}</p>
      </div>
      <div className="h-11 w-11 rounded-xl border border-white/10 bg-[#1a1a1c]/80 flex items-center justify-center">
        <Icon size={20} className={color} />
      </div>
    </div>
  );
}
