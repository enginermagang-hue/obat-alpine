<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspeksi_retur', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('retur_id');
            $table->unsignedBigInteger('detail_retur_id');
            $table->unsignedBigInteger('batch_id');
            $table->enum('hasil_inspeksi', ['layak', 'tidak_layak', 'perlu_tindakan_lanjut']);
            $table->enum('tindakan', ['didistribusi_kembali', 'dimusnahkan', 'dikembalikan_ke_supplier']);
            $table->text('catatan_inspeksi')->nullable();
            $table->unsignedBigInteger('inspected_by');
            $table->date('tanggal_inspeksi');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['retur_id', 'detail_retur_id', 'hasil_inspeksi', 'tindakan'], 'idx_inspeksi_retur_hasil');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspeksi_retur');
    }
};
