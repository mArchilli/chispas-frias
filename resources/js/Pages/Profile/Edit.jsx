import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AdminLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">Cuenta</p>
                    <h1 className="mt-1 text-xl font-semibold text-graphite sm:text-2xl">Mi perfil</h1>
                </div>
            }
        >
            <Head title="Mi Perfil - Admin" />

            <div className="mx-auto max-w-2xl space-y-4">
                <div className="rounded-xl border border-gray-200 bg-surface p-5 sm:p-6">
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                </div>

                <div className="rounded-xl border border-gray-200 bg-surface p-5 sm:p-6">
                    <UpdatePasswordForm />
                </div>

                <div className="rounded-xl border border-gray-200 bg-ice-50/40 p-5 sm:p-6">
                    <DeleteUserForm />
                </div>
            </div>
        </AdminLayout>
    );
}
