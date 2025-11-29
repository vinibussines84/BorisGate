import React, { useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import axios from "axios";
import { X } from "lucide-react";

/**
 * Formata números monetários (estilo Nubank)
 */
function maskCurrency(value) {
  if (!value) return "";
  let v = value.replace(/\D/g, "");
  v = (v / 100).toFixed(2) + "";
  return v.replace(".", ",");
}

/**
 * Formata chaves conforme tipo
 */
function maskPixKey(type, value) {
  let v = value.replace(/\s/g, "");

  switch (type) {
    case "cpf":
      v = v
        .replace(/\D/g, "")
        .replace(/(\d{3})(\d)/, "$1.$2")
        .replace(/(\d{3})(\d)/, "$1.$2")
        .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
      break;

    case "cnpj":
      v = v
        .replace(/\D/g, "")
        .replace(/^(\d{2})(\d)/, "$1.$2")
        .replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3")
        .replace(/\.(\d{3})(\d)/, ".$1/$2")
        .replace(/(\d{4})(\d)/, "$1-$2");
      break;

    case "phone":
      v = v
        .replace(/\D/g, "")
        .replace(/^(\d{2})(\d)/, "($1) $2")
        .replace(/(\d{5})(\d)/, "$1-$2");
      break;

    default:
      break;
  }

  return v;
}

/**
 * Formata BRL
 */
const formatBRL = (value) =>
  new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  }).format(value || 0);

export default function SolicitarSaque() {
  const { props } = usePage();
  const saldo = Number(props.amount_available || 0);

  const [pixType, setPixType] = useState("");
  const [pixKey, setPixKey] = useState("");
  const [amount, setAmount] = useState("");
  const [pinDigits, setPinDigits] = useState(["", "", "", ""]);
  const [loading, setLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState("");
  const [taxMsg, setTaxMsg] = useState("");

  const numericAmount = Number(amount.replace(",", ".")) || 0;
  const insufficientBalance = numericAmount > saldo;

  const TAXA_FIXA = 10.0;
  const MIN_SAQUE = 20.0;

  function handleAmountInput(e) {
    const raw = e.target.value;
    const masked = maskCurrency(raw);
    const value = Number(masked.replace(",", "."));

    if (value > saldo) {
      const saldoMasked = maskCurrency(String(Math.round(saldo * 100)));
      setAmount(saldoMasked);
      setErrorMsg("Valor máximo permitido é seu saldo disponível.");
      setTaxMsg("");
      if (navigator.vibrate) navigator.vibrate([50, 50, 50]);
      return;
    }

    // Regra de taxa fixa de R$10,00
    if (value <= TAXA_FIXA) {
      setTaxMsg(
        "A taxa fixa de R$10,00 será maior ou igual ao valor informado."
      );
    } else if (value < MIN_SAQUE) {
      setTaxMsg(`O valor mínimo para saque é R$${MIN_SAQUE.toFixed(2)}`);
    } else {
      const liquido = value - TAXA_FIXA;
      setTaxMsg(`Valor líquido após taxa: ${formatBRL(liquido)}`);
    }

    setErrorMsg("");
    setAmount(masked);
  }

  function handlePixKeyInput(e) {
    const val = e.target.value;
    setPixKey(maskPixKey(pixType, val));
  }

  function handlePinInput(value, index) {
    if (!/^\d?$/.test(value)) return;
    const newDigits = [...pinDigits];
    newDigits[index] = value;
    setPinDigits(newDigits);
    if (value && index < 3) {
      const next = document.getElementById(`pin-${index + 1}`);
      next && next.focus();
    }
  }

  const pin = pinDigits.join("");

  function validatePixKey(type, key) {
    switch (type) {
      case "cpf":
        return /^\d{3}\.\d{3}\.\d{3}-\d{2}$/.test(key);
      case "cnpj":
        return /^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/.test(key);
      case "phone":
        return /^\(\d{2}\)\s?\d{4,5}-\d{4}$/.test(key);
      case "email":
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(key);
      case "randomkey":
        return /^[a-zA-Z0-9]{8}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{12}$/.test(
          key
        );
      default:
        return false;
    }
  }

  async function submit(e) {
    e.preventDefault();
    setErrorMsg("");

    if (!pixType) return setErrorMsg("Selecione o tipo de chave PIX.");
    if (!pixKey.trim()) return setErrorMsg("Informe a chave PIX.");
    if (!validatePixKey(pixType, pixKey.trim()))
      return setErrorMsg("Formato de chave PIX inválido.");
    if (!numericAmount || numericAmount <= 0)
      return setErrorMsg("Informe um valor válido.");
    if (numericAmount < MIN_SAQUE)
      return setErrorMsg(`O valor mínimo para saque é R$${MIN_SAQUE}.`);
    if (numericAmount > saldo)
      return setErrorMsg("O valor não pode ultrapassar seu saldo disponível.");
    if (pin.length < 4) return setErrorMsg("Digite seu PIN completo.");

    setLoading(true);

    try {
      await axios.get("/sanctum/csrf-cookie");

      const payload = {
        amount: Number(numericAmount.toFixed(2)),
        pixkey: pixKey.trim(),
        pixkey_type: pixType,
        description: "Saque via painel",
        pin,
        idempotency_key: "wd_" + Math.random().toString(36).substring(2, 12),
      };

      await axios.post("/api/withdraws", payload);
      router.visit("/saques", { replace: true });
    } catch (err) {
      const bag = err?.response?.data?.errors;
      if (bag) {
        const firstKey = Object.keys(bag)[0];
        setErrorMsg(bag[firstKey][0]);
      } else {
        setErrorMsg("Erro ao solicitar saque. Verifique os dados.");
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthenticatedLayout>
      <Head title="Solicitar Saque" />

      <div className="fixed inset-0 bg-black/70 backdrop-blur-lg z-40 flex items-end justify-center px-4 pb-2">
        <div
          id="sheet"
          className="relative w-full max-w-md bg-[#0B0C0E]/95 rounded-t-3xl border border-white/10 border-b-0 p-6 shadow-[0_-10px_40px_rgba(0,0,0,0.6)] animate-slideUp max-h-[88vh] overflow-y-auto backdrop-blur-xl"
        >
          <button
            onClick={() => router.visit("/saques")}
            className="absolute right-5 top-5 p-1.5 rounded-full border border-white/10 text-gray-400 hover:bg-white/10 transition"
          >
            <X size={18} />
          </button>

          <h1 className="text-center text-xl font-semibold text-white mb-1">
            Saque via PIX
          </h1>
          <p className="text-center text-gray-400 text-xs mb-6">
            Insira os dados abaixo para concluir seu saque.
          </p>

          <form className="space-y-5" onSubmit={submit}>
            <div>
              <label className="text-gray-300 text-xs block mb-1">
                Tipo de chave PIX
              </label>
              <select
                value={pixType}
                onChange={(e) => {
                  setPixType(e.target.value);
                  setPixKey("");
                }}
                className="w-full bg-black/30 border border-white/15 rounded-xl px-4 py-3 text-sm text-white focus:border-emerald-400"
              >
                <option value="">Selecione</option>
                <option value="cpf">CPF</option>
                <option value="cnpj">CNPJ</option>
                <option value="email">E-mail</option>
                <option value="phone">Telefone</option>
                <option value="randomkey">Chave Aleatória</option>
              </select>
            </div>

            <div>
              <label className="text-gray-300 text-xs block mb-1">
                Chave PIX
              </label>
              <input
                type="text"
                value={pixKey}
                onChange={handlePixKeyInput}
                placeholder={
                  pixType === "randomkey"
                    ? "XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX"
                    : "Digite sua chave PIX"
                }
                className="w-full bg-black/30 border border-white/15 rounded-xl px-4 py-3 text-sm text-white"
              />
            </div>

            <div>
              <label className="text-gray-300 text-xs block mb-1">
                Valor do saque
              </label>
              <input
                type="text"
                value={amount}
                onChange={handleAmountInput}
                placeholder="0,00"
                className="w-full bg-black/30 border border-white/15 rounded-xl px-4 py-3 text-sm text-white font-medium"
              />
              <p className="mt-1 text-right text-[11px] text-gray-400">
                Saldo disponível:{" "}
                <span className="text-emerald-400 font-semibold">
                  {formatBRL(saldo)}
                </span>
              </p>
              {taxMsg && (
                <p className="mt-1 text-[11px] text-yellow-400 text-center">
                  {taxMsg}
                </p>
              )}
            </div>

            <div>
              <label className="text-gray-300 text-xs block mb-2">
                PIN de segurança
              </label>
              <div className="flex justify-center gap-2">
                {pinDigits.map((digit, i) => (
                  <input
                    key={i}
                    id={`pin-${i}`}
                    maxLength={1}
                    type="tel"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    value={digit}
                    onChange={(e) => handlePinInput(e.target.value, i)}
                    className="w-12 h-12 text-center text-xl font-bold bg-black/40 border border-white/10 rounded-xl text-white focus:border-emerald-400"
                  />
                ))}
              </div>
            </div>

            {errorMsg && (
              <div className="text-center text-xs text-red-400 bg-red-500/10 border border-red-500/30 py-2 px-3 rounded-xl">
                {errorMsg}
              </div>
            )}

            <button
              type="submit"
              disabled={loading || insufficientBalance}
              className={`
                w-full py-3 rounded-2xl font-semibold text-sm transition-all
                shadow-[0_0_30px_rgba(16,185,129,0.35)]
                ${
                  loading || insufficientBalance
                    ? "bg-emerald-500/40 text-black/40 cursor-not-allowed"
                    : "bg-emerald-400 text-black hover:bg-emerald-300"
                }
              `}
            >
              {loading ? "Processando..." : "Solicitar saque"}
            </button>
          </form>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
