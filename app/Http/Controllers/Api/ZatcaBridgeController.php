<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DOMDocument;

class ZatcaBridgeController extends Controller
{
    public function processFromVB6(Request $request)
    {
        try {
            // 1. جلب آخر حالة من جدول المتسلسلات (MySQL)
            $sequence = DB::table('zatca_sequences')->first();
            
            // زيادة العدادات آلياً
            $nextVbId = $sequence->last_vb6_invoice_id + 1; // سيكون 12526
            $nextIcv  = $sequence->last_icv + 1;            // سيكون 1
            $pih      = $sequence->last_hash;

            // 2. بيانات الفاتورة القادمة من VB6
            // نفترض أن VB6 يرسل (المبلغ الصافي، الضريبة، اسم العميل)
            $vbData = $request->all();
            $uuid = (string) Str::uuid();

            // 3. توليد الـ XML (باستخدام القالب الذي نجحنا به سابقاً)
            $xmlInvoice = $this->generateBaseXML(
                $uuid, 
                $nextVbId, 
                $nextIcv, 
                $pih, 
                $vbData
            );

            // 4. 🔥 حساب الهاش المطابق لزاتكا (Canonicalization)
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;
            $dom->loadXML($xmlInvoice);
            $canonicalXml = $dom->C14N(false, false);
            $invoiceHash = base64_encode(hash('sha256', $canonicalXml, true));

            // 5. إرسال الطلب لزاتكا (نفس الدالة السابقة sendToZatcaManual)
            $response = $this->sendToZatcaManual($xmlInvoice, $invoiceHash, $uuid);

            // 6. إذا تمت الموافقة (CLEARED / REPORTED)
            if ($response['http_status'] == 202) {
                
                // تحديث قاعدة البيانات فوراً للفاتورة القادمة
                DB::table('zatca_sequences')->where('id', $sequence->id)->update([
                    'last_vb6_invoice_id' => $nextVbId,
                    'last_icv' => $nextIcv,
                    'last_hash' => $invoiceHash,
                    'updated_at' => now()
                ]);

                // الرد لبرنامج VB6
                return response()->json([
                    'success' => true,
                    'invoice_no' => $nextVbId,
                    'qr_code' => $response['body']['qrSellertStatus'] ?? 'QR_DATA_HERE', 
                    'message' => 'Invoice Accepted'
                ]);
            }

            return response()->json(['success' => false, 'errors' => $response['body']], 400);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}