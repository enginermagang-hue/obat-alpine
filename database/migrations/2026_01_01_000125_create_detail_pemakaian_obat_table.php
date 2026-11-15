<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_pemakaian_obat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pemakaian_id');
            $table->unsignedBigInteger('obat_id');
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedInteger('jumlah');
            $table->string('dosis', 100)->nullable();
            $table->string('satuan_dosis', 50)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['pemakaian_id', 'obat_id'], 'idx_detail_pemakaian_obat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_pemakaian_obat');
    }
};
