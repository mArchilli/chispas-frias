import React from 'react';

const base = {
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.75,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
};

export function IconHome(props) {
    return (
        <svg {...base} {...props}>
            <path d="M3 10.5 12 3l9 7.5" />
            <path d="M5.5 9.5V20a1 1 0 0 0 1 1H9a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1h2.5a1 1 0 0 0 1-1V9.5" />
        </svg>
    );
}

export function IconLayers(props) {
    return (
        <svg {...base} {...props}>
            <path d="m12 3 8.5 4.5L12 12 3.5 7.5 12 3Z" />
            <path d="m3.5 12 8.5 4.5 8.5-4.5" />
            <path d="m3.5 16.5 8.5 4.5 8.5-4.5" />
        </svg>
    );
}

export function IconBox(props) {
    return (
        <svg {...base} {...props}>
            <path d="M21 8v8a1 1 0 0 1-.5.87l-8 4.5a1 1 0 0 1-1 0l-8-4.5A1 1 0 0 1 3 16V8a1 1 0 0 1 .5-.87l8-4.5a1 1 0 0 1 1 0l8 4.5A1 1 0 0 1 21 8Z" />
            <path d="M3.3 7.3 12 12l8.7-4.7M12 12v9.5" />
        </svg>
    );
}

export function IconTag(props) {
    return (
        <svg {...base} {...props}>
            <path d="M11.5 3H4v7.5L13.5 20a1.5 1.5 0 0 0 2.1 0l5.4-5.4a1.5 1.5 0 0 0 0-2.1L11.5 3Z" />
            <circle cx="8" cy="7.5" r="1.25" />
        </svg>
    );
}

export function IconClipboard(props) {
    return (
        <svg {...base} {...props}>
            <rect x="5" y="4.5" width="14" height="16" rx="2" />
            <path d="M9 4.5V3.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1" />
            <path d="m8.5 12.5 2 2 4.5-4.5" />
        </svg>
    );
}

export function IconChevronsLeft(props) {
    return (
        <svg {...base} {...props}>
            <path d="m11 17-5-5 5-5M18 17l-5-5 5-5" />
        </svg>
    );
}

export function IconChevronsRight(props) {
    return (
        <svg {...base} {...props}>
            <path d="m13 17 5-5-5-5M6 17l5-5-5-5" />
        </svg>
    );
}

export function IconGlobe(props) {
    return (
        <svg {...base} {...props}>
            <circle cx="12" cy="12" r="9" />
            <path d="M3 12h18M12 3c2.4 2.6 3.6 5.6 3.6 9s-1.2 6.4-3.6 9c-2.4-2.6-3.6-5.6-3.6-9s1.2-6.4 3.6-9Z" />
        </svg>
    );
}

export function IconUser(props) {
    return (
        <svg {...base} {...props}>
            <circle cx="12" cy="8" r="3.5" />
            <path d="M4.5 20a7.5 7.5 0 0 1 15 0" />
        </svg>
    );
}

export function IconLogout(props) {
    return (
        <svg {...base} {...props}>
            <path d="M15.5 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7.5a2 2 0 0 0 2-2v-2" />
            <path d="M9 12h11.5M17 8.5l3.5 3.5-3.5 3.5" />
        </svg>
    );
}

export function IconAlertTriangle(props) {
    return (
        <svg {...base} {...props}>
            <path d="M10.6 4.3 2.9 18a1.5 1.5 0 0 0 1.3 2.2h15.6a1.5 1.5 0 0 0 1.3-2.2L13.4 4.3a1.5 1.5 0 0 0-2.8 0Z" />
            <path d="M12 9.5v4M12 17h.01" />
        </svg>
    );
}

export function IconAlertOctagon(props) {
    return (
        <svg {...base} {...props}>
            <path d="M7.5 3h9L21 7.5v9L16.5 21h-9L3 16.5v-9L7.5 3Z" />
            <path d="M12 8v4.5M12 16h.01" />
        </svg>
    );
}

export function IconClock(props) {
    return (
        <svg {...base} {...props}>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3.5 2" />
        </svg>
    );
}

export function IconTrendingUp(props) {
    return (
        <svg {...base} {...props}>
            <path d="m3 16 6.5-6.5 4 4L21 6" />
            <path d="M15 6h6v6" />
        </svg>
    );
}

export function IconArrowRight(props) {
    return (
        <svg {...base} {...props}>
            <path d="M4 12h16M13 5l7 7-7 7" />
        </svg>
    );
}

export function IconPlus(props) {
    return (
        <svg {...base} {...props}>
            <path d="M12 5v14M5 12h14" />
        </svg>
    );
}

export function IconInbox(props) {
    return (
        <svg {...base} {...props}>
            <path d="M3.5 12h5l1.5 3h4l1.5-3h5" />
            <path d="M5.2 6.2 3.5 12v6a1.5 1.5 0 0 0 1.5 1.5h14a1.5 1.5 0 0 0 1.5-1.5v-6l-1.7-5.8A1.5 1.5 0 0 0 17.4 5H6.6a1.5 1.5 0 0 0-1.4 1.2Z" />
        </svg>
    );
}
