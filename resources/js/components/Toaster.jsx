import { useEffect, useState } from 'react';
import {
    AlertTriangle,
    CheckCircle2,
    Info,
    X,
    XCircle,
} from 'lucide-react';
import { subscribeToasts } from '../lib/toast';

const TONES = {
    success: {
        icon: CheckCircle2,
        className: 'border-emerald-300/70 bg-emerald-50/90 text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-950/80 dark:text-emerald-100',
        iconClass: 'text-emerald-600 dark:text-emerald-400',
    },
    error: {
        icon: XCircle,
        className: 'border-red-300/70 bg-red-50/90 text-red-900 dark:border-red-500/40 dark:bg-red-950/80 dark:text-red-100',
        iconClass: 'text-red-600 dark:text-red-400',
    },
    warning: {
        icon: AlertTriangle,
        className: 'border-amber-300/70 bg-amber-50/90 text-amber-900 dark:border-amber-500/40 dark:bg-amber-950/80 dark:text-amber-100',
        iconClass: 'text-amber-600 dark:text-amber-400',
    },
    info: {
        icon: Info,
        className: 'border-sky-300/70 bg-sky-50/90 text-sky-900 dark:border-sky-500/40 dark:bg-sky-950/80 dark:text-sky-100',
        iconClass: 'text-sky-600 dark:text-sky-400',
    },
};

export default function Toaster() {
    const [items, setItems] = useState([]);

    useEffect(() => subscribeToasts((item) => {
        setItems((current) => [...current, item]);

        window.setTimeout(() => {
            setItems((current) => current.filter((toast) => toast.id !== item.id));
        }, item.duration);
    }), []);

    function dismiss(id) {
        setItems((current) => current.filter((toast) => toast.id !== id));
    }

    if (items.length === 0) {
        return null;
    }

    return (
        <div
            className="pointer-events-none fixed bottom-4 right-4 z-50 flex w-full max-w-sm flex-col gap-2"
            role="region"
            aria-label="Notificaciones"
        >
            {items.map((item) => {
                const tone = TONES[item.type] ?? TONES.info;
                const Icon = tone.icon;

                return (
                    <div
                        key={item.id}
                        role="status"
                        className={`pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg backdrop-blur-xl ${tone.className}`}
                    >
                        <Icon className={`mt-0.5 h-5 w-5 shrink-0 ${tone.iconClass}`} strokeWidth={1.9} aria-hidden="true" />
                        <div className="min-w-0 flex-1">
                            {item.title && (
                                <p className="text-sm font-semibold">{item.title}</p>
                            )}
                            <p className="text-sm">{item.message}</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => dismiss(item.id)}
                            className="shrink-0 rounded-md p-0.5 opacity-60 transition hover:opacity-100"
                            aria-label="Cerrar notificación"
                        >
                            <X className="h-4 w-4" strokeWidth={2} aria-hidden="true" />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
