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

export function IconChevronDown(props) {
    return (
        <svg {...base} {...props}>
            <path d="m6 9 6 6 6-6" />
        </svg>
    );
}

export function IconPencil(props) {
    return (
        <svg {...base} {...props}>
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z" />
        </svg>
    );
}

export function IconTrash(props) {
    return (
        <svg {...base} {...props}>
            <path d="M4 7h16" />
            <path d="M9 7V4.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1V7" />
            <path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13" />
            <path d="M10 11v6M14 11v6" />
        </svg>
    );
}

export function IconEye(props) {
    return (
        <svg {...base} {...props}>
            <path d="M2.5 12S5.5 5.5 12 5.5 21.5 12 21.5 12 18.5 18.5 12 18.5 2.5 12 2.5 12Z" />
            <circle cx="12" cy="12" r="3" />
        </svg>
    );
}

export function IconEyeOff(props) {
    return (
        <svg {...base} {...props}>
            <path d="M3 3l18 18" />
            <path d="M10.6 5.6A9.8 9.8 0 0 1 12 5.5c6.5 0 9.5 6.5 9.5 6.5a13.2 13.2 0 0 1-3.1 3.9M6.6 6.6C3.7 8.4 2.5 12 2.5 12s3 6.5 9.5 6.5a9.6 9.6 0 0 0 4.4-1" />
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
        </svg>
    );
}

export function IconSearch(props) {
    return (
        <svg {...base} {...props}>
            <circle cx="11" cy="11" r="7" />
            <path d="m21 21-4.3-4.3" />
        </svg>
    );
}

export function IconStar({ filled = false, ...props }) {
    return (
        <svg {...base} fill={filled ? 'currentColor' : 'none'} {...props}>
            <path
                strokeLinejoin="round"
                d="m12 3.5 2.6 5.4 5.9.8-4.3 4.2 1 5.9-5.2-2.8-5.2 2.8 1-5.9-4.3-4.2 5.9-.8L12 3.5Z"
            />
        </svg>
    );
}

export function IconDotsVertical(props) {
    return (
        <svg {...base} fill="currentColor" stroke="none" {...props}>
            <circle cx="12" cy="5" r="1.5" />
            <circle cx="12" cy="12" r="1.5" />
            <circle cx="12" cy="19" r="1.5" />
        </svg>
    );
}

export function IconPhoto(props) {
    return (
        <svg {...base} {...props}>
            <rect x="3.5" y="4.5" width="17" height="15" rx="2" />
            <circle cx="8.5" cy="9.5" r="1.5" />
            <path d="m4.5 16.5 4.5-4.5a1.5 1.5 0 0 1 2.1 0l5.9 5.9M14 14l1.6-1.6a1.5 1.5 0 0 1 2.1 0l1.8 1.8" />
        </svg>
    );
}

export function IconUploadCloud(props) {
    return (
        <svg {...base} {...props}>
            <path d="M7 17.5a4.5 4.5 0 0 1-1-8.9 5.5 5.5 0 0 1 10.8-1.5A4.5 4.5 0 0 1 17 17.5" />
            <path d="M12 21v-8m0 0-3 3m3-3 3 3" />
        </svg>
    );
}

export function IconX(props) {
    return (
        <svg {...base} {...props}>
            <path d="M6 6l12 12M18 6 6 18" />
        </svg>
    );
}

export function IconPercent(props) {
    return (
        <svg {...base} {...props}>
            <path d="M5 19 19 5" />
            <circle cx="7.5" cy="7.5" r="2.25" />
            <circle cx="16.5" cy="16.5" r="2.25" />
        </svg>
    );
}

export function IconCheck(props) {
    return (
        <svg {...base} {...props}>
            <path d="m4 12 5 5L20 6" />
        </svg>
    );
}

export function IconChevronLeft(props) {
    return (
        <svg {...base} {...props}>
            <path d="m15 6-6 6 6 6" />
        </svg>
    );
}

export function IconChevronRight(props) {
    return (
        <svg {...base} {...props}>
            <path d="m9 6 6 6-6 6" />
        </svg>
    );
}

export function IconMapPin(props) {
    return (
        <svg {...base} {...props}>
            <path d="M19.5 10.5c0 5.25-7.5 10-7.5 10s-7.5-4.75-7.5-10a7.5 7.5 0 0 1 15 0Z" />
            <circle cx="12" cy="10.5" r="2.5" />
        </svg>
    );
}

export function IconVideo(props) {
    return (
        <svg {...base} {...props}>
            <rect x="3" y="6" width="12" height="12" rx="2" />
            <path d="m15 10 5.5-3v10L15 14" />
        </svg>
    );
}

export function IconTruck(props) {
    return (
        <svg {...base} {...props}>
            <path d="M2.5 6.5h11a1 1 0 011 1v8h-12a1 1 0 01-1-1v-7a1 1 0 011-1Z" />
            <path d="M14.5 10h3.5l3 3.5V15.5h-6.5V10Z" />
            <circle cx="7" cy="17.5" r="1.75" />
            <circle cx="17" cy="17.5" r="1.75" />
        </svg>
    );
}

export function IconTicket(props) {
    return (
        <svg {...base} {...props}>
            <path d="M3 9.5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a1.8 1.8 0 0 0 0 3v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a1.8 1.8 0 0 0 0-3v-1Z" />
            <path d="M9.5 8v8" strokeDasharray="1.8 2" />
        </svg>
    );
}

export function IconUsers(props) {
    return (
        <svg {...base} {...props}>
            <circle cx="9" cy="8" r="3" />
            <path d="M2.5 20a6.5 6.5 0 0 1 13 0" />
            <path d="M16 8.3a3 3 0 1 1 0 5.9" />
            <path d="M18.5 14.3a5.7 5.7 0 0 1 3 5" />
        </svg>
    );
}

export function IconCurrencyDollar(props) {
    return (
        <svg {...base} {...props}>
            <path d="M12 3v18" />
            <path d="M16.5 6.5A3.5 3.5 0 0 0 13 3.5h-2.5a3 3 0 0 0 0 6h3a3 3 0 0 1 0 6H10a3.5 3.5 0 0 1-3.5-3.5" />
        </svg>
    );
}

export function IconSparkles(props) {
    return (
        <svg {...base} {...props}>
            <path d="M12 3.5 13.6 8 18 9.6 13.6 11.2 12 15.6 10.4 11.2 6 9.6 10.4 8 12 3.5Z" />
            <path d="M18 14.5l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2Z" />
            <path d="M5.5 13.5l.6 1.6 1.6.6-1.6.6-.6 1.6-.6-1.6-1.6-.6 1.6-.6.6-1.6Z" />
        </svg>
    );
}

export function IconSettings(props) {
    return (
        <svg {...base} {...props}>
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" />
        </svg>
    );
}
