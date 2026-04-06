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
        // 1. Update teacher_adjustments table
        Schema::table('teacher_adjustments', function (Blueprint $table) {
            if (!Schema::hasColumn('teacher_adjustments', 'payment_status')) {
                $table->enum('payment_status', ['paid', 'pending'])->default('pending')->after('adjustment_date');
            }
            if (!Schema::hasColumn('teacher_adjustments', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('teacher_adjustments', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('payment_date');
            }
            if (!Schema::hasColumn('teacher_adjustments', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('teacher_adjustments', 'salary_id')) {
                $table->unsignedBigInteger('salary_id')->nullable()->after('paid_by');
            }
        });

        // 2. Create capital_additions table
        if (!Schema::hasTable('capital_additions')) {
            Schema::create('capital_additions', function (Blueprint $table) {
                $table->id();
                $table->decimal('amount', 10, 2);
                $table->string('description')->nullable();
                $table->unsignedBigInteger('added_by');
                $table->date('addition_date');
                $table->timestamps();
                
                $table->foreign('added_by')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // 3. Create admin_vaults table
        if (!Schema::hasTable('admin_vaults')) {
            Schema::create('admin_vaults', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->decimal('profit_percentage', 5, 2);
                $table->decimal('total_earned', 15, 2)->default(0);
                $table->decimal('total_withdrawn', 15, 2)->default(0);
                $table->timestamp('last_calculation')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // 4. Create admin_withdrawals table
        if (!Schema::hasTable('admin_withdrawals')) {
            Schema::create('admin_withdrawals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('vault_id');
                $table->decimal('amount', 15, 2);
                $table->enum('status', ['pending', 'approved', 'completed', 'canceled'])->default('pending');
                $table->string('receipt_method')->nullable();
                $table->text('receipt_details')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('canceled_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('canceled_by')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('vault_id')->references('id')->on('admin_vaults')->onDelete('cascade');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('canceled_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        // 5. Create profit_distributions table
        if (!Schema::hasTable('profit_distributions')) {
            Schema::create('profit_distributions', function (Blueprint $table) {
                $table->id();
                $table->decimal('total_net_profit', 15, 2);
                $table->date('distribution_date');
                $table->json('distribution_details');
                $table->unsignedBigInteger('distributed_by');
                $table->timestamps();

                $table->foreign('distributed_by')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // 6. Create salary_transfers table
        if (!Schema::hasTable('salary_transfers')) {
            Schema::create('salary_transfers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_teacher_id');
                $table->unsignedBigInteger('target_teacher_id');
                $table->decimal('amount', 10, 2);
                $table->string('reason')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->foreign('source_teacher_id')->references('teacher_id')->on('teachers')->onDelete('cascade');
                $table->foreign('target_teacher_id')->references('teacher_id')->on('teachers')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_transfers');
        Schema::dropIfExists('profit_distributions');
        Schema::dropIfExists('admin_withdrawals');
        Schema::dropIfExists('admin_vaults');
        Schema::dropIfExists('capital_additions');
        
        Schema::table('teacher_adjustments', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_date', 'payment_method', 'paid_by', 'salary_id']);
        });
    }
};
