<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_neraca_sumber_dana', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('detail_neraca_id');
            $table->unsignedBigInteger('sumber_dana_id')->nullable();
            $table->integer('stok_awal_jumlah')->default(0);
            $table->decimal('stok_awal_nilai', 14, 2)->default(0.00);
            $table->integer('masuk_jumlah')->default(0);
            $table->decimal('masuk_nilai', 14, 2)->default(0.00);
            $table->integer('keluar_jumlah')->default(0);
            $table->decimal('keluar_nilai', 14, 2)->default(0.00);
            $table->integer('stok_akhir_jumlah')->default(0);
            $table->decimal('stok_akhir_nilai', 14, 2)->default(0.00);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['detail_neraca_id', 'sumber_dana_id'], 'detail_neraca_sumber_dana_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_neraca_sumber_dana');
    }
};
