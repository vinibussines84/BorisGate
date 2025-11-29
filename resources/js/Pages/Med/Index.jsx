// resources/js/Pages/Med.jsx
import React, { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";
import {
  Eye,
  FileText,
  AlertTriangle,
  Clock,
  Info,
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
   Pagination (Inertia + Laravel)
========================== */
function Pagination({ meta }) {
  if (!meta || !Array.isArray(meta.links) || meta.links.length === 0) return null;

  const { links, from, to, total } = meta;

  return (
    <div className="mt-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <p className="text-xs text-zinc-500">
        {typeof from === "number" && typeof to === "number" && typeof total === "number"
          ? `Exibindo ${from}–${to} de ${total}`
          : null}
      </p>

      <nav className="flex items-center gap-1">
        {links.map((l, i) => {
          const isEllipsis = !l.url && l.label.includes("...");
          const isActive = l.active;

          if (isEllipsis) {
            return (
              <span
                key={`ell-${i}`}
                className="px-3 py-2 text-xs text-zinc-500 border border-white/10 rounded-lg"
              >
                …
              </span>
            );
          }

          if (!l.url) {
            return (
              <span
                key={`dis-${i}`}
                className="px-3 py-2 text-xs text-zinc-700 border border-white/10 rounded-lg cursor-not-allowed select-none"
                dangerouslySetInnerHTML={{ __html: l.label }}
              />
            );
          }

          return (
            <Link
              key={`lnk-${i}`}
              href={l.url}
              preserveScroll
              preserveState
              className={[
                "px-3 py-2 text-xs rounded-lg border transition-colors",
                isActive
                  ? "border-[#41FF85]/30 bg-[#41FF85]/10 text-[#41FF85]"
                  : "border-white/10 text-zinc-300 hover:bg-white/5",
              ].join(" ")}
              dangerouslySetInnerHTML={{ __html: l.label }}
            />
          );
        })}
      </nav>
    </div>
  );
}

/* ==========================
   Página principal
========================== */
export default function Med({ transactions, totalMed }) {
  const [motivoView, setMotivoView] = useState(null);

  const data = transactions?.data ?? [];

  const meta = {
    links: transactions?.links ?? transactions?.meta?.links ?? [],
    from: transactions?.from ?? transactions?.meta?.from,
    to: transactions?.to ?? transactions?.meta?.to,
    total: transactions?.total ?? transactions?.meta?.total,
  };

  return (
    <AuthenticatedLayout>
      <Head title="Em Mediação" />

      <div className="min-h-screen bg-[#0B0B0B] py-10 px-4 sm:px-6 lg:px-8 text-gray-100">
        <div className="max-w-6xl mx-auto space-y-10">

          {/* ==========================
              HEADER — MESMO DO WEBHOOKS
          =========================== */}
          <div className="bg-[#0f1115]/80 border border-white/10 rounded-3xl p-6 shadow-xl flex items-center gap-5 backdrop-blur">
            <div className="h-full w-1.5 rounded-full bg-[#41FF85]" />

            <div className="flex items-center gap-4">
              <div className="p-3 rounded-2xl border border-white/10 bg-black/40">
                <AlertTriangle className="w-6 h-6 text-[#41FF85]" />
              </div>

              <div>
                <h1 className="text-2xl font-bold text-white">
                  Transações em Mediação
                </h1>
                <p className="text-zinc-400 text-sm mt-1">
                  Acompanhe suas transações
                  <span className="text-zinc-300"> em análise ou disputa</span>.
                </p>
              </div>
            </div>
          </div>

          {/* Cards */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <Card title="Em Análise" value={totalMed} icon={Clock} color="text-[#41FF85]" />
            <Card title="Em Disputa" value="0" icon={AlertTriangle} color="text-rose-400" />
            <Card title="Finalizado" value="0" icon={FileText} color="text-emerald-400" />
            <Card title="Estornado" value="0" icon={Info} color="text-amber-400" />
          </div>

          {/* Tabela */}
          <div className="bg-[#0f1115]/80 backdrop-blur-sm rounded-3xl border border-white/10 shadow-lg p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-base font-semibold text-white">Transações</h3>
              <span className="text-xs text-gray-500">
                {data.length} resultados nesta página
              </span>
            </div>

            <div className="overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="text-left text-gray-400 border-b border-white/10">
                    <th className="py-2">ID</th>
                    <th className="py-2">Valor</th>
                    <th className="py-2">Taxa</th>
                    <th className="py-2">E2E</th>
                    <th className="py-2">Método</th>
                    <th className="py-2">Data</th>
                    <th className="py-2 text-right">Motivo</th>
                  </tr>
                </thead>
                <tbody>
                  {data.map((t) => (
                    <tr
                      key={t.id}
                      className="border-b border-white/5 hover:bg-white/5 transition"
                    >
                      <td className="py-3 text-gray-300 font-mono">#{t.id}</td>
                      <td className="py-3 text-white font-semibold">{BRL(t.amount)}</td>
                      <td className="py-3 text-gray-300">{BRL(t.fee)}</td>
                      <td className="py-3 text-gray-400 font-mono text-xs">
                        {t.e2e_id || "—"}
                      </td>
                      <td className="py-3 text-gray-300">{t.method || "—"}</td>
                      <td className="py-3 text-gray-300">
                        {fmtDateTime(t.created_at)}
                      </td>
                      <td className="py-3 text-right">
                        <button
                          onClick={() =>
                            setMotivoView(t.description || "Sem motivo informado")
                          }
                          className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-white/10 text-gray-300 text-xs hover:bg-white/10 transition"
                        >
                          <Eye size={13} /> Ver
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <Pagination meta={meta} />
          </div>

          {/* Modal */}
          {motivoView && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
              <div className="bg-[#0f1115] border border-white/10 rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-base font-semibold text-gray-200 flex items-center gap-2">
                    <Eye size={16} className="text-[#41FF85]" />
                    Motivo da Mediação
                  </h3>
                  <button
                    onClick={() => setMotivoView(null)}
                    className="text-gray-400 hover:text-white transition"
                  >
                    ✕
                  </button>
                </div>

                <div className="text-sm text-gray-300 leading-relaxed whitespace-pre-wrap border-y border-white/10 py-4 max-h-60 overflow-y-auto">
                  {motivoView}
                </div>

                <div className="flex justify-end mt-4">
                  <button
                    onClick={() => setMotivoView(null)}
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
