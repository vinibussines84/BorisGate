import React, { useEffect, useState } from "react";
import { Wallet2, RefreshCcw } from "lucide-react";

export default function BalanceCard() {
    const [loading, setLoading] = useState(true);
    const [balance, setBalance] = useState(null);
    const [lastUpdate, setLastUpdate] = useState(null);

    const brl = (value) =>
        value.toLocaleString("pt-BR", {
            style: "currency",
            currency: "BRL",
        });

    async function fetchBalance() {
        try {
            const res = await fetch("/balance/available");
            const json = await res.json();

            if (json.success && json.data) {
                setBalance(json.data);
                setLastUpdate(new Date().toLocaleTimeString());
            }
        } catch (error) {
            console.error("Erro ao buscar saldo:", error);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        fetchBalance();
        const interval = setInterval(fetchBalance, 5000);
        return () => clearInterval(interval);
    }, []);

    return (
        <div className="w-full mb-6">
            <div
                className="
                rounded-3xl 
                p-6 
                bg-gradient-to-br 
                from-[#1a1a1f] 
                to-[#111113]
                shadow-[0_10px_40px_-15px_rgba(0,0,0,0.6)]
                backdrop-blur-xl
                transition
                hover:shadow-[0_12px_50px_-10px_rgba(0,0,0,0.7)]
                "
            >

                {/* TOP AREA */}
                <div className="flex items-center justify-between mb-5">
                    <div className="flex items-center gap-3">
                        
                        {/* Ícone Nubank minimalista */}
                        <div
                            className="
                            p-3 
                            rounded-2xl 
                            bg-[#7d2cff]/10 
                            border border-[#7d2cff]/20
                            shadow-[0_0_15px_#7d2cff22]
                            "
                        >
                            <Wallet2 size={22} className="text-[#b18aff]" />
                        </div>

                        <div>
                            <h2 className="text-white text-lg font-normal tracking-tight">
                                Saldo disponível
                            </h2>
                            <p className="text-neutral-500 text-[11px]">
                                Atualizado automaticamente
                            </p>
                        </div>
                    </div>

                    <RefreshCcw
                        size={18}
                        className={`text-neutral-400 transition ${
                            loading ? "animate-spin" : "opacity-50 hover:opacity-100"
                        }`}
                    />
                </div>

                {/* VALOR PRINCIPAL */}
                <div className="mt-2">
                    {loading ? (
                        <div className="h-8 w-32 bg-white/10 animate-pulse rounded-lg"></div>
                    ) : (
                        <p className="
                            text-4xl 
                            font-semibold 
                            text-white 
                            drop-shadow-[0_1px_4px_rgba(255,255,255,0.15)]
                        ">
                            {brl(balance.available_balance / 100)}
                        </p>
                    )}
                </div>

                {/* GRID NUBANK STYLE */}
                {!loading && (
                    <div className="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">

                        {/* Limite diário */}
                        <div
                            className="
                            p-4 
                            rounded-2xl 
                            bg-white/[0.02] 
                            border border-white/[0.04]
                            backdrop-blur-lg
                            transition
                            hover:bg-white/[0.04]
                            "
                        >
                            <p className="text-neutral-400 text-sm">
                                Limite diário restante
                            </p>
                            <p className="text-white text-lg font-medium mt-1">
                                {brl(balance.daily_limit_remaining / 100)}
                            </p>
                        </div>

                        {/* Mínimo de saque */}
                        <div
                            className="
                            p-4 
                            rounded-2xl 
                            bg-white/[0.02] 
                            border border-white/[0.04]
                            backdrop-blur-lg
                            transition
                            hover:bg-white/[0.04]
                            "
                        >
                            <p className="text-neutral-400 text-sm">
                                Mínimo para saque
                            </p>
                            <p className="text-white text-lg font-medium mt-1">
                                {brl(balance.minimum_withdrawal / 100)}
                            </p>
                        </div>
                    </div>
                )}

                {/* ULTIMA ATUALIZAÇÃO */}
                {!loading && (
                    <p className="text-neutral-600 text-[11px] mt-4 text-right">
                        Última atualização:{" "}
                        <span className="text-neutral-400">{lastUpdate}</span>
                    </p>
                )}
            </div>
        </div>
    );
}
