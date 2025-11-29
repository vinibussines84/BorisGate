import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-neutral-100">
                    Perfil
                </h2>
            }
        >
            <Head title="Perfil" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-10 sm:px-6 lg:px-8">

                    {/* Informação do Perfil */}
                    <div className="bg-transparent p-0 shadow-none sm:rounded-none sm:p-0">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-2xl"
                        />
                    </div>

                    {/* Atualizar Senha */}
                    <div className="bg-transparent p-0 shadow-none sm:rounded-none sm:p-0">
                        <UpdatePasswordForm className="max-w-2xl" />
                    </div>

                    {/* Deletar Conta */}
                    <div className="bg-transparent p-0 shadow-none sm:rounded-none sm:p-0">
                        <DeleteUserForm className="max-w-2xl" />
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
