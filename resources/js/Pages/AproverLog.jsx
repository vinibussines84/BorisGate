import React, { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Check, XCircle, Clock, Info, Filter, FileText, Wallet2 } from "lucide-react";

export default function AproverLog() {
  const { props } = usePage();
  const user = props.auth?.user;
  const pending = props.pending;
  const totalValue = props.total_value;
  const filters = props.filters;

  const [showJSON, setShowJSON] = useState(null);
  const [rejectId, setRejectId] = useState(null);
  const [rejectReason, setRejectReason] = useState("");

  if (!user || user.dashrash !== 1) {
    return (
      <AuthenticatedLayout>
        <div className="p-12 text-center text-neutral-200">
          <h1 className="text-5xl font-semibold mb-4 tracking-tight">403</h1>
          <p className="text-neutral-400 text-lg">Acesso negado.</p>
        </div>
      </AuthenticatedLayout>
    );
  }

  function submitFilters(e) {
    e.preventDefault();
    router.get("/aproverlog", filters, { preserveScroll: true });
  }

  function approve(id) {
    router.post(`/aproverlog/${id}/approve`, {}, { preserveScroll: true });
  }

  function reject(id) {
    router.post(`/aproverlog/${id}/reject`, { reason: rejectReason }, { preserveScroll: true });
    setRejectId(null);
    setRejectReason("");
  }

  // Função para formatar moeda
  const formatCurrency = (value) =>
    new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(value);

  return (
    <AuthenticatedLayout>
      <Head title="Aprovação Manual" />

      <div className="py-10 px-6 lg:px-10 text-neutral-200 min-h-screen space-y-10">

        {/* Header */}
        <div className="flex items-center gap-3">
          <div className="p-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 backdrop-blur-lg">
            <Clock className="text-emerald-400" size={22} />
          </div>
          <div>
            <h1 className="text-2xl font-light text-white tracking-tight">
              Webhooks Pendentes
            </h1>
            <p className="text-neutral-400 text-sm">
              Transações aguardando análise manual
            </p>
          </div>
        </div>

        {/* 💰 CARD TOTAL DE PENDENTES */}
        <div className="bg-gradient-to-br from-emerald-600/20 to-emerald-400/10 backdrop-blur-xl border border-emerald-400/20 rounded-2xl shadow-[0_0_35px_rgba(0,0,0,0.3)] p-6 flex items-center justify-between hover:border-emerald-400/30 transition">
          <div className="flex items-center gap-3">
            <div className="p-3 bg-emerald-500/30 border border-emerald-400/20 rounded-xl">
              <Wallet2 className="text-emerald-300" size={28} />
            </div>
            <div>
              <h2 className="text-lg font-medium text-white">Total de Pendentes</h2>
              <p className="text-sm text-neutral-400">
                {pending.total} transações aguardando análise
              </p>
            </div>
          </div>
          <div className="text-3xl font-semibold text-emerald-300 tracking-tight">
            {formatCurrency(totalValue)}
          </div>
        </div>

        {/* Filtros */}
        <form
          onSubmit={submitFilters}
          className="bg-white/[0.05] backdrop-blur-md border border-white/10 rounded-2xl p-6 shadow-[0_0_25px_rgba(0,0,0,0.2)]"
        >
          <div className="flex items-center gap-3 mb-5">
            <Filter size={18} className="text-neutral-300" />
            <h2 className="text-lg font-medium text-white">Filtros</h2>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-5 gap-4">
            {[
              { placeholder: "Valor mín.", type: "number", step: "0.01", field: "min" },
              { placeholder: "Valor máx.", type: "number", step: "0.01", field: "max" },
              { placeholder: "Data inicial", type: "date", field: "from" },
              { placeholder: "Data final", type: "date", field: "to" },
              { placeholder: "Buscar por TXID", type: "text", field: "txid" },
            ].map((input, idx) => (
              <input
                key={idx}
                type={input.type}
                step={input.step}
                placeholder={input.placeholder}
                defaultValue={filters[input.field]}
                onChange={(e) => (filters[input.field] = e.target.value)}
                className="px-3 py-2 rounded-xl bg-white/[0.03] border border-white/10 text-white text-sm placeholder-neutral-500 focus:ring-1 focus:ring-emerald-400/40 transition"
              />
            ))}
          </div>

          <div className="mt-5 flex justify-end">
            <button
              className="px-5 py-2 bg-emerald-500/20 hover:bg-emerald-500/40 text-emerald-300 rounded-xl border border-emerald-500/20 text-sm font-medium transition"
              type="submit"
            >
              Aplicar Filtros
            </button>
          </div>
        </form>

        {/* Lista */}
        <div className="rounded-2xl border border-white/10 bg-white/[0.04] backdrop-blur-xl shadow-[0_0_30px_rgba(0,0,0,0.3)] overflow-hidden">
          <div className="grid grid-cols-[80px_120px_180px_140px_100px_120px] px-6 py-4 text-sm text-neutral-400 border-b border-white/10 bg-white/[0.05]">
            <div>ID</div>
            <div>Valor</div>
            <div>Data</div>
            <div>Status</div>
            <div>Payload</div>
            <div className="text-right">Ações</div>
          </div>

          {pending.data.length === 0 && (
            <div className="text-center py-10 text-neutral-400 text-sm">
              Nenhuma transação pendente encontrada.
            </div>
          )}

          {pending.data.map((tx) => (
            <div
              key={tx.id}
              className="grid grid-cols-[80px_120px_180px_140px_100px_120px] px-6 py-5 border-b border-white/5 hover:bg-white/[0.06] transition-all duration-200"
            >
              <div className="text-neutral-200 font-medium">#{tx.id}</div>
              <div>{formatCurrency(tx.amount)}</div>
              <div className="text-neutral-300">{tx.formatted_date}</div>
              <div>
                <span className="text-yellow-400 text-sm font-medium">Em análise</span>
              </div>

              <button
                onClick={() => setShowJSON(tx.provider_payload)}
                className="text-blue-400 text-xs underline flex items-center gap-1 hover:text-blue-300 transition"
              >
                <FileText size={14} /> Ver
              </button>

              <div className="flex gap-2 justify-end">
                <button
                  onClick={() => approve(tx.id)}
                  className="w-8 h-8 flex items-center justify-center bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 rounded-lg hover:bg-emerald-500/30 transition"
                >
                  <Check size={14} />
                </button>

                <button
                  onClick={() => setRejectId(tx.id)}
                  className="w-8 h-8 flex items-center justify-center bg-red-500/20 text-red-300 border border-red-500/30 rounded-lg hover:bg-red-500/30 transition"
                >
                  <XCircle size={14} />
                </button>
              </div>
            </div>
          ))}
        </div>

        {/* Paginação */}
        <div className="mt-6 flex justify-center">
          {pending.links.map((l, i) => (
            <Link
              key={i}
              href={l.url || "#"}
              preserveScroll
              className={[
                "px-3 py-1.5 text-xs rounded-lg mx-1 border transition-all",
                l.active
                  ? "bg-emerald-500/20 border-emerald-500/30 text-emerald-300"
                  : "border-white/10 text-gray-400 hover:bg-white/[0.04]",
              ].join(" ")}
              dangerouslySetInnerHTML={{ __html: l.label }}
            />
          ))}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
