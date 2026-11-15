<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_faskes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fasilitas_id');
            $table->unsignedBigInteger('obat_id');
            $table->integer('jumlah')->default(0);
            $table->integer('stok_minimum')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['fasilitas_id', 'obat_id'], 'stok_faskes_fasilitas_id_obat_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_faskes');
    }
};
