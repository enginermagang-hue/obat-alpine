import './bootstrap';
import Alpine from 'alpinejs';
import modal from './components/modal';
import opnameForm from './components/docForm';
import dataTable from './components/table';
import transactionForm from './components/transactionForm';
import { initAll, observe } from './services/tomselect';

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
Alpine.data('opnameForm', opnameForm);
Alpine.data('dataTable', dataTable);
Alpine.data('transactionForm', transactionForm);

window.Alpine = Alpine;
Alpine.start();

// TomSelect: enhance semua <select> setelah Alpine mengisi nilai awal
// (agar x-model + TomSelect sinkron), lalu awasi select dinamis.
initAll();
observe();
