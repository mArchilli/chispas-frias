import { Head } from '@inertiajs/react';
import { useState } from 'react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';

const EMAIL = 'chispasfrias.oficial@gmail.com';
const WHATSAPP_URL = 'https://wa.me/5491178886833?text=Hola!%20Quiero%20contactarme%20con%20un%20asesor%20de%20Chispas%20Frias%20para%20que%20me%20resuelva%20una%20duda.';

const contactChannels = [
    {
        id: 'whatsapp',
        title: 'WhatsApp',
        description: 'Respuesta inmediata para tus consultas.',
        detail: '+54 9 11 7888-6833',
        action: 'Chateá con nosotros',
        href: WHATSAPP_URL,
    },
    {
        id: 'email',
        title: 'Email',
        description: 'Consultas detalladas y cotizaciones.',
        detail: EMAIL,
        action: 'Copiar mail',
    },
    {
        id: 'instagram',
        title: 'Instagram',
        description: 'Seguinos y mirá nuestros trabajos.',
        detail: '@chispasfrias.oficial',
        action: 'Visitar perfil',
        href: 'https://instagram.com/chispasfrias.oficial',
    },
];

function ContactIcon({ type }) {
    if (type === 'email') {
        return (
            <svg className="h-9 w-9 sm:h-10 sm:w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
        );
    }

    return (
        <svg className="h-9 w-9 sm:h-10 sm:w-10" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d={type === 'whatsapp'
                ? 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z'
                : 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z'}
            />
        </svg>
    );
}

export default function Contact({ auth }) {
    const [copyStatus, setCopyStatus] = useState('idle');

    const fallbackCopy = () => {
        const previousFocus = document.activeElement;
        const textarea = document.createElement('textarea');
        textarea.value = EMAIL;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        try {
            textarea.focus();
            textarea.select();
            return document.execCommand('copy');
        } finally {
            textarea.remove();
            previousFocus?.focus();
        }
    };

    const copyEmail = async () => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(EMAIL);
                    setCopyStatus('copied');
                    return;
                } catch {
                    // Usar la alternativa del navegador si el portapapeles no está disponible.
                }
            }
            setCopyStatus(fallbackCopy() ? 'copied' : 'error');
        } catch {
            setCopyStatus('error');
        }
    };

    return (
        <div className="storefront-background flex min-h-screen flex-col overflow-x-clip text-white">
            <Head title="Contacto | Chispas Frías - Alquiler de Pirotecnia Fría">
                <meta name="description" content="Contactá a Chispas Frías para alquilar pirotecnia fría para tu evento. WhatsApp, email e Instagram. Respuesta inmediata y cotizaciones personalizadas." />
                <meta property="og:title" content="Contacto | Chispas Frías" />
                <meta property="og:description" content="Escribinos por WhatsApp, email o Instagram para cotizar chispas frías para tu evento." />
                <meta property="og:image" content="/images/chispas-frias-logo.png" />
                <meta property="og:type" content="website" />
            </Head>

            <Navbar auth={auth} />

            <header className="pb-10 pt-40 sm:pb-12 sm:pt-44">
                <div className="site-shell">
                    <h1 className="uppercase max-w-4xl text-4xl font-bold leading-tight tracking-[-0.045em] text-white sm:text-5xl lg:text-6xl">
                        Estamos acá para ayudarte
                    </h1>
                    <p className="mt-4 max-w-3xl text-base leading-relaxed text-white sm:text-lg">
                        ¿Tenés dudas sobre nuestros productos? ¿Querés cotizar para tu evento? Escribinos y te respondemos al instante.
                    </p>
                </div>
            </header>

            <main className="flex-1 pb-16 sm:pb-20">
                <div className="site-shell">
                    <div className="grid gap-5 md:grid-cols-3 lg:gap-8">
                        {contactChannels.map((channel) => (
                            <article
                                key={channel.id}
                                className="flex min-h-[320px] min-w-0 flex-col rounded-[1.75rem] border border-gray-200 bg-surface p-7 text-navy-900 shadow-card transition-shadow hover:shadow-card-hover sm:min-h-[340px] sm:p-8 lg:min-h-[380px] lg:p-10"
                            >
                                <div className="flex items-center gap-4">
                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center text-storefront">
                                        <ContactIcon type={channel.id} />
                                    </div>
                                    <h2 className="uppercase text-2xl font-bold text-navy-900">{channel.title}</h2>
                                </div>
                                <p className="mt-6 text-base leading-relaxed text-navy-900/75">
                                    {channel.description}
                                </p>
                                <p className="mt-3 break-words text-sm font-semibold leading-relaxed text-navy-900 lg:text-base">
                                    {channel.detail}
                                </p>
                                <div className="mt-auto pt-6">
                                    {channel.id === 'email' ? (
                                        <>
                                            <button
                                                type="button"
                                                onClick={copyEmail}
                                                className="inline-flex min-h-12 w-full items-center justify-center rounded-full border border-storefront px-4 py-3 text-sm font-semibold text-storefront transition hover:bg-storefront hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2"
                                            >
                                                <span aria-live="polite">
                                                    {copyStatus === 'copied' ? '¡Mail copiado! ✓' : channel.action}
                                                </span>
                                            </button>
                                            {copyStatus === 'error' && (
                                                <p role="status" className="mt-3 text-sm text-navy-700">
                                                    No pudimos copiarlo. Podés seleccionar el correo de arriba.
                                                </p>
                                            )}
                                        </>
                                    ) : (
                                        <a
                                            href={channel.href}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className={`inline-flex min-h-12 w-full items-center justify-center rounded-full border border-storefront px-4 py-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2 ${channel.id === 'whatsapp' ? 'bg-storefront text-white hover:brightness-90' : 'text-storefront hover:bg-storefront hover:text-white'}`}
                                        >
                                            {channel.action}
                                        </a>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>

                </div>
            </main>

            <Footer />
            <CartButton />
            <WhatsAppButton />
        </div>
    );
}
