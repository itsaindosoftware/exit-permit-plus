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
        Schema::create('reimbursement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reimbursement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('ref_document')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reimbursement_documents');
    }
};
