<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('archived_called_by_name')->nullable()->after('called_by_user_id');
            $table->string('archived_started_by_name')->nullable()->after('started_by_user_id');
            $table->string('archived_completed_by_name')->nullable()->after('completed_by_user_id');
            $table->string('archived_no_show_by_name')->nullable()->after('no_show_by_user_id');
        });

        Schema::table('ticket_calls', function (Blueprint $table): void {
            $table->string('archived_called_by_name')->nullable()->after('called_by_user_id');
            $table->dropForeign(['clinic_id', 'called_by_user_id']);
        });

        Schema::table('ticket_calls', function (Blueprint $table): void {
            $table->unsignedBigInteger('called_by_user_id')->nullable()->change();
            $table->foreign(['clinic_id', 'called_by_user_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::table('ticket_transfers', function (Blueprint $table): void {
            $table->string('archived_transferred_by_name')->nullable()->after('transferred_by_user_id');
            $table->dropForeign(['clinic_id', 'transferred_by_user_id']);
        });

        Schema::table('ticket_transfers', function (Blueprint $table): void {
            $table->unsignedBigInteger('transferred_by_user_id')->nullable()->change();
            $table->foreign(['clinic_id', 'transferred_by_user_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->string('archived_sender_name')->nullable()->after('sender_id');
            $table->dropForeign(['clinic_id', 'sender_id']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('sender_id')->nullable()->change();
            $table->foreign(['clinic_id', 'sender_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::table('media_items', function (Blueprint $table): void {
            $table->string('archived_created_by_name')->nullable()->after('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table): void {
            $table->dropColumn('archived_created_by_name');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropForeign(['clinic_id', 'sender_id']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('sender_id')->nullable(false)->change();
            $table->dropColumn('archived_sender_name');
            $table->foreign(['clinic_id', 'sender_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('ticket_transfers', function (Blueprint $table): void {
            $table->dropForeign(['clinic_id', 'transferred_by_user_id']);
        });

        Schema::table('ticket_transfers', function (Blueprint $table): void {
            $table->unsignedBigInteger('transferred_by_user_id')->nullable(false)->change();
            $table->dropColumn('archived_transferred_by_name');
            $table->foreign(['clinic_id', 'transferred_by_user_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::table('ticket_calls', function (Blueprint $table): void {
            $table->dropForeign(['clinic_id', 'called_by_user_id']);
        });

        Schema::table('ticket_calls', function (Blueprint $table): void {
            $table->unsignedBigInteger('called_by_user_id')->nullable(false)->change();
            $table->dropColumn('archived_called_by_name');
            $table->foreign(['clinic_id', 'called_by_user_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn([
                'archived_called_by_name',
                'archived_started_by_name',
                'archived_completed_by_name',
                'archived_no_show_by_name',
            ]);
        });
    }
};
