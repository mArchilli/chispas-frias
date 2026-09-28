import { useEffect, useRef, useState } from 'react';

const messages = [
    'ENVÍOS GRATIS A PARTIR DE $200.000',
    'TODOS LOS MÉTODOS DE PAGO',
    'TRABAJAMOS CON TODAS LAS MARCAS',
    'DISTRIBUIDORES OFICIALES',
];

function MessageSequence({ measureRef }) {
    return (
        <div ref={measureRef} className="topbar-marquee-sequence">
            {messages.map((message) => (
                <div key={message} className="flex shrink-0 items-center">
                    <span className="whitespace-nowrap text-[10px] font-bold tracking-[0.16em] text-white sm:text-xs">
                        {message}
                    </span>
                    <span className="mx-3 text-lg leading-none text-white" aria-hidden="true">
                        &bull;
                    </span>
                </div>
            ))}
        </div>
    );
}

function MessageGroup({ sequenceCopies, sequenceRef }) {
    return (
        <div className="topbar-marquee-group">
            {Array.from({ length: sequenceCopies }, (_, index) => (
                <MessageSequence
                    key={index}
                    measureRef={index === 0 ? sequenceRef : null}
                />
            ))}
        </div>
    );
}

export default function Topbar() {
    const viewportRef = useRef(null);
    const sequenceRef = useRef(null);
    const [sequenceCopies, setSequenceCopies] = useState(4);

    useEffect(() => {
        const viewport = viewportRef.current;
        const sequence = sequenceRef.current;

        if (!viewport || !sequence) {
            return undefined;
        }

        const updateSequenceCopies = () => {
            const viewportWidth = viewport.getBoundingClientRect().width;
            const sequenceWidth = sequence.getBoundingClientRect().width;

            if (sequenceWidth === 0) {
                return;
            }

            const requiredCopies = Math.max(
                2,
                Math.ceil(viewportWidth / sequenceWidth) + 1,
            );

            setSequenceCopies((currentCopies) =>
                currentCopies === requiredCopies ? currentCopies : requiredCopies,
            );
        };

        updateSequenceCopies();

        const resizeObserver = new ResizeObserver(updateSequenceCopies);
        resizeObserver.observe(viewport);
        resizeObserver.observe(sequence);

        return () => resizeObserver.disconnect();
    }, []);

    return (
        <div
            className="storefront-background flex h-9 w-full items-center text-white"
            role="region"
            aria-label={`Promociones: ${messages.join('. ')}`}
        >
            <div ref={viewportRef} className="topbar-marquee-viewport" aria-hidden="true">
                <div
                    className="topbar-marquee"
                    style={{ '--topbar-marquee-duration': `${sequenceCopies * 36}s` }}
                >
                    <MessageGroup sequenceCopies={sequenceCopies} sequenceRef={sequenceRef} />
                    <MessageGroup sequenceCopies={sequenceCopies} />
                </div>
            </div>
        </div>
    );
}
