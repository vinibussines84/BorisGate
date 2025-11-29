import React from "react";
import { Head, Link } from "@inertiajs/react";

import {
    IoTrendingUpSharp,
    IoCubeSharp,
    IoCashOutline,
    IoSettingsSharp,
    IoPowerSharp,
    IoClose,
} from "react-icons/io5";

export default function FindTreeal() {
    return (
        <>
            <Head title="Menu - Treeal" />

            <div className="min-h-screen bg-[#0f0f13] text-white flex flex-col items-center px-6 py-6">

                {/* HEADER */}
                <div className="flex justify-between items-center w-full mb-6">
                    <img
                        src="/images/logotreeal.png"
                        className="h-10 object-contain"
                        alt="Treeal Logo"
                    />

                    <Link href="/treeal">
                        <IoClose size={30} className="text-green-600" />
                    </Link>
                </div>

                {/* USER INFO */}
                <div className="text-center mt-3">
                    <h2 className="text-xl font-bold">Fernando H Melo</h2>
                    <p className="text-gray-400 text-sm">
                        hildo.fernfinanceiro2@gmail.com
                    </p>
                </div>

                {/* MENU LIST */}
                <div className="w-full mt-10 space-y-5">

                    {/* Dashboard */}
                    <Link
                        href="/treeal"
                        className="flex items-center gap-3 text-lg font-medium py-2 border-b border-[#1c1c22]"
                    >
                        <IoTrendingUpSharp size={20} className="text-white" />
                        Dashboard
                    </Link>

                    {/* Relatório de vendas */}
                    <Link
                        href="/produtos"
                        className="flex items-center gap-3 text-lg font-medium py-2 border-b border-[#1c1c22]"
                    >
                        <IoCubeSharp size={20} className="text-white" />
                        Relatório de vendas
                    </Link>

                    {/* Saques */}
                    <Link
                        href="/vendas"
                        className="flex items-center gap-3 text-lg font-medium py-2 border-b border-[#1c1c22]"
                    >
                        <IoCashOutline size={20} className="text-white" />
                        Saques
                    </Link>

                    {/* Configurações */}
                    <Link
                        href="/findtreeal"
                        className="flex items-center gap-3 text-lg font-medium py-2 border-b border-[#1c1c22]"
                    >
                        <IoSettingsSharp size={20} className="text-white" />
                        Configurações
                    </Link>
                </div>

                {/* LOGOUT BUTTON */}
                <div className="w-full flex justify-center mt-12">
                    <button
                        className="bg-green-700 text-white w-full py-3 rounded-xl flex items-center justify-center gap-2 text-lg font-semibold"
                    >
                        <IoPowerSharp size={20} />
                        Sair dessa conta
                    </button>
                </div>

                {/* VERSION */}
                <p className="text-gray-500 text-xs mt-6">
                    Treeal v1.2.1
                </p>

            </div>
        </>
    );
}
