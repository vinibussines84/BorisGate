import React, { useEffect, useRef, useState } from "react";
import { Bell, Check, Trash2 } from "lucide-react";
import axios from "axios";

export default function NotificationBell() {
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const ref = useRef(null);

    /* =======================================================
       Carrega notificações (usado em vários pontos)
    ======================================================= */
    const loadNotifications = async () => {
        try {
            const res = await axios.get("/notifications");
            setNotifications(res.data.notifications || []);
        } catch (err) {
            console.error("Erro ao carregar notificações:", err);
        }
    };

    /* =======================================================
       Carrega automaticamente ao montar o componente
    ======================================================= */
    useEffect(() => {
        loadNotifications();

        // 🔁 Atualiza a cada 30 segundos (opcional)
        const interval = setInterval(loadNotifications, 30000);
        return () => clearInterval(interval);
    }, []);

    /* =======================================================
       Fecha o dropdown ao clicar fora
    ======================================================= */
    useEffect(() => {
        const handleClickOutside = (e) => {
            if (ref.current && !ref.current.contains(e.target)) {
                setOpen(false);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    /* =======================================================
       Abre o dropdown manualmente
    ======================================================= */
    const toggleOpen = () => setOpen((prev) => !prev);

    /* =======================================================
       UI
    ======================================================= */
    return (
        <div ref={ref} className="relative">
            {/* BOTÃO DO SINO */}
            <button
                onClick={toggleOpen}
                className="
                    relative size-11 rounded-full 
                    bg-neutral-900 border border-white/10 
                    text-neutral-300 hover:text-emerald-400
                    hover:border-emerald-500/40
                    flex items-center justify-center
                    shadow-[0_0_25px_-8px_rgba(0,255,180,0.25)]
                    transition-all duration-200
                "
            >
                <Bell className="h-5 w-5" />

                {/* BADGE (mostra assim que há notificações) */}
                {notifications.length > 0 && (
                    <span
                        className="
                            absolute top-[6px] right-[6px] h-2.5 w-2.5 rounded-full 
                            bg-emerald-400 shadow-[0_0_15px_4px_rgba(16,255,180,0.45)]
                            ring-2 ring-neutral-900 animate-pulse
                        "
                    />
                )}
            </button>

            {/* DROPDOWN */}
            {open && (
                <div
                    className="
                        absolute right-0 mt-3 w-80 z-50
                        rounded-2xl overflow-hidden
                        border border-white/10 
                        bg-[#0d0f12]/90 backdrop-blur-xl
                        shadow-[0_20px_60px_-10px_rgba(0,0,0,0.7)]
                        animate-fadeIn
                    "
                >
                    {/* HEADER */}
                    <div className="flex items-center justify-between px-4 py-3 border-b border-white/10">
                        <span className="text-sm font-semibold text-neutral-200">
                            Notificações
                        </span>

                        <button
                            onClick={() => setNotifications([])}
                            className="
                                text-xs text-neutral-400 hover:text-red-400 
                                flex items-center gap-1 transition
                            "
                        >
                            <Trash2 size={12} />
                            Limpar
                        </button>
                    </div>

                    {/* LISTA */}
                    <div className="max-h-72 overflow-y-auto divide-y divide-white/5">
                        {notifications.length === 0 ? (
                            <div className="p-4 text-center text-neutral-500 text-sm">
                                Nenhuma notificação.
                            </div>
                        ) : (
                            notifications.map((n) => (
                                <div
                                    key={n.id}
                                    className="
                                        px-4 py-3 text-sm 
                                        text-neutral-300 cursor-pointer
                                        hover:bg-white/5 transition
                                        flex flex-col gap-1
                                    "
                                >
                                    <div className="flex items-center justify-between">
                                        <span>{n.text}</span>
                                        <Check size={14} className="text-emerald-400" />
                                    </div>
                                    <span className="text-[11px] text-neutral-500">
                                        {n.time}
                                    </span>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
