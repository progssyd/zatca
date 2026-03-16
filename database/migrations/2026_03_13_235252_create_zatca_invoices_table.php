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
        Schema::create('zatca_invoices', function (Blueprint $table) {
             $table->id();
            // ربط الفاتورة بطلب حقيقي في نظامك (تأكد أن جدول orders موجود)
            $table->unsignedBigInteger('order_id'); 
            
            $table->string('uuid')->unique(); // المعرف الفريد للفاتورة
            $table->integer('icv')->default(1); // عداد الفواتير (1, 2, 3...)
            
            $table->text('invoice_hash'); // هاش الفاتورة الحالية (يُستخدم كـ PIH للقادمة)
            $table->text('previous_hash')->nullable(); // هاش الفاتورة السابقة
            
            $table->text('qr_code'); // نص الـ QR Code بصيغة Base64 للعرض
            $table->enum('status', ['REPORTED', 'FAILED'])->default('REPORTED');
            
            $table->json('zatca_response')->nullable(); // رد الهيئة كاملاً (للأغراض الرقابية)
            
            $table->timestamps();

            // إضافة الفهرسة لسرعة البحث
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zatca_invoices');
    }
};
