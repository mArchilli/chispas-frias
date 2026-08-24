export default function FreeShippingProgress({ total, threshold }) {
    const numericThreshold = Number(threshold);

    if (!numericThreshold || numericThreshold <= 0) {
        return null;
    }

    const numericTotal = Number(total) || 0;
    const progress = Math.min(100, (numericTotal / numericThreshold) * 100);
    const remaining = Math.max(0, numericThreshold - numericTotal);
    const completed = numericTotal >= numericThreshold;

    return (
        <div className="bg-white rounded-lg shadow-lg p-5 border-2 border-navy/20">
            <div className="flex items-center gap-2.5 mb-3">
                {completed ? (
                    <svg className="h-5 w-5 flex-shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                ) : (
                    <svg className="h-5 w-5 flex-shrink-0 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5V6a1 1 0 011-1h9a1 1 0 011 1v2h2.5a1 1 0 01.8.4l2.7 3.6v4.5a1 1 0 01-1 1H18m-13.5 0a1.5 1.5 0 103 0m-3 0a1.5 1.5 0 013 0m10 0a1.5 1.5 0 103 0m-3 0a1.5 1.5 0 013 0M7.5 16.5H14V8H4v8.5h0" />
                    </svg>
                )}
                <p className="text-sm font-semibold text-navy">
                    {completed ? (
                        '¡Felicitaciones! Tenés envío gratis 🎉'
                    ) : (
                        <>
                            Te faltan{' '}
                            <span className="text-gold font-bold">
                                ${remaining.toLocaleString('es-AR')}
                            </span>{' '}
                            para conseguir envío gratis
                        </>
                    )}
                </p>
            </div>
            <div className="h-2.5 w-full rounded-full bg-navy/10 overflow-hidden">
                <div
                    className={`h-full rounded-full transition-all duration-500 ${
                        completed ? 'bg-green-500' : 'bg-gold'
                    }`}
                    style={{ width: `${progress}%` }}
                />
            </div>
        </div>
    );
}
