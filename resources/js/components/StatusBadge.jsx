import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    HelpCircle,
    ShieldAlert,
} from 'lucide-react';

const ICONS = {
    'check-circle': CheckCircle2,
    'exclamation-triangle': AlertTriangle,
    'exclamation-circle': AlertCircle,
    'shield-exclamation': ShieldAlert,
    'question-mark-circle': HelpCircle,
};

export default function StatusBadge({
    status,
    label,
    color,
    icon,
    className = '',
}) {
    const Icon = ICONS[icon] ?? AlertCircle;

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium text-white ${className}`}
            style={{ backgroundColor: color ?? '#6b7280' }}
            title={label}
        >
            <Icon className="h-3.5 w-3.5" strokeWidth={2} aria-hidden="true" />
            {label}
        </span>
    );
}
