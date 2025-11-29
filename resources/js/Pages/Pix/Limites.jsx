import React, { useState, useEffect } from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { ArrowLeft, Gauge, Edit3, X, Zap, Info } from "lucide-react";

/* =======================================================
   Helpers
======================================================= */
const BRL = (v = 0) =>
  v.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const calcPerc = (used = 0, total = 0) => {
  if (total <= 0) return used > 0 ? 100 : 0;
  return Math.min(100, Number(((used / total) * 100).toFixed(1)));
};

/* =======================================================
   Modal: Solicitação de Alteração (placeholder)
======================================================= */
const RequestLimitModal = ({ isOpen, onClose }) => {
  const [shouldRender, setShouldRender] = useState(isOpen);
  useEffect(() => {
    if (isOpen) setShouldRender(true);
    else {
      const t = setTimeout(() => setShouldRender(false), 250);
      return () => clearTimeout(t);
    }
  }, [isOpen]);

  if (!shouldRender) return null;
  const overlay = isOpen ? "opacity-100" : "opacity-0";
  const card = isOpen ? "translate-y-0 opacity-100" : "translate-y-2 opacity-0";

  return (
    <div
      className={`fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md transition-opacity duration-300 ${overlay}`}
      onClick={onClose}
    >
      <div
        className={`relative w-full max-w-md mx-4 rounded-2xl border border-white/10 bg-[#111] p-7 shadow-2xl shadow-black/50 transition-all duration-300 ${card}`}
        onClick={(e) => e.stopPropagation()}
      >
        <button
          onClick={onClose}
          className="absolute right-3 top-3 rounded-full p-2 text-zinc-500 transition hover:bg-white/5 hover:text-white"
          aria-label="Fechar"
          title="Fechar"
        >
          <X size={18} />
        </button>

        <div className="flex flex-col items-center text-center gap-3 mt-3">
          <div className="rounded-full border border-amber-600/30 bg-amber-500/10 p-4 ring-4 ring-amber-900/10">
            <Zap size={30} className="text-amber-400" />
          </div>
          <h3 className="text-xl font-semibold">
            Em <span className="text-amber-400">desenvolvimento</span>
          </h3>
          <p className="text-sm text-zinc-400 leading-relaxed">
            O pedido de alteração de limites estará disponível em breve.
            Por enquanto, os limites permanecem padronizados em <strong>R$ 10.000,00</strong>.
          </p>
        </div>

        <button
          onClick={onClose}
          className="mt-6 w-full rounded-xl border border-white/10 bg-white/5 py-2.5 text-sm font-medium text-white transition hover:bg-white/10"
        >
          Entendido
        </button>
      </div>
    </div>
  );
};

/* =======================================================
   Card de Limite
======================================================= */
const LimitCard = ({ label, total, used }) => {
  const pct = calcPerc(used, total);
  const barColor =
    pct > 90 ? "bg-rose-500" : pct > 70 ? "bg-amber-400" : "bg-emerald-500";

  return (
    <div className="group rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.03] to-white/[0.01] p-5 shadow-xl shadow-black/30 transition hover:border-emerald-500/40">
      <div className="mb-4 flex items-start justify-between gap-4">
        <div className="min-w-0">
          <p className="text-[13px] font-medium tracking-wide text-zinc-400">{label}</p>
          <p className="mt-1 text-[15px] text-zinc-300">
            <span className="font-semibold text-white">R$ {BRL(used)}</span>{" "}
            <span className="text-xs text-zinc-500">usado</span>
          </p>
        </div>
        <div className="text-right">
          <p className="text-[11px] text-zinc-500">Total</p>
          <p className="text-lg font-light text-white">R$ {BRL(total)}</p>
        </div>
      </div>

      <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-white/5">
        <div
          className={`h-2 rounded-full transition-all duration-700 ${barColor}`}
          style={{ width: `${pct}%` }}
        />
      </div>
      <div className="mt-2 flex items-center justify-between text-xs text-zinc-500">
        <span>{pct}% utilizado</span>
        <span>Disponível: R$ {BRL(Math.max(total - used, 0))}</span>
      </div>
    </div>
  );
};

/* =======================================================
   Página: Limites PIX (padronizado R$ 10.000,00)
======================================================= */
export default function Limites() {
  const [isModalOpen, setIsModalOpen] = useState(false);

  // ✅ Limite padrão solicitado: R$ 10.000,00 em todos os períodos
  const PADRAO = 10000.0;
  const limites = {
    diario: { total: PADRAO, used: 0.00, label: "Limite Diário (24h)" },
    semanal: { total: PADRAO, used: 0.00, label: "Limite Semanal" },
    mensal: { total: PADRAO, used: 1000.00, label: "Limite Mensal (Testes)" },
  };

  return (
    <AuthenticatedLayout>
      <Head title="Limites PIX" />
      <div className="min-h-screen bg-[#0A0A0A] py-10 px-4 text-gray-200 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-3xl space-y-8">
          {/* Voltar */}
          <a
            href="/pix"
            className="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-zinc-400 transition hover:text-white"
          >
            <ArrowLeft size={16} /> Voltar para Pix
          </a>

          {/* Header */}
          <header className="flex items-center justify-between gap-4 border-b border-white/5 pb-5">
            <div className="flex items-center gap-4">
              <div className="rounded-xl border border-emerald-600/30 bg-emerald-500/10 p-3">
                <Gauge size={22} className="text-emerald-400" />
              </div>
              <div>
                <h1 className="text-3xl font-light tracking-tight text-white">
                  Gestão de <span className="font-medium text-emerald-400">Limites PIX</span>
                </h1>
                <p className="mt-1 text-sm font-light text-zinc-400">
                  Limites padronizados em <strong>R$ 10.000,00</strong> por período.
                </p>
              </div>
            </div>
            <button
              onClick={() => setIsModalOpen(true)}
              className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-900/30 transition hover:bg-emerald-500"
            >
              <Edit3 size={16} /> Solicitar alteração
            </button>
          </header>

          {/* Aviso de política */}
          <div className="flex items-start gap-3 rounded-xl border border-white/10 bg-white/[0.03] p-4">
            <div className="rounded-md border border-sky-600/30 bg-sky-500/10 p-1.5">
              <Info size={16} className="text-sky-400" />
            </div>
            <p className="text-sm text-zinc-300">
              Para segurança, as transações PIX seguem limites padrão. Solicitações de aumento
              passam por validação e podem exigir etapas de verificação de identidade.
            </p>
          </div>

          {/* Cards */}
          <section className="space-y-4">
            <h2 className="pl-3 text-lg font-medium text-white">
              Resumo de uso
            </h2>

            <LimitCard label={limites.diario.label} total={limites.diario.total} used={limites.diario.used} />
            <LimitCard label={limites.semanal.label} total={limites.semanal.total} used={limites.semanal.used} />
            <LimitCard label={limites.mensal.label} total={limites.mensal.total} used={limites.mensal.used} />
          </section>
        </div>
      </div>

      {/* Modal */}
      <RequestLimitModal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} />
    </AuthenticatedLayout>
  );
}
