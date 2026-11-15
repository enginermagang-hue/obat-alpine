<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_distribusi_obat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('distribusi_id');
            $table->unsignedBigInteger('obat_id');
            $table->unsignedBigInteger('batch_id');
            $table->integer('jumlah');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['distribusi_id', 'obat_id', 'batch_id'], 'detail_distribusi_obat_distribusi_id_obat_id_batch_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_distribusi_obat');
    }
};
