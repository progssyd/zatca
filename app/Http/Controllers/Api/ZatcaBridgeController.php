<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use Illuminate\Support\Facades\Log;

class ZatcaBridgeController extends Controller
{
    /**
     * الدالة الرئيسية لاستقبال طلبات VB6
     */
    public function submitFromVb6(Request $request)
    {
        try {
            // جلب التسلسل من الداتابيز
            $sequence = DB::table('zatca_sequences')->first();
            if (!$sequence) {
                return response()->json(['success' => false, 'error' => 'جدول التسلسلات فارغ.'], 500);
            }

            $nextVbId = $sequence->last_vb6_invoice_id + 1;
            $nextIcv  = $sequence->last_icv + 1;
            $pih      = $sequence->last_hash ?? '';  // PIH فارغ في البداية

            // بيانات VB6 (تأكد أن VB6 يرسل الحقول دي)
            $vbData = $request->validate([
                'issue_date'    => 'nullable|date',
                'total_amount'  => 'required|numeric|min:0',
                'net_amount'    => 'nullable|numeric',
                'customer_name' => 'nullable|string',  // مثال: اسم العميل
                'item_name'     => 'nullable|string',  // اسم الصنف
                'quantity'      => 'nullable|numeric',
                'unit_price'    => 'nullable|numeric',
                // أضف حقول أخرى حسب حاجتك
            ]);

            $uuid = (string) Str::uuid();

            // توليد XML محسن
            $xmlInvoice = $this->generateEnhancedXML($uuid, $nextVbId, $nextIcv, $pih, $vbData);

            // Canonicalization + Hash
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;
            $dom->loadXML($xmlInvoice);
            $canonicalXml = $dom->C14N(false, false);
            $invoiceHash = base64_encode(hash('sha256', $canonicalXml, true));

            // إرسال إلى ZATCA
            $response = $this->sendToZatcaManual($canonicalXml, $invoiceHash, $uuid);

            if (isset($response['http_status']) && $response['http_status'] === 202) {
                // نجاح → تحديث التسلسل
                DB::table('zatca_sequences')->where('id', $sequence->id)->update([
                    'last_vb6_invoice_id' => $nextVbId,
                    'last_icv'            => $nextIcv,
                    'last_hash'           => $invoiceHash,
                    'updated_at'          => now(),
                ]);

                return response()->json([
                    'success'    => true,
                    'invoice_no' => $nextVbId,
                    'qr_code'    => $response['body']['qrCode'] ?? 'QR_SUCCESS_DATA',
                    'message'    => 'Invoice Accepted & Cleared',
                    'zatca_response' => $response['body'],
                ]);
            }

            return response()->json([
                'success' => false,
                'errors'  => $response['body'] ?? 'خطأ غير معروف من زاتكا',
                'status'  => $response['http_status'] ?? null,
            ], $response['http_status'] ?? 400);

        } catch (\Exception $e) {
            Log::error('Zatca Submit Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * XML محسن مع بيانات ديناميكية (مستوحى من الكود الجديد)
     */
    private function generateEnhancedXML($uuid, $invId, $icv, $pih, $data)
    {
        $issueDate   = $data['issue_date'] ?? now()->format('Y-m-d');
        $issueTime   = now()->format('H:i:s');
        $totalAmount = number_format($data['total_amount'] ?? 0, 2, '.', '');
        $taxAmount   = number_format(($data['total_amount'] ?? 0) * 0.15, 2, '.', '');
        $netAmount   = number_format($data['net_amount'] ?? $data['total_amount'] ?? 0, 2, '.', '');
        $quantity    = $data['quantity'] ?? 1;
        $unitPrice   = $data['unit_price'] ?? $data['total_amount'] ?? 100;
        $itemName    = $data['item_name'] ?? 'ثوب بجاد فاخر';
        $customerName = $data['customer_name'] ?? 'عميل افتراضي';

        $taxNumber = '300642364100003';  // غيّره إلى رقم ضريبي حقيقي

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" 
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" 
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
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
            <cac:PartyIdentification>
                <cbc:ID schemeID="CRN">1010000000</cbc:ID>
            </cac:PartyIdentification>
            <cac:PostalAddress>
                <cbc:StreetName>Prince Sultan St</cbc:StreetName>
                <cbc:BuildingNumber>1234</cbc:BuildingNumber>
                <cbc:CitySubdivisionName>Al Rawdah</cbc:CitySubdivisionName>
                <cbc:CityName>Jeddah</cbc:CityName>
                <cbc:PostalZone>23431</cbc:PostalZone>
                <cac:Country><cbc:IdentificationCode>SA</cbc:IdentificationCode></cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>{$taxNumber}</cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:PartyLegalEntity><cbc:RegistrationName>Bejad Al Anaqa</cbc:RegistrationName></cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingSupplierParty>

    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyLegalEntity><cbc:RegistrationName>{$customerName}</cbc:RegistrationName></cac:PartyLegalEntity>
            <cac:PartyTaxScheme><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingCustomerParty>

    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="SAR">{$taxAmount}</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="SAR">{$totalAmount}</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="SAR">{$taxAmount}</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>15.00</cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>

    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="SAR">{$totalAmount}</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="SAR">{$totalAmount}</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="SAR">{$netAmount}</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="SAR">{$netAmount}</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>

    <cac:InvoiceLine>
        <cbc:ID>1</cbc:ID>
        <cbc:InvoicedQuantity unitCode="PCE">{$quantity}</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="SAR">{$totalAmount}</cbc:LineExtensionAmount>
        <cac:TaxTotal>
            <cbc:TaxAmount currencyID="SAR">{$taxAmount}</cbc:TaxAmount>
        </cac:TaxTotal>
        <cac:Item>
            <cbc:Name>{$itemName}</cbc:Name>
            <cac:ClassifiedTaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>15.00</cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price><cbc:PriceAmount currencyID="SAR">{$unitPrice}</cbc:PriceAmount></cac:Price>
    </cac:InvoiceLine>
</Invoice>
XML;
    }

    /**
     * إرسال إلى ZATCA مع Authorization
     */
    private function sendToZatcaManual($canonicalXml, $hash, $uuid)
    {
        $url = "https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance/invoices";

        // جلب CSID و Secret (من ملف أو env – عدل حسب طريقتك)
        $authPath = storage_path('app/zatca/auth_data.json');
        $authData = file_exists($authPath) ? json_decode(file_get_contents($authPath), true) : [];
        $token  = $authData['binarySecurityToken'] ?? env('ZATCA_TOKEN', '');
        $secret = $authData['secret'] ?? env('ZATCA_SECRET', '');

        $payload = [
            'invoiceHash' => $hash,
            'uuid'        => $uuid,
            'invoice'     => base64_encode($canonicalXml),
        ];

        try {
            $response = Http::withOptions([
                'verify' => false,  // للاختبار فقط – أزل في الإنتاج
            ])->withHeaders([
                'Authorization'   => 'Basic ' . base64_encode($token . ':' . $secret),
                'Accept-Language' => 'ar',
                'Accept-Version'  => 'V2',
                'Accept'          => 'application/json',
                'Content-Type'    => 'application/json',
            ])->post($url, $payload);

            Log::info('ZATCA Compliance Response', [
                'status'  => $response->status(),
                'body'    => $response->body(),
                'payload' => $payload,
            ]);

            return [
                'http_status' => $response->status(),
                'body'        => $response->json() ?? json_decode($response->body(), true),
            ];
        } catch (\Exception $e) {
            Log::error('ZATCA Send Exception: ' . $e->getMessage());
            return [
                'http_status' => 500,
                'body'        => ['error' => $e->getMessage()],
            ];
        }
    }
}
