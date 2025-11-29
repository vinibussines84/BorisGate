// resources/js/Pages/Auth/Login.jsx
import React, { useEffect, useRef, useState } from "react";
import { Head, Link, useForm } from "@inertiajs/react";
import { ChevronLeft, Eye, EyeOff } from "lucide-react";
import axios from "axios";

/* ===========================================================
   🔧 Configuração Global Axios
   =========================================================== */
axios.defaults.withCredentials = true;
axios.defaults.baseURL = "https://equitpay.app";
axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

export default function Login() {
  const {
    data,
    setData,
    post,
    processing,
    errors,
    reset,
    setError,
    clearErrors,
  } = useForm({
    email: "",
    password: "",
    remember: false,
  });

  const [step, setStep] = useState(1);
  const [showPassword, setShowPassword] = useState(false);
  const [capsOn, setCapsOn] = useState(false);

  const emailRef = useRef(null);
  const passRef = useRef(null);

  /* ✅ Obtém cookie CSRF ao montar */
  useEffect(() => {
    axios.get("/sanctum/csrf-cookie").catch(() => {});
  }, []);

  /* Foco automático */
  useEffect(() => {
    const el = step === 1 ? emailRef.current : passRef.current;
    const t = setTimeout(() => el?.focus(), 60);
    return () => clearTimeout(t);
  }, [step]);

  /* Detecta Caps Lock */
  useEffect(() => {
    if (step !== 2 || !passRef.current) return;
    const el = passRef.current;

    const handle = (e) => {
      const getMod = e?.getModifierState?.bind?.(e);
      setCapsOn(getMod ? !!getMod("CapsLock") : false);
    };

    el.addEventListener("keydown", handle);
    el.addEventListener("keyup", handle);
    return () => {
      el.removeEventListener("keydown", handle);
      el.removeEventListener("keyup", handle);
    };
  }, [step]);

  const canGoNext = /\S+@\S+\.\S+/.test(data.email);
  const canSubmit = (data.password || "").length > 0 && !processing;

  /* Etapa 1 → E-mail */
  const next = (e) => {
    e.preventDefault();
    if (!canGoNext) return;
    clearErrors();
    setStep(2);
  };

  /* Etapa 2 → Login */
  const submit = async (e) => {
    e.preventDefault();
    if (!canSubmit) return;

    try {
      await axios.get("/sanctum/csrf-cookie"); // garante CSRF válido

      post("/login", {
        preserveScroll: true,
        onError: (errs) => {
          setStep(2);
          if (errs?.email && !errs?.password) setError("password", errs.email);
        },
        onFinish: () => reset("password"),
      });
    } catch (error) {
      console.error("Erro ao autenticar:", error);
    }
  };

  return (
    <>
      <Head title="Entrar" />

      <div
        className={[
          "min-h-screen text-neutral-200 flex items-center justify-center",
          "bg-neutral-950 relative overflow-hidden",
          "before:absolute before:inset-0 before:pointer-events-none",
          "before:[background:radial-gradient(70%_70%_at_50%_100%,rgba(2,251,92,0.15),transparent_75%)]",
        ].join(" ")}
      >
        {/* 🔥 IMAGEM DE FUNDO */}
        <img
          src="/images/line.png"
          alt="Line background"
          className="absolute inset-0 w-full h-full object-cover opacity-20 mix-blend-lighten pointer-events-none select-none"
        />

        {/* CONTEÚDO */}
        <div className="flex flex-col justify-center px-6 sm:px-10 py-10 w-full max-w-[480px] relative z-10">
          <div className="mb-4 inline-flex items-center gap-2 text-sm text-neutral-400">
            <img
              src="/images/equitpay.png"
              alt="EquityPay"
              className="h-10 md:h-12 w-auto opacity-90 grayscale-[35%] contrast-110"
            />
            <span className="px-1.5 text-neutral-600 select-none">•</span>
            <span className="text-neutral-300">Área segura</span>
          </div>

          <h1 className="text-[30px] sm:text-[36px] leading-tight font-semibold text-neutral-50">
            Acesse sua conta
          </h1>

          <p className="mt-2 text-sm text-neutral-400">
            Primeiro informe seu e-mail, depois sua senha.
          </p>

          <div
            className={[
              "mt-6 w-full rounded-[22px] p-5 sm:p-6 relative overflow-hidden",
              "bg-neutral-900/70 backdrop-blur ring-1 ring-inset ring-neutral-800",
              "shadow-[0_30px_80px_-30px_rgba(0,0,0,.8)]",
            ].join(" ")}
          >
            {step === 2 && (
              <div className="mb-3 flex items-center justify-between text-sm">
                <button
                  type="button"
                  onClick={() => {
                    setStep(1);
                    clearErrors();
                  }}
                  className="inline-flex items-center gap-1 text-neutral-400 hover:text-neutral-200"
                >
                  <ChevronLeft className="h-4 w-4" />
                  Alterar e-mail
                </button>
                <span className="truncate text-neutral-400/80">{data.email}</span>
              </div>
            )}

            {/* FORMULÁRIOS */}
            {step === 1 ? (
              <form onSubmit={next} className="space-y-4">
                <Field label="E-mail" error={errors.email}>
                  <input
                    ref={emailRef}
                    type="email"
                    placeholder="seuemail@exemplo.com"
                    className="
                      w-full rounded-2xl px-4 py-3 text-[15px]
                      bg-neutral-950 text-neutral-100 placeholder-neutral-500
                      ring-1 ring-inset ring-neutral-800 border border-transparent outline-none
                      focus:ring-[2px] focus:ring-[#02fb5c]
                    "
                    value={data.email}
                    onChange={(e) => setData("email", e.target.value)}
                    required
                  />
                </Field>

                <PrimaryButton
                  type="submit"
                  disabled={!canGoNext}
                  text="Continuar"
                  variant="outline"
                />

                <div className="mt-1.5 flex justify-end">
                  <Link
                    href="/register"
                    className="inline-flex items-center rounded-lg px-2.5 py-1 text-[11px] font-medium text-neutral-300 border border-white/10 hover:text-white hover:bg-white/5 transition"
                  >
                    Registrar
                  </Link>
                </div>
              </form>
            ) : (
              <form onSubmit={submit} className="space-y-5">
                <Field label="Senha" error={errors.password || errors.message}>
                  <div className="relative">
                    <input
                      ref={passRef}
                      type={showPassword ? "text" : "password"}
                      placeholder="********"
                      className="
                        w-full rounded-2xl px-4 py-3 pr-12 text-[15px]
                        bg-neutral-950 text-neutral-100 placeholder-neutral-500
                        ring-1 ring-inset ring-neutral-800 border border-transparent outline-none
                        focus:ring-[2px] focus:ring-[#02fb5c]
                      "
                      value={data.password}
                      onChange={(e) => setData("password", e.target.value)}
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((v) => !v)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1 text-neutral-400 hover:text-neutral-200"
                    >
                      {showPassword ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
                    </button>
                  </div>
                  {capsOn && (
                    <p className="mt-2 text-xs text-neutral-400">Caps Lock está ativado.</p>
                  )}
                </Field>

                <div className="flex items-center justify-between">
                  <label className="flex select-none items-center gap-2 text-xs text-neutral-300">
                    <input
                      type="checkbox"
                      className="h-4 w-4 rounded border-neutral-700 bg-neutral-950"
                      checked={data.remember}
                      onChange={(e) => setData("remember", e.target.checked)}
                    />
                    Lembrar de mim
                  </label>

                  <Link
                    href="/forgot-password"
                    className="text-xs text-neutral-400 hover:text-neutral-200"
                  >
                    Esqueci minha senha
                  </Link>
                </div>

                <PrimaryButton
                  type="submit"
                  disabled={!canSubmit}
                  text="Entrar"
                  variant="solid"
                  loading={processing}
                />
              </form>
            )}
          </div>

          <p className="mt-6 text-xs text-neutral-400">
            Ao acessar a conta, você concorda com os nossos termos de uso.
          </p>
        </div>
      </div>
    </>
  );
}

/* ===========================================================
   SUBCOMPONENTES
=========================================================== */
function Field({ label, error, children }) {
  return (
    <div>
      <label className="mb-1.5 block text-sm text-neutral-300">{label}</label>
      {children}
      {error && <p className="mt-2 text-xs text-rose-400">{error}</p>}
    </div>
  );
}

function PrimaryButton({ text, disabled, type = "button", variant = "solid", loading = false }) {
  const base =
    "inline-flex w-full items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-medium transition focus:outline-none";

  const styles =
    variant === "outline"
      ? disabled
        ? "ring-1 ring-inset ring-neutral-800 text-neutral-500 bg-neutral-950"
        : "ring-1 ring-inset text-[#02fb5c] ring-[#02fb5c]/60 bg-neutral-950 hover:bg-[#02fb5c]/10"
      : disabled
      ? "bg-[#02fb5c]/40 text-neutral-900 cursor-not-allowed"
      : "bg-[#02fb5c] text-neutral-950 hover:bg-[#00e756]";

  return (
    <button type={type} disabled={disabled || loading} className={`${base} ${styles}`}>
      {loading && (
        <span className="h-4 w-4 animate-spin rounded-full border-2 border-neutral-900/20 border-t-neutral-900" />
      )}
      {text}
    </button>
  );
}
