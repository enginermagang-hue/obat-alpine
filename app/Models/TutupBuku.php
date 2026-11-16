<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutupBuku extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'tutup_buku';

    protected $fillable = [
        'fasilitas_id',
        'tahun',
        'tipe',
        'cutoff_at',
        'deadline_at',
        'neraca_id',
        'status',
        'ditutup_oleh',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'cutoff_at' => 'date',
            'deadline_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getActivitylogName(): string
    {
        return 'tutup_buku';
    }

    public function fasilitas(): BelongsTo
    {
        return $this->belongsTo(FasilitasKesehatan::class, 'fasilitas_id');
    }

    public function neraca(): BelongsTo
    {
        return $this->belongsTo(NeracaTahunan::class, 'neraca_id');
    }

    public function ditutupOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditutup_oleh');
    }

    public function isTerkunci(): bool
    {
        return $this->status === 'tutup';
    }

    public function isGracePeriod(): bool
    {
        if (! $this->deadline_at) {
            return false;
        }

        return now()->lte($this->deadline_at) && $this->status === 'tutup';
    }
}
