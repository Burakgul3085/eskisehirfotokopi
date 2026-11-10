<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toplu ürün yükleme (docs/01 §6): her sürükle-bırak bir "batch"; her kitap klasörü bir "item".
 * Item yaşam döngüsü: waiting_files → queued → processing → imported | review | failed (| skipped: daha önce yüklenmiş).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('name', 255);
            $table->string('source', 20)->default('upload'); // upload | server
            $table->string('status', 20)->default('uploading'); // uploading | processing | done
            $table->unsignedInteger('total_items')->default(0);
            $table->timestamps();
        });

        Schema::create('import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->string('publisher_folder', 255)->nullable();
            $table->string('folder', 255);
            $table->string('fingerprint', 40)->index();
            $table->string('status', 20)->default('waiting_files')->index();
            $table->json('expected');                 // {"info": "Ekran görüntüsü….png", "mockup": "ChatGPT….png"}
            $table->string('info_path')->nullable();  // local disk, imports/{batch}/{item}/
            $table->string('mockup_path')->nullable();
            $table->longText('ocr_text')->nullable();
            $table->json('parsed')->nullable();       // okunan ve birleştirilen alanlar
            $table->json('issues')->nullable();       // kontrol gerektiren durumlar
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 10)->nullable(); // created | updated
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_items');
        Schema::dropIfExists('import_batches');
    }
};
