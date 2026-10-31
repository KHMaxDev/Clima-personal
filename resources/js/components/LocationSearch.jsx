import { useEffect, useRef, useState } from 'react';
import { Loader2, Search } from 'lucide-react';
import { apiFetch } from '../lib/api';

export default function LocationSearch({
    locationsUrl,
    onSelect,
    placeholder = 'Buscar ubicación…',
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const timer = useRef(null);

    useEffect(() => {
        if (query.trim().length < 2) {
            setResults([]);
            setOpen(false);

            return undefined;
        }

        clearTimeout(timer.current);
        timer.current = setTimeout(async () => {
            setLoading(true);

            try {
                const data = await apiFetch(`${locationsUrl}?q=${encodeURIComponent(query.trim())}`);
                setResults(data.results ?? []);
                setOpen(true);
            } catch {
                setResults([]);
            } finally {
                setLoading(false);
            }
        }, 300);

        return () => clearTimeout(timer.current);
    }, [query, locationsUrl]);

    function choose(location) {
        setQuery('');
        setResults([]);
        setOpen(false);
        onSelect?.(location);
    }

    return (
        <div className="relative w-full sm:w-72">
            <Search
                className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400 dark:text-slate-500"
                strokeWidth={1.75}
                aria-hidden="true"
            />
            <input
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                onFocus={() => results.length > 0 && setOpen(true)}
                placeholder={placeholder}
                className="field-input pl-9"
                aria-label="Buscar ubicación"
            />

            {loading && (
                <Loader2
                    className="absolute right-3 top-2.5 h-4 w-4 animate-spin text-slate-400 dark:text-slate-500"
                    strokeWidth={1.75}
                    aria-hidden="true"
                />
            )}

            {open && results.length > 0 && (
                <ul className="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-slate-200/70 bg-white/90 py-1 text-sm shadow-lg backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/90">
                    {results.map((item, index) => (
                        <li key={`${item.latitude}-${item.longitude}-${index}`}>
                            <button
                                type="button"
                                onClick={() => choose(item)}
                                className="block w-full px-3 py-2 text-left transition hover:bg-indigo-500/10"
                            >
                                <span className="font-medium heading">{item.name}</span>
                                <span className="block text-xs text-muted">
                                    {[item.admin1, item.country].filter(Boolean).join(', ')}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
