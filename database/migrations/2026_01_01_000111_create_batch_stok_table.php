<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_stok', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fasilitas_id')->nullable();
            $table->unsignedBigInteger('obat_id');
            $table->string('batch_number');
            $table->date('tanggal_expired');
            $table->integer('jumlah')->default(0);
            $table->enum('status', ['tersedia', 'karantina', 'expired', 'dimusnahkan'])->default('tersedia');
            $table->date('tanggal_masuk');
            $table->decimal('harga_beli', 12, 2)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('penerimaan_id')->nullable();
            $table->unsignedBigInteger('sumber_dana_id')->nullable();
            $table->index(['obat_id', 'fasilitas_id', 'tanggal_expired', 'status', 'batch_number'], 'idx_batch_faskes_expired');
            $table->index(['penerimaan_id'], 'idx_batch_penerimaan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_stok');
    }
};
