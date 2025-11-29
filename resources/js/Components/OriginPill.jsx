import React from "react";
import { Terminal, LayoutDashboard } from "lucide-react";

export default function OriginPill({ origin, label }) {
  const isApi =
    origin === "api" ||
    origin === "backend_api" ||
    origin === "remote" ||
    origin === "external";

  const Icon = isApi ? Terminal : LayoutDashboard;

  const cls = isApi
    ? "bg-indigo-500/10 text-indigo-400 border-indigo-500/20"
    : "bg-emerald-500/10 text-emerald-400 border-emerald-500/20";

  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] border rounded-lg font-medium ${cls}`}
    >
      <Icon size={12} />
      {label || (isApi ? "API" : "Painel")}
    </span>
  );
}
