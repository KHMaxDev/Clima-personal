const listeners = new Set();
let counter = 0;

function emit(type, message, options = {}) {
    const id = ++counter;
    const item = {
        id,
        type,
        message,
        title: options.title ?? null,
        duration: options.duration ?? (type === 'error' ? 6500 : 4500),
    };

    listeners.forEach((listener) => listener(item));

    return id;
}

export function subscribeToasts(listener) {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export const toast = {
    success: (message, options) => emit('success', message, options),
    error: (message, options) => emit('error', message, options),
    info: (message, options) => emit('info', message, options),
    warning: (message, options) => emit('warning', message, options),
};

export default toast;
