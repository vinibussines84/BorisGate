import React, { useRef, useEffect, useState } from "react";
import {
  X,
  CreditCard,
  Calendar,
  Clock,
  ArrowUpRight,
  ArrowDownRight,
  Zap,
  Tag,
  Hash,
  Info,
} from "lucide-react";

/* ------------------------------------------------------------------------------------
   Campo padrão (coeso com o novo design)
------------------------------------------------------------------------------------- */
function Field({ label, value, icon: Icon }) {
  const displayValue = value ?? "—";
  return (
    <div className="flex flex-col gap-1.5 min-w-0">
      <div className="flex items-center gap-2 text-[11px] uppercase tracking-wide text-gray-500">
        {Icon && <Icon size={12} className="text-gray-500" />}
        <span>{label}</span>
      </div>
      <span className="text-sm text-gray-100 break-words font-medium">
        {displayValue}
      </span>
    </div>
  );
}

/* ====================================================================================
   BOTTOM SHEET / SWAPPER BONITO
==================================================================================== */
export default function TransactionDetailModal({
  transaction,
  details,
  isLoading,
  isOpen,
  onClose,
  formatCurrency,
}) {
  if (!isOpen || !transaction) return null;

  const tx = details || transaction;

  const sheetRef = useRef(null);
  const [translateY, setTranslateY] = useState(0);
  const startY = useRef(0);
  const isDragging = useRef(false);

  /* -------------------------------------------------------------------------------
     TOUCH HANDLERS — SWIPE PARA BAIXO
  ------------------------------------------------------------------------------- */
  const handleTouchStart = (e) => {
    isDragging.current = true;
    startY.current = e.touches[0].clientY;
  };

  const handleTouchMove = (e) => {
    if (!isDragging.current) return;
    const currentY = e.touches[0].clientY;
    const delta = currentY - startY.current;
    const el = sheetRef.current;
    if (el && el.scrollTop > 0 && delta > 0) return;
    if (delta > 0) setTranslateY(delta * 0.55);
  };

  const handleTouchEnd = () => {
    isDragging.current = false;
    if (translateY > 120) {
      setTranslateY(600);
      setTimeout(onClose, 180);
    } else {
      setTranslateY(0);
    }
  };

  useEffect(() => {
    if (isOpen) setTranslateY(0);
  }, [isOpen]);

  /* --------------------------------------------------------------------------------
     UTILS
  ---------------------------------------------------------------------------------- */
  const formatDate = (iso) => {
    if (!iso) return "—";
    const d = new Date(iso);
    return d.toLocaleString("pt-BR", {
      dateStyle: "short",
      timeStyle: "short",
    });
  };

  const fmt = (v) => (formatCurrency ? formatCurrency(v) : v ?? 0);

  const isCredit = tx.credit;
  const AmountIcon = isCredit ? ArrowUpRight : ArrowDownRight;

  // ✅ Normalização de status leve (inclui UNDER_REVIEW → PENDENTE)
  const rawStatus = String(tx.visualStatus || tx.status || "").toLowerCase();
  const statusDisplay = ["under_review", "pending", "processing"].includes(rawStatus)
    ? "PENDENTE"
    : ["paid", "approved", "completed"].includes(rawStatus)
    ? "EFETIVADO"
    : ["failed", "error", "denied", "cancelled", "canceled"].includes(rawStatus)
    ? "FALHADO"
    : rawStatus.toUpperCase();

  const statusChip = {
    EFETIVADO:
      "bg-emerald-500/20 text-emerald-200 border-emerald-500/40 shadow-[0_0_20px_5px_rgba(16,255,180,0.05)]",
    PENDENTE:
      "bg-amber-500/20 text-amber-200 border-amber-500/40 shadow-[0_0_20px_5px_rgba(255,200,20,0.05)]",
    PROCESSANDO:
      "bg-amber-500/20 text-amber-200 border-amber-500/40 shadow-[0_0_20px_5px_rgba(255,200,20,0.05)]",
    FALHADO:
      "bg-red-500/20 text-red-200 border-red-500/40 shadow-[0_0_20px_5px_rgba(255,30,60,0.05)]",
    CANCELADO:
      "bg-red-500/20 text-red-200 border-red-500/40 shadow-[0_0_20px_5px_rgba(255,30,60,0.05)]",
  }[statusDisplay] || "bg-gray-700/40 text-gray-200 border-gray-600/40";

  /* ====================================================================================
       UI FINAL — FEITO PARA FICAR MUITO BONITO
  ==================================================================================== */

  return (
    <div
      className="fixed inset-0 z-[999] bg-black/70 backdrop-blur-md flex items-end sm:items-center justify-center transition-opacity"
      onClick={onClose}
    >
      {/* BOTTOM SHEET */}
      <div
        ref={sheetRef}
        onClick={(e) => e.stopPropagation()}
        onTouchStart={handleTouchStart}
        onTouchMove={handleTouchMove}
        onTouchEnd={handleTouchEnd}
        className="
          relative w-full max-w-xl sm:max-w-3xl
          bg-[#0d0f12]/95 border border-white/10 backdrop-blur-xl
          rounded-t-3xl sm:rounded-3xl
          shadow-[0_30px_80px_-10px_rgba(0,0,0,0.9)]
          px-5 sm:px-8 pt-5 sm:pt-7 pb-8
          overflow-y-auto max-h-[88vh]
          transition-transform duration-300 ease-out
        "
        style={{
          transform: `translateY(${translateY}px)`,
          transition: isDragging.current ? "none" : "transform 0.28s ease",
        }}
      >
        {/* GLASSY GLOW */}
        <div className="pointer-events-none absolute inset-0 opacity-35">
          <div
            className={`absolute -top-40 right-0 w-80 h-80 ${
              isCredit ? "bg-emerald-500/20" : "bg-red-500/20"
            } blur-[140px]`}
          />
        </div>

        {/* HANDLE */}
        <div className="flex justify-center mb-4 sm:hidden">
          <div className="w-12 h-1.5 bg-gray-500/50 rounded-full" />
        </div>

        {/* HEADER */}
        <div className="relative flex justify-between items-start mb-6">
          <div>
            <h2 className="text-2xl font-bold text-white">Comprovante</h2>
            <p className="text-xs text-gray-400 mt-0.5 flex items-center gap-1">
              <Hash size={14} className="text-gray-500" /> #{tx.id}
            </p>
          </div>

          <button
            onClick={onClose}
            className="p-2 rounded-full bg-white/5 border border-white/10 text-gray-300 hover:bg-white/10 hover:text-white transition"
          >
            <X size={18} />
          </button>
        </div>

        {/* LOADING */}
        {isLoading ? (
          <div className="py-16 flex flex-col items-center gap-3 text-gray-400">
            <div className="w-7 h-7 border-[3px] border-sky-500 border-t-transparent rounded-full animate-spin" />
            Carregando...
          </div>
        ) : (
          <>
            {/* VALOR */}
            <div className="p-5 rounded-2xl border border-white/10 bg-white/[0.01] backdrop-blur-xl mb-7">
              <p
                className={`text-[12px] uppercase tracking-wide mb-1 flex items-center gap-2 ${
                  isCredit ? "text-emerald-300" : "text-red-300"
                }`}
              >
                <AmountIcon size={14} />
                {isCredit ? "Entrada" : "Saída"}
              </p>

              <p className="text-4xl font-extrabold text-white tabular-nums">
                {fmt(tx.amount)}
              </p>
            </div>

            {/* STATUS */}
            <div
              className={`inline-flex items-center px-3 py-1.5 rounded-full border text-xs font-semibold mb-6 ${statusChip}`}
            >
              {statusDisplay}
            </div>

            {/* GRID CAMPOS */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <Field label="Criado em" value={formatDate(tx.createdAt)} icon={Calendar} />
              <Field label="Pago em" value={formatDate(tx.paidAt)} icon={Clock} />
              <Field label="Tipo" value="Fixo — Venda no Pix" icon={Tag} />
              <Field
                label="Direção"
                value={isCredit ? "Crédito" : "Débito"}
                icon={isCredit ? ArrowUpRight : ArrowDownRight}
              />
            </div>

            {/* REFERÊNCIAS */}
            <div className="border-t border-white/10 pt-6 mt-8 space-y-4">
              <h3 className="text-sm font-semibold text-gray-300 mb-2">
                Referências
              </h3>
              {tx.external_id && (
                <Field label="External ID" value={tx.external_id} icon={Hash} />
              )}
              {tx.e2eToShow && (
                <Field label="E2E" value={tx.e2eToShow} icon={Hash} />
              )}
              {tx.txid && <Field label="TXID" value={tx.txid} icon={Hash} />}
              <Field label="Descrição" value={tx.description} icon={Info} />
            </div>
          </>
        )}
      </div>
    </div>
  );
}
