<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Models\SumberDana;
use App\Models\SumberDanaPenggunaan;
use App\Support\TableMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlokasiDanaController extends Controller
{
    /**
     * Dashboard alokasi & realisasi dana (read-only).
     */
    public function index(Request $request): View|JsonResponse
    {
        abort_unless($request->user()->can('view_alokasi_dana'), 403);

        $filters = $request->only(['tahun', 'sumber_dana']);
        $tahun = (int) ($filters['tahun'] ?? now()->format('Y'));
        $sumberDanaId = ($filters['sumber_dana'] ?? null) !== null && $filters['sumber_dana'] !== ''
            ? (int) $filters['sumber_dana']
            : null;

        $query = SumberDana::query()
            ->withSum([
                'sumberDanaPenggunaans as total_realisasi' => fn ($q) => $q->where('tipe', 'realisasi'),
            ], 'total_biaya')
            ->withCount([
                'sumberDanaPenggunaans as jumlah_po' => fn ($q) => $q->where('tipe', 'realisasi'),
            ])
            ->where('tahun', $tahun)
            ->when($sumberDanaId, fn ($q) => $q->where('id', $sumberDanaId))
            ->orderBy('kode');

        $perPage = (int) $request->query('per_page', $request->user()->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = $query->paginate($perPage)->withQueryString();
        $items->through(fn (SumberDana $row) => $this->ringkasan($row));

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
            ]);
        }

        $stats = $this->stats($tahun, $sumberDanaId);

        return view('laporan.alokasi-dana.index', [
            'initial' => [
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
                'filters' => $filters,
            ],
            'filters' => $filters,
            'tahun' => $tahun,
            'stats' => $stats,
            'charts' => $this->charts($tahun, $sumberDanaId),
            'sumberDanaList' => SumberDana::query()->orderByDesc('tahun')->orderBy('kode')->get(['id', 'kode', 'nama', 'tahun']),
        ]);
    }

    /**
     * KPI: anggaran, realisasi, alokasi, perencanaan, sisa, % terpakai.
     *
     * @return array<string, float|int>
     */
    private function stats(int $tahun, ?int $sumberDanaId): array
    {
        $anggaran = (float) SumberDana::query()
            ->where('tahun', $tahun)
            ->when($sumberDanaId, fn ($q) => $q->where('id', $sumberDanaId))
            ->sum('total_anggaran');

        $perTipe = fn (string $tipe): float => (float) SumberDanaPenggunaan::query()
            ->where('tipe', $tipe)
            ->whereYear('tanggal', $tahun)
            ->when($sumberDanaId, fn ($q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->sum('total_biaya');

        $realisasi = $perTipe('realisasi');
        $alokasi = $perTipe('alokasi');
        $perencanaan = $perTipe('perencanaan');
        $sisa = $anggaran - $realisasi;

        return [
            'anggaran' => $anggaran,
            'realisasi' => $realisasi,
            'alokasi' => $alokasi,
            'perencanaan' => $perencanaan,
            'sisa' => $sisa,
            'persen' => $anggaran > 0 ? round($realisasi / $anggaran * 100, 1) : 0,
        ];
    }

    /**
     * Dataset grafik Chart.js: bar anggaran vs realisasi, doughnut per tipe, tren bulanan.
     *
     * @return array<string, mixed>
     */
    private function charts(int $tahun, ?int $sumberDanaId): array
    {
        $rows = SumberDana::query()
            ->withSum([
                'sumberDanaPenggunaans as total_realisasi' => fn ($q) => $q->where('tipe', 'realisasi'),
            ], 'total_biaya')
            ->where('tahun', $tahun)
            ->when($sumberDanaId, fn ($q) => $q->where('id', $sumberDanaId))
            ->orderBy('kode')
            ->get(['id', 'kode', 'total_anggaran']);

        $tipeLabels = ['alokasi' => 'Alokasi', 'realisasi' => 'Realisasi', 'perencanaan' => 'Perencanaan'];
        $perTipe = [];
        foreach (array_keys($tipeLabels) as $tipe) {
            $perTipe[$tipe] = (float) SumberDanaPenggunaan::query()
                ->where('tipe', $tipe)
                ->whereYear('tanggal', $tahun)
                ->when($sumberDanaId, fn ($q) => $q->where('sumber_dana_id', $sumberDanaId))
                ->sum('total_biaya');
        }

        $tren = [];
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $tren[] = (float) SumberDanaPenggunaan::query()
                ->where('tipe', 'realisasi')
                ->whereYear('tanggal', $tahun)
                ->whereMonth('tanggal', $bulan)
                ->when($sumberDanaId, fn ($q) => $q->where('sumber_dana_id', $sumberDanaId))
                ->sum('total_biaya');
        }

        return [
            'bar' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $rows->pluck('kode')->toArray(),
                    'datasets' => [
                        ['label' => 'Anggaran', 'data' => $rows->map(fn ($r) => (float) $r->total_anggaran)->toArray()],
                        ['label' => 'Realisasi', 'data' => $rows->map(fn ($r) => (float) ($r->total_realisasi ?? 0))->toArray()],
                    ],
                ],
            ],
            'doughnut' => [
                'type' => 'doughnut',
                'data' => [
                    'labels' => array_values($tipeLabels),
                    'datasets' => [['data' => array_values($perTipe)]],
                ],
            ],
            'tren' => [
                'type' => 'line',
                'data' => [
                    'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                    'datasets' => [['label' => "Realisasi {$tahun}", 'data' => $tren]],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkasan(SumberDana $row): array
    {
        $anggaran = (float) $row->total_anggaran;
        $realisasi = (float) ($row->total_realisasi ?? 0);
        $sisa = $anggaran - $realisasi;

        return array_merge($row->toArray(), [
            'sisa' => $sisa,
            'persen' => $anggaran > 0 ? round($realisasi / $anggaran * 100, 1) : 0,
        ]);
    }
}
