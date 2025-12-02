// resources/js/Pages/Auth/Login.jsx
import React, { useEffect, useRef, useState } from "react";
import { Head, Link, useForm } from "@inertiajs/react";
import { ChevronLeft, Eye, EyeOff } from "lucide-react";
import axios from "axios";

/* ===========================================================
   🔧 Global Axios Configuration
   =========================================================== */
axios.defaults.withCredentials = true;
axios.defaults.baseURL = "https://app.pixionpay.com/";
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

  /* ✅ Get CSRF Cookie on mount */
  useEffect(() => {
    axios.get("/sanctum/csrf-cookie").catch(() => {});
  }, []);

  /* Autofocus on step change */
  useEffect(() => {
    const el = step === 1 ? emailRef.current : passRef.current;
    const t = setTimeout(() => el?.focus(), 60);
    return () => clearTimeout(t);
  }, [step]);

  /* Detect Caps Lock */
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

  /* Step 1 → Email */
  const next = (e) => {
    e.preventDefault();
    if (!canGoNext) return;
    clearErrors();
    setStep(2);
  };

  /* Step 2 → Submit Login */
  const submit = async (e) => {
    e.preventDefault();
    if (!canSubmit) return;

    try {
      await axios.get("/sanctum/csrf-cookie");

      post("/login", {
        preserveScroll: true,
        onError: (errs) => {
          setStep(2);
          if (errs?.email && !errs?.password) setError("password", errs.email);
        },
        onFinish: () => reset("password"),
      });
    } catch (error) {
      console.error("Authentication error:", error);
    }
  };

  return (
    <>
      <Head title="Sign In" />

      <div
        className={[
          "min-h-screen text-neutral-200 flex items-center justify-center",
          "bg-neutral-950 relative overflow-hidden",
          "before:absolute before:inset-0 before:pointer-events-none",
          "before:[background:radial-gradient(70%_70%_at_50%_100%,rgba(255,0,93,0.35),transparent_75%)]",
        ].join(" ")}
      >
        {/* 🔥 Background Image */}
        <img
          src="/images/linepix.png"
          alt="Line background"
          className="absolute inset-0 w-full h-full object-cover opacity-5 mix-blend-lighten pointer-events-none select-none"
        />

        {/* CONTENT */}
        <div className="flex flex-col justify-center px-6 sm:px-10 py-10 w-full max-w-[480px] relative z-10">
          <div className="mb-4 inline-flex items-center gap-2 text-sm text-neutral-400">
            <img
              src="/images/logopixon.png"
              alt="EquitPay"
              className="h-10 md:h-12 w-auto opacity-90 grayscale-[35%] contrast-110"
            />
            <span className="px-1.5 text-neutral-600 select-none">•</span>
            <span className="text-neutral-300">Secure area</span>
          </div>

          <h1 className="text-[30px] sm:text-[36px] leading-tight font-semibold text-neutral-50">
            Access your account
          </h1>

          <p className="mt-2 text-sm text-neutral-400">
            First enter your email, then your password.
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
                  Change email
                </button>
                <span className="truncate text-neutral-400/80">{data.email}</span>
              </div>
            )}

            {/* FORMS */}
            {step === 1 ? (
              <form onSubmit={next} className="space-y-4">
                <Field label="Email" error={errors.email}>
                  <input
                    ref={emailRef}
                    type="email"
                    placeholder="you@example.com"
                    className="
                      w-full rounded-2xl px-4 py-3 text-[15px]
                      bg-neutral-950 text-neutral-100 placeholder-neutral-500
                      ring-1 ring-inset ring-neutral-800 border border-transparent outline-none
                      focus:ring-[2px] focus:ring-[#ff005d]
                    "
                    value={data.email}
                    onChange={(e) => setData("email", e.target.value)}
                    required
                  />
                </Field>

                <PrimaryButton
                  type="submit"
                  disabled={!canGoNext}
                  text="Continue"
                  variant="outline"
                />

                <div className="mt-1.5 flex justify-end">
                  <Link
                    href="/register"
                    className="inline-flex items-center rounded-lg px-2.5 py-1 text-[11px] font-medium text-neutral-300 border border-white/10 hover:text-white hover:bg-white/5 transition"
                  >
                    Register
                  </Link>
                </div>
              </form>
            ) : (
              <form onSubmit={submit} className="space-y-5">
                <Field label="Password" error={errors.password || errors.message}>
                  <div className="relative">
                    <input
                      ref={passRef}
                      type={showPassword ? "text" : "password"}
                      placeholder="********"
                      className="
                        w-full rounded-2xl px-4 py-3 pr-12 text-[15px]
                        bg-neutral-950 text-neutral-100 placeholder-neutral-500
                        ring-1 ring-inset ring-neutral-800 border border-transparent outline-none
                        focus:ring-[2px] focus:ring-[#ff005d]
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
                    <p className="mt-2 text-xs text-neutral-400">Caps Lock is on.</p>
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
                    Remember me
                  </label>

                  <Link
                    href="/forgot-password"
                    className="text-xs text-neutral-400 hover:text-neutral-200"
                  >
                    Forgot password
                  </Link>
                </div>

                <PrimaryButton
                  type="submit"
                  disabled={!canSubmit}
                  text="Sign In"
                  variant="solid"
                  loading={processing}
                />
              </form>
            )}
          </div>

          <p className="mt-6 text-xs text-neutral-400">
            By signing in, you agree to our terms of use.
          </p>
        </div>
      </div>
    </>
  );
}

/* ===========================================================
   SUBCOMPONENTS
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
        : "ring-1 ring-inset text-[#ff005d] ring-[#ff005d]/60 bg-neutral-950 hover:bg-[#ff005d]/10"
      : disabled
      ? "bg-[#ff005d]/40 text-neutral-900 cursor-not-allowed"
      : "bg-[#ff005d] text-neutral-950 hover:bg-[#e00052]";

  return (
    <button type={type} disabled={disabled || loading} className={`${base} ${styles}`}>
      {loading && (
        <span className="h-4 w-4 animate-spin rounded-full border-2 border-neutral-900/20 border-t-neutral-900" />
      )}
      {text}
    </button>
  );
}
