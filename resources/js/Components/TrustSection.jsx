const benefits = [
    {
        title: 'Envíos a todo el país',
        detail: 'Coordinamos el retiro en sucursal.',
        icon: (
            <>
                <path d="M3 6h11v11H3zM14 9h4l3 4v4h-7M7.5 20a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM17.5 20a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" />
            </>
        ),
    },
    {
        title: 'Hasta 6 cuotas',
        detail: 'Más opciones para pagar tu compra.',
        icon: (
            <>
                <rect x="2.5" y="5" width="19" height="14" rx="2" />
                <path d="M2.5 9h19M6 15h4" />
            </>
        ),
    },
    {
        title: 'Productos certificados',
        detail: 'Certificados por ANMAC / RENAR.',
        icon: (
            <>
                <path d="M12 2.5l2.4 1.2 2.7-.1 1 2.5 2.1 1.7-.7 2.6.7 2.6-2.1 1.7-1 2.5-2.7-.1L12 19.5l-2.4-1.2-2.7.1-1-2.5-2.1-1.7.7-2.6-.7-2.6 2.1-1.7 1-2.5 2.7.1z" />
                <path d="m8.7 11 2.2 2.2 4.5-4.5M9.5 18.4 8 22l4-1.5 4 1.5-1.5-3.6" />
            </>
        ),
    },
];

export default function TrustSection() {
    return (
        <section aria-labelledby="trust-section-heading" className="border-y border-gray-200 bg-surface text-navy-900">
            <h2 id="trust-section-heading" className="uppercase sr-only">Por qué elegir Chispas Frías</h2>
            <div className="site-shell grid grid-cols-1 divide-y divide-gray-200 py-2 sm:grid-cols-3 sm:divide-x sm:divide-y-0 sm:py-5">
                {benefits.map((benefit) => (
                    <div key={benefit.title} className="flex min-h-20 items-center gap-4 py-4 sm:min-h-24 sm:px-4 sm:py-2 sm:first:pl-0 sm:last:pr-0 lg:gap-5 lg:px-8">
                        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-ice-50 text-navy-700">
                            <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                {benefit.icon}
                            </svg>
                        </span>
                        <div className="min-w-0">
                            <h3 className="uppercase text-sm font-bold leading-snug sm:text-base">{benefit.title}</h3>
                            <p className="mt-1 text-xs leading-snug text-navy-900/65 sm:text-sm">{benefit.detail}</p>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}
