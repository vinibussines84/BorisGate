import React, { useEffect, useMemo, useRef, useState } from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
  ArrowLeft,
  KeyRound,
  PlusCircle,
  Loader2,
  Mail,
  Phone,
  User,
  Hash,
  Trash2,
  Lock,
  ShieldCheck,
  Info,
  ChevronDown,
  Check,
  Share2,
} from "lucide-react";

/* =======================================================
   Helpers (label, ícone e cor)
======================================================= */
const normalizeType = (t = "") =>
  t.toUpperCase().replace("TELEFONE", "PHONE").replace("ALEATORIA", "EVP");

const typeLabel = (t = "") => {
  const T = normalizeType(t);
  if (T === "EVP") return "Chave Aleatória";
  if (T === "CPF") return "CPF";
  if (T === "CNPJ") return "CNPJ";
  if (T === "EMAIL") return "E-mail";
  if (T === "PHONE") return "Telefone";
  return t || "Chave";
};

const TypeIcon = ({ type, className = "text-zinc-300" }) => {
  const T = normalizeType(type);
  if (T === "CPF" || T === "CNPJ") return <User size={18} className={className} />;
  if (T === "EMAIL") return <Mail size={18} className={className} />;
  if (T === "PHONE") return <Phone size={18} className={className} />;
  if (T === "EVP") return <Hash size={18} className={className} />;
  return <KeyRound size={18} className={className} />;
};

const statusPill = (s = "") => {
  const S = s.toUpperCase();
  if (S === "ATIVA")
    return { bg: "bg-emerald-500/15", text: "text-emerald-300", ring: "ring-emerald-500/30", label: "Ativa" };
  if (S === "PENDENTE")
    return { bg: "bg-amber-500/15", text: "text-amber-300", ring: "ring-amber-500/30", label: "Pendente" };
  if (S === "BLOQUEADA")
    return { bg: "bg-rose-500/15", text: "text-rose-300", ring: "ring-rose-500/30", label: "Bloqueada" };
  if (S === "INATIVA")
    return { bg: "bg-zinc-600/20", text: "text-zinc-300", ring: "ring-zinc-500/30", label: "Inativa" };
  if (["DELETADA", "EXCLUIDA", "EXCLUÍDA", "DELETED"].includes(S))
    return { bg: "bg-zinc-700/30", text: "text-zinc-300", ring: "ring-zinc-600/30", label: "Deletada" };
  return { bg: "bg-zinc-600/20", text: "text-zinc-300", ring: "ring-zinc-500/30", label: s || "—" };
};

const getCsrf = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

/* Normaliza status para comparação (uppercase, sem acento) */
const norm = (s = "") =>
  s
    .toString()
    .toUpperCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "");

/* buckets do filtro */
const STATUS_BUCKETS = {
  TODAS: null,
  ATIVAS: ["ATIVA"],
  DESATIVAS: ["INATIVA", "BLOQUEADA"],
  DELETADAS: ["DELETADA", "EXCLUIDA", "EXCLUÍDA", "DELETED"],
};

/* =======================================================
   Dropdown custom para filtro (sem <select> nativo)
======================================================= */
const FilterDropdown = ({ value, onChange }) => {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    const onClick = (e) => {
      if (!ref.current) return;
      if (!ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, []);

  const options = [
    { v: "TODAS", label: "Todas" },
    { v: "ATIVAS", label: "Ativas" },
    { v: "DESATIVAS", label: "Desativas" },
    { v: "DELETADAS", label: "Deletadas" },
  ];

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-zinc-200 hover:bg-white/10 focus:outline-none"
        aria-haspopup="listbox"
        aria-expanded={open}
      >
        {options.find((o) => o.v === value)?.label || "Filtro"}
        <ChevronDown size={16} className="text-zinc-400" />
      </button>

      {open && (
        <div
          role="listbox"
          // A classe 'z-20' garante que o dropdown fique acima de outros elementos com z-index menor ou implícito.
          className="absolute right-0 mt-2 w-44 rounded-2xl border border-white/10 bg-[#141414] text-white shadow-2xl p-1 z-20"
        >
          {options.map((o) => {
            const active = o.v === value;
            return (
              <button
                key={o.v}
                role="option"
                aria-selected={active}
                onClick={() => {
                  onChange(o.v);
                  setOpen(false);
                }}
                className={`w-full text-left px-3 py-2 rounded-xl transition ${
                  active
                    ? "bg-white/10 text-white"
                    : "text-zinc-300 hover:bg-white/5"
                }`}
              >
                <span className="flex items-center gap-2">
                  {active && <Check size={16} className="text-emerald-400" />}
                  <span className="text-sm">{o.label}</span>
                </span>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
};

/* =======================================================
   Modal: Criar Chave (Refatorado para Grid 2x2)
======================================================= */
const CreateKeyModal = ({ isOpen, onClose, onSuccess }) => {
  const [selectedType, setSelectedType] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  if (!isOpen) return null;

  const types = [
    { label: "Aleatória", value: "EVP", desc: "Chave única e segura" },
    { label: "E-mail", value: "EMAIL", desc: "Seu endereço de e-mail" },
    { label: "CPF", value: "CPF", desc: "Seu documento pessoal" },
    { label: "Telefone", value: "PHONE", desc: "Seu número de celular" },
    // CNPJ é menos comum, mas pode ser adicionado
    // { label: "CNPJ", value: "CNPJ", desc: "Documento da sua empresa" }, 
  ];

  const handleCreate = async () => {
    if (!selectedType) return setError("Selecione o tipo de chave Pix.");
    setLoading(true);
    setError(null);
    try {
      const res = await fetch("/api/stric/pix/keys", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": getCsrf(),
        },
        body: JSON.stringify({ keyType: selectedType }),
      });
      const data = await res.json();
      if (data.success) {
        onSuccess?.(selectedType);
        onClose();
      } else {
        setError(data.message || "Erro ao criar chave Pix.");
      }
    } catch {
      setError("Erro de conexão com o servidor.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/80 backdrop-blur-sm">
      <div
        className="w-full max-w-lg rounded-3xl border border-white/10 bg-[#111111] p-8 shadow-2xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-start justify-between">
            <div className="flex items-center gap-3 mb-1">
                <div className="grid h-8 w-8 place-items-center rounded-xl ring-1 ring-inset ring-emerald-500/30 bg-emerald-500/10">
                    <KeyRound size={16} className="text-emerald-400" />
                </div>
                <div>
                    <h2 className="text-xl font-bold text-white">Criar Chave Pix</h2>
                    <p className="text-zinc-400 text-sm">
                      Escolha o tipo de identificação.
                    </p>
                </div>
            </div>
            <button
                onClick={onClose}
                className="p-1.5 rounded-lg text-zinc-400 hover:text-white hover:bg-white/5 transition"
                title="Fechar"
            >
                <ArrowLeft size={20} />
            </button>
        </div>

        <div className="mt-6 grid grid-cols-2 gap-4">
          {types.map((t) => (
            <button
              key={t.value}
              onClick={() => setSelectedType(t.value)}
              className={`flex flex-col items-start rounded-xl border p-4 text-left transition duration-200
                ${
                  selectedType === t.value
                    ? "border-emerald-500 bg-emerald-600/10 text-emerald-100 shadow-lg shadow-emerald-900/20 ring-4 ring-emerald-500/10"
                    : "border-white/10 bg-white/[0.03] text-zinc-200 hover:border-emerald-500/30 hover:bg-white/10"
                }
              `}
            >
                <div className="flex items-center gap-3">
                    <div className={`grid h-10 w-10 place-items-center rounded-lg ring-1 ring-inset ${selectedType === t.value ? 'ring-emerald-500/50 bg-emerald-500/20' : 'ring-white/10 bg-white/5'}`}>
                        <TypeIcon type={t.value} className={selectedType === t.value ? 'text-emerald-300' : 'text-zinc-300'} />
                    </div>
                    <span className={`text-base font-semibold ${selectedType === t.value ? 'text-white' : 'text-zinc-100'}`}>
                        {t.label}
                    </span>
                </div>
                <p className="mt-2 text-xs text-zinc-400">{t.desc}</p>
            </button>
          ))}
        </div>

        {error && <p className="text-rose-400 text-sm font-medium mt-4">{error}</p>}
        
        <div className="mt-8 flex items-center justify-between gap-2 border-t border-white/5 pt-4">
            <p className="text-xs text-zinc-500 flex items-center gap-1">
                <Info size={14} /> Somente chaves ativas podem receber PIX.
            </p>
            <button
                onClick={handleCreate}
                disabled={loading || !selectedType}
                className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-emerald-500 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {loading ? <Loader2 size={16} className="animate-spin" /> : <PlusCircle size={16} />}
                {loading ? "Criando..." : `Criar Chave ${typeLabel(selectedType).split(' ')[0]}`}
            </button>
        </div>
      </div>
    </div>
  );
};

/* =======================================================
   Modal: Confirmar Exclusão
======================================================= */
const DeleteKeyModal = ({ isOpen, onClose, onConfirm, keyData, loading }) => {
  if (!isOpen) return null;
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/80 backdrop-blur-sm">
      <div
        className="w-full max-w-sm rounded-2xl border border-white/10 bg-[#111111] p-6 shadow-2xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl ring-1 ring-inset ring-rose-500/30 bg-rose-500/10">
          <Lock size={22} className="text-rose-400" />
        </div>
        <h3 className="text-center text-white text-lg font-semibold">Confirmar exclusão</h3>
        <p className="mt-1 text-center text-sm text-zinc-400">
          Tem certeza que deseja excluir a chave{" "}
          <span className="text-rose-300 font-medium">{keyData?.value}</span>?
        </p>

        <div className="mt-6 flex items-center justify-center gap-2">
          <button
            onClick={onClose}
            disabled={loading}
            className="rounded-xl border border-white/10 bg-white/5 px-5 py-2 text-sm text-zinc-200 hover:bg-white/10"
          >
            Cancelar
          </button>
          <button
            onClick={onConfirm}
            disabled={loading}
            className="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-6 py-2 text-sm font-semibold text-white hover:bg-rose-500 transition disabled:opacity-60"
          >
            {loading ? <Loader2 size={16} className="animate-spin" /> : <Trash2 size={16} />}
            {loading ? "Excluindo..." : "Excluir"}
          </button>
        </div>
      </div>
    </div>
  );
};

/* =======================================================
   Card de Chave (com Compartilhar)
======================================================= */
const KeyCard = ({ id, type, value, status, onDelete }) => {
  const pill = useMemo(() => statusPill(status), [status]);
  const [shared, setShared] = useState(false);

  const handleShare = async () => {
    try {
      await navigator.clipboard.writeText(String(value || ""));
      setShared(true);
      setTimeout(() => setShared(false), 1400);
    } catch {}
  };

  return (
    <div className="group relative overflow-hidden rounded-2xl border border-white/10 bg-[#111111] p-6 transition hover:border-emerald-500/30 hover:bg-white/[0.04]">
      <div className="pointer-events-none absolute -top-28 -right-24 h-40 w-40 rounded-full bg-emerald-500/10 blur-3xl opacity-0 group-hover:opacity-100 transition" />
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="grid h-12 w-12 place-items-center rounded-xl ring-1 ring-inset ring-white/10 bg-white/5">
            <TypeIcon type={type} />
          </div>
          <div className="min-w-0">
            <div className="text-sm text-zinc-400">{typeLabel(type)}</div>
            <div className="text-[13px] text-white/90 break-all">{value}</div>
          </div>
        </div>

        <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-medium ring-1 ${pill.bg} ${pill.text} ${pill.ring}`}>
          <ShieldCheck size={12} />
          {pill.label}
        </span>
      </div>

      <div className="mt-4 flex items-center justify-between gap-2">
        <span className="text-[11px] text-zinc-500">
          ID: <span className="text-zinc-300">{id}</span>
        </span>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={handleShare}
            className={`inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs transition ${
              shared ? "text-emerald-300 hover:bg-emerald-900/10" : "text-zinc-300 hover:bg-white/10"
            }`}
            title="Copiar chave"
          >
            {shared ? <Check size={14} /> : <Share2 size={14} />}
            {shared ? "Copiado!" : "Compartilhar"}
          </button>

          <button
            onClick={() => onDelete({ id, type, value })}
            className="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs text-zinc-300 hover:text-rose-300 hover:border-rose-400/30 hover:bg-rose-500/10 transition"
            title="Excluir chave"
          >
            <Trash2 size={14} />
            Excluir
          </button>
        </div>
      </div>
    </div>
  );
};

/* =======================================================
   Skeleton (Carregando)
======================================================= */
const KeyCardSkeleton = () => (
  <div className="rounded-2xl border border-white/10 bg-[#111111] p-6 animate-pulse">
    <div className="flex items-center justify-between">
      <div className="flex items-center gap-3">
        <div className="h-12 w-12 rounded-xl bg-white/10" />
        <div>
          <div className="h-3 w-28 rounded bg-white/10" />
          <div className="mt-2 h-3 w-44 rounded bg-white/10" />
        </div>
      </div>
      <div className="h-5 w-24 rounded-full bg-white/10" />
    </div>
    <div className="mt-4 h-6 w-32 rounded bg-white/10" />
  </div>
);

/* =======================================================
   Página Principal
======================================================= */
export default function GenerateKeys() {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [keyToDelete, setKeyToDelete] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [chaves, setChaves] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [statusFilter, setStatusFilter] = useState("TODAS");

  const filteredChaves = useMemo(() => {
    const bucket = STATUS_BUCKETS[statusFilter];
    if (!bucket) return chaves;
    return chaves.filter((c) => bucket.includes(norm(c?.status)));
  }, [chaves, statusFilter]);

  const fetchKeys = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch("/api/stric/pix/keys");
      const data = await res.json();
      if (data.success) setChaves(data.keys || []);
      else setError(data.message || "Erro ao buscar chaves Pix.");
    } catch {
      setError("Erro de conexão com o servidor.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchKeys();
  }, []);

  const openDeleteModal = (key) => {
    setKeyToDelete(key);
    setDeleteModalOpen(true);
  };

  const handleDeleteConfirm = async () => {
    if (!keyToDelete) return;
    setDeleteLoading(true);
    setError(null);
    try {
      const res = await fetch(`/api/stric/pix/keys/${keyToDelete.id}`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": getCsrf(),
        },
      });
      const data = await res.json();
      if (data.success) {
        setChaves((prev) => prev.filter((c) => c.id !== keyToDelete.id));
        setDeleteModalOpen(false);
      } else {
        setError(data.message || "Erro ao excluir chave Pix.");
      }
    } catch {
      setError("Erro de conexão ao excluir chave.");
    } finally {
      setDeleteLoading(false);
    }
  };

  return (
    <AuthenticatedLayout>
      <Head title="Chaves Pix" />

      <div className="min-h-screen bg-[#0B0B0B] text-white selection:bg-emerald-600/30">
        {/* Header */}
        <div className="sticky top-0 z-10 border-b border-white/10 bg-[#0B0B0B]/80 backdrop-blur supports-[backdrop-filter]:bg-[#0B0B0B]/70">
          <div className="mx-auto max-w-7xl px-4 py-4 flex items-center justify-between">
            <button
              onClick={() => (window.location.href = "/pix")}
              className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-zinc-300 hover:text-white hover:bg-white/10 transition"
            >
              <ArrowLeft size={16} /> Voltar
            </button>

            <div className="flex items-center gap-2">
              <KeyRound size={18} className="text-emerald-400" />
              <h1 className="text-base font-semibold tracking-tight">Minhas Chaves Pix</h1>
            </div>

            <div className="text-[11px] px-2 py-0.5 rounded-full bg-emerald-900/25 border border-emerald-700/30 text-emerald-300 inline-flex items-center gap-1">
              <ShieldCheck size={12} /> Seguro
            </div>
          </div>
        </div>

        {/* Conteúdo */}
        <div className="mx-auto max-w-7xl px-4 py-8 space-y-8">
          {/* CTA + Filtro */}
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-zinc-400 max-w-2xl">
              Cadastre e gerencie suas chaves Pix. Você pode ter uma chave aleatória (EVP) e/ou chaves como CPF, e-mail e telefone.
            </p>

            <div className="flex items-center gap-2">
              {/* Dropdown custom */}
              <FilterDropdown value={statusFilter} onChange={setStatusFilter} />

              {/* Nova Chave */}
              <button
                onClick={() => setIsModalOpen(true)}
                className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 transition"
              >
                <PlusCircle size={16} />
                Nova Chave
              </button>
            </div>
          </div>

          {/* Lista / estados */}
          {loading ? (
            <div className="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(420px,1fr))]">
              {Array.from({ length: 6 }).map((_, i) => (
                <KeyCardSkeleton key={i} />
              ))}
            </div>
          ) : error ? (
            <div className="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-200">
              <div className="flex items-start gap-2">
                <Info size={16} className="mt-0.5" />
                <div>
                  <div className="font-medium mb-0.5">Não foi possível carregar suas chaves</div>
                  <div>{error}</div>
                  <button
                    onClick={fetchKeys}
                    className="mt-3 inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs text-zinc-200 hover:bg-white/10 transition"
                  >
                    Tentar novamente
                  </button>
                </div>
              </div>
            </div>
          ) : filteredChaves.length === 0 ? (
            <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center">
              <div className="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl ring-1 ring-inset ring-white/10 bg-white/5">
                <KeyRound className="text-zinc-300" size={20} />
              </div>
              <h3 className="text-white font-semibold">Nenhuma chave neste filtro</h3>
              <p className="mt-1 text-sm text-zinc-400">
                Ajuste o filtro de status ou crie uma nova chave para começar.
              </p>
              <div className="mt-5 flex items-center justify-center gap-2">
                <button
                  onClick={() => setStatusFilter("TODAS")}
                  className="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-xs text-zinc-200 hover:bg-white/10 transition"
                >
                  Limpar filtro
                </button>
                <button
                  onClick={() => setIsModalOpen(true)}
                  className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 transition"
                >
                  <PlusCircle size={16} />
                  Criar Chave
                </button>
              </div>
            </div>
          ) : (
            <div className="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(420px,1fr))]">
              {filteredChaves.map((chave) => (
                <KeyCard key={chave.id} {...chave} onDelete={openDeleteModal} />
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Modais */}
      <CreateKeyModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSuccess={fetchKeys}
      />

      <DeleteKeyModal
        isOpen={deleteModalOpen}
        onClose={() => setDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        keyData={keyToDelete}
        loading={deleteLoading}
      />
    </AuthenticatedLayout>
  );
}