<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chatbox_rag_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('original_filename');
            $table->string('file_type', 10);
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
            $table->unsignedInteger('chunk_count')->default(0);
            $table->text('content')->nullable(); // raw text stored for reprocessing
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chatbox_rag_documents');
    }
};
