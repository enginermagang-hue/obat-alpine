<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retur_obat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_retur');
            $table->unsignedBigInteger('distribusi_id')->nullable();
            $table->unsignedBigInteger('penerimaan_id')->nullable();
            $table->unsignedBigInteger('fasilitas_pengirim_id')->nullable();
            $table->unsignedBigInteger('fasilitas_penerima_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->enum('tipe_retur', ['puskesmas_ke_gudang', 'pustu_ke_puskesmas', 'gudang_ke_supplier']);
            $table->enum('alasan', ['expired', 'rusak', 'kelebihan_stok', 'salah_kirim', 'recall', 'near_expiry', 'lainnya']);
            $table->text('alasan_lainnya')->nullable();
            $table->enum('status', ['draft', 'menunggu_approval', 'disetujui', 'ditolak', 'dalam_pengiriman', 'diterima', 'selesai'])->default('draft');
            $table->date('tanggal_retur');
            $table->date('tanggal_disetujui')->nullable();
            $table->date('tanggal_ditolak')->nullable();
            $table->date('tanggal_dikirim')->nullable();
            $table->date('tanggal_diterima')->nullable();
            $table->unsignedBigInteger('disetujui_oleh')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['nomor_retur'], 'retur_obat_nomor_retur_unique');
            $table->index(['status', 'tipe_retur', 'alasan', 'tanggal_retur'], 'retur_obat_status_tipe_retur_alasan_tanggal_retur_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retur_obat');
    }
};
