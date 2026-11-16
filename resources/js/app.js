import './bootstrap';
import Alpine from 'alpinejs';
import modal from './components/modal';

Alpine.store('ui', {
    sidebarOpen: window.innerWidth >= 1024,
    mobileOpen: false,
    dark: (localStorage.getItem('theme') ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')) === 'dark',

    applyTheme() {
        document.documentElement.classList.toggle('dark', this.dark);
        localStorage.setItem('theme', this.dark ? 'dark' : 'light');
    },

    toggleDark() {
        this.dark = !this.dark;
        this.applyTheme();
    },

    toggleSidebar() {
        if (window.innerWidth < 1024) {
            this.mobileOpen = !this.mobileOpen;

            return;
        }

        this.sidebarOpen = !this.sidebarOpen;
    },
});

Alpine.store('toast', {
    items: [],

    push(message, type = 'success') {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, type });
        setTimeout(() => this.dismiss(id), 5000);
    },

    dismiss(id) {
        this.items = this.items.filter((item) => item.id !== id);
    },
});

Alpine.data('dropdown', () => ({
    open: false,
    toggle() {
        this.open = !this.open;
    },
    close() {
        this.open = false;
    },
}));

Alpine.data('modal', modal);

window.Alpine = Alpine;
Alpine.start();
