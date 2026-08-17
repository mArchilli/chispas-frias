import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import toast from 'react-hot-toast';

function Field({ label, htmlFor, error, children }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="block text-sm font-medium text-slate-700">
                {label}
            </label>
            <div className="mt-1.5">{children}</div>
            {error && <p className="mt-1.5 text-xs font-medium text-rose-600">{error}</p>}
        </div>
    );
}

function inputClasses(hasError) {
    return `block w-full rounded-lg border px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2 ${
        hasError
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
            : 'border-slate-300 focus:border-navy focus:ring-navy/10'
    }`;
}

export default function UpdatePasswordForm() {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();

    const { data, setData, errors, put, reset, processing } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                toast.success('Contraseña actualizada exitosamente');
            },
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }
                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section>
            <header>
                <h2 className="text-base font-semibold text-slate-900">Actualizar contraseña</h2>
                <p className="mt-1 text-sm text-slate-500">
                    Usá una contraseña larga y aleatoria para mantener tu cuenta segura.
                </p>
            </header>

            <form onSubmit={updatePassword} className="mt-5 space-y-4">
                <Field label="Contraseña actual" htmlFor="current_password" error={errors.current_password}>
                    <input
                        id="current_password"
                        ref={currentPasswordInput}
                        type="password"
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        autoComplete="current-password"
                        className={inputClasses(errors.current_password)}
                    />
                </Field>

                <Field label="Nueva contraseña" htmlFor="password" error={errors.password}>
                    <input
                        id="password"
                        ref={passwordInput}
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password"
                        className={inputClasses(errors.password)}
                    />
                </Field>

                <Field
                    label="Confirmar contraseña"
                    htmlFor="password_confirmation"
                    error={errors.password_confirmation}
                >
                    <input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                        className={inputClasses(errors.password_confirmation)}
                    />
                </Field>

                <div className="flex justify-end border-t border-slate-100 pt-4">
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95 disabled:opacity-50"
                    >
                        {processing ? 'Guardando...' : 'Guardar cambios'}
                    </button>
                </div>
            </form>
        </section>
    );
}
