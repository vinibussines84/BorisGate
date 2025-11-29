import React, { useState, useEffect } from "react";
import { X } from "lucide-react";

export default function WebhookModal({ show, onClose, onSubmit }) {
  const [form, setForm] = useState({ url: "", type: "" });

  /* 🔄 Resetar formulário sempre que o modal fechar */
  useEffect(() => {
    if (!show) setForm({ url: "", type: "" });
  }, [show]);

  /* 📨 Enviar formulário */
  const handleSubmit = (e) => {
    e.preventDefault();
    if (form.url.trim() && form.type.trim()) {
      onSubmit(form);
    }
  };

  /* 🔒 Evita renderização fora de hora */
  if (!show) return null;

  return (
    <div
      className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 animate-fadeIn"
      onClick={(e) => {
        // fecha ao clicar fora do modal
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div className="bg-[#131517] border border-white/10 rounded-3xl shadow-2xl p-6 w-full max-w-lg relative animate-scaleIn">
        {/* BOTÃO FECHAR */}
        <button
          onClick={onClose}
          aria-label="Fechar Modal"
          className="absolute top-4 right-4 text-gray-400 hover:text-gray-200 transition"
        >
          <X size={22} />
        </button>

        {/* TÍTULO */}
        <h2 className="text-lg font-semibold text-white mb-6 flex items-center gap-2">
          <span className="h-5 w-1 bg-[#41FF85] rounded-full"></span>
          Novo Webhook
        </h2>

        {/* FORM */}
        <form onSubmit={handleSubmit}>
          {/* CAMPO URL */}
          <div className="mb-5">
            <label htmlFor="url" className="text-sm text-gray-300">
              URL de Postback
            </label>
            <input
              id="url"
              type="url"
              placeholder="https://seu-site.com/webhook"
              value={form.url}
              onChange={(e) => setForm({ ...form, url: e.target.value })}
              required
              autoFocus
              className="mt-2 w-full bg-[#0f1114] border border-white/10 rounded-xl px-4 py-3 
                text-sm text-white placeholder-gray-500 
                focus:border-[#41FF85]/60 focus:shadow-[0_0_8px_#41FF85]/30 
                outline-none transition-colors"
              style={{
                WebkitAppearance: "none",
                MozAppearance: "none",
                appearance: "none",
              }}
            />
          </div>

          {/* CAMPO SELECT */}
          <div className="mb-8">
            <label htmlFor="type" className="text-sm text-gray-300">
              Tipo de Eventos
            </label>
            <div className="relative mt-2">
              <select
                id="type"
                value={form.type}
                onChange={(e) => setForm({ ...form, type: e.target.value })}
                required
                className="w-full bg-[#0f1114] border border-white/10 rounded-xl px-4 py-3 
                  text-sm text-white 
                  focus:border-[#41FF85]/60 focus:shadow-[0_0_8px_#41FF85]/30 
                  outline-none appearance-none transition-colors"
                style={{
                  WebkitAppearance: "none",
                  MozAppearance: "none",
                  appearance: "none",
                }}
              >
                <option value="" disabled>
                  Selecionar tipo de evento
                </option>
                <option value="transacoes">Transações</option>
                <option value="saques">Saques</option>
                <option value="todos">Todos</option>
              </select>

              {/* ÍCONE SETA */}
              <div className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  className="h-4 w-4 text-gray-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  strokeWidth="2"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M19 9l-7 7-7-7"
                  />
                </svg>
              </div>
            </div>
          </div>

          {/* BOTÕES */}
          <div className="flex justify-end gap-3">
            <button
              type="button"
              onClick={onClose}
              className="px-6 py-2 rounded-full border border-white/10 bg-white/5 text-gray-300 hover:bg-white/10 transition"
            >
              Cancelar
            </button>
            <button
              type="submit"
              disabled={!form.url || !form.type}
              className="px-6 py-2 rounded-full bg-[#41FF85] text-[#0B0B0B] font-semibold hover:brightness-110 active:scale-95 transition disabled:opacity-50"
            >
              Cadastrar Webhook
            </button>
          </div>
        </form>
      </div>

      {/* 💅 FIX universal para Safari/Chrome autofill */}
      <style>{`
        @keyframes scaleIn {
          from { transform: scale(0.9); opacity: 0; }
          to { transform: scale(1); opacity: 1; }
        }
        .animate-scaleIn {
          animation: scaleIn 0.2s ease-out;
        }
        input:-webkit-autofill,
        select:-webkit-autofill {
          -webkit-text-fill-color: #fff !important;
          box-shadow: 0 0 0px 1000px #0f1114 inset !important;
          background-color: #0f1114 !important;
        }
      `}</style>
    </div>
  );
}
