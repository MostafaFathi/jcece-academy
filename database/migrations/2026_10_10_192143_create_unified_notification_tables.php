<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->string('category')->default('system');
            $t->string('priority')->default('normal');
            $t->string('target_type')->nullable();
            $t->unsignedBigInteger('target_id')->nullable();
            $t->unsignedBigInteger('target_version')->default(0);
            $t->unsignedInteger('aggregate_count')->default(1);
            $t->index(['notifiable_type', 'notifiable_id', 'read_at', 'created_at'], 'notification_inbox');
            $t->index(['target_type', 'target_id']);
        });
        Schema::create('notification_receipts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('event_key', 64);
            $t->string('type');
            $t->uuid('notification_id')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'event_key']);
        });
        Schema::create('notification_preferences', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->boolean('in_app');
            $t->boolean('email');
            $t->boolean('digest')->default(false);
            $t->timestamps();
            $t->unique(['user_id', 'type']);
        });
        Schema::create('notification_type_policies', function (Blueprint $t): void {
            $t->id();
            $t->string('type')->unique();
            $t->boolean('in_app')->default(true);
            $t->boolean('email')->default(false);
            $t->timestamps();
        });
        Schema::create('notification_deliveries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('event_key', 64);
            $t->string('type');
            $t->string('channel')->default('email');
            $t->json('payload');
            $t->string('status')->default('queued');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at');
            $t->timestamp('last_attempt_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->string('error_code')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'event_key', 'channel']);
            $t->index(['status', 'available_at']);
        });
        Schema::create('announcements', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('published_by')->nullable()->constrained('users');
            $t->json('title');
            $t->json('body');
            $t->json('audience');
            $t->string('status')->default('draft');
            $t->timestamp('publish_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'publish_at']);
        });
        Schema::create('announcement_recipients', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['announcement_recipients', 'announcements', 'notification_deliveries', 'notification_type_policies', 'notification_preferences', 'notification_receipts', 'notifications'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
