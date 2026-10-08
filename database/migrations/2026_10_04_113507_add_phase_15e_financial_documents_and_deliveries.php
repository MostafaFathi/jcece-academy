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
        Schema::table('users', function (Blueprint $table): void {
            $table->char('preferred_locale', 2)->default('ar')->after('email');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->char('locale_snapshot', 2)->nullable()->after('customer_email');
        });

        Schema::create('financial_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->unique()->constrained('refunds')->restrictOnDelete();
            $table->string('source_key', 100)->unique();
            $table->string('document_number', 100)->unique();
            $table->string('kind', 16);
            $table->char('locale', 2);
            $table->string('customer_name');
            $table->string('customer_email');
            $table->char('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->json('snapshot');
            $table->string('pdf_disk', 64);
            $table->string('pdf_path', 512);
            $table->char('pdf_sha256', 64);
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->index(['order_id', 'kind']);
        });

        Schema::create('transactional_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 150)->unique();
            $table->string('message_type', 50);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->string('recipient_email');
            $table->char('locale', 2);
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->json('payload');
            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error_code', 100)->nullable();
            $table->timestamp('queued_at');
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
            $table->index(['status', 'queued_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactional_deliveries');
        Schema::dropIfExists('financial_documents');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('locale_snapshot');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('preferred_locale');
        });
    }
};
