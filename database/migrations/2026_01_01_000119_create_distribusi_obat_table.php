<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribusi_obat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_surat_jalan');
            $table->unsignedBigInteger('permintaan_id')->nullable();
            $table->enum('tipe_distribusi', ['dinas_ke_puskesmas', 'puskesmas_ke_pustu']);
            $table->unsignedBigInteger('fasilitas_pengirim_id')->nullable();
            $table->unsignedBigInteger('fasilitas_penerima_id');
            $table->enum('status', ['draft', 'dalam_pengiriman', 'diterima', 'ditolak'])->default('draft');
            $table->date('tanggal_kirim')->nullable();
            $table->date('tanggal_terima')->nullable();
            $table->date('tanggal_ditolak')->nullable();
            $table->unsignedBigInteger('pengirim_id');
            $table->unsignedBigInteger('penerima_id')->nullable();
            $table->unsignedBigInteger('penerimaan_stok_id')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['nomor_surat_jalan'], 'distribusi_obat_nomor_surat_jalan_unique');
            $table->index(['permintaan_id', 'status'], 'distribusi_obat_permintaan_id_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribusi_obat');
    }
};
