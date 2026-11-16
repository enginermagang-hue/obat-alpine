<?php

namespace App\Http\Controllers;

use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\PemakaianObat;
use App\Models\PenerimaanStok;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $fasilitasId = $user->fasilitas_kesehatan_id;

        $scoped = fn ($query) => $fasilitasId !== null ? $query->where('fasilitas_id', $fasilitasId) : $query;

        $kpi = [
            'obat' => Obat::query()->where('status', 'aktif')->count(),
            'fasilitas' => FasilitasKesehatan::query()->where('status', 'aktif')->count(),
            'expired' => $scoped(BatchStok::query())
                ->where('jumlah', '>', 0)
                ->whereDate('tanggal_expired', '<=', Carbon::today()->addDays(90))
                ->count(),
            'pemakaian' => $scoped(PemakaianObat::query())
                ->whereBetween('tanggal_pemakaian', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                ->count(),
        ];

        $batchExpired = $scoped(BatchStok::query())
            ->with(['obat:id,nama_obat,satuan', 'fasilitas:id,nama'])
            ->where('jumlah', '>', 0)
            ->whereDate('tanggal_expired', '<=', Carbon::today()->addDays(90))
            ->orderBy('tanggal_expired')
            ->limit(8)
            ->get();

        $penerimaanTerakhir = $scoped(PenerimaanStok::query())
            ->with('fasilitas:id,nama')
            ->latest('tanggal_penerimaan')
            ->limit(5)
            ->get();

        $aktivitas = Activity::query()
            ->with('causer:id,name')
            ->latest()
            ->limit(6)
            ->get();

        return view('dashboard', [
            'user' => $user,
            'kpi' => $kpi,
            'batchExpired' => $batchExpired,
            'penerimaanTerakhir' => $penerimaanTerakhir,
            'aktivitas' => $aktivitas,
            'fasilitas' => $user->fasilitasKesehatan,
            'totalUser' => User::query()->count(),
        ]);
    }
}
