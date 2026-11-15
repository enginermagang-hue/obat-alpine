<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_rko', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rko_id');
            $table->unsignedBigInteger('obat_id');
            $table->integer('pemakaian_tahun_sebelumnya');
            $table->integer('rata_rata_pemakaian_bulanan');
            $table->integer('stok_akhir');
            $table->integer('kebutuhan_tahunan');
            $table->integer('usulan');
            $table->decimal('harga_perkiraan', 12, 2)->nullable();
            $table->decimal('total_harga', 14, 2)->nullable();
            $table->decimal('buffer_stock_persen', 5, 2)->default(0.00);
            $table->integer('buffer_stok_qty')->default(0);
            $table->integer('total_kebutuhan')->default(0);
            $table->string('ven_kategori', 1)->nullable();
            $table->integer('rencana_kebutuhan')->default(0);
            $table->char('abc_kategori', 1)->nullable();
            $table->unsignedBigInteger('lplpo_referensi_id')->nullable();
            $table->unsignedBigInteger('prediksi_id')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_rko');
    }
};
