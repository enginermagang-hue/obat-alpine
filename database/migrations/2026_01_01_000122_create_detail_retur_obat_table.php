<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_retur_obat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('retur_id');
            $table->unsignedBigInteger('obat_id');
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->integer('jumlah_retur');
            $table->string('bukti_foto')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['retur_id', 'obat_id', 'batch_id'], 'detail_retur_obat_retur_id_obat_id_batch_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_retur_obat');
    }
};
