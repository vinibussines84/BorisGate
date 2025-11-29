import React, { useState } from "react";
import { Head, Link } from "@inertiajs/react";

import {
    IoSettingsSharp,
    IoHomeSharp,
    IoCubeSharp,
    IoTrendingUpSharp,
    IoMenuSharp,
    IoNotifications,
    IoChevronDown,
    IoChevronUp,
    IoFilter
} from "react-icons/io5";

export default function Produtos() {
    const [openTaxas, setOpenTaxas] = useState(false);
    const [openAfiliados, setOpenAfiliados] = useState(false);
    const [openReceita, setOpenReceita] = useState(false);

    return (
        <>
            <Head title="Vendas - Treeal" />

            <div className="min-h-screen bg-[#0f0f13] text-white flex flex-col">

                {/* HEADER */}
                <div className="flex items-center justify-between px-4 py-4 bg-[#0f0f13] shadow-md">
                    <IoMenuSharp size={26} className="text-white" />

                    {/* LOGO TREEAL */}
                    <img
                        src="/images/logotreeal.png"
                        alt="Treeal"
                        className="h-6 object-contain"
                    />

                    <IoNotifications size={24} className="text-white" />
                </div>

                {/* CONTENT */}
                <div className="px-5 mt-4 flex-grow overflow-y-auto pb-20">

                    {/* Título */}
                    <div className="flex justify-between items-center">
                        <div>
                            <h2 className="text-2xl font-bold">Vendas</h2>
                            <p className="text-gray-400 text-sm">
                                Periodo filtrado: Mês atual
                            </p>
                        </div>

                        {/* Botão Filtros */}
                        <button className="bg-green-700 px-4 py-2 rounded-lg flex items-center gap-2">
                            <span>Filtros</span>
                            <IoFilter size={18} />
                        </button>
                    </div>

                    {/* CARDS */}
                    <div className="mt-6 space-y-4">
                        <div className="bg-[#16161c] rounded-xl p-4 border border-[#24242c]">
                            <span className="text-xl font-bold">R$ 374.594,28</span>
                            <p className="text-gray-400 text-sm mt-1">Vendas totais</p>
                        </div>

                        <div className="bg-[#16161c] rounded-xl p-4 border border-[#24242c]">
                            <span className="text-xl font-bold">R$ 365.193,72</span>
                            <p className="text-gray-400 text-sm mt-1">Minhas comissões</p>
                        </div>
                    </div>

                    {/* ACORDEÕES */}
                    <div className="mt-6">
                        <button
                            onClick={() => setOpenTaxas(!openTaxas)}
                            className="w-full flex justify-between items-center py-3 text-left"
                        >
                            <span className="flex items-center gap-2">
                                <span className="text-lg">%</span> Taxas de Conversão
                            </span>
                            {openTaxas ? <IoChevronUp /> : <IoChevronDown />}
                        </button>

                        {openTaxas && (
                            <div className="bg-[#16161c] p-4 rounded-lg border border-[#24242c] text-gray-300 text-sm">
                                Conversão média: 74%  
                                <br /> Visitantes: 3941  
                                <br /> Cliques: 532
                            </div>
                        )}
                    </div>

                    <div className="mt-4">
                        <button
                            onClick={() => setOpenAfiliados(!openAfiliados)}
                            className="w-full flex justify-between items-center py-3 text-left"
                        >
                            <span className="flex items-center gap-2">
                                <IoCubeSharp /> Informações de afiliados
                            </span>
                            {openAfiliados ? <IoChevronUp /> : <IoChevronDown />}
                        </button>

                        {openAfiliados && (
                            <div className="bg-[#16161c] p-4 rounded-lg border border-[#24242c] text-gray-300 text-sm">
                                Afiliados ativos: 0  
                                <br /> Cadastros pendentes: 0  
                                <br /> Taxa base: R$1.50 Fixa
                            </div>
                        )}
                    </div>

                    <div className="mt-4">
                        <button
                            onClick={() => setOpenReceita(!openReceita)}
                            className="w-full flex justify-between items-center py-3 text-left"
                        >
                            <span className="flex items-center gap-2">
                                <span className="text-xl">+</span> Receita adicional
                            </span>
                            {openReceita ? <IoChevronUp /> : <IoChevronDown />}
                        </button>

                        {openReceita && (
                            <div className="bg-[#16161c] p-4 rounded-lg border border-[#24242c] text-gray-300 text-sm">
                                Nenhuma receita adicional no período.
                            </div>
                        )}
                    </div>

                    {/* LISTA DE VENDAS */}
                    <div className="mt-8">
                        <h3 className="text-lg font-bold">Lista de vendas</h3>
                        <p className="text-gray-400 text-sm mb-3">Toque para ver os detalhes</p>

                        <div className="space-y-3">
                            <div className="bg-[#16161c] p-4 rounded-lg border border-[#24242c]">
                                <p className="text-white font-semibold">Venda #001</p>
                                <p className="text-gray-400 text-sm">Cliente: Marcela Ribeiro Silva</p>
                                <p className="text-green-500 text-sm mt-1">R$ 1.319,90</p>
                            </div>

                            <div className="bg-[#16161c] p-4 rounded-lg border border-[#24242c]">
                                <p className="text-white font-semibold">Venda #002</p>
                                <p className="text-gray-400 text-sm">Cliente: Maria Santos</p>
                                <p className="text-green-500 text-sm mt-1">R$ 89,90</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* MENU INFERIOR */}
                <div className="w-full fixed bottom-0 left-0 bg-[#0f0f13] border-t border-[#1c1c22] py-3 flex justify-around">

                    <Link href="/treeal" className="flex flex-col justify-center items-center text-white">
                        <IoHomeSharp size={24} />
                    </Link>

                    <Link href="/produtos" className="flex flex-col justify-center items-center text-green-600">
                        <IoCubeSharp size={24} />
                    </Link>

                    <Link href="/vendas" className="flex flex-col justify-center items-center text-white">
                        <IoTrendingUpSharp size={24} />
                    </Link>

                    {/* CONFIGURAÇÕES → FINDTREEAL */}
                    <Link href="/findtreeal" className="flex flex-col justify-center items-center text-white">
                        <IoSettingsSharp size={24} />
                    </Link>
                </div>
            </div>
        </>
    );
}
