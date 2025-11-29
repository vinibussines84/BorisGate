import React from "react";
import { Clock, Loader2, CheckCircle2, XCircle } from "lucide-react";

export default function StatusPill({ status }) {
  const s = String(status || "").toLowerCase();

  const map = {
    pending: {
      cls: "bg-amber-500/10 text-amber-400 border-amber-500/20",
      icon: Loader2,
      label: "Pendente",
    },
    processing: {
      cls: "bg-sky-500/10 text-sky-400 border-sky-500/20",
      icon: Loader2,
      label: "Processando",
    },
    approved: {
      cls: "bg-emerald-500/10 text-emerald-400 border-emerald-500/20",
      icon: CheckCircle2,
      label: "Aprovado",
    },
    paid: {
      cls: "bg-emerald-500/10 text-emerald-400 border-emerald-500/20",
      icon: CheckCircle2,
      label: "Pago",
    },
    canceled: {
      cls: "bg-rose-500/10 text-rose-400 border-rose-500/20",
      icon: XCircle,
      label: "Cancelado",
    },
    failed: {
      cls: "bg-rose-500/10 text-rose-400 border-rose-500/20",
      icon: XCircle,
      label: "Falhou",
    },
  };

  const cfg =
    map[s] ||
    {
      cls: "bg-white/10 text-gray-300 border-white/20",
      icon: Clock,
      label: s || "—",
    };

  const Icon = cfg.icon;

  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2 py-0.5 border text-[11px] font-medium rounded-lg ${cfg.cls}`}
    >
      <Icon
        size={12}
        className={["pending", "processing"].includes(s) ? "animate-spin" : ""}
      />
      {cfg.label}
    </span>
  );
}
