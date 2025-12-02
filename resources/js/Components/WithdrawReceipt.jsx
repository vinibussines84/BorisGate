import React from "react";
import { ReceiptText, ArrowLeft, Printer, X } from "lucide-react";

/* Util simples para BRL */
const BRL = (v) =>
  (Number(v) || 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  });

export default function WithdrawReceipt({
  data,
  onBack,
  showBack = true,
  orientation = "vertical",
  onClose, // botão fechar no mobile
}) {
  const isHorizontal = orientation === "horizontal";

  const toBRLMaybe = (v) =>
    typeof v === "number" ? BRL(v) : v ?? "—";

  const InfoRow = ({ label, value, mono = false }) => (
    <div className="bg-black/20 border border-white/10 rounded-xl p-3">
      <div className="text-[10px] uppercase tracking-wide text-gray-400 mb-1">
        {label}
      </div>
      <div className={`text-sm ${mono ? "font-mono" : "font-medium"} text-white break-words`}>
        {value || "—"}
      </div>
    </div>
  );

  return (
    <div
      className="
        relative
        w-full 
        max-w-[calc(100vw-2rem)]
        sm:max-w-2xl
        lg:max-w-4xl

        bg-gradient-to-br from-[#101214] to-[#0b0c0e] 
        border border-white/10 
        rounded-3xl 
        p-4 sm:p-6
        shadow-2xl shadow-black/40

        max-h-none 
        overflow-visible
        sm:max-h-[90vh] sm:overflow-y-auto

        scrollbar-thin 
        scrollbar-thumb-gray-700 
        scrollbar-track-transparent
      "
    >
      {/* BOTÃO FECHAR — MOBILE */}
      {onClose && (
        <button
          onClick={onClose}
          className="
            sm:hidden
            absolute top-3 right-3
            z-50
            p-2 
            rounded-full 
            bg-white/10 
            text-gray-200 
            border border-white/20 
            active:scale-95 
            transition
          "
        >
          <X size={18} />
        </button>
      )}

      {/* HEADER */}
      <div className="flex items-center gap-3 mb-6">
        <div className="p-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
          <ReceiptText size={20} />
        </div>

        <div className="flex-1">
          <h2 className="text-lg sm:text-xl font-semibold text-white">
            {data?.title || "Comprovante de Saque"}
          </h2>
          <p className="text-gray-400 text-xs sm:text-sm">Comprovante do pedido de saque</p>
        </div>

        {/* Botões desktop */}
        <div className="hidden sm:flex items-center gap-2 flex-shrink-0">
          {showBack && onBack && (
            <button
              type="button"
              onClick={onBack}
              className="inline-flex items-center justify-center gap-2 px-3 py-2 
              rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-xs transition"
            >
              <ArrowLeft size={16} />
              Voltar
            </button>
          )}

          <button
            type="button"
            onClick={() => window.print()}
            className="inline-flex items-center justify-center gap-2 px-3 py-2 
            rounded-xl border border-emerald-500/30 bg-emerald-500/15 
            hover:bg-emerald-500/25 text-emerald-300 text-xs font-medium transition"
          >
            <Printer size={16} />
            Imprimir
          </button>
        </div>
      </div>

      {/* GRID DE INFORMAÇÕES */}
      <div
        className={
          isHorizontal
            ? "grid grid-cols-1 md:grid-cols-2 gap-4 text-sm"
            : "grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm"
        }
      >
        <InfoRow label="Pagador" value={data?.payer || "PixionPay"} />
        <InfoRow label="Recebedor" value={data?.receiver || "—"} />
        <InfoRow label="Chave Pix" value={data?.pixKey || "—"} />
        <InfoRow label="Tipo de chave" value={data?.pixType || "—"} />
        <InfoRow label="Status" value={data?.status || "Pendente"} />
        <InfoRow label="Valor bruto" value={toBRLMaybe(data?.amountGross)} />
        <InfoRow label="Taxa" value={toBRLMaybe(data?.amountFee)} />
        <InfoRow label="Valor líquido" value={toBRLMaybe(data?.amountLiquid)} />
        <InfoRow label="Idempotency Key" value={data?.idempotency || "—"} mono />
      </div>

      {/* MENSAGEM FINAL */}
      <div className="mt-6 text-xs sm:text-sm text-gray-300 bg-white/5 border border-white/10 rounded-xl p-3">
        Dentro de <span className="text-white font-medium">10 minutos</span> o seu saque estará
        creditado em sua conta.
      </div>

      {/* BOTÕES MOBILE */}
      <div className="mt-6 sm:hidden flex flex-col gap-3">
        {showBack && onBack && (
          <button
            type="button"
            onClick={onBack}
            className="inline-flex items-center justify-center gap-2 px-4 py-2 
            rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-sm transition"
          >
            <ArrowLeft size={16} />
            Voltar
          </button>
        )}

        <button
          type="button"
          onClick={() => window.print()}
          className="inline-flex items-center justify-center gap-2 px-4 py-2 
          rounded-xl border border-emerald-500/30 bg-emerald-500/15 
          hover:bg-emerald-500/25 text-emerald-300 text-sm font-medium transition"
        >
          <Printer size={16} />
          Imprimir
        </button>

        {onClose && (
          <button
            onClick={onClose}
            className="inline-flex items-center justify-center px-4 py-2 
            rounded-xl border border-red-500/40 bg-red-500/10 
            text-red-400 text-sm hover:bg-red-500/20 transition"
          >
            Fechar
          </button>
        )}
      </div>
    </div>
  );
}
