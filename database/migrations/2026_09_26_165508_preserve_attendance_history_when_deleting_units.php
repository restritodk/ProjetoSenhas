<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance rows referenced the unit, desk and sector with NOT NULL foreign keys.
 * tickets and ticket_calls cascaded on unit delete, which erased history.
 * ticket_transfers restricted the delete, which blocked it.
 * Composite keys cannot use ON DELETE SET NULL because clinic_id must stay.
 * This migration keeps clinic_id, stores the names the live relations used to
 * provide, and makes the operational references nullable with RESTRICT so the
 * application detaches them before the unit is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('archived_unit_name')->nullable()->after('unit_id');
            $table->string('archived_sector_name')->nullable()->after('sector_id');
            $table->string('archived_desk_name')->nullable()->after('current_desk_id');
        });

        Schema::table('ticket_calls', function (Blueprint $table): void {
            $table->string('archived_unit_name')->nullable()->after('unit_id');
            $table->string('archived_sector_name')->nullable()->after('sector_id');
            $table->string('archived_desk_name')->nullable()->after('desk_id');
        });

        Schema::table('ticket_transfers', function (Blueprint $table): void {
            $table->string('archived_unit_name')->nullable()->after('unit_id');
            $table->string('archived_from_desk_name')->nullable()->after('from_desk_id');
            $table->string('archived_to_desk_name')->nullable()->after('to_desk_id');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropForeign(['clinic_id', 'unit_id']);
        });

        Schema::table('ticket_calls', function (Blueprint $table): void {
            $table->dropForeign(['clinic_id', 'unit_id']);
            $table->dropForeign(['clinic_id', 'desk_id']);
        });

        Schema::table('ticket_transfers', function (Blueprint $table): void {
            $table->dropForeign(['clinic_id', 'unit_id']);
            $table->dropForeign(['clinic_id', 'from_desk_id']);
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_id')->nullable()->change();
        });

        Schema::table('ticket_calls', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_id')->nullable()->change();
            $table->unsignedBigInteger('desk_id')->nullable()->change();
        });

        Schema::table('ticket_transfers', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_id')->nullable()->change();
            $table->unsignedBigInteger('from_desk_id')->nullable()->change();
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreign(['clinic_id', 'unit_id'])
                ->references(['clinic_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
        });

        Schema::table('ticket_calls', function (Blueprint $table): void {
            $table->foreign(['clinic_id', 'unit_id'])
                ->references(['clinic_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
            $table->foreign(['clinic_id', 'desk_id'])
                ->references(['clinic_id', 'id'])
                ->on('desks')
                ->restrictOnDelete();
        });

        Schema::table('ticket_transfers', function (Blueprint $table): void {
            $table->foreign(['clinic_id', 'unit_id'])
                ->references(['clinic_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
            $table->foreign(['clinic_id', 'from_desk_id'])
                ->references(['clinic_id', 'id'])
                ->on('desks')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropForeign(['clinic_id', 'unit_id']);
        });

        Schema::table('ticket_calls', function (Blueprint $table): void {
            $table->dropForeign(['clinic_id', 'unit_id']);
            $table->dropForeign(['clinic_id', 'desk_id']);
        });

        Schema::table('ticket_transfers', function (Blueprint $table): void {
            $table->dropForeign(['clinic_id', 'unit_id']);
            $table->dropForeign(['clinic_id', 'from_desk_id']);
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_id')->nullable(false)->change();
        });

        Schema::table('ticket_calls', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_id')->nullable(false)->change();
            $table->unsignedBigInteger('desk_id')->nullable(false)->change();
        });

        Schema::table('ticket_transfers', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_id')->nullable(false)->change();
            $table->unsignedBigInteger('from_desk_id')->nullable(false)->change();
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn(['archived_unit_name', 'archived_sector_name', 'archived_desk_name']);
            $table->foreign(['clinic_id', 'unit_id'])
                ->references(['clinic_id', 'id'])
                ->on('units')
                ->cascadeOnDelete();
        });

        Schema::table('ticket_calls', function (Blueprint $table): void {
            $table->dropColumn(['archived_unit_name', 'archived_sector_name', 'archived_desk_name']);
            $table->foreign(['clinic_id', 'unit_id'])
                ->references(['clinic_id', 'id'])
                ->on('units')
                ->cascadeOnDelete();
            $table->foreign(['clinic_id', 'desk_id'])
                ->references(['clinic_id', 'id'])
                ->on('desks')
                ->cascadeOnDelete();
        });

        Schema::table('ticket_transfers', function (Blueprint $table): void {
            $table->dropColumn(['archived_unit_name', 'archived_from_desk_name', 'archived_to_desk_name']);
            $table->foreign(['clinic_id', 'unit_id'])
                ->references(['clinic_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
            $table->foreign(['clinic_id', 'from_desk_id'])
                ->references(['clinic_id', 'id'])
                ->on('desks')
                ->restrictOnDelete();
        });
    }
};
