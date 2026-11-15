<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_neraca_tahunan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('neraca_id');
            $table->unsignedBigInteger('obat_id');
            $table->integer('stok_awal')->default(0);
            $table->integer('total_masuk')->default(0);
            $table->integer('total_keluar')->default(0);
            $table->integer('stok_akhir')->default(0);
            $table->integer('stok_optimum')->nullable();
            $table->integer('permintaan')->nullable();
            $table->decimal('harga_satuan', 12, 2)->nullable();
            $table->decimal('nilai_stok', 14, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['neraca_id', 'obat_id'], 'detail_neraca_tahunan_neraca_id_obat_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_neraca_tahunan');
    }
};
