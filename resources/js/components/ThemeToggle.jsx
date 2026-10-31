import { useEffect, useState } from 'react';
import { Moon, Sun } from 'lucide-react';

function currentIsDark() {
    if (typeof document === 'undefined') {
        return false;
    }

    return document.documentElement.classList.contains('dark');
}

export default function ThemeToggle() {
    const [dark, setDark] = useState(currentIsDark);

    useEffect(() => {
        setDark(currentIsDark());
    }, []);

    function toggle() {
        const next = !currentIsDark();

        document.documentElement.classList.toggle('dark', next);
        setDark(next);

        try {
            localStorage.setItem('theme', next ? 'dark' : 'light');
        } catch {
            // Sin almacenamiento disponible: se conserva solo en esta sesión.
        }
    }

    return (
        <button
            type="button"
            onClick={toggle}
            className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 bg-white/70 text-slate-600 transition hover:bg-slate-100 dark:border-white/15 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
            aria-label={dark ? 'Activar tema claro' : 'Activar tema oscuro'}
            title={dark ? 'Tema claro' : 'Tema oscuro'}
        >
            {dark ? (
                <Sun className="h-[1.15rem] w-[1.15rem]" strokeWidth={1.75} aria-hidden="true" />
            ) : (
                <Moon className="h-[1.15rem] w-[1.15rem]" strokeWidth={1.75} aria-hidden="true" />
            )}
        </button>
    );
}
