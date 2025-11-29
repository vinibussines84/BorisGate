import React, { useEffect, useMemo, useRef, useState } from "react";
import { Head, router } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import {
  Search,
  X,
  ShieldCheck,
  Sparkles,
  Loader2,
  Filter,
  User,
  Mail,
  Hash,
  ChevronDown,
  ChevronUp,
} from "lucide-react";

/* ========== util highlight ========== */
const highlight = (text = "", q = "") => {
  if (!q?.trim()) return text;
  try {
    const parts = q
      .trim()
      .split(/\s+/)
      .filter(Boolean)
      .map((s) => s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"));
    if (!parts.length) return text;

    const reSplit = new RegExp(`(${parts.join("|")})`, "i");
    const reFull = new RegExp(`^(${parts.join("|")})$`, "i");

    return String(text)
      .split(reSplit)
      .map((chunk, i) =>
        reFull.test(chunk) ? (
          <mark key={i} className="rounded bg-pink-500/20 px-0.5 text-pink-200">
            {chunk}
          </mark>
        ) : (
          <span key={i}>{chunk}</span>
        )
      );
  } catch {
    return text;
  }
};

export default function UsersIndex({ title = "Usuários", users, q: qProp, filter: filterProp }) {
  const list = users ?? null;
  const [q, setQ] = useState((qProp ?? "").toString());
  const [filter, setFilter] = useState((filterProp ?? "all").toString());
  const [loading, setLoading] = useState(false);
  const [compact, setCompact] = useState(false);

  /* -------- palette state -------- */
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [qp, setQp] = useState(q);
  const [paletteIndex, setPaletteIndex] = useState(0);

  const inputRef = useRef(null);
  const debounceRef = useRef(null);

  /* -------- busca -------- */
  const go = (params = {}) =>
    router.get(route("admin.users.index"), { q, filter, ...params }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onStart: () => setLoading(true),
      onFinish: () => setLoading(false),
    });

  const doSearch = (e) => { e?.preventDefault?.(); go(); };
  const clearQ = () => { setQ(""); go({ q: "" }); inputRef.current?.focus(); };
  const setFilterAndGo = (val) => {
    setFilter(val);
    router.get(route("admin.users.index"), { q, filter: val }, {
      preserveState: true, preserveScroll: true, replace: true,
      onStart: () => setLoading(true), onFinish: () => setLoading(false),
    });
  };

  /* debounce q */
  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      if ((q ?? "") !== (qProp ?? "")) go();
    }, 380);
    return () => clearTimeout(debounceRef.current);
  }, [q]);

  /* atalhos globais */
  useEffect(() => {
    const onKey = (e) => {
      const key = e.key.toLowerCase();
      const metaK = (e.metaKey || e.ctrlKey) && key === "k";
      if (metaK) {
        e.preventDefault();
        setQp(q);
        setPaletteIndex(0);
        setPaletteOpen(true);
        return;
      }
      if (key === "/") {
        const tag = (document.activeElement?.tagName || "").toLowerCase();
        if (tag !== "input" && tag !== "textarea") {
          e.preventDefault();
          inputRef.current?.focus();
        }
      }
      if (key === "escape") {
        if (paletteOpen) setPaletteOpen(false);
        else if (document.activeElement === inputRef.current && q) clearQ();
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [q, paletteOpen]);

  /* filtros + dados */
  const filterDefs = [
    { key: "all", label: "Todos" },
    { key: "admin", label: "Admins" },
    { key: "nonadmin", label: "Não-admin" },
  ];
  const resultCount =
    typeof list?.total === "number"
      ? new Intl.NumberFormat("pt-BR").format(list.total)
      : null;

  /* palette preview */
  const paletteResults = useMemo(() => {
    const data = Array.isArray(list?.data) ? list.data : [];
    const term = (qp || "").trim().toLowerCase();
    if (!term) return data.slice(0, 7);
    const norm = (s) => (s || "").toString().toLowerCase();
    const digits = (s) => (s || "").toString().replace(/\D+/g, "");
    return data
      .filter((u) => {
        const n = norm(u.name);
        const e = norm(u.email);
        const d = digits(u.cpf_cnpj);
        return n.includes(term) || e.includes(term) || d.includes(digits(term));
      })
      .slice(0, 7);
  }, [list?.data, qp]);

  const selectPaletteItem = (u) => {
    const chosen = u?.email || u?.name || "";
    setQ(chosen);
    setPaletteOpen(false);
    setTimeout(() => go({ q: chosen }), 0);
  };

  /* -------- render -------- */
  return (
    <AdminLayout>
      <Head title={title} />
      <div className="space-y-6">
        {/* header */}
        <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-semibold text-white">{title}</h1>
            <Sparkles className="h-5 w-5 text-pink-400/80" />
          </div>
          <div className="flex items-center gap-2">
            <div className="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/[0.03] px-3 py-1.5 text-sm text-gray-300">
              <ShieldCheck className="h-4 w-4 text-pink-400" />
              <span>Admin</span>
            </div>
            <button
              onClick={() => setCompact((v) => !v)}
              className="rounded-lg border border-white/10 bg-white/[0.03] px-3 py-1.5 text-xs text-gray-300 hover:bg-white/[0.06] transition"
            >
              {compact ? "Expandido" : "Compacto"}
            </button>
          </div>
        </header>

        {/* filtros + busca */}
        <section className="relative overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-white/[0.05] to-white/[0.02] p-4 sm:p-5">
          <div className="pointer-events-none absolute inset-0 z-0 opacity-30 [mask-image:radial-gradient(60%_60%_at_50%_0%,#000_20%,transparent_70%)]">
            <div className="absolute -top-24 left-1/2 h-48 w-[36rem] -translate-x-1/2 rounded-full bg-pink-500/20 blur-3xl" />
          </div>

          {/* filtros */}
          <div className="relative z-10 mb-3 flex flex-wrap items-center gap-2">
            <Filter className="h-4 w-4 text-pink-300/80" />
            {filterDefs.map((f) => {
              const active = filter === f.key;
              return (
                <button
                  key={f.key}
                  onClick={() => setFilterAndGo(f.key)}
                  className={`rounded-full border px-3 py-1.5 text-xs font-medium transition ${
                    active
                      ? "border-pink-500/60 bg-pink-500/15 text-pink-200"
                      : "border-white/10 bg-white/[0.04] text-gray-300 hover:bg-white/[0.07]"
                  }`}
                >
                  {f.label}
                </button>
              );
            })}
            {resultCount && (
              <span className="ml-auto text-xs text-gray-400">
                {resultCount} resultado(s)
              </span>
            )}
          </div>

          {/* search bar grid */}
          <form onSubmit={doSearch} className="relative z-10">
            <div className="rounded-2xl border border-white/10 bg-[#0E0E10]/80 backdrop-blur transition focus-within:border-pink-500/50 focus-within:shadow-[0_0_0_4px_rgba(236,72,153,0.08)] px-3 py-2">
              <div className="grid grid-cols-[auto,1fr,auto] items-center gap-2">
                <span className="pl-1 text-gray-400">
                  {loading ? (
                    <Loader2 className="h-5 w-5 animate-spin text-pink-300" />
                  ) : (
                    <Search className="h-5 w-5" />
                  )}
                </span>

                <input
                  ref={inputRef}
                  type="search"
                  value={q}
                  onChange={(e) => setQ(e.target.value)}
                  placeholder="Buscar…  (/ foca • ⌘K palette)"
                  className="w-full rounded-xl border-0 bg-transparent py-3 text-sm text-gray-100 placeholder:text-gray-400 outline-none focus:ring-0"
                />

                <div className="flex items-center gap-2 pr-1">
                  <button
                    type="button"
                    onClick={() => {
                      setQp(q);
                      setPaletteIndex(0);
                      setPaletteOpen(true);
                    }}
                    className="hidden sm:inline-flex items-center rounded-lg border border-white/10 bg-white/[0.05] px-3 py-1.5 text-[11px] text-gray-300 hover:bg-white/[0.08] transition"
                  >
                    ⌘K
                  </button>
                  {q && (
                    <button
                      type="button"
                      onClick={clearQ}
                      className="inline-flex items-center justify-center rounded-lg border border-white/10 bg-white/[0.05] px-2 py-1.5 text-xs text-gray-300 hover:bg-white/[0.08]"
                    >
                      <X size={14} />
                    </button>
                  )}
                  <button
                    type="submit"
                    disabled={loading}
                    className="inline-flex items-center rounded-lg bg-pink-600/90 hover:bg-pink-600 px-3 py-1.5 text-xs font-medium text-white shadow-lg shadow-pink-600/20 disabled:opacity-60"
                  >
                    Buscar
                  </button>
                </div>
              </div>
            </div>
          </form>
        </section>

        {/* tabela */}
        <section className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
          <h2 className="text-lg font-semibold text-white mb-3">
            Lista de usuários
          </h2>
          {Array.isArray(list?.data) && list.data.length > 0 ? (
            <div className="overflow-x-auto">
              <table className={`min-w-full text-sm ${compact && "table-fixed"}`}>
                <thead>
                  <tr className="text-left text-gray-400">
                    <th className="px-3 py-2">Nome</th>
                    <th className="px-3 py-2">E-mail</th>
                    <th className="px-3 py-2">CPF/CNPJ</th>
                    <th className="px-3 py-2">Admin</th>
                    <th className="px-3 py-2">Criado em</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/10">
                  {list.data.map((u) => (
                    <tr key={u.id} className="text-gray-200">
                      <td className="px-3 py-2">{highlight(u.name, q)}</td>
                      <td className="px-3 py-2">{highlight(u.email, q)}</td>
                      <td className="px-3 py-2">{highlight(u.cpf_cnpj, q)}</td>
                      <td className="px-3 py-2">
                        <span
                          className={`inline-flex rounded-md px-2 py-0.5 text-xs ${
                            u.is_admin
                              ? "bg-pink-500/15 text-pink-300 border border-pink-500/30"
                              : "bg-white/[0.06] text-gray-300 border border-white/10"
                          }`}
                        >
                          {u.is_admin ? "Sim" : "Não"}
                        </span>
                      </td>
                      <td className="px-3 py-2">
                        {new Date(u.created_at).toLocaleDateString("pt-BR")}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="text-sm text-gray-400">Nenhum usuário encontrado.</p>
          )}
        </section>
      </div>

      {/* COMMAND PALETTE */}
      {paletteOpen && (
        <div
          className="fixed inset-0 z-50 flex items-start justify-center bg-black/70 backdrop-blur-sm"
          onClick={() => setPaletteOpen(false)}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            className="mt-24 w-full max-w-md rounded-2xl border border-white/10 bg-[#0E0E10]/95 p-4 shadow-2xl"
          >
            <div className="flex items-center gap-2 border-b border-white/10 pb-2">
              <Search className="h-4 w-4 text-pink-400" />
              <input
                autoFocus
                value={qp}
                onChange={(e) => {
                  setQp(e.target.value);
                  setPaletteIndex(0);
                }}
                onKeyDown={(e) => {
                  if (e.key === "ArrowDown")
                    setPaletteIndex((i) =>
                      Math.min(i + 1, paletteResults.length - 1)
                    );
                  if (e.key === "ArrowUp")
                    setPaletteIndex((i) => Math.max(i - 1, 0));
                  if (e.key === "Enter" && paletteResults[paletteIndex])
                    selectPaletteItem(paletteResults[paletteIndex]);
                }}
                placeholder="Digite para buscar…"
                className="flex-1 bg-transparent outline-none text-sm text-gray-100 placeholder:text-gray-500"
              />
            </div>

            <ul className="mt-3 max-h-60 overflow-y-auto">
              {paletteResults.map((u, i) => (
                <li
                  key={u.id}
                  onClick={() => selectPaletteItem(u)}
                  className={`flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm transition ${
                    i === paletteIndex
                      ? "bg-pink-600/20 text-pink-100"
                      : "text-gray-300 hover:bg-white/[0.05]"
                  }`}
                >
                  <User className="h-4 w-4 text-pink-300/80" />
                  <span className="flex-1">{u.name}</span>
                  <Mail className="h-4 w-4 opacity-60" />
                  <span className="text-xs text-gray-400">{u.email}</span>
                </li>
              ))}
              {paletteResults.length === 0 && (
                <li className="px-3 py-2 text-sm text-gray-400">
                  Nenhum resultado.
                </li>
              )}
            </ul>
          </div>
        </div>
      )}
    </AdminLayout>
  );
}
