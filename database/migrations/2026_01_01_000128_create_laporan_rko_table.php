<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_rko', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_rko');
            $table->unsignedBigInteger('fasilitas_id');
            $table->integer('periode_tahun');
            $table->enum('status', ['draft', 'diajukan', 'disetujui', 'ditolak'])->default('draft');
            $table->date('tanggal_pembuatan');
            $table->date('tanggal_pengajuan')->nullable();
            $table->date('tanggal_disetujui')->nullable();
            $table->decimal('total_anggaran', 14, 2)->nullable();
            $table->unsignedBigInteger('dibuat_oleh');
            $table->unsignedBigInteger('disetujui_oleh')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['fasilitas_id', 'periode_tahun'], 'laporan_rko_fasilitas_id_periode_tahun_unique');
            $table->unique(['nomor_rko'], 'laporan_rko_nomor_rko_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_rko');
    }
};
