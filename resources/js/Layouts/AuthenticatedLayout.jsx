import { Link, usePage, router } from '@inertiajs/react';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  Home, FileText, CreditCard, Wallet, Activity,
  Code, ChevronsUpDown, User as UserIcon, LogOut,
} from 'lucide-react';
import NotificationBell from '@/Components/NotificationBell';

/* ---------- Configurações e Helpers ---------- */
const HIGHLIGHT = '#22c55e';
const ACTIVE_CLS = 'bg-emerald-600/15 text-white font-semibold';
const INACTIVE_CLS = 'text-gray-300 hover:bg-white/[0.04]';
const INACTIVE_ICON = '#9ca3af';

const hasRoute = (name) => {
  try {
    return typeof route === 'function' && route().has && route().has(name);
  } catch {
    return false;
  }
};
const urlFor = (name, fallback) => (hasRoute(name) ? route(name) : fallback);
const normalizePath = (p) => (p || '/').replace(/\/+$/, '').toLowerCase();
const getPath = (href = '') => normalizePath(href.split('?')[0]);

/* --- Logo --- */
function BrandLogo({ className = 'block h-[28px] w-auto md:h-[32px]' }) {
  return (
    <img
      src="/images/equitpay.png"
      alt="Logo"
      className={[
        className,
        'select-none pointer-events-none opacity-90 hover:opacity-100 transition grayscale-[35%] contrast-110',
      ].join(' ')}
      loading="eager"
      decoding="async"
    />
  );
}

/* --- 🔥 Favicon com menu de logout --- */
function UserFavicon({ initials }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);

  useEffect(() => {
    const handleClick = (e) => ref.current && !ref.current.contains(e.target) && setOpen(false);
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen(!open)}
        className="grid size-9 place-items-center rounded-full bg-neutral-900 text-[13px] font-medium text-neutral-200 ring-1 ring-neutral-700 hover:ring-emerald-500 transition"
      >
        {initials}
      </button>
      {open && (
        <div className="absolute right-0 mt-2 w-40 rounded-xl border border-neutral-800/70 bg-neutral-950/95 shadow-xl z-50">
          <Link
            href={urlFor('logout', '/logout')}
            method="post"
            as="button"
            className="flex items-center gap-2 w-full px-4 py-2 text-sm text-neutral-300 hover:bg-neutral-900/80 transition-colors"
          >
            <LogOut className="h-4 w-4 text-neutral-400" /> Sair
          </Link>
        </div>
      )}
    </div>
  );
}

/* --- Reset e UI Auxiliar --- */
function GlobalDarkReset() {
  return (
    <style>{`
      html, body { margin: 0; padding: 0; overflow-x: hidden; }
      #app { height: 100%; overscroll-behavior: none; }
      [data-dark-root] { color-scheme: dark; }
      [data-dark-root] .bg-white, [data-dark-root] .bg-white\\/\\d+, [data-dark-root] .bg-neutral-50 { background-color: transparent !important; }
      [data-dark-root] .ring-black\\/5, [data-dark-root] .border-black\\/5 { --tw-ring-color: rgba(23,23,23,0.4) !important; border-color: rgba(39,39,42,0.4) !important; }
      :where([data-dark-root]) { font-feature-settings:"ss01","ss02","tnum","liga","kern"; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; letter-spacing: .01em; }
      .hide-scrollbar::-webkit-scrollbar { display: none; }
      .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    `}</style>
  );
}

function DarkBackdrop() {
  return (
    <>
      <div className="fixed inset-0 -z-10 bg-neutral-950" />
      <div
        className="fixed inset-0 -z-10 pointer-events-none"
        style={{
          background:
            'radial-gradient(50% 50% at 50% 50%, rgba(16,185,129,0.05) 0%, transparent 60%)',
          filter: 'blur(120px)',
          opacity: 0.8,
        }}
      />
      <div
        className="fixed inset-0 -z-10 opacity-[0.08] pointer-events-none"
        style={{
          backgroundImage:
            'linear-gradient(to right, rgba(255,255,255,0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.05) 1px, transparent 1px)',
          backgroundSize: '40px 40px',
        }}
      />
    </>
  );
}

/* --- Spinner no canto --- */
function CornerSpinner({ active }) {
  return (
    <div
      className={[
        'fixed right-4 top-4 z-[9998] rounded-full bg-neutral-900/90 ring-1 ring-neutral-800 px-3 py-2 shadow-[0_10px_30px_-10px_rgba(0,0,0,.7)] transition-opacity duration-200',
        active ? 'opacity-100' : 'opacity-0 pointer-events-none',
      ].join(' ')}
      aria-live="polite"
      aria-busy={active}
    >
      <span className="relative inline-flex h-5 w-5 items-center justify-center">
        <span className="absolute inline-block h-5 w-5 rounded-full border-2 border-neutral-600/70 border-t-transparent animate-spin" />
        <span className="relative inline-block h-2 w-2 rounded-full bg-neutral-300/90" />
      </span>
    </div>
  );
}

/* --- Tela de loading inicial --- */
function PagePreloader() {
  return (
    <div className="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-[#0B0B0B] text-neutral-300">
      <BrandLogo className="h-[48px] w-auto opacity-90" />
      <div className="mt-5 h-5 w-5 border-2 border-emerald-500/40 border-t-transparent rounded-full animate-spin" />
      <p className="mt-4 text-xs text-neutral-500 tracking-wide">Carregando painel...</p>
    </div>
  );
}

/* ===== Item de menu ===== */
function MenuItem({ href, children, method, as }) {
  const isButton = as === 'button';
  const base = 'flex items-center gap-2.5 px-5 py-3 text-sm text-neutral-200 hover:bg-neutral-900/70';
  return isButton ? (
    <Link href={href} method={method} as="button" className={base} preserveScroll>
      {children}
    </Link>
  ) : (
    <Link href={href} className={base}>
      {children}
    </Link>
  );
}

/* ===== Menu Perfil ===== */
function ProfileMenu({ initials, user, mobile = false }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  const safeRoute = (name, fallback = '#') => urlFor(name, fallback);

  useEffect(() => {
    const handleClick = (e) => ref.current && !ref.current.contains(e.target) && setOpen(false);
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  const buttonCls = mobile
    ? 'grid size-10 place-items-center rounded-full bg-neutral-900 text-[13px] text-neutral-200 ring-1 ring-neutral-700'
    : 'w-full inline-flex items-center gap-3.5 rounded-xl border border-neutral-800/70 bg-neutral-900/80 px-3 py-2.5';

  const dropdownCls = mobile
    ? 'absolute right-0 mt-2 w-56 rounded-xl border border-neutral-800/70 bg-neutral-950/95 shadow-xl z-50'
    : 'absolute bottom-full left-0 right-0 mb-2 rounded-xl border border-neutral-800/70 bg-neutral-950/95 shadow-xl z-50';

  return (
    <div ref={ref} className="relative">
      <button onClick={() => setOpen(!open)} className={buttonCls}>
        {mobile ? (
          initials
        ) : (
          <>
            <div className="grid size-10 place-items-center rounded-full bg-neutral-800 text-[13px] font-medium text-neutral-200 ring-1 ring-neutral-700">
              {initials}
            </div>
            <div className="min-w-0 flex-1 text-left">
              <div className="truncate text-neutral-100">{user?.name}</div>
              <div className="truncate text-[12px] text-neutral-400">{user?.email}</div>
            </div>
            <ChevronsUpDown className="h-5 w-5 text-neutral-400" />
          </>
        )}
      </button>
      {open && (
        <div className={dropdownCls}>
          <MenuItem href={safeRoute('profile.edit', '/profile')}>
            <UserIcon className="h-4 w-4" /> Perfil
          </MenuItem>
          <MenuItem href={safeRoute('logout', '/logout')} method="post" as="button">
            <LogOut className="h-4 w-4" /> Sair
          </MenuItem>
        </div>
      )}
    </div>
  );
}

/* ===== Sidebar Link ===== */
function SidebarLink({ href, active, icon: Icon, children }) {
  const activeCls = 'bg-neutral-900/90 text-neutral-100 ring-1 ring-neutral-700/70';
  const inactiveCls = 'text-neutral-400 hover:bg-neutral-900/80 hover:text-neutral-100';
  return (
    <Link
      href={href}
      className={[
        'group flex items-center gap-3.5 rounded-xl px-3 py-2.5 text-[14.5px] transition',
        active ? activeCls : inactiveCls,
      ].join(' ')}
    >
      <Icon className="h-[18px] w-[18px]" />
      <span className="truncate tracking-wide">{children}</span>
    </Link>
  );
}

/* ===== Sidebar Dropdown ===== */
function SidebarDropdown({ label, active, icon: Icon, children = [] }) {
  const [open, setOpen] = useState(active);
  useEffect(() => {
    if (active && !open) setOpen(true);
  }, [active]);
  const buttonCls = active
    ? 'bg-neutral-900 text-white ring-1 ring-neutral-700'
    : 'text-neutral-400 hover:bg-neutral-900/80';
  return (
    <div className="space-y-1.5">
      <button
        onClick={() => setOpen(!open)}
        className={[
          'group flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-[14.5px]',
          buttonCls,
        ].join(' ')}
      >
        <div className="flex items-center gap-3.5">
          <Icon className="h-[18px] w-[18px]" />
          <span className="truncate">{label}</span>
        </div>
        <ChevronsUpDown className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>
      {open && (
        <ul className="pl-8 space-y-1">
          {children.map((item) => (
            <li key={item.key}>
              <Link
                href={item.href}
                className={`block px-3 py-1.5 text-sm rounded-lg ${
                  item.active
                    ? 'text-emerald-400 bg-emerald-600/10'
                    : 'text-neutral-400 hover:bg-neutral-900/60'
                }`}
              >
                {item.label}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

/* ===== Layout Principal ===== */
export default function AuthenticatedLayout({ header, children, boxed = false }) {
  const page = usePage();
  const user = page?.props?.auth?.user;
  const rawPath = (typeof window !== 'undefined' && window.location?.pathname) || page?.url || '/';
  const currentPath = normalizePath(rawPath);
  const isActivePath = (p) => currentPath.startsWith(normalizePath(p));

  const [isReady, setIsReady] = useState(false);
  const [navLoading, setNavLoading] = useState(false);

  // Espera tudo carregar antes de renderizar o layout
  useEffect(() => {
    const handleReady = () => setTimeout(() => setIsReady(true), 300);
    if (document.readyState === 'complete') handleReady();
    else window.addEventListener('load', handleReady);
    return () => window.removeEventListener('load', handleReady);
  }, []);

  // Eventos de navegação Inertia
  useEffect(() => {
    const start = () => setNavLoading(true);
    const stop = () => setTimeout(() => setNavLoading(false), 120);

    router.on('start', start);
    router.on('finish', stop);
    router.on('error', stop);

    return () => {
      if (router._events) {
        router._events.start = router._events.start?.filter((fn) => fn !== start);
        router._events.finish = router._events.finish?.filter((fn) => fn !== stop);
        router._events.error = router._events.error?.filter((fn) => fn !== stop);
      }
    };
  }, []);

  const initials = useMemo(() => {
    const n = (user?.name || '').trim();
    return n ? n.split(/\s+/).slice(0, 2).map((s) => s[0]?.toUpperCase() || '').join('') : 'U';
  }, [user?.name]);

  const nav = useMemo(
    () => [
      { key: 'dashboard', label: 'Início', href: urlFor('dashboard', '/dashboard'), icon: Home, primary: true },
      { key: 'extrato', label: 'Extrato', href: '/extrato', icon: FileText, primary: true },
      { key: 'cobranca', label: 'Cobrança', href: '/cobranca', icon: CreditCard, primary: true },
      { key: 'saque', label: 'Saque', href: '/saques', icon: Wallet, primary: true },
      { key: 'med', label: 'Med', href: '/med', icon: Activity, primary: true },
      {
        key: 'integracao',
        label: 'Integração',
        icon: Code,
        primary: true,
        isDropdown: true,
        children: [
          { key: 'api', label: 'Tokens API', href: '/api' },
          { key: 'webhook', label: 'Webhooks', href: '/webhooks' },
        ],
      },
    ],
    []
  );

  const primaryLinks = useMemo(
    () =>
      nav.filter((n) => n.primary).map((item) => ({
        ...item,
        active: item.isDropdown
          ? item.children.some((child) => isActivePath(child.href))
          : isActivePath(item.href),
      })),
    [nav, currentPath]
  );

  const [activeItem, setActiveItem] = useState(getPath(currentPath));
  useEffect(() => setActiveItem(getPath(currentPath)), [currentPath]);

  if (!isReady) return <PagePreloader />;

  return (
    <div className="min-h-screen bg-[#0B0B0B] text-gray-100 antialiased overflow-x-hidden" data-dark-root>
      <GlobalDarkReset />
      <DarkBackdrop />
      <CornerSpinner active={navLoading} />

      {/* Sidebar desktop */}
      <aside className="fixed inset-y-0 left-0 z-40 hidden w-[282px] lg:flex lg:flex-col lg:border-r lg:border-neutral-800/60 lg:bg-neutral-950">
        <div className="flex items-center gap-3 px-6 py-5 border-b border-neutral-800/70">
          <Link href="/" className="flex items-center gap-3">
            <BrandLogo />
          </Link>
        </div>
        <nav className="min-h-0 flex-1 overflow-y-auto px-3 py-5">
          <ul className="space-y-1.5">
            {primaryLinks.map((item) => (
              <li key={item.key}>
                {item.isDropdown ? (
                  <SidebarDropdown label={item.label} active={item.active} icon={item.icon}>
                    {item.children.map((child) => ({ ...child, active: isActivePath(child.href) }))}
                  </SidebarDropdown>
                ) : (
                  <SidebarLink href={item.href} active={item.active} icon={item.icon}>
                    {item.label}
                  </SidebarLink>
                )}
              </li>
            ))}
          </ul>
        </nav>
        <div className="border-t border-neutral-800/70 p-5">
          <ProfileMenu initials={initials} user={user} mobile={false} />
        </div>
      </aside>

      {/* Conteúdo principal */}
      <div className="lg:pl-[282px] flex flex-col flex-1 min-w-0">
        {/* HEADER */}
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
          {/* NAV MOBILE */}
          <nav className="lg:hidden border-t border-neutral-800/70">
            <div className="flex w-full items-center gap-2 overflow-x-auto px-4 py-2 hide-scrollbar">
              {primaryLinks.flatMap(({ href, label, icon: Icon, isDropdown, children = [], active: parentActive }) => {
                const links = isDropdown ? children : [{ href, label, icon: Icon }];
                return links.map(({ href: childHref, label: childLabel, icon: childIcon }) => {
                  const IconComp = childIcon || Icon;
                  const path = getPath(childHref);
                  const active =
                    parentActive || activeItem === path || currentPath === normalizePath(childHref);
                  return (
                    <Link
                      key={childHref}
                      href={childHref}
                      className={[
                        'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors',
                        active ? ACTIVE_CLS : INACTIVE_CLS,
                      ].join(' ')}
                      onClick={() => setActiveItem(path)}
                    >
                      <IconComp size={18} strokeWidth={1.8} color={active ? HIGHLIGHT : INACTIVE_ICON} />
                      <span className="truncate">{childLabel}</span>
                    </Link>
                  );
                });
              })}
            </div>
          </nav>
        </div>

        {header && <header className="px-5 pt-6 sm:px-7 lg:px-9">{header}</header>}

        <main
          className={[
            'px-5 py-7 sm:px-7 lg:px-9 flex-1 min-h-[calc(100vh-96px)] transition-opacity duration-200',
            navLoading ? 'opacity-70' : 'opacity-100',
          ].join(' ')}
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
              © {new Date().getFullYear()} EquitPay — todos os direitos reservados
            </span>
          </footer>
        </main>
      </div>
    </div>
  );
}
