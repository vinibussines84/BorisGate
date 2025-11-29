import React from "react";
import { X } from "lucide-react";
import WithdrawReceipt from "@/Components/WithdrawReceipt";

export default function ReceiptModal({ open, data, onClose }) {
  if (!open || !data) return null;

  return (
    <div
      className="fixed inset-0 bg-black/70 backdrop-blur-sm z-[9999] flex items-center justify-center p-4"
      onClick={onClose}
    >
      <div
        onClick={(e) => e.stopPropagation()}
        className="max-w-xl w-full bg-[#0f1115]/90 rounded-2xl border border-white/10 shadow-xl p-6 relative"
      >
        <button
          onClick={onClose}
          className="absolute right-4 top-4 p-2 rounded-full bg-black/40 border border-white/10 text-gray-300 hover:bg-black/60"
        >
          <X size={16} />
        </button>

        <WithdrawReceipt
          data={data}
          orientation="horizontal"
          showBack={false}
        />
      </div>
    </div>
  );
}
