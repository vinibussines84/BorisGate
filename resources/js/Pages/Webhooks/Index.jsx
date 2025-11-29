/** ✅ COMPLETE + WITHOUT REGISTER BUTTON + WITHOUT REGISTER MODAL **/

import React, { useState, useEffect } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
  Link2,
  Clock,
  RefreshCcw,
  RotateCcw,
  X,
  Activity,
  Database,
  ArrowLeftRight,
  ChevronsLeft,
  ChevronLeft,
  ChevronRight,
  ChevronsRight,
} from "lucide-react";

/* ----------------------------------------
 * Date Formatter
 * ---------------------------------------- */
const fmtDateTime = (iso) => {
    if (!iso) return "—";
    try {
        const d = new Date(iso);
        return d.toLocaleString("en-US", {
            dateStyle: "short",
            timeStyle: "short",
        });
    } catch {
        return "—";
    }
};

/* ----------------------------------------
 * Local Pagination
 * ---------------------------------------- */
function paginate(data, page, perPage = 10) {
    const total = data.length;
    const totalPages = Math.ceil(total / perPage);
    const start = (page - 1) * perPage;
    const end = start + perPage;
    return {
        items: data.slice(start, end),
        totalPages,
    };
}

/* ----------------------------------------
 * Pagination Component
 * ---------------------------------------- */
const Pagination = ({ page, totalPages, setPage }) => {
    if (totalPages <= 1) return null;
    return (
        <div className="flex items-center justify-center gap-3 mt-6">
            <button
                onClick={() => setPage(1)}
                disabled={page === 1}
                className="p-2 rounded-full border border-white/10 text-gray-300 hover:bg-white/10 disabled:opacity-30"
            >
                <ChevronsLeft size={16} />
            </button>

            <button
                onClick={() => setPage(page - 1)}
                disabled={page === 1}
                className="p-2 rounded-full border border-white/10 text-gray-300 hover:bg-white/10 disabled:opacity-30"
            >
                <ChevronLeft size={16} />
            </button>

            <span className="px-4 py-1 rounded-full border border-white/10 text-gray-300 text-sm">
                Page {page} of {totalPages}
            </span>

            <button
                onClick={() => setPage(page + 1)}
                disabled={page === totalPages}
                className="p-2 rounded-full border border-white/10 text-gray-300 hover:bg-white/10 disabled:opacity-30"
            >
                <ChevronRight size={16} />
            </button>

            <button
                onClick={() => setPage(totalPages)}
                disabled={page === totalPages}
                className="p-2 rounded-full border border-white/10 text-gray-300 hover:bg-white/10 disabled:opacity-30"
            >
                <ChevronsRight size={16} />
            </button>
        </div>
    );
};

/* ----------------------------------------
 * Toast
 * ---------------------------------------- */
const Toast = ({ message, type = "success", onClose }) => {
    if (!message) return null;
    const color =
        type === "error"
            ? "bg-red-500/20 text-red-400 border border-red-500/40"
            : "bg-[#02fb5c]/20 text-[#02fb5c] border border-[#02fb5c]/30";

    return (
        <div
            className={`fixed bottom-6 right-6 px-5 py-3 rounded-xl ${color} backdrop-blur-md flex items-center gap-3 shadow-lg animate-fade-in`}
        >
            <span className="font-medium text-sm">{message}</span>
            <button onClick={onClose} className="text-white/60 hover:text-white">
                <X size={14} />
            </button>
        </div>
    );
};

/* ----------------------------------------
 * Confirmation Modal
 * ---------------------------------------- */
const ConfirmModal = ({ show, onConfirm, onCancel }) => {
    if (!show) return null;
    return (
        <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50">
            <div className="bg-[#0b0b0b] border border-white/10 rounded-2xl p-8 max-w-sm w-full text-center shadow-[0_0_20px_rgba(0,0,0,0.8)]">
                <h2 className="text-lg font-semibold text-white mb-3">
                    Do you want to resend this webhook?
                </h2>
                <p className="text-gray-400 text-sm mb-6">
                    The event will be resent to the configured endpoint.
                </p>

                <div className="flex justify-center gap-4">
                    <button
                        onClick={onCancel}
                        className="px-5 py-2.5 rounded-full border border-white/20 text-gray-300 hover:bg-white/10 transition"
                    >
                        Cancel
                    </button>

                    <button
                        onClick={onConfirm}
                        className="px-5 py-2.5 rounded-full bg-[#02fb5c] text-[#0b0b0b] font-medium shadow-[0_0_10px_rgba(2,251,92,0.4)] hover:brightness-110 active:scale-95 transition"
                    >
                        Resend
                    </button>
                </div>
            </div>
        </div>
    );
};

/* ----------------------------------------
 * Main Page
 * ---------------------------------------- */
export default function WebhooksIndex({ webhooks = [] }) {
    const [activeTab, setActiveTab] = useState("webhooks");
    const [webhookPage, setWebhookPage] = useState(1);
    const [logPage, setLogPage] = useState(1);

    const [logs, setLogs] = useState([]);
    const [loadingLogs, setLoadingLogs] = useState(false);
    const [resendingId, setResendingId] = useState(null);
    const [confirmId, setConfirmId] = useState(null);
    const [toast, setToast] = useState({ message: "", type: "success" });

    /* -------- Ask to Resend -------- */
    const handleAskResend = (id) => setConfirmId(id);
    const closeConfirm = () => setConfirmId(null);

    /* -------- Resend Webhook -------- */
    const handleResend = async () => {
        const id = confirmId;
        if (!id) return;

        setConfirmId(null);
        setResendingId(id);

        try {
            const token = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content");

            const response = await fetch(`/webhooks/resend/${id}`, {
                method: "POST",
                headers: { "X-CSRF-TOKEN": token, Accept: "application/json" },
            });

            const data = await response.json();

            if (!response.ok) throw new Error(data.message || "Failed to resend");

            setToast({
                message: "✔️ Webhook successfully resent!",
                type: "success",
            });

            fetchLogs();
        } catch {
            setToast({ message: "✖️ Failed to resend webhook.", type: "error" });
        } finally {
            setResendingId(null);
            setTimeout(() => setToast({ message: "", type: "success" }), 4000);
        }
    };

    /* -------- Auto Logs Fetch -------- */
    const fetchLogs = async () => {
        setLoadingLogs(true);
        try {
            const res = await fetch("/webhooks/logs", {
                headers: { Accept: "application/json" },
            });
            if (!res.ok) throw new Error();
            const data = await res.json();
            setLogs(data);
        } catch {
            setToast({ message: "✖️ Error loading logs.", type: "error" });
        } finally {
            setLoadingLogs(false);
            setTimeout(() => setToast({ message: "", type: "success" }), 4000);
        }
    };

    useEffect(() => {
        fetchLogs();
    }, []);

    const webhooksPaginated = paginate(webhooks, webhookPage, 10);
    const logsPaginated = paginate(logs, logPage, 10);

    return (
        <AuthenticatedLayout>
            <Head title="Webhooks" />

            <div className="min-h-screen bg-[#0b0b0b] py-10 px-6 text-gray-100">
                <div className="max-w-6xl mx-auto space-y-8">
                    {/* HEADER */}
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                        <div className="flex-1 bg-[#0b0b0b]/90 rounded-full px-8 py-5 flex items-center justify-between shadow-[0_0_20px_-8px_rgba(0,0,0,0.8)] border border-white/10">
                            <div className="flex items-center gap-3">
                                <span className="w-2 h-8 bg-[#02fb5c] rounded-full shadow-[0_0_10px_rgba(2,251,92,0.5)]"></span>

                                <h1 className="text-xl sm:text-2xl font-semibold text-white">
                                    Webhook Management
                                </h1>
                            </div>

                            {/* TABS */}
                            <div className="hidden sm:flex bg-[#111]/70 rounded-full p-1 border border-white/10 ml-6">
                                {["webhooks", "logs"].map((tab) => (
                                    <button
                                        key={tab}
                                        onClick={() => setActiveTab(tab)}
                                        className={`px-6 py-2 rounded-full text-sm font-medium transition ${
                                            activeTab === tab
                                                ? "bg-[#02fb5c] text-black shadow-[0_0_10px_rgba(2,251,92,0.5)]"
                                                : "text-gray-400 hover:text-white"
                                        }`}
                                    >
                                        {tab === "webhooks"
                                            ? "Webhooks"
                                            : "Sent Events"}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* OVERVIEW */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="bg-[#0b0b0b]/90 rounded-2xl border border-white/10 p-5 flex items-center gap-4 shadow-[0_0_15px_-5px_rgba(0,0,0,0.8)]">
                            <div className="bg-[#02fb5c]/10 p-3 rounded-xl border border-[#02fb5c]/30">
                                <Database size={22} className="text-[#02fb5c]" />
                            </div>
                            <div>
                                <p className="text-sm text-gray-400">Registered webhooks</p>
                                <h3 className="text-xl font-bold text-white">
                                    {webhooks.length}
                                </h3>
                            </div>
                        </div>

                        <div className="bg-[#0b0b0b]/90 rounded-2xl border border-white/10 p-5 flex items-center gap-4 shadow-[0_0_15px_-5px_rgba(0,0,0,0.8)]">
                            <div className="bg-[#02fb5c]/10 p-3 rounded-xl border border-[#02fb5c]/30">
                                <Activity size={22} className="text-[#02fb5c]" />
                            </div>
                            <div>
                                <p className="text-sm text-gray-400">Recent events</p>
                                <h3 className="text-xl font-bold text-white">
                                    {logs.length ?? 0}
                                </h3>
                            </div>
                        </div>
                    </div>

                    {/* TABLES */}
                    {activeTab === "webhooks" ? (
                        <>
                            <div className="bg-[#0b0b0b]/90 rounded-3xl border border-white/10 shadow-xl p-6 overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="text-gray-400 border-b border-white/10">
                                        <tr>
                                            <th className="text-left pb-3">Postback URL</th>
                                            <th className="text-left pb-3">Event Type</th>
                                            <th className="text-left pb-3">Created At</th>
                                            <th className="text-right pb-3">Actions</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {webhooksPaginated.items.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={4}
                                                    className="py-10 text-center text-gray-500"
                                                >
                                                    No webhooks registered.
                                                </td>
                                            </tr>
                                        ) : (
                                            webhooksPaginated.items.map((hook) => (
                                                <tr
                                                    key={hook.id}
                                                    className="border-b border-white/5 hover:bg-white/5 transition"
                                                >
                                                    <td className="py-3 pr-4 text-[#02fb5c] font-medium">
                                                        <div className="flex items-center gap-2">
                                                            <Link2 size={14} />
                                                            <a
                                                                href={hook.url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="truncate hover:underline max-w-[320px]"
                                                            >
                                                                {hook.url}
                                                            </a>
                                                        </div>
                                                    </td>

                                                    <td className="py-3 pr-4 text-gray-300 capitalize whitespace-nowrap">
                                                        <div className="flex items-center gap-2">
                                                            <ArrowLeftRight
                                                                size={16}
                                                                className="text-[#02fb5c]"
                                                            />
                                                            {hook.type}
                                                        </div>
                                                    </td>

                                                    <td className="py-3 pr-4 text-gray-400 whitespace-nowrap">
                                                        <Clock size={14} className="inline mr-1" />
                                                        {fmtDateTime(hook.created_at)}
                                                    </td>

                                                    <td className="py-3 text-right text-gray-500 italic text-xs">
                                                        —
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            <Pagination
                                page={webhookPage}
                                totalPages={webhooksPaginated.totalPages}
                                setPage={setWebhookPage}
                            />
                        </>
                    ) : (
                        <>
                            <div className="bg-[#0b0b0b]/90 rounded-3xl border border-white/10 shadow-xl p-6 overflow-x-auto">
                                <div className="flex items-center justify-between mb-4">
                                    <h2 className="text-lg font-semibold text-white">
                                        Event History
                                    </h2>

                                    <button
                                        onClick={fetchLogs}
                                        disabled={loadingLogs}
                                        className="flex items-center gap-2 text-sm px-4 py-2 rounded-full border border-[#02fb5c]/30 text-[#02fb5c] hover:bg-[#02fb5c]/10 transition disabled:opacity-50"
                                    >
                                        <RefreshCcw size={16} />
                                        Refresh
                                    </button>
                                </div>

                                {loadingLogs ? (
                                    <div className="text-center py-8 text-gray-400">
                                        Loading events...
                                    </div>
                                ) : logsPaginated.items.length === 0 ? (
                                    <div className="text-center py-8 text-gray-500">
                                        No events registered yet.
                                    </div>
                                ) : (
                                    <table className="min-w-full text-sm">
                                        <thead className="text-gray-400 border-b border-white/10">
                                            <tr>
                                                <th className="text-left py-2">Type</th>
                                                <th className="text-left py-2">Status</th>
                                                <th className="text-left py-2">Code</th>
                                                <th className="text-left py-2">Date</th>
                                                <th className="text-right py-2">Actions</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            {logsPaginated.items.map((log) => (
                                                <tr
                                                    key={log.id}
                                                    className="border-b border-white/5 hover:bg-white/5 transition"
                                                >
                                                    <td className="py-3 text-gray-200">{log.type}</td>

                                                    <td
                                                        className={`py-3 capitalize ${
                                                            log.status === "success"
                                                                ? "text-green-400"
                                                                : log.status === "error"
                                                                ? "text-red-400"
                                                                : "text-gray-400"
                                                        }`}
                                                    >
                                                        {log.status}
                                                    </td>

                                                    <td className="py-3">
                                                        {log.response_code ? (
                                                            <span
                                                                className={`px-3 py-1 text-xs font-semibold rounded-full ${
                                                                    log.response_code >= 200 &&
                                                                    log.response_code < 300
                                                                        ? "bg-green-500/20 text-green-400 border border-green-500/30"
                                                                        : log.response_code >= 400 &&
                                                                          log.response_code < 500
                                                                        ? "bg-yellow-500/20 text-yellow-300 border border-yellow-500/30"
                                                                        : log.response_code >= 500
                                                                        ? "bg-red-500/20 text-red-400 border border-red-500/30"
                                                                        : "bg-gray-500/20 text-gray-400 border border-gray-500/30"
                                                                }`}
                                                            >
                                                                {log.response_code}
                                                            </span>
                                                        ) : (
                                                            "—"
                                                        )}
                                                    </td>

                                                    <td className="py-3 text-gray-400 whitespace-nowrap">
                                                        {fmtDateTime(log.created_at)}
                                                    </td>

                                                    <td className="py-3 text-right">
                                                        <button
                                                            onClick={() =>
                                                                handleAskResend(log.id)
                                                            }
                                                            disabled={
                                                                resendingId === log.id
                                                            }
                                                            className={`p-2 rounded-lg transition ${
                                                                resendingId === log.id
                                                                    ? "text-blue-400 animate-pulse"
                                                                    : "text-gray-400 hover:text-blue-400 hover:bg-blue-400/10"
                                                            }`}
                                                            title="Resend webhook"
                                                        >
                                                            <RotateCcw size={16} />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </div>

                            <Pagination
                                page={logPage}
                                totalPages={logsPaginated.totalPages}
                                setPage={setLogPage}
                            />
                        </>
                    )}
                </div>
            </div>

            {/* Resend Modal */}
            <ConfirmModal
                show={!!confirmId}
                onConfirm={handleResend}
                onCancel={closeConfirm}
            />

            <Toast
                message={toast.message}
                type={toast.type}
                onClose={() => setToast({ message: "" })}
            />
        </AuthenticatedLayout>
    );
}
