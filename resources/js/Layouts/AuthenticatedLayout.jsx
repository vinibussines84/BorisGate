// resources/js/Layouts/AuthenticatedLayout.jsx
import { Link, usePage, router } from "@inertiajs/react";
import React, { useEffect, useMemo, useRef, useState } from "react";
import {
  Home,
  FileText,
  CreditCard,
  Wallet,
  Activity,
  Code,
  ChevronsUpDown,
  User as UserIcon,
  LogOut,
} from "lucide-react";
import NotificationBell from "@/Components/NotificationBell";

/* ---------- Helpers ---------- */
const HIGHLIGHT = "#22c55e";
const ACTIVE_CLS = "bg-emerald-600/15 text-white font-semibold";
const INACTIVE_CLS = "text-gray-300 hover:bg-white/[0.04]";
const INACTIVE_ICON = "#9ca3af";

const hasRoute = (name) => {
  try {
    return typeof route === "function" && route().has && route().has(name);
  } catch {
    return false;
  }
};
const urlFor = (name, fallback) => (hasRoute(name) ? route(name) : fallback);
const normalizePath = (p) => (p || "/").replace(/\/+$/, "").toLowerCase();

/* ---------- Logo ---------- */
function BrandLogo({ className = "block h-[28px] w-auto md:h-[32px]" }) {
  return (
    <img
      src="/images/equitpay.png"
      alt="Logo"
      className={`${className} select-none pointer-events-none opacity-90 hover:opacity-100 transition grayscale-[35%] contrast-110`}
      loading="eager"
      decoding="async"
    />
  );
}

/* ---------- Avatar Menu ---------- */
function UserFavicon({ initials }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    const handleClick = (e) => ref.current && !ref.current.contains(e.target) && setOpen(false);
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, []);
  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((v) => !v)}
        className="grid size-9 place-items-center rounded-full bg-neutral-900 text-[13px] font-medium text-neutral-200 ring-1 ring-neutral-700 hover:ring-emerald-500 transition"
      >
        {initials}
      </button>
      {open && (
        <div className="absolute right-0 mt-2 w-40 rounded-xl border border-neutral-800/70 bg-neutral-950/95 shadow-xl z-50">
          <Link
            href={urlFor("logout", "/logout")}
            method="post"
            as="button"
            className="flex items-center gap-2 w-full px-4 py-2 text-sm text-neutral-300 hover:bg-neutral-900/80 transition-colors"
          >
            <LogOut className="h-4 w-4 text-neutral-400" /> Logout
          </Link>
        </div>
      )}
    </div>
  );
}

/* ---------- Reset e Fundo ---------- */
function GlobalDarkReset() {
  return (
    <style>{`
      html, body { margin: 0; padding: 0; overflow-x: hidden; }
      #app { height: 100%; overscroll-behavior: none; }
      [data-dark-root] { color-scheme: dark; }
      :where([data-dark-root]) { font-feature-settings:"ss01","ss02","tnum","liga","kern"; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
      .hide-scrollbar::-webkit-scrollbar { display: none; }
      .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    `}</style>
  );
}

function DarkBackdrop({ ready }) {
  return (
    <>
      <div className="fixed inset-0 -z-10 bg-neutral-950" />
      {ready && (
        <>
          <div
            className="fixed inset-0 -z-10 pointer-events-none"
            style={{
              background:
                "radial-gradient(50% 50% at 50% 50%, rgba(16,185,129,0.05) 0%, transparent 60%)",
              filter: "blur(90px)",
              opacity: 0.8,
              transition: "opacity .5s ease",
            }}
          />
          <div
            className="fixed inset-0 -z-10 opacity-[0.08] pointer-events-none"
            style={{
              backgroundImage:
                "linear-gradient(to right, rgba(255,255,255,0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.05) 1px, transparent 1px)",
              backgroundSize: "40px 40px",
            }}
          />
        </>
      )}
    </>
  );
}

/* ---------- Spinner ---------- */
function CornerSpinner({ active }) {
  return (
    <div
      className={[
        "fixed right-4 top-4 z-[9998] rounded-full bg-neutral-900/90 ring-1 ring-neutral-800 px-3 py-2 shadow-[0_10px_30px_-10px_rgba(0,0,0,.7)] transition-opacity duration-200",
        active ? "opacity-100" : "opacity-0 pointer-events-none",
      ].join(" ")}
    >
      <span className="relative inline-flex h-5 w-5 items-center justify-center">
        <span className="absolute inline-block h-5 w-5 rounded-full border-2 border-neutral-600/70 border-t-transparent animate-spin" />
        <span className="relative inline-block h-2 w-2 rounded-full bg-neutral-300/90" />
      </span>
    </div>
  );
}

/* ---------- Loading Inicial ---------- */
function PagePreloader() {
  return (
    <div className="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-[#0B0B0B] text-neutral-300">
      <BrandLogo className="h-[48px] w-auto opacity-90" />
      <div className="mt-5 h-5 w-5 border-2 border-emerald-500/40 border-t-transparent rounded-full animate-spin" />
      <p className="mt-4 text-xs text-neutral-500 tracking-wide">Loading dashboard...</p>
    </div>
  );
}

/* ---------- Layout Principal ---------- */
export default function AuthenticatedLayout({ header, children, boxed = false }) {
  const page = usePage();
  const user = page?.props?.auth?.user;
  const currentPath = normalizePath(
    typeof window !== "undefined" ? window.location.pathname : page?.url || "/"
  );

  const [isReady, setIsReady] = useState(false);
  const [navLoading, setNavLoading] = useState(false);

  /* Espera página carregar completamente */
  useEffect(() => {
    const handleReady = () => requestAnimationFrame(() => setIsReady(true));
    if (document.readyState === "complete") handleReady();
    else window.addEventListener("load", handleReady);
    return () => window.removeEventListener("load", handleReady);
  }, []);

  /* Proteção anti-travamento Inertia */
  useEffect(() => {
    const start = () => setNavLoading(true);

    const stop = () => {
      requestAnimationFrame(() => setNavLoading(false));
      document.body.style.opacity = "1";
    };

    const failSafe = () => {
      setTimeout(() => setNavLoading(false), 500);
      document.body.style.opacity = "1";
    };

    router.on("start", start);
    router.on("finish", stop);
    router.on("error", failSafe);

    const watchdog = setInterval(() => {
      const app = document.querySelector("#app");
      if (app && app.style.opacity === "0") {
        app.style.opacity = "1";
        setNavLoading(false);
      }
    }, 2000);

    return () => {
      router.off?.("start", start);
      router.off?.("finish", stop);
      router.off?.("error", failSafe);
      clearInterval(watchdog);
    };
  }, []);

  const initials = useMemo(() => {
    const n = (user?.name || "").trim();
    return n
      ? n
          .split(/\s+/)
          .slice(0, 2)
          .map((s) => s[0]?.toUpperCase() || "")
          .join("")
      : "U";
  }, [user?.name]);

  const nav = useMemo(
    () => [
      { key: "dashboard", label: "Dashboard", href: "/dashboard", icon: Home, primary: true },
      { key: "extrato", label: "Statement", href: "/extrato", icon: FileText, primary: true },
      { key: "cobranca", label: "Billing", href: "/cobranca", icon: CreditCard, primary: true },
      { key: "saque", label: "Withdraw", href: "/saques", icon: Wallet, primary: true },
      { key: "med", label: "Mediation", href: "/med", icon: Activity, primary: true },
      {
        key: "integration",
        label: "Integration",
        icon: Code,
        primary: true,
        isDropdown: true,
        children: [
          { key: "api", label: "Tokens API", href: "/api" },
          { key: "webhook", label: "Webhooks", href: "/webhooks" },
        ],
      },
    ],
    []
  );

  const primaryLinks = useMemo(
    () =>
      nav.map((item) => ({
        ...item,
        active: item.isDropdown
          ? item.children.some((c) => currentPath.startsWith(normalizePath(c.href)))
          : currentPath.startsWith(normalizePath(item.href)),
      })),
    [nav, currentPath]
  );

  if (!isReady) return <PagePreloader />;

  return (
    <div
      className="min-h-screen bg-[#0B0B0B] text-gray-100 antialiased overflow-x-hidden"
      data-dark-root
    >
      <GlobalDarkReset />
      <DarkBackdrop ready={isReady} />
      <CornerSpinner active={navLoading} />

      {/* Sidebar */}
      <aside className="fixed inset-y-0 left-0 z-40 hidden w-[282px] lg:flex lg:flex-col lg:border-r lg:border-neutral-800/60 lg:bg-neutral-950">
        <div className="flex items-center gap-3 px-6 py-5 border-b border-neutral-800/70">
          <Link href="/" className="flex items-center gap-3">
            <BrandLogo />
          </Link>
        </div>
        <nav className="min-h-0 flex-1 overflow-y-auto px-3 py-5 hide-scrollbar">
          <ul className="space-y-1.5">
            {primaryLinks.map((item) => (
              <li key={item.key}>
                {item.isDropdown ? (
                  <div className="space-y-1.5">
                    <button
                      onClick={() => (item.open = !item.open)}
                      className={`group flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-[14.5px] ${
                        item.active
                          ? "bg-neutral-900 text-white ring-1 ring-neutral-700"
                          : "text-neutral-400 hover:bg-neutral-900/80"
                      }`}
                    >
                      <div className="flex items-center gap-3.5">
                        <item.icon className="h-[18px] w-[18px]" />
                        <span>{item.label}</span>
                      </div>
                    </button>
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
          <div className="relative">
            <UserFavicon initials={initials} />
          </div>
        </div>
      </aside>

      {/* Main */}
      <div className="lg:pl-[282px] flex flex-col flex-1 min-w-0">
        <div className="sticky top-0 z-40 border-b border-neutral-800/70 bg-[#0B0B0B]/95 backdrop-blur">
          <div className="flex items-center px-5 py-3 sm:px-7 lg:px-9">
            <div className="flex items-center lg:hidden">
              <BrandLogo className="block h-[36px] w-auto md:h-[42px]" />
            </div>
            <div className="ml-auto flex items-center gap-4">
              <NotificationBell />
              <UserFavicon initials={initials} />
            </div>
          </div>
        </div>

        {header && <header className="px-5 pt-6 sm:px-7 lg:px-9">{header}</header>}

        <main
          className={`px-5 py-7 sm:px-7 lg:px-9 flex-1 min-h-[calc(100vh-96px)] transition-opacity duration-200 ${
            navLoading ? "opacity-70" : "opacity-100"
          }`}
        >
          {boxed ? (
            <div className="rounded-3xl border border-neutral-800/70 bg-neutral-950/95 p-5 shadow-[0_20px_60px_-30px_rgba(0,0,0,0.7)] sm:p-7">
              {children}
            </div>
          ) : (
            children
          )}

          <footer className="mt-10 pb-10 text-center text-[13px] text-neutral-500">
            <span className="inline-block rounded-full bg-neutral-900 px-3 py-1 text-neutral-300 ring-1 ring-inset ring-neutral-800/70 tracking-wide">
              © {new Date().getFullYear()} EquitPay — All rights reserved
            </span>
          </footer>
        </main>
      </div>
    </div>
  );
}
