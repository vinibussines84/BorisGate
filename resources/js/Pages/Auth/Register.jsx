// resources/js/Pages/Auth/Register.jsx
import React, { useMemo, useState, useEffect, useRef } from "react";
import { Head, Link, useForm } from "@inertiajs/react";
import {
  Lock,
  ChevronLeft,
  ChevronRight,
  Eye,
  EyeOff,
  CheckCircle2 as CheckCircle,
  XCircle,
  Info,
} from "lucide-react";

/* ===========================
   Helpers
=========================== */
const onlyDigits = (s = "") => (s || "").replace(/\D/g, "");
const formatCpfMask = (v = "") =>
  v
    .replace(/\D/g, "")
    .slice(0, 11)
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d{1,2})$/, "$1-$2");

/* ===========================
   Input Styling
=========================== */
function inputClass(hasError = false) {
  return [
    "w-full rounded-2xl px-4 py-3 text-[15px] outline-none",
    "bg-neutral-950 text-neutral-100 placeholder-neutral-500",
    "ring-1 ring-inset border border-transparent",
    hasError
      ? "ring-rose-600/70"
      : "ring-neutral-800 focus:ring-[2px] focus:ring-[#ff0a66]",
  ].join(" ");
}

/* ===========================
   Status Badge
=========================== */
function StatusBadge({
  state,
  okText = "OK",
  errText = "Error",
  hintText = "Waiting",
}) {
  if (state === "ok") {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full border border-[#ff0a66]/40 bg-[#ff0a66]/10 px-2 py-0.5 text-[11px] text-[#ff0a66]">
        <CheckCircle size={12} /> {okText}
      </span>
    );
  }
  if (state === "err") {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full border border-rose-600/40 bg-rose-600/10 px-2 py-0.5 text-[11px] text-rose-300">
        <XCircle size={12} /> {errText}
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-2 py-0.5 text-[11px] text-white/70">
      <Info size={12} /> {hintText}
    </span>
  );
}

/* ===========================
   Primary Button
=========================== */
function PrimaryButton({
  text,
  disabled,
  type = "button",
  variant = "solid",
  loading = false,
}) {
  const base =
    "inline-flex w-full items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-medium transition focus:outline-none";

  const styles =
    variant === "outline"
      ? disabled
        ? "ring-1 ring-inset ring-neutral-800 text-neutral-600 bg-neutral-950"
        : "ring-1 ring-inset ring-[#ff0a66] text-[#ff0a66] bg-neutral-950 hover:bg-[#ff0a66]/10"
      : disabled
      ? "bg-[#ff0a66]/40 text-neutral-900 cursor-not-allowed"
      : "bg-[#ff0a66] text-neutral-900 hover:bg-[#e0085a]";

  return (
    <button
      type={type}
      disabled={disabled || loading}
      className={`${base} ${styles}`}
    >
      {loading && (
        <span className="h-4 w-4 animate-spin rounded-full border-2 border-neutral-800/20 border-t-neutral-800" />
      )}
      {text}
    </button>
  );
}

/* ===========================
   Field Component
=========================== */
function Field({ label, error, children }) {
  return (
    <div>
      <label className="mb-1.5 block text-sm text-neutral-300">{label}</label>
      {children}
      {error ? <p className="mt-2 text-xs text-rose-400">{error}</p> : null}
    </div>
  );
}

/* ===========================
   Register Page
=========================== */
export default function Register() {
  const { data, setData, post, processing, errors, reset, transform } = useForm({
    nome_completo: "",
    data_nascimento: "",
    cpf: "",
    email: "",
    password: "",
    password_confirmation: "",
    cpf_cnpj: "",
  });

  const [step, setStep] = useState(1);
  const totalSteps = 2;

  const firstRef = useRef(null);
  const secondRef = useRef(null);
  const passRef = useRef(null);

  const [showPass, setShowPass] = useState(false);
  const [showPass2, setShowPass2] = useState(false);

  /* Autofocus */
  useEffect(() => {
    const el = step === 1 ? firstRef.current : secondRef.current;
    const t = setTimeout(() => el?.focus(), 60);
    return () => clearTimeout(t);
  }, [step]);

  /* Step selection based on errors */
  useEffect(() => {
    const mapStep = {
      nome_completo: 1,
      data_nascimento: 1,
      cpf: 1,
      cpf_cnpj: 1,
      email: 2,
      password: 2,
      password_confirmation: 2,
    };
    const keys = Object.keys(errors || {});
    if (keys.length) {
      let go = 2;
      keys.forEach((k) => (go = Math.min(go, mapStep[k] || go)));
      setStep(go);
    }
  }, [errors]);

  /* Progress */
  const progressPct = Math.round((step / totalSteps) * 100);

  const next = (e) => {
    e?.preventDefault?.();
    setStep(2);
  };

  const back = () => setStep(1);

  const submit = (e) => {
    e.preventDefault();

    transform((d) => ({
      ...d,
      nome_completo: d.nome_completo.trim().replace(/\s+/g, " "),
      cpf_cnpj: onlyDigits(d.cpf).slice(0, 11),
    }));

    post("/register", {
      preserveScroll: true,
      onFinish: () => reset("password", "password_confirmation"),
    });
  };

  const serverErrors = Object.values(errors || {});
  const hasServerErrors = serverErrors.length > 0;

  return (
    <>
      <Head title="Register" />

      {/* Background */}
      <div
        className={[
          "min-h-screen flex items-center justify-center text-neutral-200 bg-neutral-950 relative",
          "before:absolute before:inset-0 before:pointer-events-none",
          "before:[background:radial-gradient(70%_70%_at_50%_100%,rgba(255,10,102,0.14),transparent_80%)]",
        ].join(" ")}
      >
        <div className="w-full max-w-[480px] px-6 sm:px-10 py-10">
          {/* Header */}
          <div className="mb-4 inline-flex items-center gap-2 text-sm text-neutral-400">
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-2xl ring-1 ring-inset ring-neutral-800 bg-neutral-900/70 backdrop-blur-sm">
              <Lock size={16} className="text-neutral-300" />
            </span>
            <span className="text-neutral-300">Secure area</span>
          </div>

          <h1 className="text-[30px] sm:text-[36px] leading-tight font-semibold text-neutral-50">
            Create your account
          </h1>

          <p className="mt-2 text-sm text-neutral-400">
            Complete the 2 steps to finish your registration.
          </p>

          {/* Card */}
          <div
            className={[
              "mt-6 w-full rounded-[22px] p-5 sm:p-6 relative overflow-hidden",
              "bg-neutral-900/70 backdrop-blur ring-1 ring-inset ring-neutral-800",
              "shadow-[0_30px_80px_-30px_rgba(0,0,0,.8)]",
            ].join(" ")}
          >
            {/* Server Errors */}
            {hasServerErrors && (
              <div className="mb-4 rounded-2xl border border-rose-600/30 bg-rose-600/10 p-3 text-sm text-rose-200">
                <div className="mb-1 font-medium flex items-center gap-2">
                  <XCircle size={16} />
                  Please correct the following fields:
                </div>
                <ul className="list-disc pl-6 space-y-1">
                  {serverErrors.map((msg, idx) => (
                    <li key={idx}>{msg}</li>
                  ))}
                </ul>
              </div>
            )}

            {/* Progress bar */}
            <div className="mb-4">
              <div className="flex items-center justify-between text-xs text-neutral-400 mb-2">
                <span>
                  Step {step} of {totalSteps}
                </span>
                <span>{progressPct}%</span>
              </div>

              <div className="h-2 w-full rounded-full bg-white/10 overflow-hidden">
                <div
                  className="h-full bg-[#ff0a66] transition-all"
                  style={{ width: `${progressPct}%` }}
                />
              </div>

              <div className="mt-2 flex justify-between text-[11px] text-neutral-400/80">
                <span>Personal Info</span>
                <span>Access Data</span>
              </div>
            </div>

            {/* STEP 1 */}
            {step === 1 && (
              <form onSubmit={next} className="space-y-4">
                {/* Full Name */}
                <div>
                  <div className="mb-1.5 flex items-center justify-between">
                    <label className="text-sm text-neutral-300">Full name</label>
                    <StatusBadge
                      state={data.nome_completo ? "ok" : "idle"}
                      hintText="First and Last name"
                    />
                  </div>

                  <input
                    ref={firstRef}
                    type="text"
                    value={data.nome_completo}
                    onChange={(e) => setData("nome_completo", e.target.value)}
                    className={inputClass(!!errors.nome_completo)}
                    placeholder="Ex.: John Doe"
                  />
                </div>

                {/* Birthdate */}
                <div>
                  <div className="mb-1.5 flex items-center justify-between">
                    <label className="text-sm text-neutral-300">Birthdate</label>
                    <StatusBadge state={data.data_nascimento ? "ok" : "idle"} />
                  </div>

                  <input
                    type="date"
                    value={data.data_nascimento}
                    onChange={(e) => setData("data_nascimento", e.target.value)}
                    className={inputClass(!!errors.data_nascimento)}
                  />
                </div>

                {/* CPF */}
                <div>
                  <div className="mb-1.5 flex items-center justify-between">
                    <label className="text-sm text-neutral-300">CPF</label>
                    <StatusBadge state={data.cpf ? "ok" : "idle"} hintText="Required" />
                  </div>

                  <input
                    type="text"
                    inputMode="numeric"
                    placeholder="000.000.000-00"
                    value={formatCpfMask(data.cpf)}
                    onChange={(e) =>
                      setData("cpf", onlyDigits(e.target.value).slice(0, 11))
                    }
                    className={inputClass(!!errors.cpf || !!errors.cpf_cnpj)}
                  />
                </div>

                <PrimaryButton type="submit" text="Next" variant="outline" />

                <div className="mt-1.5 flex justify-end">
                  <Link
                    href="/login"
                    className="inline-flex items-center rounded-lg px-2.5 py-1 text-[11px] font-medium text-neutral-300 border border-white/10 hover:text-white hover:bg-white/5 transition"
                  >
                    Sign In
                  </Link>
                </div>
              </form>
            )}

            {/* STEP 2 */}
            {step === 2 && (
              <form onSubmit={submit} className="space-y-4">
                {/* Header */}
                <div className="mb-1 flex items-center justify-between text-sm">
                  <button
                    type="button"
                    onClick={back}
                    className="inline-flex items-center gap-1 text-neutral-400 hover:text-neutral-200"
                  >
                    <ChevronLeft className="h-4 w-4" />
                    Back
                  </button>

                  <span className="truncate text-neutral-400/80">
                    {data.nome_completo || "New user"}
                  </span>
                </div>

                {/* Email */}
                <Field label="E-mail" error={errors.email}>
                  <input
                    ref={secondRef}
                    type="email"
                    inputMode="email"
                    autoComplete="username email"
                    value={data.email}
                    onChange={(e) => setData("email", e.target.value)}
                    className={inputClass(!!errors.email)}
                    placeholder="you@example.com"
                  />
                </Field>

                {/* Password */}
                <Field label="Password" error={errors.password}>
                  <div className="relative">
                    <input
                      ref={passRef}
                      type={showPass ? "text" : "password"}
                      value={data.password}
                      onChange={(e) => setData("password", e.target.value)}
                      className={inputClass(!!errors.password)}
                      placeholder="********"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPass((v) => !v)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-200"
                    >
                      {showPass ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
                    </button>
                  </div>
                </Field>

                {/* Confirm Password */}
                <Field label="Confirm password" error={errors.password_confirmation}>
                  <div className="relative">
                    <input
                      type={showPass2 ? "text" : "password"}
                      value={data.password_confirmation}
                      onChange={(e) => setData("password_confirmation", e.target.value)}
                      className={inputClass(!!errors.password_confirmation)}
                      placeholder="********"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPass2((v) => !v)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-200"
                    >
                      {showPass2 ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
                    </button>
                  </div>
                </Field>

                <div className="flex items-center gap-2">
                  <ChevronRight className="h-4 w-4 text-neutral-400" />
                  <span className="text-xs text-neutral-400">
                    By creating your account, you agree to our terms.
                  </span>
                </div>

                <PrimaryButton
                  type="submit"
                  text={processing ? "Sending..." : "Create account"}
                  variant="solid"
                  loading={processing}
                />
              </form>
            )}
          </div>

          {/* Footer */}
          <p className="mt-6 text-xs text-neutral-400 text-center">
            By continuing, you agree to our{" "}
            <a
              href="#"
              className="underline underline-offset-2 hover:text-neutral-200"
            >
              Privacy Policy
            </a>
            .
          </p>
        </div>
      </div>
    </>
  );
}
