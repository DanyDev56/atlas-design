import type { ReactNode } from 'react';
import { Brand } from '@/components/ui/Brand';
import { Icon } from '@/components/ui/Icon';

export function AuthLayout({
    children,
    title,
    subtitle,
    wide = false,
}: {
    children: ReactNode;
    title: string;
    subtitle: string;
    wide?: boolean;
}) {
    return (
        <div className="relative flex min-h-screen overflow-hidden bg-atlas-surface">
            <div className="relative hidden w-[46%] flex-col justify-between overflow-hidden bg-atlas-sidebar p-10 text-white lg:flex xl:p-14">
                <div className="pointer-events-none absolute -right-36 -top-28 size-[34rem] rounded-full border border-white/[0.06]" />
                <div className="pointer-events-none absolute -right-16 -top-8 size-[22rem] rounded-full border border-white/[0.07]" />
                <div className="pointer-events-none absolute bottom-[-14rem] left-[-10rem] size-[30rem] rounded-full bg-atlas-accent/20 blur-3xl" />

                <div className="relative z-10">
                    <Brand inverse />
                    <h1 className="mt-20 max-w-lg text-[2.75rem] font-semibold leading-[1.08] tracking-[-0.045em] xl:text-[3.35rem]">
                        Pilotez votre activité avec confiance.
                    </h1>
                    <p className="mt-6 max-w-md text-[0.97rem] leading-7 text-white/58">
                        Vos clients, vos revenus et vos prochaines décisions réunis dans un espace clair, fiable et actionnable.
                    </p>
                    <div className="mt-10 space-y-4">
                        {[
                            'Une vision commerciale vraiment exploitable',
                            'Une facturation suivie de bout en bout',
                            'Des recommandations toujours expliquées',
                        ].map((benefit) => (
                            <div key={benefit} className="flex items-center gap-3 text-sm text-white/72">
                                <span className="grid size-6 place-items-center rounded-full bg-white/[0.08] text-[#58c8ac]">
                                    <Icon name="check" className="size-3.5" />
                                </span>
                                {benefit}
                            </div>
                        ))}
                    </div>
                </div>
                <div className="relative z-10 flex items-center gap-3 border-t border-white/[0.08] pt-6 text-xs text-white/38">
                    <span className="size-1.5 rounded-full bg-[#58c8ac]" />
                    Conçu pour décider à partir de données réelles.
                </div>
            </div>

            <div className="relative flex w-full flex-col justify-center px-4 py-8 sm:px-8 lg:w-[54%] lg:px-12 xl:px-20">
                <div className="pointer-events-none absolute right-[-8rem] top-[-8rem] size-80 rounded-full bg-atlas-accent/5 blur-3xl" />
                <div className={`relative mx-auto w-full ${wide ? 'max-w-2xl' : 'max-w-[29rem]'}`}>
                    <div className="mb-10 lg:hidden">
                        <Brand />
                    </div>
                    <div className="rounded-[1.75rem] border border-white/80 bg-white/88 p-6 shadow-[0_24px_70px_rgb(16_28_26/0.09)] backdrop-blur sm:p-9">
                        <p className="atlas-kicker">Espace sécurisé</p>
                        <h2 className="mt-2.5 text-[1.8rem] font-semibold leading-tight tracking-[-0.035em] text-atlas-ink">{title}</h2>
                        <p className="mt-2.5 text-sm leading-6 text-atlas-ink-muted">{subtitle}</p>
                        <div className="mt-8">{children}</div>
                    </div>
                    <p className="mt-5 text-center text-[11px] text-atlas-ink-muted/75">
                        Atlas protège vos données et ne présente jamais d’indicateur inventé.
                    </p>
                </div>
            </div>
        </div>
    );
}

export function FormField({
    label,
    hint,
    children,
}: {
    label: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-semibold text-atlas-ink">{label}</span>
            {children}
            {hint && <span className="mt-1.5 block text-xs leading-relaxed text-atlas-ink-muted">{hint}</span>}
        </label>
    );
}

export const inputClassName =
    'w-full min-h-11 rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm text-atlas-ink shadow-[0_1px_2px_rgb(16_28_26/0.025)] outline-none focus:border-atlas-accent focus:ring-4 focus:ring-atlas-accent/10';

export function SubmitButton({
    loading,
    disabled = false,
    loadingLabel = 'Patientez…',
    children,
}: {
    loading: boolean;
    disabled?: boolean;
    loadingLabel?: string;
    children: ReactNode;
}) {
    return (
        <button
            type="submit"
            disabled={loading || disabled}
            aria-busy={loading}
            className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-atlas-accent px-4 py-3 text-sm font-semibold text-white hover:bg-[#066557] disabled:cursor-not-allowed disabled:opacity-60"
        >
            {loading && <span className="size-4 animate-spin rounded-full border-2 border-white/35 border-t-white" aria-hidden="true" />}
            {loading ? loadingLabel : children}
        </button>
    );
}

export function ErrorBanner({ message }: { message: string | null }) {
    if (!message) return null;
    return (
        <div role="alert" className="mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50/90 px-4 py-3 text-sm leading-5 text-red-800">
            <Icon name="warning" className="mt-0.5 size-4 shrink-0" />
            <span>{message}</span>
        </div>
    );
}

export function SuccessBanner({ message }: { message: string | null }) {
    if (!message) return null;

    return (
        <div
            role="status"
            className="mb-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm leading-5 text-emerald-900"
        >
            <Icon name="check" className="mt-0.5 size-4 shrink-0" />
            <span>{message}</span>
        </div>
    );
}
