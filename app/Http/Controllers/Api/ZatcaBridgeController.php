<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use DOMDocument;

class ZatcaBridgeController extends Controller
{
    /**
     * الدالة الرئيسية لاستقبال طلبات VB6 لمتجر بجاد الأناقة
     */
    public function submitFromVb6(Request $request)
    {
        try {
            // 1. جلب بيانات التسلسل من MySQL
            $sequence = DB::table('zatca_sequences')->first();
            if (!$sequence) {
                return response()->json(['success' => false, 'error' => 'جدول التسلسلات فارغ.'], 500);
            }

            // زيادة العدادات آلياً بناءً على الرقم 12525 الذي حددته
            $nextVbId = $sequence->last_vb6_invoice_id + 1; 
            $nextIcv  = $sequence->last_icv + 1;            
            $pih      = $sequence->last_hash;

            // 2. استقبال بيانات VB6
            $vbData = $request->all();
            $uuid = (string) Str::uuid();

            // 3. توليد الـ XML
            $xmlInvoice = $this->generateBaseXML($uuid, $nextVbId, $nextIcv, $pih, $vbData);

            // 4. 🔥 حساب الهاش (Canonicalization)
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;
            $dom->loadXML($xmlInvoice);
            $canonicalXml = $dom->C14N(false, false);
            $invoiceHash = base64_encode(hash('sha256', $canonicalXml, true));

            // 5. الإرسال لزاتكا (Simulation/Production)
            $response = $this->sendToZatcaManual($xmlInvoice, $invoiceHash, $uuid);

            // 6. معالجة الرد (نجاح 202)
            if (isset($response['http_status']) && $response['http_status'] == 202) {
                
                // تحديث قاعدة البيانات فوراً للفاتورة القادمة
                DB::table('zatca_sequences')->where('id', $sequence->id)->update([
                    'last_vb6_invoice_id' => $nextVbId,
                    'last_icv' => $nextIcv,
                    'last_hash' => $invoiceHash,
                    'updated_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'invoice_no' => $nextVbId,
                    'qr_code' => $response['body']['qrCode'] ?? 'QR_SUCCESS_DATA', 
                    'message' => 'Invoice Accepted & Cleared'
                ]);
            }

            return response()->json([
                'success' => false, 
                'errors' => $response['body'] ?? 'خطأ غير معروف من زاتكا'
            ], 400);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e.getMessage()], 500);
        }
    }

    /**
     * دالة توليد الـ XML المتوافق مع UBL 2.1
     */
    private function generateBaseXML($uuid, $invId, $icv, $pih, $data)
    {
        $issueDate = $data['issue_date'] ?? date('Y-m-d');
        $issueTime = date('H:i:s');
        $totalAmount = number_format($data['total_amount'] ?? 0, 2, '.', '');
        $taxAmount = number_format(($data['total_amount'] ?? 0) * 0.15, 2, '.', '');
        $netAmount = number_format($data['net_amount'] ?? 0, 2, '.', '');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>TRX-1.0</cbc:CustomizationID>
    <cbc:ProfileID>reporting:1.0</cbc:ProfileID>
    <cbc:ID>INV-{$invId}</cbc:ID>
    <cbc:UUID>{$uuid}</cbc:UUID>
    <cbc:IssueDate>{$issueDate}</cbc:IssueDate>
    <cbc:IssueTime>{$issueTime}</cbc:IssueTime>
    <cbc:InvoiceTypeCode name="0111110">388</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>SAR</cbc:DocumentCurrencyCode>
    <cbc:TaxCurrencyCode>SAR</cbc:TaxCurrencyCode>
    <cac:AdditionalDocumentReference>
        <cbc:ID>ICV</cbc:ID>
        <cbc:UUID>{$icv}</cbc:UUID>
    </cac:AdditionalDocumentReference>
    <cac:AdditionalDocumentReference>
        <cbc:ID>PIH</cbc:ID>
        <cac:Attachment>
            <cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">{$pih}</cbc:EmbeddedDocumentBinaryObject>
        </cac:Attachment>
    </cac:AdditionalDocumentReference>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyTaxScheme>
                <cbc:RegistrationName>BEJAD AL ANAQA</cbc:RegistrationName>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="SAR">{$totalAmount}</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="SAR">{$totalAmount}</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="SAR">{$netAmount}</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="SAR">{$netAmount}</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
</Invoice>
XML;
    }

    /**
     * دالة التواصل اليدوي مع API زاتكا
     */
    private function sendToZatcaManual($xml, $hash, $uuid)
    {
        // ملاحظة: تأكد من وضع بيانات الـ CSID الصحيحة هنا
        $url = "https://gw-fatoora.zatca.gov.sa/simulation/v2/invoice/compliance";
        
        $payload = [
            'invoiceHash' => $hash,
            'uuid' => $uuid,
            'invoice' => base64_encode($xml)
        ];

        $response = Http::withHeaders([
            'Accept-Language' => 'en',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            // 'Authorization' => 'Basic ' . base64_encode('USERNAME:PASSWORD')
        ])->post($url, $payload);

        return [
            'http_status' => $response->status(),
            'body' => $response->json()
        ];
    }
}
