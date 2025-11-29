import React, { useMemo, useRef, useState, useCallback, useEffect } from "react";
import { Head, useForm } from "@inertiajs/react";
import PrimaryButton from "@/Components/PrimaryButton";
import DarkGuestLayout from "@/Layouts/DarkGuestLayout";

export default function ConfirmPassword() {
  const { data, setData, post, processing, errors, reset } = useForm({
    password: "",
  });

  const [digits, setDigits] = useState(["", "", "", ""]);
  const inputsRef = useRef([]);

  const pin = useMemo(() => digits.join(""), [digits]);

  /* Atualiza o form do Inertia */
  useEffect(() => {
    setData("password", pin);
  }, [pin, setData]);

  const focusIndex = (i) => inputsRef.current[i]?.focus();

  /* Digitação */
  const onChange = (idx) => (e) => {
    const val = e.target.value.replace(/\D+/g, "").slice(0, 1);
    const next = [...digits];
    next[idx] = val;

    setDigits(next);

    if (val && idx < 3) focusIndex(idx + 1);
  };

  /* Navegação com teclas */
  const onKeyDown = (idx) => (e) => {
    if (e.key === "Backspace" && !digits[idx] && idx > 0) {
      e.preventDefault();
      const next = [...digits];
      next[idx - 1] = "";
      setDigits(next);
      focusIndex(idx - 1);
    }

    if (e.key === "ArrowLeft" && idx > 0) {
      e.preventDefault();
      focusIndex(idx - 1);
    }

    if (e.key === "ArrowRight" && idx < 3) {
      e.preventDefault();
      focusIndex(idx + 1);
    }
  };

  /* Colar PIN */
  const onPaste = (e) => {
    const text = (e.clipboardData?.getData("text") || "")
      .replace(/\D+/g, "")
      .slice(0, 4);

    if (text.length) {
      e.preventDefault();
      setDigits(Array.from({ length: 4 }, (_, i) => text[i] || ""));
      focusIndex(Math.min(text.length, 3));
    }
  };

  /* Enviar formulário */
  const submit = useCallback(
    (e) => {
      e.preventDefault();

      post("/confirm-password", {
        onFinish: () => {
          reset("password");
          setDigits(["", "", "", ""]);
          focusIndex(0);
        },
      });
    },
    [post, reset]
  );

  return (
    <DarkGuestLayout>
      <Head title="Confirmar PIN" />

      <div className="rounded-2xl border border-white/10 bg-[#121212]/90 backdrop-blur p-6 md:p-8 shadow-[0_0_40px_rgba(16,185,129,0.10)]">
        <div className="mb-6">
          <h1 className="text-3xl font-semibold tracking-tight text-white leading-tight">
            Digite seu <span className="text-emerald-400">PIN de 4 dígitos</span>
          </h1>

          <p className="mt-2 text-sm text-gray-400">
            Área segura — confirme seu PIN para continuar.
          </p>
        </div>

        <form onSubmit={submit} className="space-y-6">
          <div className="flex items-center justify-between gap-3" onPaste={onPaste}>
            {digits.map((d, i) => (
              <input
                key={i}
                ref={(el) => (inputsRef.current[i] = el)}
                value={d}
                onChange={onChange(i)}
                onKeyDown={onKeyDown(i)}
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={1}
                className={[
                  "h-16 w-16 md:h-18 md:w-18 rounded-xl text-center text-3xl text-white",
                  "bg-black/60 border border-emerald-500/30 outline-none",
                  "focus:ring-2 focus:ring-emerald-400/70 focus:border-emerald-400/70",
                  "shadow-[inset_0_0_12px_rgba(16,185,129,0.16),0_0_28px_rgba(16,185,129,0.10)]",
                  "transition-all",
                ].join(" ")}
              />
            ))}
          </div>

          {errors.password && (
            <p className="text-sm text-rose-400">{errors.password}</p>
          )}

          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500">
              Apenas números — exatamente 4 dígitos.
            </span>

            <PrimaryButton
              disabled={processing || pin.length !== 4}
              className="disabled:opacity-50 disabled:cursor-not-allowed bg-emerald-500 hover:bg-emerald-400 text-black border-0"
            >
              Confirmar
            </PrimaryButton>
          </div>
        </form>
      </div>
    </DarkGuestLayout>
  );
}
