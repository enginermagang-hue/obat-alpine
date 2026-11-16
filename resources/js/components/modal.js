export default (initial = {}) => ({
    formOpen: false,
    confirmOpen: false,
    importOpen: false,
    resetOpen: false,
    resetId: null,
    resetEmail: '',
    editing: null,
    form: { ...initial },
    confirm: { title: '', message: '', action: '', label: 'Hapus' },

    openCreate() {
        this.editing = null;
        this.form = { ...initial };
        this.formOpen = true;
    },

    openEdit(row) {
        this.editing = row.id;
        this.form = { ...initial, ...row };
        this.formOpen = true;
    },

    openConfirm({ title, message, action, method = 'DELETE', label = 'Hapus' }) {
        this.confirm = { title, message, action, method, label };
        this.confirmOpen = true;
    },
});
