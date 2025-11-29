import React from "react";
import { PlusCircle } from "lucide-react";
import { router } from "@inertiajs/react";

export default function FloatingWithdrawButton() {
  const handleClick = () => router.visit("/saques/solicitar");

  return (
    <button
      onClick={handleClick}
      className="fixed bottom-6 right-6 z-50 p-2.5 rounded-full 
               transition-all duration-300 transform hover:scale-110
               bg-emerald-600/90 text-white border border-emerald-400/50 
               shadow-lg shadow-emerald-900/50 
               hover:bg-emerald-500 hover:shadow-xl hover:shadow-emerald-500/50 
               focus:outline-none focus:ring-4 focus:ring-emerald-500/50"
      title="Solicitar Novo Saque"
    >
      <PlusCircle size={20} className="text-white" />
    </button>
  );
}
