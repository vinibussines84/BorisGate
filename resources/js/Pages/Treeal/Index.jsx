import React, { useState } from "react";
import { Head, Link } from "@inertiajs/react";

import {
    IoSettingsSharp,
    IoHomeSharp,
    IoCubeSharp,
    IoTrendingUpSharp,
    IoEyeOutline,
    IoMenuSharp,
    IoNotifications,
    IoChevronDown
} from "react-icons/io5";

export default function Index() {
    const [openFilter, setOpenFilter] = useState(false);
    const [period, setPeriod] = useState("Última semana");

    // Valores mock por período
    const faturamentoMap = {
        "Última semana": "R$ 0,00",
        "Última quinzena": "R$ 143.904,72",
        "Último mês": "R$ 89.250,15",
        "Últimos 3 meses": "R$ 201.480,00",
        "Último ano": "R$ 374.594,28"
    };

    return (
        <>
            <Head title="Treeal" />

            <div className="min-h-screen bg-[#0f0f13] text-white flex flex-col relative">

                {/* HEADER */}
                <div className="flex items-center justify-between px-4 py-4 bg-[#0f0f13] shadow-md">

                    {/* Botão menu */}
                    <IoMenuSharp size={26} className="text-white" />

                    {/* LOGO TREEAL */}
                    <img
                        src="/images/logotreeal.png"
                        alt="Treeal"
                        className="h-6 object-contain"
                    />

                    {/* Notificações */}
                    <IoNotifications size={24} className="text-white" />
                </div>

                {/* CONTENT */}
                <div className="px-5 mt-4 flex-grow overflow-y-auto">

                    <h2 className="text-2xl font-bold">Dashboard</h2>
                    <p className="text-gray-400 text-sm">Total vendido</p>

                    {/* SELECT PERÍODO */}
                    <div className="mt-4 relative">
                        <button
                            onClick={() => setOpenFilter(!openFilter)}
                            className="w-full bg-green-700 text-white py-2 rounded-lg text-center flex justify-between items-center px-4"
                        >
                            {period}
                            <IoChevronDown size={18} />
                        </button>

                        {/* DROPDOWN */}
                        {openFilter && (
                            <div className="absolute mt-2 w-full bg-[#16161c] border border-[#24242c] rounded-lg shadow-lg z-20">

                                {[
                                    "Última semana",
                                    "Última quinzena",
                                    "Último mês",
                                    "Últimos 3 meses",
                                    "Último ano"
                                ].map((item) => (
                                    <button
                                        key={item}
                                        onClick={() => {
                                            setPeriod(item);
                                            setOpenFilter(false);
                                        }}
                                        className={`w-full text-left px-4 py-2 text-sm hover:bg-[#1f1f25] ${
                                            period === item
                                                ? "text-green-500"
                                                : "text-gray-300"
                                        }`}
                                    >
                                        {item}
                                    </button>
                                ))}

                            </div>
                        )}
                    </div>

                    {/* CARDS */}
                    <div className="grid grid-cols-2 gap-4 mt-6">

                        <div className="bg-[#16161c] rounded-xl p-4 border border-[#24242c] flex flex-col">
                            <span className="text-lg font-bold">R$ 29,43</span>
                            <div className="flex justify-between items-center mt-1">
                                <p className="text-gray-400 text-xs">Saldo disponível</p>
                                <IoEyeOutline className="text-gray-300" size={18} />
                            </div>
                        </div>

                        <div className="bg-[#16161c] rounded-xl p-4 border border-[#24242c] flex flex-col">
                            <span className="text-lg font-bold">R$ 0,00</span>
                            <div className="flex justify-between items-center mt-1">
                                <p className="text-gray-400 text-xs">Saldo pendente</p>
                                <IoEyeOutline className="text-gray-300" size={18} />
                            </div>
                        </div>

                    </div>

                    {/* FATURAMENTO */}
                    <div className="mt-10 text-center">
                        <h3 className="text-3xl font-bold">
                            {faturamentoMap[period]}
                        </h3>
                        <p className="text-gray-400 text-sm mt-1">
                            Faturamento no período
                        </p>
                    </div>
                </div>

                {/* MENU INFERIOR */}
                <div className="w-full bg-[#0f0f13] border-t border-[#1c1c22] py-3 flex justify-around mt-4">

                    <Link href="/dashboard" className="flex flex-col justify-center items-center text-green-600">
                        <IoHomeSharp size={24} />
                    </Link>

                    <Link href="/produtos" className="flex flex-col justify-center items-center text-white">
                        <IoCubeSharp size={24} />
                    </Link>

                    <Link href="/vendas" className="flex flex-col justify-center items-center text-white">
                        <IoTrendingUpSharp size={24} />
                    </Link>

                    <Link href="/findtreeal" className="flex flex-col justify-center items-center text-white">
                        <IoSettingsSharp size={24} />
                    </Link>

                </div>

            </div>
        </>
    );
}
