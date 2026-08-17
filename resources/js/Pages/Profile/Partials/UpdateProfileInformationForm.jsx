import { Link, useForm, usePage } from '@inertiajs/react';
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

export default function UpdateProfileInformation({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'), {
            onSuccess: () => toast.success('Perfil actualizado exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <section>
            <header>
                <h2 className="text-base font-semibold text-slate-900">Información del perfil</h2>
                <p className="mt-1 text-sm text-slate-500">Actualizá tu nombre y tu correo electrónico.</p>
            </header>

            <form onSubmit={submit} className="mt-5 space-y-4">
                <Field label="Nombre" htmlFor="name" error={errors.name}>
                    <input
                        id="name"
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus
                        autoComplete="name"
                        className={inputClasses(errors.name)}
                    />
                </Field>

                <Field label="Correo electrónico" htmlFor="email" error={errors.email}>
                    <input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                        className={inputClasses(errors.email)}
                    />
                </Field>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <p>
                            Tu correo electrónico no está verificado.{' '}
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="font-medium underline hover:text-amber-900"
                            >
                                Reenviar el correo de verificación.
                            </Link>
                        </p>
                        {status === 'verification-link-sent' && (
                            <p className="mt-1.5 font-medium text-emerald-700">
                                Se envió un nuevo enlace de verificación a tu correo electrónico.
                            </p>
                        )}
                    </div>
                )}

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
