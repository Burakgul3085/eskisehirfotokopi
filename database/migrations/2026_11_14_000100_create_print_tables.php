<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotokopi (K1): müşterinin yüklediği belge (print_files) ve seçenekleriyle fiyatlanmış baskı işi (print_jobs).
 * Belge, oturumdaki rastgele sahip anahtarına (owner) bağlıdır; misafir de yükleyebilir. Baskı işi sepete
 * "print:{id}" satırı olarak girer, siparişte order_items.print_job_id ile bağlanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_files', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->string('owner', 40)->index();
            $table->string('original_name', 200);
            $table->string('extension', 10);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('received')->default(0);
            $table->string('path', 255);
            $table->string('pdf_path', 255)->nullable();
            $table->string('preview_path', 255)->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->string('status', 20)->default('uploading'); // uploading | processing | ready | failed | deleted
            $table->string('error', 300)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('print_files')->restrictOnDelete();
            $table->string('owner', 40)->index();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('options');
            $table->unsignedInteger('pages');
            $table->unsignedInteger('sides');
            $table->unsignedInteger('sheets');
            $table->unsignedInteger('copies');
            $table->decimal('price', 12, 2); // tüm kopyalar
            $table->json('breakdown');
            $table->timestamps();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('print_job_id')->references('id')->on('print_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropForeign(['print_job_id']));
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('print_files');
    }
};
