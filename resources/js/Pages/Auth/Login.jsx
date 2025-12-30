import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Ingresar" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <h2 className="text-2xl font-extrabold text-navy mb-4">Ingresar a tu cuenta</h2>
            <p className="text-sm text-navy/70 mb-6">Ingresa tus credenciales para acceder al panel de compras y servicios.</p>

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="Correo electrónico" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full border border-navy/10"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Contraseña" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full border border-navy/10"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 flex items-center justify-between">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                        />
                        <span className="ms-2 text-sm text-navy">Recordarme</span>
                    </label>

                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-sm text-navy/70 hover:text-navy underline"
                        >
                            ¿Olvidaste tu contraseña?
                        </Link>
                    )}
                </div>

                <div className="mt-6">
                    <PrimaryButton
                        className="w-full px-6 py-3 rounded-full font-bold text-base normal-case bg-gold text-navy hover:bg-gold/90 hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-2xl"
                        disabled={processing}
                    >
                        Ingresar
                    </PrimaryButton>
                </div>

                <div className="mt-4 text-center text-sm text-navy/70">
                    ¿No tenés cuenta? <Link href={route('register')} className="text-navy font-medium underline">Registrate</Link>
                </div>

                <div className="mt-2 text-center text-sm">
                    <Link href="/" className="text-navy font-medium underline">Volver al inicio</Link>
                </div>
            </form>
        </GuestLayout>
    );
}
