<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core tables for the in-process enpii/assistant package.
 *
 * Notable changes vs the legacy orchestrator schema:
 *   - No tenants / api_keys / chat_sessions tables — host app owns tenant
 *     identity via TenantResolver binding.
 *   - tenant_id is a plain string column on KnowledgeSource, Conversation,
 *     ToolExecution, AuditLog (no FK to a tenants table).
 *   - Tools.persona scoping stays (Persona is part of the package).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_personas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('system_prompt');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_tools', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description');
            $table->json('json_schema');
            $table->boolean('requires_confirmation')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_persona_tool', function (Blueprint $table): void {
            $table->foreignUuid('persona_id')->constrained('ai_personas')->cascadeOnDelete();
            $table->foreignUuid('tool_id')->constrained('ai_tools')->cascadeOnDelete();
            $table->primary(['persona_id', 'tool_id']);
        });

        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('persona_id')->nullable()
                ->constrained('ai_personas')->nullOnDelete();
            $table->string('external_user_id');
            $table->string('tenant_id')->index();
            $table->string('channel', 20)->default('web');
            $table->string('status', 20)->default('open');
            $table->text('summary')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'external_user_id', 'status']);
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content')->nullable();
            $table->json('tool_call_json')->nullable();
            $table->json('tool_result_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ai_tool_executions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->foreignUuid('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('tenant_id')->index();
            $table->json('input_params');
            $table->json('output')->nullable();
            $table->string('status', 40)->default('pending_confirmation');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('executed_at')->nullable();
        });

        Schema::create('ai_confirmations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tool_execution_id')->constrained('ai_tool_executions')->cascadeOnDelete();
            $table->string('confirmed_by')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ai_knowledge_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('persona_id')->nullable()
                ->constrained('ai_personas')->nullOnDelete();
            $table->string('name');
            $table->string('source_type', 30)->default('faq_manual');
            $table->string('status', 20)->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('knowledge_source_id')->constrained('ai_knowledge_sources')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('question')->nullable();
            $table->text('answer')->nullable();
            $table->text('content_raw')->nullable();
            $table->string('source_format', 20)->default('manual');
            $table->timestamps();
        });

        Schema::create('ai_document_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('ai_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('chunk_text');
            $table->text('embedding_json')->nullable();
        });

        Schema::create('ai_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->string('actor');
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_logs');
        Schema::dropIfExists('ai_document_chunks');
        Schema::dropIfExists('ai_documents');
        Schema::dropIfExists('ai_knowledge_sources');
        Schema::dropIfExists('ai_confirmations');
        Schema::dropIfExists('ai_tool_executions');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_persona_tool');
        Schema::dropIfExists('ai_tools');
        Schema::dropIfExists('ai_personas');
    }
};
