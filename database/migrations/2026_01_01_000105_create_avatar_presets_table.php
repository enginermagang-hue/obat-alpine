<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avatar_presets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama');
            $table->string('file_path');
            $table->enum('kategori', ['hewan', 'profesi', 'abstrak', 'emoji', 'alam', 'boy', 'girl']);
            $table->boolean('is_active')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['kategori', 'is_active', 'nama'], 'avatar_presets_kategori_is_active_nama_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avatar_presets');
    }
};
