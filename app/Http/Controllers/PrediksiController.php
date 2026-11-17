<?php

namespace App\Http\Controllers;

use App\Jobs\TrainPrediksiModelJob;
use App\Models\FasilitasKesehatan;
use App\Models\ModelPrediksi;
use App\Models\Obat;
use App\Models\PermintaanObat;
use App\Models\PrediksiKebutuhan;
use App\Services\BuatPermintaanService;
use App\Services\PrediksiRekomendasiService;
use App\Support\FaskesAccess;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PrediksiController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        abort_unless($request->user()->can('view_prediksi_kebutuhan'), 403);

        $filters = $this->validateFilters($request);
        $user = $request->user();
        $isFaskes = filled($user->fasilitas_kesehatan_id);
        $visibleIds = $isFaskes ? FaskesAccess::visibleFasilitasIds($user) : [];

        $fasilitasId = $this->resolveFasilitasId($user, $filters['fasilitas_id'], $visibleIds);

        // Default periode: bulan berjalan; fallback ke periode terakhir yang tersedia.
        [$bulan, $tahun] = $this->resolvePeriod($filters, $fasilitasId, $visibleIds);

        $service = new PrediksiRekomendasiService(
            fasilitasId: $fasilitasId,
            bulan: $bulan,
            tahun: $tahun,
            horizon: $filters['horizon'],
            kategori: $filters['kategori'],
            cari: $filters['cari'],
            visibleFasilitasIds: $fasilitasId === null && ! empty($visibleIds) ? $visibleIds : null,
        );

        $rows = collect($service->rows());
        if ($filters['sort']) {
            $dir = $filters['sortDir'] === 'desc' ? -1 : 1;
            $rows = $rows->sortBy(fn ($r) => $r[$filters['sort']] ?? 0, SORT_REGULAR, $dir === -1)->values();
        }

        $perPage = $request->integer('per_page', $user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $page = max(1, $request->integer('page', 1));
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $paginator->items(),
                'meta' => TableMeta::from($paginator),
            ]);
        }

        $kpi = $service->kpi($this->rataAkurasi($fasilitasId, $visibleIds));

        return view('prediksi.index', [
            'initial' => [
                'data' => $paginator->items(),
                'meta' => TableMeta::from($paginator),
                'filters' => array_merge($filters, ['bulan' => $bulan, 'tahun' => $tahun]),
            ],
            'filters' => array_merge($filters, ['bulan' => $bulan, 'tahun' => $tahun]),
            'kpi' => $kpi,
            'akurasiModel' => $this->rataAkurasi($fasilitasId, $visibleIds),
            'lonjakan' => $service->lonjakan(5),
            'chart' => $this->chartRealisasiVsPrediksi($service, $fasilitasId, $visibleIds, $bulan, $tahun, $filters['horizon']),
            'models' => $this->modelTerbaru($fasilitasId, $visibleIds),
            'modelStats' => $this->modelStats($fasilitasId, $visibleIds),
            'faskesList' => $this->faskesList($user, $visibleIds),
            'kategoriList' => Obat::whereNotNull('kategori')->distinct()->orderBy('kategori')->pluck('kategori'),
            'isFaskes' => $isFaskes,
            'hasPrediksi' => PrediksiKebutuhan::exists(),
            'canBuatPermintaan' => filled($user->fasilitas_kesehatan_id),
        ]);
    }

    public function detail(Request $request, Obat $obat): JsonResponse
    {
        abort_unless($request->user()->can('view_prediksi_kebutuhan'), 403);

        $filters = $this->validateFilters($request);
        $user = $request->user();
        $isFaskes = filled($user->fasilitas_kesehatan_id);
        $visibleIds = $isFaskes ? FaskesAccess::visibleFasilitasIds($user) : [];
        $fasilitasId = $this->resolveFasilitasId($user, $filters['fasilitas_id'], $visibleIds);
        [$bulan, $tahun] = $this->resolvePeriod($filters, $fasilitasId, $visibleIds);

        $service = new PrediksiRekomendasiService(
            fasilitasId: $fasilitasId,
            bulan: $bulan,
            tahun: $tahun,
            horizon: $filters['horizon'],
            visibleFasilitasIds: $fasilitasId === null && ! empty($visibleIds) ? $visibleIds : null,
        );

        $detail = $service->detail($obat->id);
        abort_if($detail === null, 404);

        return response()->json($detail);
    }

    public function buatPermintaan(Request $request): RedirectResponse
    {
        $this->authorize('create', PermintaanObat::class);
        $user = $request->user();
        abort_unless(filled($user->fasilitas_kesehatan_id), 403);

        $data = $request->validate([
            'obat_id' => ['nullable', 'integer', 'exists:obats,id'],
            'bulan' => ['nullable', 'integer', 'min:1', 'max:12'],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'horizon' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $filters = $this->validateFilters($request);
        $visibleIds = FaskesAccess::visibleFasilitasIds($user);
        $faskesId = (int) $user->fasilitas_kesehatan_id;
        [$bulan, $tahun] = $this->resolvePeriod($filters, $faskesId, $visibleIds);

        $service = new PrediksiRekomendasiService(
            fasilitasId: $faskesId,
            bulan: $bulan,
            tahun: $tahun,
            horizon: (int) ($data['horizon'] ?? $filters['horizon']),
        );
        $rows = collect($service->rows())->where('rekom', '>', 0)->values();
        if (! empty($data['obat_id'])) {
            $rows = $rows->where('obat_id', (int) $data['obat_id'])->values();
        }

        $permintaan = BuatPermintaanService::buat(
            $faskesId,
            $rows,
            'Dibuat otomatis dari Prediksi AI ('.Carbon::create($tahun, $bulan)->translatedFormat('F Y').', '.($data['horizon'] ?? $filters['horizon']).' bulan)',
        );

        if (! $permintaan) {
            return redirect()->route('prediksi.index', $request->query())->with('error', 'Tidak ada obat yang perlu dipesan untuk filter ini.');
        }

        return redirect()->route('permintaan.show', $permintaan);
    }

    public function trainModel(Request $request, ModelPrediksi $model): RedirectResponse
    {
        abort_unless($request->user()->can('view_model_prediksi'), 403);
        abort_unless($request->user()->hasAnyRole(['super_admin', 'admin_gudang']), 403);

        $user = $request->user();
        if (filled($user->fasilitas_kesehatan_id)) {
            abort_unless(in_array($model->fasilitas_id, FaskesAccess::visibleFasilitasIds($user), true), 403);
        }

        TrainPrediksiModelJob::dispatch($model->fasilitas_id, $model->obat_id, $user->id);

        return redirect()->route('prediksi.index')->with('success', 'Training dijadwalkan. Jalankan queue:work bila di dev.');
    }

    public function trainSemua(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);

        TrainPrediksiModelJob::dispatch(null, null, $request->user()->id);

        return redirect()->route('prediksi.index')->with('success', 'Training semua model dijadwalkan.');
    }

    /** @return array{fasilitas_id:?int,kategori:?string,cari:?string,bulan:?int,tahun:?int,horizon:int,sort:?string,sortDir:string} */
    private function validateFilters(Request $request): array
    {
        $data = $request->validate([
            'fasilitas_id' => ['nullable', 'integer', 'exists:fasilitas_kesehatan,id'],
            'kategori' => ['nullable', 'string', 'max:100'],
            'cari' => ['nullable', 'string', 'max:100'],
            'bulan' => ['nullable', 'integer', 'min:1', 'max:12'],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'horizon' => ['nullable', 'integer', 'in:1,3,6,12'],
            'sort' => ['nullable', 'string', 'in:rekom,nama_obat,spike_pct,stok,prediksi_horizon'],
            'sortDir' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        return [
            'fasilitas_id' => isset($data['fasilitas_id']) ? (int) $data['fasilitas_id'] : null,
            'kategori' => $data['kategori'] ?? null,
            'cari' => $data['cari'] ?? null,
            'bulan' => isset($data['bulan']) ? (int) $data['bulan'] : null,
            'tahun' => isset($data['tahun']) ? (int) $data['tahun'] : null,
            'horizon' => (int) ($data['horizon'] ?? 3),
            'sort' => $data['sort'] ?? null,
            'sortDir' => $data['sortDir'] ?? 'asc',
        ];
    }

    /** @param  int[]  $visibleIds */
    private function resolveFasilitasId($user, ?int $requested, array $visibleIds): ?int
    {
        if (filled($user->fasilitas_kesehatan_id)) {
            if ($requested !== null) {
                abort_unless(in_array($requested, $visibleIds, true), 403);
            }

            return $requested ?? (count($visibleIds) === 1 ? $visibleIds[0] : null);
        }

        return $requested;
    }

    /** @param  int[]  $visibleIds
     *  @return array{0:int,1:int} */
    private function resolvePeriod(array $filters, ?int $fasilitasId, array $visibleIds): array
    {
        if ($filters['bulan'] && $filters['tahun']) {
            return [$filters['bulan'], $filters['tahun']];
        }
        $q = PrediksiKebutuhan::query()
            ->when($fasilitasId, fn ($qq) => $qq->where('fasilitas_id', $fasilitasId))
            ->when($fasilitasId === null && ! empty($visibleIds), fn ($qq) => $qq->whereIn('fasilitas_id', $visibleIds))
            ->orderByDesc('periode_tahun')->orderByDesc('periode_bulan');
        $latest = $q->first(['periode_bulan', 'periode_tahun']);

        return [(int) ($filters['bulan'] ?? $latest?->periode_bulan ?? now()->month), (int) ($filters['tahun'] ?? $latest?->periode_tahun ?? now()->year)];
    }

    /** @param  int[]|null  $visibleIds */
    private function rataAkurasi(?int $fasilitasId, ?array $visibleIds): float
    {
        return (float) ModelPrediksi::query()
            ->where('status', 'aktif')->whereNotNull('akurasi_r2')
            ->when($fasilitasId, fn ($q) => $q->where('fasilitas_id', $fasilitasId))
            ->when($fasilitasId === null && ! empty($visibleIds), fn ($q) => $q->whereIn('fasilitas_id', $visibleIds))
            ->avg('akurasi_r2') ?? 0.0;
    }

    /** @param  int[]|null  $visibleIds
     *  @return array<string,mixed> */
    private function chartRealisasiVsPrediksi(PrediksiRekomendasiService $service, ?int $fasilitasId, ?array $visibleIds, int $bulan, int $tahun, int $horizon): array
    {
        $periods = $service->periods();
        $anchor = Carbon::create($tahun, $bulan, 1);
        $past = [];
        for ($i = 6; $i >= 1; $i--) {
            $past[] = $anchor->copy()->subMonths($i);
        }
        $labels = [];
        $realisasi = [];
        foreach ($past as $d) {
            $labels[] = $d->translatedFormat('M Y');
        }
        foreach ($periods as $p) {
            $labels[] = Carbon::create($p['y'], $p['m'], 1)->translatedFormat('M Y');
        }

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $select = $isSqlite
            ? "strftime('%Y', p.tanggal_pemakaian) as y, strftime('%m', p.tanggal_pemakaian) as m, SUM(d.jumlah) as total"
            : 'YEAR(p.tanggal_pemakaian) as y, MONTH(p.tanggal_pemakaian) as m, SUM(d.jumlah) as total';

        $q = DB::table('detail_pemakaian_obat as d')
            ->join('pemakaian_obat as p', 'p.id', '=', 'd.pemakaian_id')
            ->whereBetween('p.tanggal_pemakaian', [$past[0]->format('Y-m-d'), end($past)->endOfMonth()->format('Y-m-d')])
            ->selectRaw($select)->groupBy('y', 'm');
        if ($fasilitasId) {
            $q->where('p.fasilitas_id', $fasilitasId);
        } elseif (! empty($visibleIds)) {
            $q->whereIn('p.fasilitas_id', $visibleIds);
        }
        $realRaw = $q->get()->keyBy(fn ($r) => (int) $r->y.'-'.(int) $r->m);
        foreach ($past as $d) {
            $realisasi[] = (int) ($realRaw->get(((int) $d->format('Y')).'-'.((int) $d->format('n')))?->total ?? 0);
            $prediksi[] = null;
        }
        $predRaw = PrediksiKebutuhan::query()
            ->selectRaw('periode_tahun as y, periode_bulan as m, SUM(jumlah_prediksi) as total')
            ->where(function ($qq) use ($periods) {
                foreach ($periods as $i => $p) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $qq->$method(fn ($q2) => $q2->where('periode_tahun', $p['y'])->where('periode_bulan', $p['m']));
                }
            })
            ->when($fasilitasId, fn ($qq) => $qq->where('fasilitas_id', $fasilitasId))
            ->when($fasilitasId === null && ! empty($visibleIds), fn ($qq) => $qq->whereIn('fasilitas_id', $visibleIds))
            ->groupBy('y', 'm')->get()->keyBy(fn ($r) => (int) $r->y.'-'.(int) $r->m);
        $predData = [];
        foreach ($periods as $p) {
            $predData[] = (int) ($predRaw->get($p['y'].'-'.$p['m'])?->total ?? 0);
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    ['label' => 'Realisasi', 'data' => array_merge($realisasi, array_fill(0, count($predData), null))],
                    ['label' => 'Prediksi AI', 'data' => array_merge(array_fill(0, count($realisasi), null), $predData)],
                ],
            ],
        ];
    }

    /** @param  int[]|null  $visibleIds */
    private function modelTerbaru(?int $fasilitasId, ?array $visibleIds)
    {
        return ModelPrediksi::query()->with(['fasilitas:id,nama', 'obat:id,nama_obat'])
            ->when($fasilitasId, fn ($q) => $q->where('fasilitas_id', $fasilitasId))
            ->when($fasilitasId === null && ! empty($visibleIds), fn ($q) => $q->whereIn('fasilitas_id', $visibleIds))
            ->orderByDesc('updated_at')->limit(10)->get();
    }

    /** @param  int[]|null  $visibleIds
     *  @return array<string,int> */
    private function modelStats(?int $fasilitasId, ?array $visibleIds): array
    {
        $base = ModelPrediksi::query()
            ->when($fasilitasId, fn ($q) => $q->where('fasilitas_id', $fasilitasId))
            ->when($fasilitasId === null && ! empty($visibleIds), fn ($q) => $q->whereIn('fasilitas_id', $visibleIds));
        $rows = (clone $base)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            'aktif' => (int) ($rows['aktif'] ?? 0),
            'kadaluarsa' => (int) ($rows['kadaluarsa'] ?? 0),
            'gagal' => (int) ($rows['gagal'] ?? 0),
            'data_belum_cukup' => (int) ($rows['data_belum_cukup'] ?? 0),
        ];
    }

    /** @param  int[]  $visibleIds */
    private function faskesList($user, array $visibleIds)
    {
        if (filled($user->fasilitas_kesehatan_id)) {
            return FasilitasKesehatan::whereIn('id', $visibleIds)->orderBy('nama')->get(['id', 'nama']);
        }

        return FasilitasKesehatan::orderBy('nama')->get(['id', 'nama']);
    }
}
