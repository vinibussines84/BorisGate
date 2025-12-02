import { Link, usePage, router } from "@inertiajs/react";
import React, { useEffect, useMemo, useRef, useState } from "react";
import {
  Home,
  FileText,
  CreditCard,
  Wallet,
  Activity,
  Code,
  LogOut,
  Menu,
  X,
} from "lucide-react";
import NotificationBell from "@/Components/NotificationBell";

const normalizePath = (p) => (p || "/").replace(/\/+$/, "").toLowerCase();

/* ---------- Logo ---------- */
function BrandLogo({ className = "block h-[28px] w-auto md:h-[32px]" }) {
  return (
    <img
      src="/images/equitpay.png"
      alt="Logo"
      className={`${className} select-none pointer-events-none opacity-90 hover:opacity-100 transition`}
      loading="eager"
      decoding="async"
    />
  );
}

/* ---------- Avatar Menu (100% FIXED) ---------- */
function UserFavicon({ initials, closeMobile }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);

  const handleLogout = () => {
    setOpen(false);
    closeMobile?.(); // close mobile menu if open

    // 🚀 SAFE LOGOUT (NO ZIGGY, NO route(), NO ERRORS)
    router.post("/logout", {}, {
      onFinish: () => {
        document.body.style.overflow = "auto";
      }
    });
  };

  useEffect(() => {
    const handleClick = (e) => {
      if (ref.current && !ref.current.contains(e.target)) {
        setOpen(false);
      }
    };

    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, []);

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((v) => !v)}
        className="grid size-9 place-items-center rounded-full bg-neutral-900 
        text-[13px] font-medium text-neutral-200 ring-1 ring-neutral-700 
        hover:ring-emerald-500 transition"
      >
        {initials}
      </button>

      {open && (
        <div className="absolute right-0 mt-2 w-40 rounded-xl border 
        border-neutral-800/70 bg-neutral-950 shadow-xl z-50">
          <button
            onClick={handleLogout}
            className="flex items-center gap-2 w-full px-4 py-2 text-sm text-neutral-300 
            hover:bg-neutral-900/80 transition-colors"
          >
            <LogOut className="h-4 w-4 text-neutral-400" /> Logout
          </button>
        </div>
      )}
    </div>
  );
}

/* ---------- Main Layout ---------- */
export default function AuthenticatedLayout({ header, children, boxed = false }) {
  const page = usePage();
  const user = page?.props?.auth?.user;
  const currentPath = normalizePath(
    typeof window !== "undefined" ? window.location.pathname : page?.url || "/"
  );

  const [mobileOpen, setMobileOpen] = useState(false);
  const closeMobileMenu = () => setMobileOpen(false);

  const initials = useMemo(() => {
    const n = (user?.name || "").trim();
    return n
      ? n.split(/\s+/).slice(0, 2).map((s) => s[0]?.toUpperCase()).join("")
      : "U";
  }, [user?.name]);

  const nav = useMemo(
    () => [
      { key: "dashboard", label: "Dashboard", href: "/dashboard", icon: Home },
      { key: "extrato", label: "Statement", href: "/extrato", icon: FileText },
      { key: "cobranca", label: "Billing", href: "/cobranca", icon: CreditCard },
      { key: "saque", label: "Withdraw", href: "/saques", icon: Wallet },
      { key: "med", label: "Mediation", href: "/med", icon: Activity },
      {
        key: "integration",
        label: "Integration",
        icon: Code,
        isDropdown: true,
        children: [
          { key: "api", label: "Tokens API", href: "/api" },
          { key: "webhook", label: "Webhooks", href: "/webhooks" },
        ],
      },
    ],
    []
  );

  const primaryLinks = nav.map((item) => ({
    ...item,
    active: item.isDropdown
      ? item.children.some((c) =>
          currentPath.startsWith(normalizePath(c.href))
        )
      : currentPath === normalizePath(item.href) ||
        currentPath.startsWith(normalizePath(item.href)),
  }));

  return (
    <div className="min-h-screen bg-[#0B0B0B] text-gray-100 flex flex-col" data-dark-root>

      {/* ===== TOPBAR ===== */}
      <header className="sticky top-0 z-50 border-b border-neutral-800/60 bg-[#0B0B0B]/95 backdrop-blur">
        <div className="flex items-center justify-between px-5 py-3">

          <div className="flex items-center gap-3">
            <button
              onClick={() => setMobileOpen((v) => !v)}
              className="lg:hidden p-2 rounded-md border border-neutral-800 bg-neutral-900 hover:bg-neutral-800 transition"
            >
              {mobileOpen ? <X size={18} /> : <Menu size={18} />}
            </button>

            <Link href="/dashboard">
              <BrandLogo className="h-[36px] w-auto" />
            </Link>
          </div>

          <div className="flex items-center gap-4">
            <NotificationBell />
            <UserFavicon initials={initials} closeMobile={closeMobileMenu} />
          </div>

        </div>
      </header>

      {/* ===== SIDEBAR DESKTOP ===== */}
      <aside className="fixed inset-y-0 left-0 z-40 hidden w-[282px] lg:flex lg:flex-col 
      lg:border-r lg:border-neutral-800/60 lg:bg-neutral-950">
        
        <div className="flex items-center gap-3 px-6 py-5 border-b border-neutral-800/70">
          <Link href="/dashboard" className="flex items-center gap-3">
            <BrandLogo />
          </Link>
        </div>

        <nav className="min-h-0 flex-1 overflow-y-auto px-3 py-5 hide-scrollbar">
          <ul className="space-y-1.5">
            {primaryLinks.map((item) => (
              <li key={item.key}>
                {item.isDropdown ? (
                  <div className="space-y-1.5">

                    <div className="flex items-center gap-3 text-sm font-medium text-neutral-400 px-3">
                      <item.icon size={17} /> {item.label}
                    </div>

                    <ul className="pl-8 space-y-1">
                      {item.children.map((c) => (
                        <li key={c.key}>
                          <Link
                            href={c.href}
                            className={`block px-3 py-1.5 text-sm rounded-lg ${
                              currentPath.startsWith(normalizePath(c.href))
                                ? "text-emerald-400 bg-emerald-600/10"
                                : "text-neutral-400 hover:bg-neutral-900/60"
                            }`}
                          >
                            {c.label}
                          </Link>
                        </li>
                      ))}
                    </ul>

                  </div>
                ) : (
                  <Link
                    href={item.href}
                    className={`flex items-center gap-3.5 rounded-xl px-3 py-2.5 text-[14.5px] transition ${
                      item.active
                        ? "bg-neutral-900/90 text-neutral-100 ring-1 ring-neutral-700/70"
                        : "text-neutral-400 hover:bg-neutral-900/80 hover:text-neutral-100"
                    }`}
                  >
                    <item.icon className="h-[18px] w-[18px]" />
                    <span>{item.label}</span>
                  </Link>
                )}
              </li>
            ))}
          </ul>
        </nav>

        <div className="border-t border-neutral-800/70 p-5">
          <UserFavicon initials={initials} closeMobile={closeMobileMenu} />
        </div>
      </aside>

      {/* ===== MOBILE MENU ===== */}
      {mobileOpen && (
        <div className="lg:hidden fixed inset-0 z-40 bg-black/80 backdrop-blur-sm">
          
          <div className="absolute left-0 top-0 h-full w-64 bg-neutral-950 
          border-r border-neutral-800/60 shadow-xl p-5 space-y-4 overflow-y-auto">

            {primaryLinks.map((item) =>
              item.isDropdown ? (
                <div key={item.key} className="space-y-2">
                  <div className="flex items-center gap-2 text-sm text-neutral-300 font-medium">
                    <item.icon size={16} />
                    {item.label}
                  </div>

                  {item.children.map((c) => (
                    <Link
                      key={c.key}
                      href={c.href}
                      onClick={closeMobileMenu}
                      className={`block pl-6 py-1 text-sm rounded-lg ${
                        currentPath.startsWith(normalizePath(c.href))
                          ? "text-emerald-400 bg-emerald-600/10"
                          : "text-neutral-400 hover:bg-neutral-900/60"
                      }`}
                    >
                      {c.label}
                    </Link>
                  ))}
                </div>
              ) : (
                <Link
                  key={item.key}
                  href={item.href}
                  onClick={closeMobileMenu}
                  className={`flex items-center gap-3 rounded-lg px-2 py-2 text-[14px] ${
                    item.active
                      ? "bg-neutral-900 text-white ring-1 ring-neutral-700/70"
                      : "text-neutral-400 hover:bg-neutral-900/60"
                  }`}
                >
                  <item.icon size={17} />
                  {item.label}
                </Link>
              )
            )}

            {/* Logout added inside mobile area */}
            <button
              onClick={() => {
                closeMobileMenu();
                router.post("/logout");
              }}
              className="flex items-center gap-2 w-full px-2 py-2 text-sm 
              text-neutral-300 hover:bg-neutral-900/80 rounded-lg mt-4"
            >
              <LogOut size={17} className="text-neutral-400" /> Logout
            </button>

          </div>
        </div>
      )}

      {/* ===== MAIN ===== */}
      <div className="lg:pl-[282px] flex-1 flex flex-col min-w-0">
        
        <main className="flex-1 px-5 sm:px-7 lg:px-9 py-7">
          {boxed ? (
            <div className="rounded-3xl border border-neutral-800/70 bg-neutral-950/95 
            p-5 shadow-[0_20px_60px_-30px_rgba(0,0,0,0.7)] sm:p-7">
              {children}
            </div>
          ) : (
            children
          )}
        </main>

        <footer className="mt-auto pb-10 text-center text-[13px] text-neutral-500">
          <span className="inline-block rounded-full bg-neutral-900 px-3 py-1 
          text-neutral-300 ring-1 ring-inset ring-neutral-800/70 tracking-wide">
            © {new Date().getFullYear()} EquitPay — All rights reserved
          </span>
        </footer>

      </div>
    </div>
  );
}
