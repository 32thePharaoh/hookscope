<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('captured_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('method', 16);
            $table->text('path');
            $table->text('query')->nullable();
            $table->json('headers');
            $table->binary('body');
            $table->string('body_encoding', 32);
            $table->string('content_type')->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('size_bytes');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['endpoint_id', 'received_at']);
        });

        DB::statement('ALTER TABLE captured_requests MODIFY body LONGBLOB NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('captured_requests');
    }
};
