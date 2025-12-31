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
        // Tabla para historial detallado de uso de tokens
        Schema::create('token_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('thread_id')->nullable()->index();
            $table->string('model')->nullable(); // ej: gpt-4o, claude-3, etc.
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->string('request_type')->default('chat'); // chat, tool_call, etc.
            $table->json('meta')->nullable(); // datos adicionales
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['thread_id', 'created_at']);
        });

        // Agregar campos acumulados a conversations
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('total_input_tokens')->default(0)->after('meta');
            $table->unsignedBigInteger('total_output_tokens')->default(0)->after('total_input_tokens');
            $table->unsignedBigInteger('total_tokens')->default(0)->after('total_output_tokens');
        });

        // Agregar campos acumulados a users para totales globales
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('total_tokens_used')->default(0)->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_usage');

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['total_input_tokens', 'total_output_tokens', 'total_tokens']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('total_tokens_used');
        });
    }
};
