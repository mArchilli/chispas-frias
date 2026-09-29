export default function FreeShippingProgress({ total, threshold, freeShippingByCombo = false }) {
    // El envío gratis por combo tiene prioridad: se muestra fijo, sin barra de
    // progreso (el pedido ya califica sin importar el umbral global).
    if (freeShippingByCombo) {
        return (
            <div className="rounded-[1.75rem] border border-gray-200 bg-surface p-5 text-navy-900 shadow-card sm:p-6">
                <div className="flex items-center gap-2.5">
                    <svg className="h-5 w-5 flex-shrink-0 text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <p className="text-sm font-semibold text-navy-900">¡Tu combo incluye envío gratis!</p>
                </div>
            </div>
        );
    }

    const numericThreshold = Number(threshold);

    if (!numericThreshold || numericThreshold <= 0) {
        return null;
    }

    const numericTotal = Number(total) || 0;
    const progress = Math.min(100, (numericTotal / numericThreshold) * 100);
    const remaining = Math.max(0, numericThreshold - numericTotal);
    const completed = numericTotal >= numericThreshold;

    return (
        <div className="rounded-[1.75rem] border border-gray-200 bg-surface p-5 text-navy-900 shadow-card sm:p-6">
            <div className="flex items-center gap-2.5 mb-3">
                {completed ? (
                    <svg className="h-5 w-5 flex-shrink-0 text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                ) : (
                    <svg className="h-5 w-5 flex-shrink-0 text-navy-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5V6a1 1 0 011-1h9a1 1 0 011 1v2h2.5a1 1 0 01.8.4l2.7 3.6v4.5a1 1 0 01-1 1H18m-13.5 0a1.5 1.5 0 103 0m-3 0a1.5 1.5 0 013 0m10 0a1.5 1.5 0 103 0m-3 0a1.5 1.5 0 013 0M7.5 16.5H14V8H4v8.5h0" />
                    </svg>
                )}
                <p className="text-sm font-semibold text-navy-900">
                    {completed ? (
                        '¡Tu pedido tiene envío gratis!'
                    ) : (
                        <>
                            Te faltan{' '}
                            <span className="text-navy-700 font-bold">
                                ${remaining.toLocaleString('es-AR')}
                            </span>{' '}
                            para conseguir envío gratis
                        </>
                    )}
                </p>
            </div>
            <div className="h-2.5 w-full overflow-hidden rounded-full bg-ice-50">
                <div
                    className={`h-full rounded-full transition-all duration-500 ${
                        completed ? 'bg-navy-900' : 'bg-navy-700'
                    }`}
                    style={{ width: `${progress}%` }}
                />
            </div>
        </div>
    );
}
