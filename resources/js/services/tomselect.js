import TomSelect from 'tom-select';

/**
 * TomSelect service — auto-enhance semua <select> bawaan Blade.
 *
 * Cara pakai: cukup panggil `initAll()` sekali setelah Alpine.start(),
 * lalu `observe()` agar <select> yang muncul belakangan (modal, x-if,
 * x-for) ikut di-enhance otomatis.
 *
 * Opt-out per elemen : <select data-tomselect-off>
 * Opsi per elemen    :
 *   data-tomselect-placeholder="Cari…"  (timpa placeholder otomatis)
 *   data-tomselect-search="false"       (matikan kotak cari, cocok utk opsi <= 3)
 *   data-tomselect-create="true"        (izinkan opsi baru dari ketikan)
 *   data-tomselect-url="/api/obat/search" (+ data-tomselect-label/value)
 *     → mode remote: opsi diambil via fetch(`${url}?q=...`)
 *       respons JSON: [{ value, label }] atau array string.
 */

const instances = new WeakMap();
const nativeValueDesc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');

let pendingScan = false;

function isDisabled(el) {
    return el.disabled || el.closest('fieldset[disabled]');
}

/** Tentukan apakah dropdown harus dirender di <body> (mis. di dalam modal). */
function needsBodyDropdown(el) {
    return Boolean(el.closest('[role="dialog"], .modal, [data-tomselect-body]'));
}

/** Bangun placeholder dari <option value=""> bila ada, jika tidak dari label. */
function resolvePlaceholder(el) {
    const forced = el.getAttribute('data-tomselect-placeholder');
    if (forced !== null) return forced;

    const empty = el.querySelector('option[value=""]');
    const text = (empty?.textContent ?? '').trim();
    if (text && !/^—|^-|pilih/i.test(text)) return text;
    if (text) return text.replace(/^[—–-]+\s*|\s*[—–-]+$/g, '').trim() || undefined;

    return undefined;
}

function buildSettings(el) {
    const isMultiple = el.multiple;
    const searchAttr = el.getAttribute('data-tomselect-search');
    const createAttr = el.getAttribute('data-tomselect-create');
    const remoteUrl = el.getAttribute('data-tomselect-url');
    const placeholder = resolvePlaceholder(el);
    const required = el.required;

    const settings = {
        // Biarkan opsi kosong ("Semua …", "— Pilih —") tetap bisa dipilih.
        allowEmptyOption: true,
        placeholder,
        // Dropdown di dalam modal: render di <body> agar tidak
        // terpotong oleh `overflow-hidden` pada card modal.
        ...(needsBodyDropdown(el) ? { dropdownParent: 'body' } : {}),
        // Matikan pencarian utk select kecil (status, VEN, metode, …).
        // controlInput null → input cari tidak dirender, fallback fokus
        // ke .ts-control via onInitialize agar keyboard tetap jalan.
        ...(searchAttr === 'false'
            ? {
                controlInput: null,
                onInitialize() {
                    // @ts-ignore — `this` adalah instance TomSelect.
                    this.control.tabIndex = this.tabIndex || 0;
                },
            }
            : {}),
        // Izinkan entry baru dari ketikan bila diminta eksplisit.
        ...(createAttr === 'true' ? { create: true } : {}),
        plugins: {
            // Tombol hapus (×) utk <select multiple>.
            ...(isMultiple ? { remove_button: { title: 'Hapus' } } : {}),
        },
        // Nilai maks opsi tampil; biarkan default TomSelect bila tidak diset.
        maxOptions: 1000,
        // Nilai yang wajib diisi: 'required' native dipindah ke validasi JS
        // (lihat hookRequired) karena <select> tersembunyi tidak focusable
        // dan akan memblokir submit dengan error browser yang membingungkan.
        ...(required ? { required: false } : {}),
    };

    if (remoteUrl) {
        settings.valueField = el.getAttribute('data-tomselect-value') || 'value';
        settings.labelField = el.getAttribute('data-tomselect-label') || 'label';
        settings.searchField = [settings.labelField];
        settings.load = (query, callback) => {
            fetch(`${remoteUrl}?q=${encodeURIComponent(query)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((res) => (res.ok ? res.json() : []))
                .then((json) => callback(Array.isArray(json) ? json : []))
                .catch(() => callback());
        };
    }

    return { settings, required };
}

/**
 * Sinkronisasi dua arah dengan Alpine `x-model`.
 *
 * TomSelect → Alpine : TomSelect mengubah <select> asli + dispatch
 *   event `change`, jadi `x-model` ikut ter-update otomatis.
 * Alpine → TomSelect : Alpine menulis `el.value = …` langsung tanpa
 *   event (mis. `openEdit(row)` di modal), sehingga override setter
 *   `value` per elemen agar TomSelect ikut ter-update.
 */
function hookAlpineSync(el) {
    if (Object.prototype.hasOwnProperty.call(el, 'value')) return;
    if (!nativeValueDesc) return;

    Object.defineProperty(el, 'value', {
        configurable: true,
        enumerable: true,
        get() {
            return nativeValueDesc.get.call(this);
        },
        set(v) {
            nativeValueDesc.set.call(this, v);
            const ts = instances.get(this);
            if (!ts) return;
            const next = v ?? '';
            const current = ts.getValue();
            if (String(Array.isArray(current) ? current.join(',') : current) !== String(next)) {
                // silent=true → tanpa event change, hindari loop.
                ts.setValue(next, true);
            }
        },
    });

    // <select multiple>: Alpine x-model menulis `option.selected`
    // per opsi, bukan via `el.value`. Patch tiap opsi agar TomSelect sync.
    if (el.multiple) {
        let raf = 0;
        const scheduleSync = () => {
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => {
                const ts = instances.get(el);
                if (!ts) return;
                const selected = Array.from(el.options)
                    .filter((o) => o.selected)
                    .map((o) => o.value);
                ts.setValue(selected, true);
            });
        };
        Array.from(el.options).forEach((opt) => {
            if (Object.prototype.hasOwnProperty.call(opt, 'selected')) return;
            const desc = Object.getOwnPropertyDescriptor(HTMLOptionElement.prototype, 'selected');
            if (!desc) return;
            Object.defineProperty(opt, 'selected', {
                configurable: true,
                enumerable: true,
                get() {
                    return desc.get.call(this);
                },
                set(v) {
                    desc.set.call(this, v);
                    scheduleSync();
                },
            });
        });
    }
}

/**
 * Gantikan validasi `required` native (rusak utk elemen tersembunyi)
 * dengan validasi saat submit: blokir + fokus ke kontrol TomSelect.
 */
function hookRequired(el, ts) {
    el.removeAttribute('required');
    el.setAttribute('aria-required', 'true');

    const clearError = () => ts.control?.classList.remove('ts-control--invalid');

    ts.on('change', clearError);
    ts.on('focus', clearError);

    // Pasang penjaga submit sekali per form.
    const form = el.closest('form');
    if (!form || form._tomselectSubmitHooked) return;
    form._tomselectSubmitHooked = true;
    form.addEventListener('submit', (e) => {
        const invalid = Array.from(form.querySelectorAll('select[data-tomselect-required]')).find((sel) => {
            const inst = instances.get(sel);
            if (!inst) return false;
            const val = inst.getValue();
            return val === '' || (Array.isArray(val) && val.length === 0);
        });
        if (invalid) {
            e.preventDefault();
            e.stopPropagation();
            const inst = instances.get(invalid);
            inst?.control?.classList.add('ts-control--invalid');
            inst?.focus();
        }
    });
}

/** Inisialisasi satu <select>. Idempoten — aman dipanggil berulang. */
export function initTomSelect(el, overrides = {}) {
    if (!(el instanceof HTMLSelectElement)) return null;
    if (el.hasAttribute('data-tomselect-off')) return null;
    if (instances.has(el) || el.tomselect) return el.tomselect ?? instances.get(el) ?? null;
    if (isDisabled(el) && el.options.length === 0) return null;

    const { settings, required } = buildSettings(el);

    hookAlpineSync(el);

    // TomSelect selalu menyalin class <select> ke .ts-wrapper, sehingga
    // class Tailwind (border, rounded-xl, padding, bg, shadow) ikut tampil
    // sebagai kotak luar → select terlihat bersarang. Lepas dulu class-nya,
    // kembalikan setelah init agar destroy() tetap rapi.
    const originalClass = el.getAttribute('class');
    if (originalClass) el.removeAttribute('class');

    let ts;
    try {
        ts = new TomSelect(el, { ...settings, copyClassesToDropdown: false, ...overrides });
    } finally {
        if (originalClass) el.classList.add(...originalClass.split(/\s+/).filter(Boolean));
    }
    instances.set(el, ts);

    if (required) {
        el.setAttribute('data-tomselect-required', '1');
        hookRequired(el, ts);
    }

    return ts;
}

/** Scan & enhance semua <select> dalam root (default: document). */
export function initAll(root = document) {
    const selects = root instanceof HTMLSelectElement
        ? [root]
        : Array.from(root.querySelectorAll('select:not([data-tomselect-off])'));

    selects.forEach((el) => {
        try {
            initTomSelect(el);
        } catch (err) {
            // Jangan biarkan satu select rusak menggagalkan yang lain.
            console.error('[tomselect] gagal init:', el?.name || el?.id, err);
        }
    });

    return selects.length;
}

/** Scan ulang dengan debounce rAF (dipakai observer). */
function scheduleScan() {
    if (pendingScan) return;
    pendingScan = true;
    requestAnimationFrame(() => {
        pendingScan = false;
        initAll(document);
    });
}

/**
 * Awasi <select> yang muncul belakangan (modal Alpine, x-if, x-for,
 * hasil fetch). Juga bersihkan instance bila <select> dihapus dari DOM
 * agar tidak bocor memori.
 */
export function observe(target = document.body) {
    // Hanya scan ulang bila node baru benar-benar mengandung <select>;
    // tanpa filter ini, render ulang opsi dropdown TomSelect tiap ketikan
    // ikut memicu scan seluruh dokumen.
    const containsSelect = (node) =>
        node.nodeType === 1 && (node.matches?.('select') || node.querySelector?.('select'));

    // Bersihkan instance yang nodenya sudah terlepas dari DOM.
    const observer = new MutationObserver((mutations) => {
        let added = false;
        for (const m of mutations) {
            m.removedNodes.forEach((node) => {
                if (node instanceof HTMLSelectElement) {
                    instances.get(node)?.destroy();
                    instances.delete(node);
                } else if (node instanceof Element) {
                    node.querySelectorAll?.('select').forEach((sel) => {
                        instances.get(sel)?.destroy();
                        instances.delete(sel);
                    });
                }
            });
            if (m.type === 'childList' && !added) {
                added = Array.from(m.addedNodes).some(containsSelect);
            }
        }
        if (added) scheduleScan();
    });

    observer.observe(target, { childList: true, subtree: true });

    return () => observer.disconnect();
}

/** Ambil instance TomSelect dari sebuah <select> (untuk pemakaian manual). */
export function getInstance(el) {
    return instances.get(el) ?? el?.tomselect ?? null;
}

/** Sinkron ulang semua instance dalam root (mis. setelah reset form massal). */
export function syncAll(root = document) {
    const selects = root instanceof HTMLSelectElement
        ? [root]
        : Array.from(root.querySelectorAll('select'));
    selects.forEach((sel) => {
        const ts = instances.get(sel);
        if (!ts) return;
        if (sel.multiple) {
            ts.setValue(Array.from(sel.options).filter((o) => o.selected).map((o) => o.value), true);
        } else {
            ts.setValue(sel.value ?? '', true);
        }
    });
}
