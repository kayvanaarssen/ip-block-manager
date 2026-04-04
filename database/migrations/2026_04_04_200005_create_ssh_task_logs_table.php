<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssh_task_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('blocked_ip_id')->nullable()->constrained('blocked_ips')->nullOnDelete();
            $table->foreignUlid('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('command');
            $table->string('status')->default('pending');
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_task_logs');
    }
};
