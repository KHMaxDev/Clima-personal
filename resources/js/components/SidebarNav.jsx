import { motion, useReducedMotion } from 'motion/react';
import {
    Bot,
    CalendarDays,
    CloudSun,
    History,
    Home,
    ListChecks,
    Settings,
} from 'lucide-react';

const ICONS = {
    home: Home,
    calendar: CalendarDays,
    activities: ListChecks,
    forecast: CloudSun,
    history: History,
    assistant: Bot,
    settings: Settings,
};

export default function SidebarNav({ items = [], comingSoon = [] }) {
    const reduceMotion = useReducedMotion();

    return (
        <nav className="flex-1 space-y-1 px-3 py-4">
            {items.map((item) => {
                const Icon = ICONS[item.icon] ?? Home;

                return (
                    <motion.a
                        key={item.label}
                        href={item.href}
                        className={`nav-link relative isolate ${item.active ? 'nav-link-active' : ''}`}
                        aria-current={item.active ? 'page' : undefined}
                        whileHover={reduceMotion ? undefined : { x: 3 }}
                        whileTap={reduceMotion ? undefined : { scale: 0.98 }}
                        transition={{ type: 'spring', stiffness: 420, damping: 30 }}
                    >
                        {item.active && (
                            <motion.span
                                layoutId="nav-active-pill"
                                className="absolute inset-0 -z-10 rounded-xl bg-indigo-600/10 ring-1 ring-indigo-500/20 dark:bg-indigo-500/15 dark:ring-indigo-400/20"
                                transition={{ type: 'spring', stiffness: 380, damping: 32 }}
                                aria-hidden="true"
                            />
                        )}
                        <Icon className="h-[1.15rem] w-[1.15rem]" strokeWidth={1.75} aria-hidden="true" />
                        {item.label}
                    </motion.a>
                );
            })}

            {comingSoon.map((item) => {
                const Icon = ICONS[item.icon] ?? Settings;

                return (
                    <span
                        key={item.label}
                        className="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-400 dark:text-slate-600"
                    >
                        <Icon className="h-[1.15rem] w-[1.15rem]" strokeWidth={1.75} aria-hidden="true" />
                        {item.label}
                        <span className="ml-auto rounded-md border border-slate-200 px-1.5 py-0.5 text-[0.6rem] uppercase tracking-wide dark:border-white/10">
                            Pronto
                        </span>
                    </span>
                );
            })}
        </nav>
    );
}
