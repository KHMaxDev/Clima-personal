import { useState } from 'react';
import { PencilLine, Sparkles } from 'lucide-react';
import ActivityForm from './ActivityForm';
import ActivityGenerator from './ActivityGenerator';

const TABS = [
    { id: 'manual', label: 'Manual', icon: PencilLine },
    { id: 'ai', label: 'Generar con IA', icon: Sparkles },
];

export default function ActivityCreate({ manual = {}, generator = {} }) {
    const [tab, setTab] = useState('manual');

    return (
        <div className="space-y-5">
            <div
                className="glass-soft inline-flex gap-1 rounded-xl p-1"
                role="tablist"
                aria-label="Método de creación"
            >
                {TABS.map((item) => {
                    const Icon = item.icon;
                    const active = tab === item.id;

                    return (
                        <button
                            key={item.id}
                            type="button"
                            role="tab"
                            aria-selected={active}
                            onClick={() => setTab(item.id)}
                            className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition ${
                                active
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'text-slate-600 hover:bg-white/60 dark:text-slate-300 dark:hover:bg-white/10'
                            }`}
                        >
                            <Icon className="h-4 w-4" strokeWidth={1.9} aria-hidden="true" />
                            {item.label}
                        </button>
                    );
                })}
            </div>

            {tab === 'manual' ? (
                <ActivityForm {...manual} />
            ) : (
                <ActivityGenerator {...generator} />
            )}
        </div>
    );
}
