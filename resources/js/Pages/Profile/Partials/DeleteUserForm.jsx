import Modal from '@/Components/Modal';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function DeleteUserForm() {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();

    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({
        password: '',
    });

    const deleteUser = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);
        clearErrors();
        reset();
    };

    return (
        <section>
            <header>
                <h2 className="text-base font-semibold text-rose-900">Eliminar cuenta</h2>
                <p className="mt-1 text-sm text-rose-700/80">
                    Una vez que elimines tu cuenta, todos sus recursos y datos se eliminarán
                    permanentemente. Descargá cualquier información que quieras conservar antes de
                    continuar.
                </p>
            </header>

            <button
                type="button"
                onClick={() => setConfirmingUserDeletion(true)}
                className="mt-5 rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100"
            >
                Eliminar cuenta
            </button>

            <Modal show={confirmingUserDeletion} onClose={closeModal} maxWidth="md">
                <form onSubmit={deleteUser} className="p-6">
                    <h2 className="text-base font-semibold text-slate-900">¿Eliminar tu cuenta?</h2>
                    <p className="mt-1.5 text-sm text-slate-500">
                        Esta acción no se puede deshacer. Ingresá tu contraseña para confirmar que
                        querés eliminar tu cuenta de forma permanente.
                    </p>

                    <div className="mt-4">
                        <label htmlFor="delete_password" className="block text-sm font-medium text-slate-700">
                            Contraseña
                        </label>
                        <input
                            id="delete_password"
                            type="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="Tu contraseña"
                            autoFocus
                            className={`mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm transition focus:outline-none focus:ring-2 ${
                                errors.password
                                    ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                                    : 'border-slate-300 focus:border-navy focus:ring-navy/10'
                            }`}
                        />
                        {errors.password && (
                            <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.password}</p>
                        )}
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={closeModal}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:opacity-50"
                        >
                            {processing ? 'Eliminando...' : 'Eliminar cuenta'}
                        </button>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
