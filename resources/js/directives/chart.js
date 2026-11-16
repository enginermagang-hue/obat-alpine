/**
 * Chart.js sebagai Alpine directive: x-chart="config".
 *
 * config: { type, data, options } — objek Chart.js biasa.
 * Chart dibuat saat elemen masuk DOM, dihancurkan saat dilepas
 * (aman dipakai di dalam x-if / modal).
 *
 * Warna default mengikuti palet "Ruang Obat" + dark mode sederhana
 * (dibaca dari class .dark pada <html> saat init).
 */
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

function palette() {
    const dark = document.documentElement.classList.contains('dark');
    return {
        grid: dark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(148, 163, 184, 0.25)',
        tick: dark ? '#94a3b8' : '#64748b',
        brand: '#0083bf',
        accent: '#0d8f88',
        amber: '#d97706',
        red: '#dc2626',
        emerald: '#059669',
        violet: '#7c3aed',
    };
}

export default (el, { expression }, { evaluateLater, cleanup }) => {
    const evaluate = evaluateLater(expression);
    let chart = null;

    const build = (config) => {
        if (!config || !config.data) return;
        if (chart) chart.destroy();

        const pal = palette();
        const withDefaults = {
            ...config,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: pal.tick, boxWidth: 12 } } },
                scales: Object.fromEntries(
                    Object.entries(config.options?.scales ?? { x: {}, y: {} }).map(([axis, opts]) => [
                        axis,
                        {
                            grid: { color: pal.grid },
                            ticks: { color: pal.tick, font: { size: 11 } },
                            ...opts,
                        },
                    ]),
                ),
                ...(config.options ?? {}),
            },
        };

        chart = new Chart(el, withDefaults);
    };

    evaluate(build);
    cleanup(() => chart?.destroy());
};
