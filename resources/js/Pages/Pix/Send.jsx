import React, { useState } from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { ArrowLeft, KeyRound, Send as SendIcon } from "lucide-react";
import { PixKeyForm } from "@/Components/PixKeyForm";

export default function Send() {
    const [selectedOption, setSelectedOption] = useState("pix-key");
    const [pixKey, setPixKey] = useState("");
    const [loading, setLoading] = useState(false);
    const [pixData, setPixData] = useState(null);
    const [error, setError] = useState(null);

    const handlePixKeyCheck = async (e) => {
        e.preventDefault();
        if (!pixKey.trim()) return;

        setLoading(true);
        setError(null);
        setPixData(null);

        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content");

            const res = await fetch("/api/stric/pix/key-info", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken || "",
                },
                credentials: "include",
                body: JSON.stringify({ key: pixKey }),
            });

            const data = await res.json();

            if (!res.ok || !data?.success) {
                setError(data?.message || "Chave não encontrada.");
            } else {
                setPixData(data.key);
            }
        } catch (err) {
            setError("Erro de conexão com o servidor.");
        } finally {
            setLoading(false);
        }
    };

    // 👉 Quando clicar em "Prosseguir"
    const handleProceed = () => {
        if (!pixData) return; // garante que há dados válidos

        router.get(route("pix.amount"), {
            key: pixKey,
            name: pixData.name,
            doc: pixData.document,
            keyInfoId: pixData.id,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Enviar via Pix" />
            <div className="min-h-screen bg-[#0B0B0B] text-white">
                <div className="sticky top-0 flex items-center gap-4 p-4 border-b border-gray-800 bg-[#0B0B0B]/90">
                    <button
                        onClick={() => router.visit("/pix")}
                        className="p-2 text-gray-300 hover:text-white hover:bg-[#181818] rounded-full"
                    >
                        <ArrowLeft size={22} />
                    </button>

                    <KeyRound size={20} className="text-emerald-400" />
                    <h1 className="text-xl font-semibold">Enviar via Chave Pix</h1>
                </div>

                <PixKeyForm
                    pixKey={pixKey}
                    setPixKey={setPixKey}
                    loading={loading}
                    error={error}
                    pixData={pixData}
                    handlePixKeyCheck={handlePixKeyCheck}
                    onProceed={handleProceed} // 🔗 aqui está a conexão!
                />
            </div>
        </AuthenticatedLayout>
    );
}