import React from "react";
import { Head, Link } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import {
  ShieldCheck,
  Users,
  Settings,
  KeyRound,
  ArrowRight,
  BarChart3,
} from "lucide-react";

export default function AdminIndex({ title = "Admin", metrics = {}, users }) {
  const totalUsers = metrics?.totalUsers ?? 0;
  const totalAdmins = metrics?.totalAdmins ?? 0;

  return (
    <AdminLayout>
      <Head title={title} />

      <div className="space-y-6">
        {/* Cabeçalho */}
        <header className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold text-white">{title}</h1>
          <div className="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/[0.03] px-3 py-1.5 text-sm text-gray-300">
            <ShieldCheck className="h-4 w-4 text-pink-400" />
            <span>Acesso de administrador</span>
          </div>
        </header>

        {/* Métricas principais */}
        <section className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
          {/* Usuários */}
          <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-400">Usuários</span>
              <Users className="h-4 w-4 text-pink-400" />
            </div>
            <div className="mt-2 text-2xl font-semibold text-white">
              {totalUsers.toLocaleString("pt-BR")}
            </div>
            <p className="mt-1 text-xs text-gray-400">Total de usuários</p>
          </div>

          {/* Admins */}
          <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-400">Administradores</span>
              <KeyRound className="h-4 w-4 text-pink-400" />
            </div>
            <div className="mt-2 text-2xl font-semibold text-white">
              {totalAdmins.toLocaleString("pt-BR")}
            </div>
            <p className="mt-1 text-xs text-gray-400">
              Contas com permissões elevadas
            </p>
          </div>

          {/* Transações */}
          <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-400">Transações</span>
              <BarChart3 className="h-4 w-4 text-pink-400" />
            </div>
            <div className="mt-2 text-2xl font-semibold text-white">
              {metrics?.totalTransactions
                ? metrics.totalTransactions.toLocaleString("pt-BR")
                : "—"}
            </div>
            <p className="mt-1 text-xs text-gray-400">
              Total registrado no sistema
            </p>
          </div>

          {/* Segurança */}
          <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-400">Segurança</span>
              <ShieldCheck className="h-4 w-4 text-pink-400" />
            </div>
            <div className="mt-2 text-2xl font-semibold text-white">OK</div>
            <p className="mt-1 text-xs text-gray-400">
              Monitoramento e logs ativos
            </p>
          </div>
        </section>

        {/* Acesso rápido */}
        <section className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
          <h2 className="text-lg font-semibold text-white mb-3">
            Acesso rápido
          </h2>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <Link
              href={route("admin.users.index")}
              className="group flex items-center justify-between rounded-xl border border-white/10 bg-white/[0.04] px-4 py-3 text-gray-300 hover:bg-white/[0.08] transition"
            >
              <div className="flex items-center gap-2">
                <Users className="h-4 w-4 text-pink-400" />
                <span>Gerenciar Usuários</span>
              </div>
              <ArrowRight className="h-4 w-4 opacity-0 group-hover:opacity-100 transition" />
            </Link>

            <Link
              href="#"
              className="group flex items-center justify-between rounded-xl border border-white/10 bg-white/[0.04] px-4 py-3 text-gray-300 hover:bg-white/[0.08] transition"
            >
              <div className="flex items-center gap-2">
                <Settings className="h-4 w-4 text-pink-400" />
                <span>Configurações</span>
              </div>
              <ArrowRight className="h-4 w-4 opacity-0 group-hover:opacity-100 transition" />
            </Link>

            <Link
              href="#"
              className="group flex items-center justify-between rounded-xl border border-white/10 bg-white/[0.04] px-4 py-3 text-gray-300 hover:bg-white/[0.08] transition"
            >
              <div className="flex items-center gap-2">
                <ShieldCheck className="h-4 w-4 text-pink-400" />
                <span>Logs e Auditoria</span>
              </div>
              <ArrowRight className="h-4 w-4 opacity-0 group-hover:opacity-100 transition" />
            </Link>
          </div>
        </section>

        {/* Texto final */}
        <section className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
          <h2 className="text-lg font-semibold text-white">
            Bem-vindo ao painel administrativo
          </h2>
          <p className="mt-2 text-sm text-gray-300">
            Aqui você poderá gerenciar usuários, permissões, configurações e
            acompanhar auditorias do sistema.
          </p>
        </section>
      </div>
    </AdminLayout>
  );
}
