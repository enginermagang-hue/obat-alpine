<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penerimaan_stok', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_penerimaan');
            $table->enum('tipe', ['pembelian', 'hibah', 'stok_awal', 'penyesuaian', 'distribusi', 'manual']);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('nomor_po')->nullable();
            $table->string('nomor_invoice')->nullable();
            $table->date('tanggal_penerimaan');
            $table->unsignedBigInteger('fasilitas_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->enum('status', ['draft', 'dikonfirmasi', 'dibatalkan'])->default('draft');
            $table->text('catatan')->nullable();
            $table->decimal('total_biaya', 12, 2)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('sumber_dana_id')->nullable();
            $table->unsignedBigInteger('distribusi_id')->nullable();
            $table->unique(['nomor_penerimaan'], 'penerimaan_stok_nomor_penerimaan_unique');
            $table->index(['tipe', 'status'], 'penerimaan_stok_tipe_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penerimaan_stok');
    }
};
