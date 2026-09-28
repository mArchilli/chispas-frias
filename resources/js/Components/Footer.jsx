import { useState } from 'react';

export default function Footer() {
    const [emailCopied, setEmailCopied] = useState(false);
    const [tooltipPos, setTooltipPos] = useState({ x: 0, y: 0 });

    const copyEmail = (e) => {
        navigator.clipboard.writeText('chispasfrias.oficial@gmail.com');
        setTooltipPos({ x: e.clientX, y: e.clientY });
        setEmailCopied(true);
        setTimeout(() => setEmailCopied(false), 2000);
    };

    return (
        <>
        <footer className="border-t border-gray-200 bg-white text-navy-900">
            <div className="site-shell pb-8 pt-12 sm:pb-10 sm:pt-14">
                <section aria-labelledby="footer-cta-title" className="relative overflow-hidden rounded-[2rem] bg-storefront px-6 py-8 text-white sm:px-10 sm:py-10 lg:px-12">
                    <div aria-hidden="true" className="pointer-events-none absolute -right-24 -top-28 h-72 w-72 rounded-full border-[36px] border-white/80 sm:h-96 sm:w-96" />
                    <div className="relative z-10 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between lg:gap-12">
                        <div className="max-w-2xl">
                            <h2 id="footer-cta-title" className="text-3xl font-bold uppercase leading-tight text-white sm:text-4xl">Tu próximo evento merece brillar.</h2>
                            <p className="mt-5 max-w-xl text-base leading-relaxed text-white sm:text-lg">Contanos qué tenés en mente y te ayudamos a encontrar el efecto ideal.</p>
                        </div>
                        <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center lg:flex-col lg:items-start">
                            <a
                                href="https://wa.me/5491178886833?text=Hola!%20Quiero%20asesoramiento%20para%20mi%20evento."
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Hablemos de tu evento por WhatsApp"
                                className="inline-flex min-h-12 items-center justify-center gap-3 rounded-full bg-white px-7 py-3 text-sm font-semibold text-navy-900 shadow-soft transition-colors hover:bg-ice-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white sm:text-base"
                            >
                                Hablemos de tu evento
                                <svg aria-hidden="true" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </section>

                <div className="grid grid-cols-1 gap-10 py-10 sm:grid-cols-2 sm:gap-12 lg:grid-cols-12 lg:gap-8 lg:py-12">
                    <div className="sm:col-span-2 lg:col-span-5">
                        <a href="/" className="inline-block rounded-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-navy-700" aria-label="Chispas Frías, ir al inicio">
                            <img
                                src="/images/chispas-frias-logo.png"
                                alt=""
                                className="w-24 brightness-0 drop-shadow-sm sm:w-28"
                            />
                        </a>
                        <p className="mt-6 max-w-sm text-lg font-semibold leading-snug text-navy-900">Pirotecnia fría que eleva tu evento.</p>
                        <p className="mt-3 max-w-sm text-sm leading-relaxed text-graphite/75">Chispas que acompañan tus mejores momentos.</p>
                    </div>
                    <div className="lg:col-span-3">
                        <h3 className="mb-5 text-xs font-bold uppercase tracking-[0.18em] text-navy-700">Navegación</h3>
                        <ul className="space-y-3 text-sm font-medium">
                            <li><a href="/productos" className="rounded-sm transition-colors hover:text-navy-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-700">Catálogo</a></li>
                            <li><a href="/#servicios" className="rounded-sm transition-colors hover:text-navy-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-700">Servicios</a></li>
                            <li><a href="/contacto" className="rounded-sm transition-colors hover:text-navy-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-700">Contacto</a></li>
                            <li><a href="/login" className="rounded-sm transition-colors hover:text-navy-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-700">Ingresar</a></li>
                        </ul>
                    </div>
                    <div className="min-w-0 lg:col-span-4">
                        <h3 className="mb-5 text-xs font-bold uppercase tracking-[0.18em] text-navy-700">Contacto</h3>
                        <div className="space-y-4 text-sm font-medium">
                            {/* Email */}
                            <div className="flex min-w-0 items-start gap-3">
                                <svg aria-hidden="true" className="mt-0.5 h-5 w-5 flex-shrink-0 text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <button type="button" onClick={copyEmail} aria-label={emailCopied ? 'Correo electrónico copiado' : 'Copiar correo electrónico'} className="min-w-0 break-all text-left transition-colors hover:text-navy-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-700">
                                    {emailCopied ? '¡Copiado! ✓' : 'chispasfrias.oficial@gmail.com'}
                                </button>
                            </div>
                            
                            {/* Instagram */}
                            <div className="flex min-w-0 items-start gap-3">
                                <svg aria-hidden="true" className="mt-0.5 h-5 w-5 flex-shrink-0 text-navy-700" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                </svg>
                                <a href="https://instagram.com/chispasfrias.oficial" target="_blank" rel="noopener noreferrer" className="break-all transition-colors hover:text-navy-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-700">
                                    @chispasfrias.oficial
                                </a>
                            </div>
                            
                            {/* WhatsApp */}
                            <div className="flex min-w-0 items-start gap-3">
                                <svg aria-hidden="true" className="mt-0.5 h-5 w-5 flex-shrink-0 text-navy-700" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                <a href="https://wa.me/5491178886833?text=Hola!%20quiero%20contactarme%20con%20un%20asesor%20de%20Chispas%20Frias%20para%20que%20me%20resuelva%20una%20duda." target="_blank" rel="noopener noreferrer" className="transition-colors hover:text-navy-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-700">
                                    +54 9 11 7888-6833
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="flex flex-col gap-3 border-t border-gray-200 pt-8 text-sm text-graphite/75 sm:flex-row sm:items-center sm:justify-between">
                    <p>&copy; {new Date().getFullYear()} Chispas Frías. Todos los derechos reservados.</p>
                    <p>Powered by Pampa Labs</p>
                </div>
            </div>
        </footer>

        {emailCopied && (
            <div
                className="fixed z-50 bg-navy-900 text-white text-sm px-3 py-1.5 rounded-lg shadow-lg pointer-events-none"
                style={{ left: tooltipPos.x + 14, top: tooltipPos.y - 14 }}
            >
                Mail copiado con éxito ✓
            </div>
        )}
        </>
    );
}
