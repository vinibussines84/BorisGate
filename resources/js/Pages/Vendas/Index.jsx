import React, { useState } from "react";
import { Head, Link } from "@inertiajs/react";

import {
    IoSettingsSharp,
    IoHomeSharp,
    IoCubeSharp,
    IoTrendingUpSharp,
    IoMenuSharp,
    IoNotifications,
    IoCashOutline,
    IoClose,
} from "react-icons/io5";

export default function Index() {

    // MOCK DE SAQUES
    const saquesMock = [
        {
            id: "WK-918273",
            e2e: "E2E-889912000111",
            valor: 34000.55,
            data: "03/11/2025 14:22",
            chave: "CPF: 072.342.343-33",
            status: "Efetivado",
        },
        {
            id: "WK-918274",
            e2e: "E2E-778812910001",
            valor: 7800.00,
            data: "01/11/2025 09:18",
            chave: "E-mail: ronakjuc2@gmail.com",
            status: "Efetivado",
        },
        {
            id: "WK-918275",
            e2e: "E2E-002191291181",
            valor: 13490.83,
            data: "28/10/2025 11:40",
            chave: "Celular: +55 11 98888-0000",
            status: "Efetivado",
        },
        {
            id: "WK-918276",
            e2e: "E2E-127891291551",
            valor: 53004.00,
            data: "23/10/2025 18:55",
            chave: "Aleatória: 9981-ABCD-9921",
            status: "Efetivado",
        },
        {
            id: "WK-918277",
            e2e: "E2E-991291291661",
            valor: 28003.00,
            data: "17/10/2025 15:12",
            chave: "CPF: 987.654.321-22",
            status: "Efetivado",
        },
        {
            id: "WK-918278",
            e2e: "E2E-771291299161",
            valor: 25000.00,
            data: "13/10/2025 08:45",
            chave: "Email: maisdisp23@gmail.com",
            status: "Efetivado",
        },
    ];

    const [abrirModal, setAbrirModal] = useState(false);
    const [tipoChave, setTipoChave] = useState("cpf");
    const [chavePix, setChavePix] = useState("");
    const [pin, setPin] = useState("");

    const enviarSolicitacao = () => {
        alert(`
Tipo de chave: ${tipoChave}
Chave PIX: ${chavePix}
PIN: ${pin}
        `);
        setAbrirModal(false);
    };

    return (
        <>
            <Head title="Saques" />

            <div className="min-h-screen bg-[#0f0f13] text-white flex flex-col">

                {/* HEADER */}
                <div className="flex items-center justify-between px-4 py-4 border-b border-[#1c1c22] bg-[#0f0f13]">

                    <IoMenuSharp size={26} className="text-white" />

                    {/* LOGO TREEAL */}
                    <img
                        src="/images/logotreeal.png"
                        alt="Treeal"
                        className="h-6 object-contain"
                    />

                    <IoNotifications size={24} className="text-white" />
                </div>

                {/* CONTEÚDO */}
                <div className="px-4 mt-4 flex-grow">

                    {/* Título + botão */}
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-2xl font-bold">Saques</h2>
                            <p className="text-gray-400 text-sm">Solicite e acompanhe seus saques</p>
                        </div>

                        <button
                            onClick={() => setAbrirModal(true)}
                            className="bg-green-700 hover:bg-green-800 transition px-4 py-2 rounded-lg flex items-center gap-2"
                        >
                            <IoCashOutline size={18} />
                            Sacar
                        </button>
                    </div>

                    {/* TABELA RESPONSIVA */}
                    <div className="mt-6">

                        {/* Cabeçalho — apenas DESKTOP */}
                        <div className="hidden md:grid grid-cols-5 text-[11px] text-gray-400 mb-2 px-2 uppercase tracking-wide">
                            <span>ID</span>
                            <span>E2E</span>
                            <span>Valor</span>
                            <span>Pago em</span>
                            <span>Chave PIX</span>
                        </div>

                        <div className="space-y-3">
                            {saquesMock.map((s) => (
                                <div
                                    key={s.id}
                                    className="bg-[#16161c] border border-[#24242c] rounded-xl p-4 md:p-3 md:grid md:grid-cols-5 md:gap-2 text-[12px] md:text-[11px]"
                                >

                                    {/* MOBILE VIEW — CARD */}
                                    <div className="md:hidden mb-2">
                                        <p className="text-lg font-bold text-green-500">
                                            R$ {s.valor.toFixed(2).replace(".", ",")}
                                        </p>
                                        <p className="text-gray-400 text-xs">{s.status}</p>
                                    </div>

                                    {/* ID */}
                                    <div className="truncate">
                                        <span className="md:hidden text-gray-400 text-xs">ID: </span>
                                        {s.id}
                                    </div>

                                    {/* E2E */}
                                    <div className="truncate">
                                        <span className="md:hidden text-gray-400 text-xs">E2E: </span>
                                        {s.e2e}
                                    </div>

                                    {/* VALOR */}
                                    <div className="text-green-500 font-semibold">
                                        <span className="md:hidden text-gray-400 text-xs">
                                            Valor:{" "}
                                        </span>
                                        R$ {s.valor.toFixed(2).replace(".", ",")}
                                    </div>

                                    {/* DATA */}
                                    <div className="truncate">
                                        <span className="md:hidden text-gray-400 text-xs">Pago em: </span>
                                        {s.data}
                                    </div>

                                    {/* CHAVE */}
                                    <div className="truncate">
                                        <span className="md:hidden text-gray-400 text-xs">Chave: </span>
                                        {s.chave}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                </div>

                {/* MENU INFERIOR */}
                <div className="w-full bg-[#0f0f13] border-t border-[#1c1c22] py-3 flex justify-around">
                    <Link href="/treeal" className="text-white">
                        <IoHomeSharp size={24} />
                    </Link>

                    <Link href="/produtos" className="text-white">
                        <IoCubeSharp size={24} />
                    </Link>

                    <Link href="/vendas" className="text-white">
                        <IoTrendingUpSharp size={24} />
                    </Link>

                    <Link href="/saques" className="text-green-600">
                        <IoCashOutline size={24} />
                    </Link>

                    <Link href="/findtreeal" className="text-white">
                        <IoSettingsSharp size={24} />
                    </Link>
                </div>
            </div>

            {/* MODAL */}
            {abrirModal && (
                <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm flex justify-center items-center z-50">
                    <div className="bg-[#16161c] border border-[#2a2a33] w-11/12 md:w-1/3 p-5 rounded-xl">

                        {/* Topo */}
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-xl font-bold">Solicitar saque</h2>
                            <IoClose
                                size={26}
                                className="cursor-pointer text-gray-300 hover:text-white"
                                onClick={() => setAbrirModal(false)}
                            />
                        </div>

                        {/* Tipo chave */}
                        <label className="text-gray-300 text-sm">Tipo de chave</label>
                        <select
                            value={tipoChave}
                            onChange={(e) => setTipoChave(e.target.value)}
                            className="w-full mt-1 p-2 rounded-lg bg-[#0f0f13] border border-[#24242c]"
                        >
                            <option value="cpf">CPF</option>
                            <option value="email">E-mail</option>
                            <option value="celular">Celular</option>
                            <option value="aleatoria">Aleatória</option>
                        </select>

                        {/* Chave */}
                        <label className="text-gray-300 mt-4 block text-sm">Chave PIX</label>
                        <input
                            type="text"
                            placeholder="Digite sua chave"
                            value={chavePix}
                            onChange={(e) => setChavePix(e.target.value)}
                            className="w-full mt-1 p-2 rounded-lg bg-[#0f0f13] border border-[#24242c]"
                        />

                        {/* PIN */}
                        <label className="text-gray-300 mt-4 block text-sm">PIN</label>
                        <input
                            type="password"
                            placeholder="Digite seu PIN"
                            value={pin}
                            onChange={(e) => setPin(e.target.value)}
                            className="w-full mt-1 p-2 rounded-lg bg-[#0f0f13] border border-[#24242c]"
                        />

                        {/* Botão */}
                        <button
                            onClick={enviarSolicitacao}
                            className="w-full mt-6 bg-green-700 hover:bg-green-800 py-2 rounded-lg font-bold"
                        >
                            Enviar solicitação
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}
