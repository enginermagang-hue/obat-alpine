<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use App\Services\NomorFormatService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NeracaTahunan extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'neraca_tahunan';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $record) {
            if (blank($record->nomor_neraca)) {
                $record->nomor_neraca = NomorFormatService::generate(
                    'neraca_tahunan',
                    $record->fasilitas_id,
                    now()->toDateString(),
                );
            }
        });
    }

    protected $fillable = [
        'nomor_neraca',
        'fasilitas_id',
        'tahun',
        'status',
        'catatan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getActivitylogName(): string
    {
        return 'laporan_neraca';
    }

    public function getTotalNilaiStokAttribute(): float
    {
        return (float) $this->details()->sum('nilai_stok');
    }

    public function fasilitas(): BelongsTo
    {
        return $this->belongsTo(FasilitasKesehatan::class, 'fasilitas_id');
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function details(): HasMany
    {
        return $this->hasMany(DetailNeracaTahunan::class, 'neraca_id');
    }
}
