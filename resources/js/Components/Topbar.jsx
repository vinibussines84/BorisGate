// resources/js/Components/Topbar.jsx
import { useEffect, useState, useRef, useMemo, useCallback } from "react";
import { Settings, LogOut, User, ChevronDown } from "lucide-react";
import { usePage, router } from "@inertiajs/react";
import UserActiveGlow from "./UserActiveGlow";

export default function Topbar() {
  const { props } = usePage();
  const user = props?.auth?.user ?? null;

  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef(null);
  const btnRef = useRef(null);

  /* ===== Utils ===== */
  const safeRouteLogout = useCallback(() => {
    try {
      if (typeof route === "function") {
        router.post(route("logout"));
      } else {
        router.post("/logout");
      }
    } catch {
      router.post("/logout");
    }
  }, []);

  const formatCpfCnpj = (value) => {
    if (!value) return "•••.•••.•••-••";
    const digits = String(value).replace(/\D/g, "");
    if (digits.length === 11) {
      // CPF
      return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
    }
    if (digits.length === 14) {
      // CNPJ
      return digits.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
    }
    return "•••.•••.•••-••";
  };

  const nomeCompleto = user?.nome_completo || user?.name || "Usuário";
  const primeiroNome = useMemo(
    () => (nomeCompleto || "Usuário").trim().split(/\s+/)[0] || "Usuário",
    [nomeCompleto]
  );

  // 👇 agora pega do cpf_cnpj
  const docId = useMemo(() => formatCpfCnpj(user?.cpf_cnpj), [user?.cpf_cnpj]);

  const iniciais = useMemo(() => {
    const parts = (nomeCompleto || "Usuário").trim().split(/\s+/).filter(Boolean);
    return (parts[0]?.[0] || "U").toUpperCase() + (parts[1]?.[0]?.toUpperCase() || "");
  }, [nomeCompleto]);

  /* ===== Close handlers ===== */
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (!menuOpen) return;
      if (menuRef.current && !menuRef.current.contains(e.target)) setMenuOpen(false);
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [menuOpen]);

  useEffect(() => {
    const onKey = (e) => e.key === "Escape" && setMenuOpen(false);
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, []);

  useEffect(() => {
    if (menuOpen) {
      menuRef.current?.querySelector('[role="menuitem"]')?.focus?.();
    } else {
      btnRef.current?.focus?.();
    }
  }, [menuOpen]);

  return (
    <header className="sticky top-0 z-40 w-full">
      {/* Linha superior */}
      <div className="pointer-events-none h-px w-full bg-gradient-to-r from-transparent via-white/10 to-transparent" />

      <div className="relative bg-[#0B0B0B]/90 backdrop-blur-xl border-b border-white/10">
        {/* Glows sutis */}
        <div className="pointer-events-none absolute -top-16 left-10 h-28 w-28 rounded-full bg-emerald-500/10 blur-2xl" />
        <div className="pointer-events-none absolute -bottom-16 right-10 h-24 w-24 rounded-full bg-pink-500/10 blur-2xl" />

        <div className="mx-auto flex h-[70px] items-center justify-between px-4 sm:px-6">
          {/* Esquerda */}
          <div className="min-w-0">
            <h1 className="truncate text-lg font-semibold text-gray-100 tracking-tight" />
            {/* “Bem-vindo de volta” (desktop) */}
            <p className="hidden md:block text-sm text-gray-500">
              Bem-vindo de volta,{" "}
              <span className="text-gray-200 font-medium">{primeiroNome}</span> 👋
            </p>
          </div>

          {/* Direita */}
          <div className="relative flex items-center gap-3" ref={menuRef}>
            {/* Nome + CPF/CNPJ (desktop) */}
            <div className="hidden flex-col items-end md:flex" title={nomeCompleto}>
              <span className="text-sm text-gray-400">Olá, {primeiroNome}!</span>
              <span className="text-sm font-semibold text-gray-200">{docId}</span>
            </div>

            {/* Avatar + Dropdown */}
            <div className="relative z-50">
              <button
                ref={btnRef}
                type="button"
                onClick={() => setMenuOpen((v) => !v)}
                className="flex items-center gap-2 rounded-xl px-1.5 py-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30"
                aria-haspopup="menu"
                aria-expanded={menuOpen}
                aria-controls="topbar-user-menu"
              >
                <UserActiveGlow size={40}>{iniciais || <User size={18} />}</UserActiveGlow>
                <ChevronDown
                  size={16}
                  className={`text-gray-400 transition-transform ${menuOpen ? "rotate-180" : ""}`}
                />
              </button>

              {menuOpen && (
                <div
                  id="topbar-user-menu"
                  role="menu"
                  aria-label="Menu do usuário"
                  className="absolute right-0 mt-3 w-56 overflow-hidden rounded-xl border border-white/10 bg-[#0F0F0F] shadow-[0_12px_40px_-12px_rgba(0,0,0,0.65)]"
                >
                  <div className="px-4 py-3">
                    <p className="truncate text-sm font-medium text-gray-100" title={nomeCompleto}>
                      {nomeCompleto}
                    </p>
                    <p className="text-xs text-gray-400">Documento: {docId}</p>
                  </div>

                  <div className="h-px w-full bg-white/10" />

                  <a
                    href="/configuracoes"
                    className="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-200 transition hover:bg-white/5 focus:bg-white/5"
                    role="menuitem"
                    tabIndex={0}
                  >
                    <Settings size={16} />
                    <span className="flex-1">Configurações</span>
                  </a>

                  <button
                    onClick={safeRouteLogout}
                    className="flex w-full items-center gap-2 px-4 py-2 text-sm text-rose-400 transition hover:bg-rose-500/10 focus:bg-rose-500/10"
                    role="menuitem"
                    tabIndex={0}
                  >
                    <LogOut size={16} />
                    <span className="flex-1">Sair</span>
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Linha inferior */}
      <div className="pointer-events-none h-px w-full bg-gradient-to-r from-transparent via-white/10 to-transparent" />
    </header>
  );
}
