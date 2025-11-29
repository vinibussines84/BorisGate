// resources/js/Pages/Profile/Partials/DeleteUserForm.jsx
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ShieldAlert, Eye, EyeOff, Lock } from 'lucide-react';

export default function DeleteUserForm({ className = '' }) {
  const [confirming, setConfirming] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [capsOn, setCapsOn] = useState(false);
  const passwordInput = useRef();

  const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({
    password: '',
  });

  const confirm = () => setConfirming(true);

  const submit = (e) => {
    e.preventDefault();
    destroy(route('profile.destroy'), {
      preserveScroll: true,
      onSuccess: close,
      onError: () => passwordInput.current?.focus(),
      onFinish: reset,
    });
  };

  const close = () => {
    setConfirming(false);
    clearErrors();
    reset();
    setShowPassword(false);
    setCapsOn(false);
  };

  useEffect(() => {
    if (confirming) setTimeout(() => passwordInput.current?.focus(), 120);
  }, [confirming]);

  const handleCaps = (e) =>
    e.getModifierState && setCapsOn(e.getModifierState('CapsLock'));

  return (
    <section className={`space-y-6 ${className}`}>
      <header className="rounded-xl border border-neutral-800 bg-neutral-950/70 backdrop-blur-sm px-5 py-5 ring-1 ring-neutral-900/40">
        <h2 className="text-lg font-semibold text-neutral-100">Excluir conta</h2>
        <p className="mt-1 text-sm text-neutral-400">
          A exclusão é permanente. Não haverá como recuperar seus dados depois.
        </p>

        <div className="mt-4">
          <DangerButton onClick={confirm} disabled={processing}>
            Excluir conta
          </DangerButton>
        </div>
      </header>

      <Modal show={confirming} onClose={close} maxWidth="md">
        <form onSubmit={submit} className="relative overflow-hidden rounded-2xl bg-neutral-950/90 px-6 py-8 backdrop-blur-xl ring-1 ring-white/10">
          {/* Glow */}
          <div className="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-b from-red-500/10 to-transparent" />

          <div className="flex items-start gap-4">
            <div className="rounded-xl p-3 bg-red-500/15 ring-1 ring-red-500/25">
              <ShieldAlert className="h-6 w-6 text-red-400" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-neutral-100">
                Tem certeza disso?
              </h2>
              <p className="mt-1 text-sm text-neutral-400 leading-relaxed">
                Essa ação é irreversível. Para confirmar, digite sua senha abaixo.
              </p>
            </div>
          </div>

          {/* input */}
          <div className="mt-6">
            <div className="relative">
              <div className="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                <Lock className="h-4 w-4 text-neutral-500" />
              </div>

              <TextInput
                id="password"
                ref={passwordInput}
                type={showPassword ? 'text' : 'password'}
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                onKeyUp={handleCaps}
                className="w-full rounded-lg bg-neutral-900/60 pl-9 pr-10 text-neutral-100 placeholder-neutral-500 ring-1 ring-neutral-800 focus:ring-red-500 focus:border-red-500"
                placeholder="Digite sua senha"
                autoComplete="current-password"
              />

              <button
                type="button"
                onClick={() => setShowPassword((s) => !s)}
                className="absolute inset-y-0 right-2 flex items-center px-2 text-neutral-400 hover:text-neutral-200 transition"
              >
                {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>

            {capsOn && !showPassword && (
              <p className="mt-1 text-xs text-amber-300/80">Caps Lock ativo</p>
            )}

            <InputError message={errors.password} className="mt-2" />
          </div>

          {/* actions */}
          <div className="mt-8 flex justify-end gap-3">
            <SecondaryButton onClick={close} disabled={processing}>
              Cancelar
            </SecondaryButton>

            <DangerButton disabled={processing || !data.password}>
              {processing ? 'Excluindo…' : 'Excluir definitivamente'}
            </DangerButton>
          </div>
        </form>
      </Modal>
    </section>
  );
}
