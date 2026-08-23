import type { ReactNode, SVGProps } from 'react';

export type IconName =
    | 'activity'
    | 'advisor'
    | 'arrow-right'
    | 'bell'
    | 'billing'
    | 'check'
    | 'chevron-right'
    | 'close'
    | 'crm'
    | 'dashboard'
    | 'logout'
    | 'menu'
    | 'plus'
    | 'search'
    | 'settings'
    | 'upload'
    | 'warning';

const paths: Record<IconName, ReactNode> = {
    activity: <><path d="M4 13h3l2.2-6 4.1 11 2.2-7H20" /></>,
    advisor: <><path d="m12 3 1.35 4.15a2 2 0 0 0 1.28 1.28L19 10l-4.37 1.57a2 2 0 0 0-1.28 1.28L12 17l-1.35-4.15a2 2 0 0 0-1.28-1.28L5 10l4.37-1.57a2 2 0 0 0 1.28-1.28L12 3Z" /><path d="m18 16 .55 1.45L20 18l-1.45.55L18 20l-.55-1.45L16 18l1.45-.55L18 16Z" /></>,
    'arrow-right': <><path d="M5 12h14" /><path d="m14 7 5 5-5 5" /></>,
    bell: <><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7" /><path d="M10 20h4" /></>,
    billing: <><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z" /><path d="M9 8h6M9 12h6" /></>,
    check: <><path d="m5 12 4 4L19 6" /></>,
    'chevron-right': <><path d="m9 18 6-6-6-6" /></>,
    close: <><path d="m6 6 12 12M18 6 6 18" /></>,
    crm: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></>,
    dashboard: <><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="4" rx="1.5" /><rect x="14" y="11" width="7" height="10" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /></>,
    logout: <><path d="M10 17l5-5-5-5M15 12H3" /><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5" /></>,
    menu: <><path d="M4 7h16M4 12h16M4 17h16" /></>,
    plus: <><path d="M12 5v14M5 12h14" /></>,
    search: <><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></>,
    settings: <><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1v.1h-4v-.1A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1-.4h-.1v-4H3A1.7 1.7 0 0 0 4.6 8.6a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1v-.1h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.1.38.31.72.6 1 .28.27.63.42 1 .4h.1v4H21a1.7 1.7 0 0 0-1.6.6Z" /></>,
    upload: <><path d="M12 16V4M7 9l5-5 5 5" /><path d="M5 20h14" /></>,
    warning: <><path d="M10.3 3.7 2.4 17.2A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.8L13.7 3.7a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4M12 17h.01" /></>,
};

export function Icon({ name, ...props }: { name: IconName } & SVGProps<SVGSVGElement>) {
    return (
        <svg
            aria-hidden="true"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            {...props}
        >
            {paths[name]}
        </svg>
    );
}
