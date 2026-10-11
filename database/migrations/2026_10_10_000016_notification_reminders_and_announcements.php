<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications:
 *   - notifications.type gains 'announcement' (sent by an administrator) and
 *     'disconnection' (warning before the disconnection date)
 *   - announcements: history of what administrators sent, and to whom
 */
return new class extends Migration
{
    public function up(): void
    {
        $types = "'bill_due','bill_overdue','payment_received','general','announcement','disconnection'";
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_type_check');
            DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_type_check CHECK (type::text IN ($types))");
        } else {
            DB::statement("ALTER TABLE `notifications` MODIFY COLUMN `type` ENUM($types) NOT NULL DEFAULT 'general'");
        }

        if (!Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->increments('announcement_id');
                $table->string('title', 150);
                $table->string('message', 500);
                $table->string('purok_ids', 255)->nullable()->comment('comma-separated purok IDs; NULL = all consumers');
                $table->unsignedInteger('recipient_count')->default(0);
                $table->boolean('emailed')->default(false);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('created_by', 'fk_announcement_creator')->references('user_id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
