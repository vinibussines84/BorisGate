// resources/js/Pages/Profile/Partials/UpdatePasswordForm.jsx
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useRef, useState, useMemo } from 'react';
import { Lock, Eye, EyeOff, ShieldCheck, CheckCircle2, XCircle } from 'lucide-react';

export default function UpdatePasswordForm({ className = '' }) {
  const passwordInput = useRef(null);
  const currentPasswordInput = useRef(null);

  const [showCurrent, setShowCurrent] = useState(false);
  const [showNew, setShowNew] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);

  const {
    data,
    setData,
    errors,
    put,
    reset,
    processing,
    recentlySuccessful,
  } = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  // ---- Regras de validação
  const checks = useMemo(() => {
    const pwd = data.password || '';
    return {
      length: pwd.length >= 8,
      upper: /[A-Z]/.test(pwd),
      lower: /[a-z]/.test(pwd),
      number: /\d/.test(pwd),
      symbol: /[^A-Za-z0-9]/.test(pwd),
      notCommon: !/(password|123456|qwerty|abcdef|111111|letmein)/i.test(pwd),
      match: (data.password || '') === (data.password_confirmation || ''),
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data.password, data.password_confirmation]);

  // ---- Score de força (0-4)
  const strength = useMemo(() => {
    let score = 0;
    if (checks.length) score++;
    if (checks.upper && checks.lower) score++;
    if (checks.number) score++;
    if (checks.symbol) score++;
    if (!checks.notCommon) score = Math.max(0, score - 1);
    return Math.min(score, 4);
  }, [checks]);

  const strengthLabel = ['Muito fraca', 'Fraca', 'Ok', 'Forte', 'Excelente'][strength];
  const strengthPct = [0, 25, 50, 75, 100][strength];

  const allValid =
    checks.length &&
    checks.upper &&
    checks.lower &&
    checks.number &&
    checks.symbol &&
    checks.notCommon &&
    checks.match &&
    !processing;

  const updatePassword = (e) => {
    e.preventDefault();
    put(route('password.update'), {
      preserveScroll: true,
      onSuccess: () => reset(),
      onError: (errs) => {
        if (errs.password) {
          reset('password', 'password_confirmation');
          passwordInput.current?.focus();
        }
        if (errs.current_password) {
          reset('current_password');
          currentPasswordInput.current?.focus();
        }
      },
    });
  };

  const fieldBase =
    'mt-1 block w-full rounded-xl border-neutral-800 bg-neutral-900/70 text-neutral-100 placeholder-neutral-500 ' +
    'ring-1 ring-inset ring-neutral-800 focus:border-neutral-500 focus:ring-neutral-500';

  const Check = ({ ok }) =>
    ok ? (
      <CheckCircle2 className="h-4 w-4 text-neutral-300" />
    ) : (
      <XCircle className="h-4 w-4 text-neutral-600" />
    );

  return (
    <section className={className} data-no-white-bg>
      {/* remove wrappers brancos/sombras padrão */}
      <style>{`
        [data-no-white-bg] :where(.bg-white, .bg-gray-50) { background-color: transparent !important; }
        [data-no-white-bg] :where(.shadow, .shadow-sm, .shadow-md, .shadow-lg) { box-shadow: none !important; }
        [data-no-white-bg] :where(.ring-1, .ring-2) { --tw-ring-color: transparent !important; }
      `}</style>

      <div className="rounded-2xl border border-neutral-800 bg-neutral-950/90 shadow-[0_20px_60px_-30px_rgba(0,0,0,.7)] ring-1 ring-inset ring-neutral-900/30">
        {/* Header */}
        <div className="flex items-center gap-4 border-b border-neutral-800 px-5 py-4">
          <div className="grid size-11 place-items-center rounded-full bg-neutral-800 text-neutral-100 ring-1 ring-neutral-700">
            <Lock className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold tracking-tight text-neutral-100">Atualizar senha</h2>
            <p className="mt-0.5 text-sm text-neutral-400">
              Use uma senha longa e aleatória para manter sua conta segura.
            </p>
          </div>
        </div>

        {/* Form */}
        <form onSubmit={updatePassword} className="space-y-6 px-5 py-5">
          {/* Senha atual */}
          <div>
            <InputLabel htmlFor="current_password" value="Senha atual" className="text-neutral-300" />
            <div className="relative">
              <TextInput
                id="current_password"
                ref={currentPasswordInput}
                value={data.current_password}
                onChange={(e) => setData('current_password', e.target.value)}
                type={showCurrent ? 'text' : 'password'}
                className={fieldBase + ' pr-11'}
                autoComplete="current-password"
                placeholder="••••••••"
              />
              <button
                type="button"
                onClick={() => setShowCurrent((v) => !v)}
                className="absolute inset-y-0 right-0 mr-2 grid place-items-center rounded-lg px-2 text-neutral-400 hover:text-neutral-200 focus:outline-none"
                aria-label={showCurrent ? 'Ocultar senha atual' : 'Mostrar senha atual'}
              >
                {showCurrent ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
              </button>
            </div>
            <InputError message={errors.current_password} className="mt-2 text-red-400" />
          </div>

          {/* Nova senha + força */}
          <div>
            <InputLabel htmlFor="password" value="Nova senha" className="text-neutral-300" />
            <div className="relative">
              <TextInput
                id="password"
                ref={passwordInput}
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                type={showNew ? 'text' : 'password'}
                className={fieldBase + ' pr-11'}
                autoComplete="new-password"
                placeholder="Mínimo de 8 caracteres"
              />
              <button
                type="button"
                onClick={() => setShowNew((v) => !v)}
                className="absolute inset-y-0 right-0 mr-2 grid place-items-center rounded-lg px-2 text-neutral-400 hover:text-neutral-200 focus:outline-none"
                aria-label={showNew ? 'Ocultar nova senha' : 'Mostrar nova senha'}
              >
                {showNew ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
              </button>
            </div>

            {/* Barra de força (tons de cinza) */}
            <div className="mt-3">
              <div className="flex items-center justify-between text-xs text-neutral-500">
                <span>Força da senha</span>
                <span className="text-neutral-400">{strengthLabel}</span>
              </div>
              <div className="mt-1 h-2 w-full rounded-full bg-neutral-800 ring-1 ring-inset ring-neutral-800/80">
                <div
                  className="h-2 rounded-full bg-neutral-400 transition-[width] duration-300"
                  style={{ width: `${strengthPct}%` }}
                />
              </div>
            </div>

            <InputError message={errors.password} className="mt-2 text-red-400" />
          </div>

          {/* Confirmar senha */}
          <div>
            <InputLabel htmlFor="password_confirmation" value="Confirmar nova senha" className="text-neutral-300" />
            <div className="relative">
              <TextInput
                id="password_confirmation"
                value={data.password_confirmation}
                onChange={(e) => setData('password_confirmation', e.target.value)}
                type={showConfirm ? 'text' : 'password'}
                className={fieldBase + ' pr-11'}
                autoComplete="new-password"
                placeholder="Repita a nova senha"
              />
              <button
                type="button"
                onClick={() => setShowConfirm((v) => !v)}
                className="absolute inset-y-0 right-0 mr-2 grid place-items-center rounded-lg px-2 text-neutral-400 hover:text-neutral-200 focus:outline-none"
                aria-label={showConfirm ? 'Ocultar confirmação' : 'Mostrar confirmação'}
              >
                {showConfirm ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
              </button>
            </div>
            <InputError message={errors.password_confirmation} className="mt-2 text-red-400" />
          </div>

          {/* Regras visuais */}
          <div className="grid grid-cols-1 gap-2 rounded-xl border border-neutral-800 bg-neutral-900/40 p-4 text-sm">
            <div className="flex items-center gap-2 text-neutral-300">
              <Check ok={checks.length} />
              <span>Mínimo de 8 caracteres</span>
            </div>
            <div className="flex items-center gap-2 text-neutral-300">
              <Check ok={checks.upper && checks.lower} />
              <span>Letras maiúsculas e minúsculas</span>
            </div>
            <div className="flex items-center gap-2 text-neutral-300">
              <Check ok={checks.number} />
              <span>Ao menos um número</span>
            </div>
            <div className="flex items-center gap-2 text-neutral-300">
              <Check ok={checks.symbol} />
              <span>Ao menos um símbolo</span>
            </div>
            <div className="flex items-center gap-2 text-neutral-300">
              <Check ok={checks.notCommon} />
              <span>Evite senhas comuns</span>
            </div>
            <div className="flex items-center gap-2 text-neutral-300">
              <Check ok={checks.match} />
              <span>Confirmação igual à nova senha</span>
            </div>
          </div>

          {/* Ações */}
          <div className="flex flex-wrap items-center gap-4">
            <PrimaryButton
              disabled={!allValid}
              className={
                'rounded-xl bg-neutral-300 text-neutral-900 hover:bg-neutral-200 disabled:opacity-50 ' +
                'focus:ring-neutral-500'
              }
            >
              {processing ? 'Salvando…' : 'Salvar nova senha'}
            </PrimaryButton>

            <div className="flex items-center gap-2 text-sm text-neutral-400">
              <ShieldCheck className="h-4 w-4 text-neutral-400" />
              <span>As alterações entram em vigor imediatamente.</span>
            </div>

            <Transition
              show={recentlySuccessful}
              enter="transition ease-out duration-200"
              enterFrom="opacity-0 translate-y-0.5"
              enterTo="opacity-100 translate-y-0"
              leave="transition ease-in duration-150"
              leaveFrom="opacity-100 translate-y-0"
              leaveTo="opacity-0 translate-y-0.5"
            >
              <p className="text-sm text-neutral-400">Senha atualizada com sucesso.</p>
            </Transition>
          </div>
        </form>
      </div>
    </section>
  );
}
