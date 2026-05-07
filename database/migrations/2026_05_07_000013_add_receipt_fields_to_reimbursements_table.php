<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->string('paid_to')->nullable()->after('request_date');
            $table->string('amount_in_words')->nullable()->after('amount');
            $table->string('expense_type')->nullable()->after('amount_in_words');
            $table->text('purpose')->nullable()->after('expense_type');
            $table->string('ref_document')->nullable()->after('purpose');
            $table->string('attachment_path')->nullable()->after('description');
            $table->string('attachment_original_name')->nullable()->after('attachment_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropColumn([
                'paid_to',
                'amount_in_words',
                'expense_type',
                'purpose',
                'ref_document',
                'attachment_path',
                'attachment_original_name',
            ]);
        });
    }
};
