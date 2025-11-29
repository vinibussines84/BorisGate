import React, { useState } from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { ShieldCheck, Loader2 } from "lucide-react";
import { QRCodeCanvas } from "qrcode.react";

export default function Setup2FA({ qr_url, secret, cpf_cnpj }) {
    const [code, setCode] = useState("");
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    // ✳️ Submeter o código digitado
    const handleSubmit = (e) => {
        e.preventDefault();
        if (!code.trim()) return setError("Informe o código de 6 dígitos.");
        setLoading(true);
        setError(null);

        router.post(
            route("setup.2fa.verify"),
            { code },
            {
                onError: (errors) => {
                    setError(errors.msg || "Código inválido. Tente novamente.");
                },
                onFinish: () => setLoading(false),
            }
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Ativar Segurança 2FA" />

            <div className="min-h-screen flex flex-col items-center justify-center bg-[#0B0B0B] text-white p-6">
                {/* 🔰 Cabeçalho */}
                <div className="flex items-center gap-2 mb-4">
                    <ShieldCheck size={26} className="text-pink-500" />
                    <h1 className="text-2xl font-bold">Ativar autenticação em 2 etapas</h1>
                </div>

                <p className="text-gray-400 mb-8 text-center max-w-md">
                    Escaneie o QR Code abaixo no aplicativo{" "}
                    <b>Google Authenticator</b> (ou similar) e insira o código de 6 dígitos
                    para confirmar.
                </p>

                {/* 🧩 QR Code + Secret */}
                <div className="bg-[#121212] border border-gray-800 rounded-2xl p-6 mb-8 text-center shadow-lg w-[280px]">
                    <div className="flex justify-center mb-4">
                        <QRCodeCanvas
                            value={qr_url}
                            size={220}
                            bgColor="#0B0B0B"
                            fgColor="#ffffff"
                            level="H"
                            includeMargin={true}
                        />
                    </div>

                    <p className="text-sm text-gray-400">
                        Chave manual:{" "}
                        <span className="text-pink-500 font-mono">{secret}</span>
                    </p>
                    {cpf_cnpj && (
                        <p className="text-gray-500 text-xs mt-2">
                            Conta vinculada: <b>{cpf_cnpj}</b>
                        </p>
                    )}
                </div>

                {/* 🔢 Campo de código com animação */}
                <form
                    onSubmit={handleSubmit}
                    className="flex flex-col items-center gap-4 w-full max-w-xs"
                >
                    <div className="relative flex gap-2 justify-center">
                        {Array.from({ length: 6 }).map((_, i) => (
                            <div
                                key={i}
                                className={`relative w-10 h-12 flex items-center justify-center text-xl font-mono rounded-lg border border-gray-700 transition-all duration-200 ${
                                    code[i]
                                        ? "bg-pink-600/10 border-pink-600 text-white animate-slide-up"
                                        : "bg-[#1A1A1A] text-gray-500"
                                }`}
                            >
                                {code[i] || ""}
                            </div>
                        ))}
                        <input
                            type="text"
                            inputMode="numeric"
                            maxLength={6}
                            autoFocus
                            value={code}
                            onChange={(e) =>
                                setCode(e.target.value.replace(/\D/g, "").slice(0, 6))
                            }
                            className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                        />
                    </div>

                    {error && (
                        <p className="text-red-400 text-sm font-medium">{error}</p>
                    )}

                    <button
                        type="submit"
                        disabled={loading}
                        className={`flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-pink-600 hover:bg-pink-700 
                                   transition font-semibold text-white w-full shadow-lg ${
                            loading ? "opacity-70 cursor-not-allowed" : ""
                        }`}
                    >
                        {loading ? (
                            <>
                                <Loader2 size={18} className="animate-spin" />
                                Verificando...
                            </>
                        ) : (
                            "Ativar segurança"
                        )}
                    </button>
                </form>

                {/* 🔒 Dica */}
                <p className="text-gray-500 text-xs mt-8">
                    Após a confirmação, sua conta estará protegida com autenticação em dois fatores.
                </p>
            </div>

            {/* ✨ Animação suave dos dígitos */}
            <style>
                {`
                @keyframes slideUp {
                    0% { opacity: 0; transform: translateY(8px); }
                    60% { opacity: 1; transform: translateY(-2px); }
                    100% { opacity: 1; transform: translateY(0); }
                }
                .animate-slide-up {
                    animation: slideUp 0.25s ease-out;
                }
                `}
            </style>
        </AuthenticatedLayout>
    );
}
