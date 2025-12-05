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
  ChevronDown,
  ChevronUp,
} from "lucide-react";
import NotificationBell from "@/Components/NotificationBell";

const normalizePath = (p) => (p || "/").replace(/\/+$/, "").toLowerCase();

/* ---------- Modal de confirmação ---------- */
function LogoutConfirmModal({ open, onCancel, onConfirm }) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 backdrop-blur-sm transition">
      <div className="bg-neutral-900 border border-neutral-700 rounded-2xl p-6 w-[90%] max-w-sm shadow-xl">
        <h2 className="text-lg font-semibold text-white">Confirm Logout</h2>
        <p className="text-neutral-400 text-sm mt-2">
          Are you sure you want to logout?
        </p>

        <div className="flex justify-end gap-2 mt-5">
          <button
            onClick={onCancel}
            className="px-4 py-2 text-sm rounded-lg bg-neutral-800 text-neutral-300 hover:bg-neutral-700 transition"
          >
            Cancel
          </button>

          <button
            onClick={onConfirm}
            className="px-4 py-2 text-sm rounded-lg bg-[#ff005d] hover:bg-[#e00052] text-white transition"
          >
            Logout
          </button>
        </div>
      </div>
    </div>
  );
}

/* ---------- Logo ---------- */
function BrandLogo({ className = "block h-[28px] w-auto md:h-[32px]" }) {
  return (
    <img
      src="/images/logopixon.png"
      alt="Logo"
      className={`${className} select-none pointer-events-none opacity-90 hover:opacity-100 transition`}
      loading="eager"
      decoding="async"
    />
  );
}

/* ---------- Avatar Menu ---------- */
function UserFavicon({ initials, closeMobile, openConfirm }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);

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
        hover:ring-[#ff005d] transition"
      >
        {initials}
      </button>

      {open && (
        <div className="absolute right-0 mt-2 w-40 rounded-xl border 
        border-neutral-800/70 bg-neutral-950 shadow-xl z-50">
          <button
            onClick={() => {
              setOpen(false);
              closeMobile?.();
              openConfirm();
            }}
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

/* ---------- Layout Principal ---------- */
export default function AuthenticatedLayout({ header, children, boxed = false }) {
  const page = usePage();
  const user = page?.props?.auth?.user;
  const [mobileOpen, setMobileOpen] = useState(false);
  const [confirmLogout, setConfirmLogout] = useState(false);
  const [integrationOpen, setIntegrationOpen] = useState(false);

  const closeMobileMenu = () => setMobileOpen(false);

  const initials = useMemo(() => {
    const n = (user?.name || "").trim();
    return n
      ? n.split(/\s+/).slice(0, 2).map((s) => s[0]?.toUpperCase()).join("")
      : "U";
  }, [user?.name]);

  const currentPath = normalizePath(
    typeof window !== "undefined" ? window.location.pathname : page?.url || "/"
  );

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
      ? item.children.some((c) => currentPath.startsWith(normalizePath(c.href)))
      : currentPath === normalizePath(item.href) ||
        currentPath.startsWith(normalizePath(item.href)),
  }));

  return (
    <div className="min-h-screen bg-[#0B0B0B] text-gray-100 flex flex-col">

      <LogoutConfirmModal
        open={confirmLogout}
        onCancel={() => setConfirmLogout(false)}
        onConfirm={() => router.post("/logout")}
      />

      {/* TOPBAR */}
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
            <UserFavicon
              initials={initials}
              closeMobile={closeMobileMenu}
              openConfirm={() => setConfirmLogout(true)}
            />
          </div>
        </div>
      </header>

      {/* SIDEBAR MOBILE */}
      {mobileOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
          onClick={() => setMobileOpen(false)}
        />
      )}

      <aside
        className={`fixed inset-y-0 left-0 z-50 w-[260px] bg-neutral-950 border-r border-neutral-800/60
        transform transition-transform duration-300 lg:hidden
        ${mobileOpen ? "translate-x-0" : "-translate-x-full"}`}
      >
        <nav className="p-6 space-y-2">
          {primaryLinks.map((item) => (
            <div key={item.key}>
              {!item.isDropdown && (
                <Link
                  href={item.href}
                  onClick={() => setMobileOpen(false)}
                  className={`flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition
                  ${
                    item.active
                      ? "bg-[#ff005d]/10 text-[#ff005d]"
                      : "text-neutral-400 hover:bg-neutral-900/80 hover:text-white"
                  }`}
                >
                  <item.icon size={18} />
                  {item.label}
                </Link>
              )}

              {item.isDropdown && (
                <>
                  <button
                    onClick={() =>
                      setIntegrationOpen((prev) => (prev === item.key ? false : item.key))
                    }
                    className="flex items-center justify-between w-full px-4 py-3 rounded-xl text-sm text-neutral-400 hover:text-white hover:bg-neutral-900/70"
                  >
                    <span className="flex items-center gap-2">
                      <item.icon size={18} />
                      {item.label}
                    </span>
                    {integrationOpen === item.key ? <ChevronUp /> : <ChevronDown />}
                  </button>

                  {integrationOpen === item.key && (
                    <div className="ml-6 space-y-1 mt-1">
                      {item.children.map((c) => (
                        <Link
                          key={c.key}
                          href={c.href}
                          onClick={() => setMobileOpen(false)}
                          className={`block px-4 py-2 rounded-lg text-sm transition
                          ${
                            currentPath.startsWith(c.href)
                              ? "text-[#ff005d] bg-[#ff005d]/10"
                              : "text-neutral-400 hover:bg-neutral-900/60 hover:text-neutral-300"
                          }`}
                        >
                          {c.label}
                        </Link>
                      ))}
                    </div>
                  )}
                </>
              )}
            </div>
          ))}
        </nav>
      </aside>

      {/* SIDEBAR DESKTOP */}
      <aside className="fixed inset-y-0 left-0 z-40 hidden w-[282px] 
      lg:flex lg:flex-col lg:border-r lg:border-neutral-800/60 lg:bg-neutral-950">
        <div className="flex items-center gap-3 px-6 py-5 border-b border-neutral-800/70">
          <Link href="/dashboard" className="flex items-center gap-3">
            <BrandLogo />
          </Link>
        </div>

        <nav className="min-h-0 flex-1 overflow-y-auto px-4 py-6 hide-scrollbar">
          <ul className="space-y-1">
            {primaryLinks.map((item) => (
              <li key={item.key}>
                {/* --- DROPDOWN DESKTOP --- */}
                {item.isDropdown ? (
                  <>
                    <button
                      onClick={() =>
                        setIntegrationOpen((prev) => (prev === item.key ? false : item.key))
                      }
                      className={`flex items-center justify-between w-full px-4 py-3 rounded-xl text-sm font-medium transition 
                      ${
                        item.active
                          ? "text-[#ff005d]"
                          : "text-neutral-400 hover:text-white hover:bg-neutral-900/80"
                      }`}
                    >
                      <span className="flex items-center gap-2">
                        <item.icon size={17} />
                        {item.label}
                      </span>
                      {integrationOpen === item.key ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
                    </button>

                    {integrationOpen === item.key && (
                      <ul className="pl-6 mt-1 space-y-1">
                        {item.children.map((c) => (
                          <li key={c.key}>
                            <Link
                              href={c.href}
                              className={`block px-4 py-2 text-sm rounded-lg transition 
                              ${
                                currentPath.startsWith(normalizePath(c.href))
                                  ? "text-[#ff005d] bg-[#ff005d]/10 font-medium"
                                  : "text-neutral-400 hover:bg-neutral-900/60 hover:text-neutral-300"
                              }`}
                            >
                              {c.label}
                            </Link>
                          </li>
                        ))}
                      </ul>
                    )}
                  </>
                ) : (
                  <Link
                    href={item.href}
                    className={`flex items-center gap-3.5 rounded-xl px-4 py-3 text-[14.5px] transition font-medium 
                    ${
                      item.active
                        ? "bg-[#ff005d]/10 text-[#ff005d] shadow-inner shadow-black/30 ring-1 ring-[#ff005d]/30"
                        : "text-neutral-400 hover:bg-neutral-900/80 hover:text-white"
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
      </aside>

      {/* MAIN */}
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
            © {new Date().getFullYear()} PixionPay — All rights reserved
          </span>
        </footer>
      </div>
    </div>
  );
}
