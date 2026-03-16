<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZatcaInvoice extends Model
{
    protected $fillable = [
        'order_id', 'uuid', 'icv', 'invoice_hash', 
        'previous_hash', 'qr_code', 'status', 'zatca_response'
    ];

    protected $casts = [
        'zatca_response' => 'array',
    ];

    // علاقة مع جدول الطلبات (تأكد من اسم الموديل عندك، غالباً Order)
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
