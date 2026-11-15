<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permintaan_obat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_permintaan');
            $table->unsignedBigInteger('fasilitas_pengirim_id');
            $table->unsignedBigInteger('fasilitas_tujuan_id')->nullable();
            $table->enum('tipe_permintaan', ['pustu_ke_puskesmas', 'puskesmas_ke_dinas']);
            $table->unsignedBigInteger('lplpo_id')->nullable();
            $table->enum('status', ['draft', 'menunggu_persetujuan', 'disetujui', 'ditolak', 'sedang_didistribusi', 'diterima', 'dibatalkan'])->default('draft');
            $table->date('tanggal_permintaan');
            $table->date('tanggal_disetujui')->nullable();
            $table->date('tanggal_ditolak')->nullable();
            $table->date('tanggal_dikirim')->nullable();
            $table->date('tanggal_diterima')->nullable();
            $table->unsignedBigInteger('disetujui_oleh')->nullable();
            $table->text('catatan')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->string('surat_permintaan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['status', 'tipe_permintaan', 'tanggal_permintaan', 'lplpo_id'], 'idx_permintaan_status_tipe');
            $table->unique(['nomor_permintaan'], 'permintaan_obat_nomor_permintaan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_obat');
    }
};
