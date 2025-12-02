import React, { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router } from "@inertiajs/react";
import {
  Calculator,
  CheckCircle,
  ArrowDownCircle,
  Banknote,
  Eye,
} from "lucide-react";

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
export default function TaxChecker({ transactions, stats, users, selected_user_id, date_range }) {
  const [selectedUser, setSelectedUser] = useState(selected_user_id || "");
  const [reasonView, setReasonView] = useState(null);

  const handleUserChange = (e) => {
    const userId = e.target.value || "";
    setSelectedUser(userId);
    router.get(
      route("tax-checker.index"),
      userId ? { user_id: userId } : {},
      { preserveScroll: true, preserveState: false }
    );
  };

  const data = transactions?.data ?? [];
  const meta = transactions?.links ?? [];

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
                  Validador de Taxas — {new Date(date_range.start).toLocaleDateString("pt-BR")}
                </h1>
                <p className="text-zinc-400 text-sm mt-1">
                  Filtra as transações de hoje (00h–23h59) e calcula taxas, lucros e estatísticas.
                </p>
              </div>
            </div>
          </div>

          {/* FILTROS */}
          <div className="bg-[#0b0b0b]/80 border border-white/10 rounded-3xl p-5 flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
            <div>
              <label className="block text-xs text-zinc-400 mb-1">Filtrar por Usuário</label>
              <select
                value={selectedUser}
                onChange={handleUserChange}
                className="bg-black/40 border border-white/10 rounded-xl text-sm text-white px-4 py-2 focus:outline-none focus:border-[#02fb5c]/40"
              >
                <option value="">Todos os usuários</option>
                {users?.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name || u.email}
                  </option>
                ))}
              </select>
            </div>

            <div className="text-sm text-gray-400">
              <span className="font-semibold text-white">Período:</span>{" "}
              {new Date(date_range.start).toLocaleString("pt-BR", { dateStyle: "short" })}{" "}
              a{" "}
              {new Date(date_range.end).toLocaleString("pt-BR", { dateStyle: "short" })}
            </div>
          </div>

          {/* CARDS DE ESTATÍSTICAS */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Card
              title="Pedidos pagos"
              value={stats?.paid_orders_count ?? 0}
              color="text-[#02fb5c]"
              icon={CheckCircle}
            />
            <Card
              title="Saques solicitados"
              value={stats?.withdraw_count ?? 0}
              color="text-amber-400"
              icon={ArrowDownCircle}
            />
            <Card
              title="Valor total sacado"
              value={BRL(stats?.withdraw_total ?? 0)}
              color="text-emerald-400"
              icon={Banknote}
            />
          </div>

          {/* TABELA DE TRANSAÇÕES */}
          <div className="bg-[#0b0b0b]/90 backdrop-blur-sm rounded-3xl border border-white/10 shadow-lg p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-base font-semibold text-white">
                Transações do dia ({data.length})
              </h3>
            </div>

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
                          {t.user?.name || t.user?.email || "—"}
                        </td>
                        <td className="py-3 text-white font-semibold">{BRL(t.amount)}</td>
                        <td className="py-3 text-[#02fb5c] font-semibold">
                          {BRL(t.expected_liquid)}
                        </td>
                        <td className="py-3 text-amber-400">{BRL(t.expected_client)}</td>
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
                        Nenhuma transação encontrada para hoje.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {/* MODAL OPCIONAL */}
          {reasonView && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
              <div className="bg-[#0b0b0b] border border-white/10 rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-base font-semibold text-gray-200 flex items-center gap-2">
                    <Eye size={16} className="text-[#02fb5c]" />
                    Detalhes
                  </h3>
                  <button
                    onClick={() => setReasonView(null)}
                    className="text-gray-400 hover:text-white transition"
                  >
                    ✕
                  </button>
                </div>

                <div className="text-sm text-gray-300 leading-relaxed border-y border-white/10 py-4">
                  {reasonView}
                </div>

                <div className="flex justify-end mt-4">
                  <button
                    onClick={() => setReasonView(null)}
                    className="px-4 py-2 rounded-lg border border-white/10 bg-white/5 text-gray-300 hover:bg-white/10 transition text-sm"
                  >
                    Fechar
                  </button>
                </div>
              </div>
            </div>
          )}
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
