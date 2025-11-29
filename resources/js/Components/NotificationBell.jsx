// resources/js/Components/NotificationBell.jsx
import React, { useEffect, useRef, useState, useCallback, useMemo } from "react";
import { Bell, Check, Trash2 } from "lucide-react";
import axios from "axios";

export default function NotificationBell() {
  const [open, setOpen] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(false);
  const ref = useRef(null);
  const controllerRef = useRef(null);

  /* =======================================================
     🔁 Carrega notificações (otimizado com AbortController)
  ======================================================= */
  const loadNotifications = useCallback(async () => {
    if (loading) return;
    setLoading(true);
    try {
      if (controllerRef.current) controllerRef.current.abort();
      controllerRef.current = new AbortController();

      const res = await axios.get("/notifications", {
        signal: controllerRef.current.signal,
      });

      if (res?.data?.notifications) {
        setNotifications(res.data.notifications);
      }
    } catch (err) {
      if (err.name !== "CanceledError" && err.name !== "AbortError") {
        console.warn("Error loading notifications:", err.message);
      }
    } finally {
      setLoading(false);
    }
  }, [loading]);

  /* =======================================================
     ⚙️ Carrega apenas quando o dropdown abrir
  ======================================================= */
  useEffect(() => {
    if (open) loadNotifications();
  }, [open, loadNotifications]);

  /* =======================================================
     🔁 Atualiza automaticamente a cada 60s (somente aberto)
  ======================================================= */
  useEffect(() => {
    if (!open) return;
    const interval = setInterval(() => loadNotifications(), 60000);
    return () => clearInterval(interval);
  }, [open, loadNotifications]);

  /* =======================================================
     ❌ Fecha o dropdown ao clicar fora
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
     🧠 Memoização de ícones e badge
  ======================================================= */
  const hasNotifications = useMemo(() => notifications.length > 0, [notifications.length]);

  /* =======================================================
     🧹 Limpar notificações
  ======================================================= */
  const clearNotifications = useCallback(() => setNotifications([]), []);

  /* =======================================================
     UI
  ======================================================= */
  return (
    <div ref={ref} className="relative">
      {/* Botão do Sino */}
      <button
        onClick={() => setOpen((v) => !v)}
        className="
          relative size-11 rounded-full 
          bg-neutral-900 border border-white/10 
          text-neutral-300 hover:text-emerald-400
          hover:border-emerald-500/40
          flex items-center justify-center
          shadow-[0_0_25px_-8px_rgba(0,255,180,0.25)]
          transition-all duration-200
        "
        aria-label="Open notifications"
      >
        <Bell className="h-5 w-5" />
        {hasNotifications && (
          <span
            className="
              absolute top-[6px] right-[6px] h-2.5 w-2.5 rounded-full 
              bg-emerald-400 shadow-[0_0_15px_4px_rgba(16,255,180,0.45)]
              ring-2 ring-neutral-900 animate-pulse
            "
          />
        )}
      </button>

      {/* Dropdown */}
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
          {/* Header */}
          <div className="flex items-center justify-between px-4 py-3 border-b border-white/10">
            <span className="text-sm font-semibold text-neutral-200">
              Notifications
            </span>

            <button
              onClick={clearNotifications}
              className="
                text-xs text-neutral-400 hover:text-red-400 
                flex items-center gap-1 transition
              "
            >
              <Trash2 size={12} />
              Clear
            </button>
          </div>

          {/* Lista */}
          <div className="max-h-72 overflow-y-auto divide-y divide-white/5">
            {loading ? (
              <div className="p-4 text-center text-neutral-500 text-sm">
                Loading notifications...
              </div>
            ) : notifications.length === 0 ? (
              <div className="p-4 text-center text-neutral-500 text-sm">
                No notifications.
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
