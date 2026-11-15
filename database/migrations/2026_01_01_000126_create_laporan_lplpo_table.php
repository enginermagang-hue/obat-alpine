<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_lplpo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_laporan');
            $table->unsignedBigInteger('fasilitas_id');
            $table->integer('periode_bulan');
            $table->integer('periode_tahun');
            $table->string('tipe_pengajuan')->nullable();
            $table->enum('status', ['draft', 'selesai'])->default('draft');
            $table->enum('status_verifikasi', ['menunggu_verifikasi', 'sudah_diverifikasi', 'menunggu_revisi'])->nullable();
            $table->dateTime('tanggal_kirim')->nullable();
            $table->dateTime('tanggal_verifikasi')->nullable();
            $table->unsignedBigInteger('verifikator_id')->nullable();
            $table->text('catatan_verifikator')->nullable();
            $table->date('tanggal_pembuatan');
            $table->date('tanggal_pengajuan')->nullable();
            $table->date('tanggal_disetujui')->nullable();
            $table->unsignedBigInteger('dibuat_oleh');
            $table->unsignedBigInteger('disetujui_oleh')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('parent_lplpo_id')->nullable();
            $table->unique(['nomor_laporan'], 'laporan_lplpo_nomor_laporan_unique');
            $table->index(['fasilitas_id'], 'lplpo_fasilitas_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_lplpo');
    }
};
