import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

window.notificationBell = (keys = []) => ({
    open: false,
    keys: Array.isArray(keys) ? keys.map((key) => String(key)) : [],
    dismissed: [],

    init() {
        try {
            const raw = window.localStorage.getItem('nsync.notifications.dismissed') || '[]';
            const parsed = JSON.parse(raw);
            this.dismissed = Array.isArray(parsed) ? parsed.map((key) => String(key)) : [];
        } catch (error) {
            this.dismissed = [];
        }
    },

    isDismissed(key) {
        return this.dismissed.includes(String(key));
    },

    dismiss(key) {
        const normalized = String(key);
        if (this.isDismissed(normalized)) {
            return;
        }

        this.dismissed = [...this.dismissed, normalized];
        this.persist();
    },

    markAllRead() {
        const all = [...new Set([...this.dismissed, ...this.keys])];
        this.dismissed = all;
        this.persist();
    },

    persist() {
        try {
            window.localStorage.setItem('nsync.notifications.dismissed', JSON.stringify(this.dismissed));
        } catch (error) {
            // Ignore storage errors (private mode/quota).
        }
    },

    get unreadCount() {
        return this.keys.filter((key) => !this.dismissed.includes(String(key))).length;
    },
});

const showToast = (message, type = 'success') => {
    if (!message) return;

    const containerId = 'nsync-toast-container';
    let container = document.getElementById(containerId);

    if (!container) {
        container = document.createElement('div');
        container.id = containerId;
        container.className = 'fixed top-4 right-4 z-[9999] flex flex-col gap-2 w-[min(90vw,24rem)]';
        document.body.appendChild(container);
    }

    const palette = {
        success: 'bg-green-600',
        warning: 'bg-amber-500',
        error: 'bg-red-600',
        info: 'bg-blue-600',
    };

    const toast = document.createElement('div');
    toast.className = `${palette[type] ?? palette.info} text-white shadow-lg rounded-lg px-4 py-3 text-sm flex items-start justify-between gap-3`;
    toast.innerHTML = `
        <span class="leading-5">${String(message)}</span>
        <button type="button" class="opacity-80 hover:opacity-100" aria-label="Close">x</button>
    `;

    const close = () => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    };

    toast.querySelector('button')?.addEventListener('click', close);
    container.appendChild(toast);
    setTimeout(close, 4500);
};

window.addEventListener('notify', (event) => {
    const detail = event.detail;
    let message = '';
    let type = 'success';

    if (Array.isArray(detail) && detail.length > 0) {
        message = detail[0] ?? '';
    } else if (typeof detail === 'object' && detail !== null) {
        message = detail.message ?? '';
        type = detail.type ?? type;
    } else if (typeof detail === 'string') {
        message = detail;
    }

    showToast(message, type);
});
