import { Link, useForm, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';

function Field({ label, htmlFor, error, children }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="block text-sm font-medium text-graphite/85">
                {label}
            </label>
            <div className="mt-1.5">{children}</div>
            {error && <p className="mt-1.5 text-xs font-medium text-navy-700">{error}</p>}
        </div>
    );
}

function inputClasses(hasError) {
    return `block w-full rounded-lg border px-3 py-2 text-sm text-graphite placeholder:text-gray-500 transition focus:outline-none focus:ring-2 ${
        hasError
            ? 'border-navy-700 focus:border-ice-500 focus:ring-ice-100'
            : 'border-gray-200 focus:border-ice-500 focus:ring-ice-100'
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
                <h2 className="text-base font-semibold text-graphite">Información del perfil</h2>
                <p className="mt-1 text-sm text-gray-500">Actualizá tu nombre y tu correo electrónico.</p>
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
                    <div className="rounded-lg bg-ice-50 px-4 py-3 text-sm text-navy-900">
                        <p>
                            Tu correo electrónico no está verificado.{' '}
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="font-medium underline hover:text-navy-900"
                            >
                                Reenviar el correo de verificación.
                            </Link>
                        </p>
                        {status === 'verification-link-sent' && (
                            <p className="mt-1.5 font-medium text-navy-700">
                                Se envió un nuevo enlace de verificación a tu correo electrónico.
                            </p>
                        )}
                    </div>
                )}

                <div className="flex justify-end border-t border-gray-200 pt-4">
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center rounded-lg bg-storefront px-4 py-2 text-sm font-semibold text-white transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront disabled:bg-gray-200 disabled:text-gray-500"
                    >
                        {processing ? 'Guardando...' : 'Guardar cambios'}
                    </button>
                </div>
            </form>
        </section>
    );
}
