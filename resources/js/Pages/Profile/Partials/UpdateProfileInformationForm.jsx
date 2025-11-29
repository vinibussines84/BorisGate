// resources/js/Pages/Profile/Partials/UpdateProfileInformation.jsx
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({ mustVerifyEmail, status, className = '' }) {
  const user = usePage().props.auth.user;

  const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
    name: user.name,
    email: user.email,
  });

  const submit = (e) => {
    e.preventDefault();
    patch(route('profile.update'));
  };

  const initials = (user?.name || 'U')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((s) => s[0]?.toUpperCase() || '')
    .join('');

  return (
    <section className={className} data-no-white-bg>
      {/* remove SOMENTE o wrapper branco/sombra do Jetstream ao redor deste bloco */}
      <style>{`
        [data-no-white-bg] :where(.bg-white, .bg-gray-50) { background-color: transparent !important; }
        [data-no-white-bg] :where(.shadow, .shadow-sm, .shadow-md, .shadow-lg) { box-shadow: none !important; }
        [data-no-white-bg] :where(.ring-1, .ring-2) { --tw-ring-color: transparent !important; }
        /* mantém seu cartão escuro intacto (não tem classes bg-white/gray) */
      `}</style>

      <div className="rounded-2xl border border-neutral-800 bg-neutral-950/90 shadow-[0_20px_60px_-30px_rgba(0,0,0,.7)] ring-1 ring-inset ring-neutral-900/30">
        <div className="flex items-center gap-4 border-b border-neutral-800 px-5 py-4">
          <div className="grid size-11 place-items-center rounded-full bg-neutral-800 text-sm font-semibold text-neutral-100 ring-1 ring-neutral-700">
            {initials}
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold tracking-tight text-neutral-100">Informações do perfil</h2>
            <p className="mt-0.5 text-sm text-neutral-400">Atualize seu nome e endereço de e-mail.</p>
          </div>
        </div>

        <form onSubmit={submit} className="space-y-6 px-5 py-5">
          <div>
            <InputLabel htmlFor="name" value="Nome" className="text-neutral-300" />
            <TextInput
              id="name"
              className="mt-1 block w-full rounded-xl border-neutral-800 bg-neutral-900/70 text-neutral-100 placeholder-neutral-500 ring-1 ring-inset ring-neutral-800 focus:border-amber-500 focus:ring-amber-500"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              required
              isFocused
              autoComplete="name"
              placeholder="Seu nome completo"
            />
            <InputError className="mt-2 text-red-400" message={errors.name} />
          </div>

          <div>
            <InputLabel htmlFor="email" value="E-mail" className="text-neutral-300" />
            <TextInput
              id="email"
              type="email"
              className="mt-1 block w-full rounded-xl border-neutral-800 bg-neutral-900/70 text-neutral-100 placeholder-neutral-500 ring-1 ring-inset ring-neutral-800 focus:border-amber-500 focus:ring-amber-500"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              required
              autoComplete="username"
              placeholder="seu@email.com"
            />
            <InputError className="mt-2 text-red-400" message={errors.email} />
          </div>

          {mustVerifyEmail && user.email_verified_at === null && (
            <div className="rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-sm">
              <p className="text-neutral-300">
                Seu e-mail ainda não foi verificado.
                <Link
                  href={route('verification.send')}
                  method="post"
                  as="button"
                  className="ml-1 rounded-md px-1.5 py-0.5 text-amber-400 underline underline-offset-4 hover:text-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-500/60"
                >
                  Reenviar verificação
                </Link>
              </p>
              {status === 'verification-link-sent' && (
                <div className="mt-2 font-medium text-amber-300">Enviamos um novo link de verificação para seu e-mail.</div>
              )}
            </div>
          )}

          <div className="flex flex-wrap items-center gap-4">
            <PrimaryButton disabled={processing} className="rounded-xl bg-amber-500 text-neutral-900 hover:bg-amber-400 focus:ring-amber-500 disabled:opacity-70">
              {processing ? 'Salvando…' : 'Salvar alterações'}
            </PrimaryButton>

            <Transition
              show={recentlySuccessful}
              enter="transition ease-out duration-200"
              enterFrom="opacity-0 translate-y-0.5"
              enterTo="opacity-100 translate-y-0"
              leave="transition ease-in duration-150"
              leaveFrom="opacity-100 translate-y-0"
              leaveTo="opacity-0 translate-y-0.5"
            >
              <p className="text-sm text-neutral-400">Salvo com sucesso.</p>
            </Transition>
          </div>
        </form>
      </div>
    </section>
  );
}
