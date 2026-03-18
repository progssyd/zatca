<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('zatca_sequences', function (Blueprint $table) {
        $table->id();
        $table->string('invoice_type')->default('simplified'); 
        
        // رقم آخر فاتورة في نظام VB6 (مثلاً 12000)
        $table->integer('last_vb6_invoice_id')->default(12000); 
        
        // عداد زاتكا (ICV) - يبدأ من 0 ويزيد مع كل إرسال ناجح
        $table->integer('last_icv')->default(0); 
        
        // الهاش الخاص بآخر فاتورة مقبولة (PIH)
        $table->text('last_hash')->default('NWZlY2ViOTZmOTY5NzY4NGU3YmQ0ZTYzMzE1MmE5MWZkYTMzMjY2YmU5ZDFlODEwYmU3NjhiMzg1MGExY2I3Mw==');
        
        $table->timestamps();
    });

    // إدراج السجل الأول لبدء السلسلة
    DB::table('zatca_sequences')->insert([
        'invoice_type' => 'simplified',
        'last_vb6_invoice_id' => 12525,
        'last_icv' => 0,
        'last_hash' => 'NWZlY2ViOTZmOTY5NzY4NGU3YmQ0ZTYzMzE1MmE5MWZkYTMzMjY2YmU5ZDFlODEwYmU3NjhiMzg1MGExY2I3Mw==',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zatca_sequences');
    }
};
