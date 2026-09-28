<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Documentos para vendedores: manuales / instructivos que pueden ser un
        // enlace externo (type = link, se usa url) o un PDF alojado dentro de
        // public/ (type = pdf, se usa path relativo a public/, bajo la carpeta
        // config('documents.pdf_path')).
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('type'); // 'link' | 'pdf' -> App\Enums\TipoDocumento
            $table->string('url')->nullable();  // solo si type = link
            $table->string('path')->nullable(); // solo si type = pdf, ruta dentro de public/
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
