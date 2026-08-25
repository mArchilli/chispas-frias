const messages = [
    'ENVÍOS GRATIS A PARTIR DE $200.000',
    'TODOS LOS MÉTODOS DE PAGO',
    'TRABAJAMOS CON TODAS LAS MARCAS',
    'DISTRIBUIDORES OFICIALES',
];

function MessageGroup() {
    return (
        <div className="topbar-marquee-group">
            {messages.map((message) => (
                <div key={message} className="flex shrink-0 items-center">
                    <span className="whitespace-nowrap text-[10px] font-semibold tracking-[0.16em] text-navy sm:text-xs">
                        {message}
                    </span>
                    <span className="mx-3 text-xs text-navy/35" aria-hidden="true">
                        &bull;
                    </span>
                </div>
            ))}
        </div>
    );
}

export default function Topbar() {
    return (
        <div
            className="flex h-9 w-full items-center"
            role="region"
            aria-label={`Promociones: ${messages.join('. ')}`}
        >
            <div className="topbar-marquee-viewport" aria-hidden="true">
                <div className="topbar-marquee">
                    <MessageGroup />
                    <MessageGroup />
                </div>
            </div>
        </div>
    );
}
