<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaporanLplpo extends Model
{
    use LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SELESAI = 'selesai';

    public const VERIF_MENUNGGU = 'menunggu_verifikasi';

    public const VERIF_DIVERIFIKASI = 'sudah_diverifikasi';

    public const VERIF_REVISI = 'menunggu_revisi';

    protected $table = 'laporan_lplpo';

    protected $fillable = [
        'nomor_laporan',
        'fasilitas_id',
        'periode_bulan',
        'periode_tahun',
        'status',
        'status_verifikasi',
        'tanggal_pembuatan',
        'tanggal_kirim',
        'tanggal_verifikasi',
        'dibuat_oleh',
        'verifikator_id',
        'catatan',
        'catatan_verifikator',
        'parent_lplpo_id',
    ];

    protected function casts(): array
    {
        return [
            'periode_bulan' => 'integer',
            'periode_tahun' => 'integer',
            'tanggal_pembuatan' => 'date',
            'tanggal_kirim' => 'datetime',
            'tanggal_verifikasi' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifikator_id');
    }

    public function getActivitylogName(): string
    {
        return 'laporan_lplpo';
    }

    public function fasilitas(): BelongsTo
    {
        return $this->belongsTo(FasilitasKesehatan::class, 'fasilitas_id');
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function parentLplpo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_lplpo_id');
    }

    public function revisiLplpo(): HasMany
    {
        return $this->hasMany(self::class, 'parent_lplpo_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(DetailLplpo::class, 'lplpo_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('parent_lplpo_id');
    }

    public function isTerlambat(): bool
    {
        if (! $this->tanggal_kirim) {
            return false;
        }

        $batas = Carbon::create((int) $this->periode_tahun, (int) $this->periode_bulan, 1)->endOfMonth()->addDays(2);

        return $this->tanggal_kirim->gt($batas);
    }

    public function labelStatusVerifikasi(): string
    {
        return match ($this->status_verifikasi) {
            self::VERIF_DIVERIFIKASI => 'Sudah Diverifikasi',
            self::VERIF_REVISI => 'Menunggu Revisi',
            self::VERIF_MENUNGGU => $this->isTerlambat() ? 'Terlambat' : 'Menunggu Verifikasi',
            default => 'Belum Dikirim',
        };
    }
}
