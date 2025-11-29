// resources/js/Components/Balance.jsx
import React from "react";
import { RefreshCw } from "lucide-react";

export default function Balance({ amount = 0 }) {
    return (
        <div className="mt-6 p-6 bg-black text-white rounded-xl shadow-md">
            <div className="flex items-center justify-between">
                <p className="text-sm text-gray-400">Saldo disponível</p>
                <RefreshCw className="w-5 h-5 text-green-500" />
            </div>
            <div className="mt-2 flex items-baseline gap-1">
                <span className="text-lg font-medium">R$</span>
                <span className="text-4xl font-bold tracking-tight">
                    {amount.toLocaleString("pt-BR", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    })}
                </span>
            </div>
        </div>
    );
}
