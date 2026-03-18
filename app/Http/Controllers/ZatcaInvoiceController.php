<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use DOMDocument;
use Saleh7\Zatca\Helpers\Certificate;
use Saleh7\Zatca\InvoiceSigner;

class ZatcaInvoiceController extends Controller
{
   private function generateBaseXML($uuid)
{
    $date = now()->format('Y-m-d');
    $time = now()->format('H:i:s');
    $taxNumber = '300642364100003';

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:ProfileID>reporting:1.0</cbc:ProfileID>
    <cbc:ID>INV-2026-001</cbc:ID>
    <cbc:UUID>{$uuid}</cbc:UUID>
    <cbc:IssueDate>{$date}</cbc:IssueDate>
    <cbc:IssueTime>{$time}</cbc:IssueTime>
    <cbc:InvoiceTypeCode name="0111010">388</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>SAR</cbc:DocumentCurrencyCode>
    <cbc:TaxCurrencyCode>SAR</cbc:TaxCurrencyCode>
    <cac:AdditionalDocumentReference>
        <cbc:ID>ICV</cbc:ID>
        <cbc:UUID>1</cbc:UUID>
    </cac:AdditionalDocumentReference>
    <cac:AdditionalDocumentReference>
        <cbc:ID>PIH</cbc:ID>
        <cac:Attachment>
            <cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">NWZlY2ViOTZmOTY5NzY4NGU3YmQ0ZTYzMzE1MmE5MWZkYTMzMjY2YmU5ZDFlODEwYmU3NjhiMzg1MGExY2I3Mw==</cbc:EmbeddedDocumentBinaryObject>
        </cac:Attachment>
    </cac:AdditionalDocumentReference>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyIdentification><cbc:ID schemeID="CRN">1010000000</cbc:ID></cac:PartyIdentification>
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
            <cac:PostalAddress>
                <cbc:StreetName>King Road</cbc:StreetName>
                <cbc:BuildingNumber>5678</cbc:BuildingNumber>
                <cbc:CitySubdivisionName>Al Malqa</cbc:CitySubdivisionName>
                <cbc:CityName>Riyadh</cbc:CityName>
                <cbc:PostalZone>13521</cbc:PostalZone>
                <cac:Country><cbc:IdentificationCode>SA</cbc:IdentificationCode></cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>
            <cac:PartyLegalEntity><cbc:RegistrationName>Ahmed Customer</cbc:RegistrationName></cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:Delivery><cbc:ActualDeliveryDate>{$date}</cbc:ActualDeliveryDate></cac:Delivery>
    <cac:PaymentMeans><cbc:PaymentMeansCode>10</cbc:PaymentMeansCode></cac:PaymentMeans>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="SAR">15.00</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="SAR">100.00</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="SAR">15.00</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>15.00</cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="SAR">100.00</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="SAR">100.00</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="SAR">115.00</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="SAR">115.00</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    <cac:InvoiceLine>
        <cbc:ID>1</cbc:ID>
        <cbc:InvoicedQuantity unitCode="PCE">1</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="SAR">100.00</cbc:LineExtensionAmount>
        <cac:TaxTotal>
            <cbc:TaxAmount currencyID="SAR">15.00</cbc:TaxAmount>
            <cbc:RoundingAmount currencyID="SAR">115.00</cbc:RoundingAmount>
        </cac:TaxTotal>
        <cac:Item>
            <cbc:Name>ثوب بجاد فاخر</cbc:Name>
            <cac:ClassifiedTaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>15.00</cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price><cbc:PriceAmount currencyID="SAR">100.00</cbc:PriceAmount></cac:Price>
        <cac:ItemPriceExtension>
    <cbc:Amount currencyID="SAR">115.00</cbc:Amount>
</cac:ItemPriceExtension>
    </cac:InvoiceLine>
</Invoice>
XML;
}

 public function sendTestInvoice()
{
    try {
        $authPath = storage_path('app/zatca/auth_data.json');
        $keyPath  = storage_path('app/private/zatca/private_key.pem'); 
        $authData = json_decode(file_get_contents($authPath), true);
        
        $privateKeyRaw = file_get_contents($keyPath);
        $cleanKey = str_replace(["-----BEGIN PRIVATE KEY-----", "-----END PRIVATE KEY-----", "\r", "\n", " "], '', $privateKeyRaw);
        $formattedKey = "-----BEGIN PRIVATE KEY-----\n" . chunk_split($cleanKey, 64, "\n") . "-----END PRIVATE KEY-----";

        $pkeyResource = openssl_pkey_get_private($formattedKey);

        // 1. توليد الـ XML
        $uuid = \Illuminate\Support\Str::uuid()->toString();
        $xmlInvoice = $this->generateBaseXML($uuid); 

        // 2. 🔥 السر الحقيقي هنا: Canonicalization
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xmlInvoice);

        // تحويل الـ XML للصيغة القياسية (إزالة المسافات الزائدة تماماً)
        // زاتكا تطلب C14N بدون تعليقات
        $canonicalXml = $dom->C14N(false, false); 
        
        // حساب الهاش من النسخة "المطهرة"
        $invoiceHash = base64_encode(hash('sha256', $canonicalXml, true));

        // 3. التوقيع الرقمي (اختياري للإرسال ولكن مهم للأرشفة)
        $signature = '';
        openssl_sign($invoiceHash, $signature, $pkeyResource, OPENSSL_ALGO_SHA256);

        // 4. إعداد الـ Payload
         $canonicalXml = $dom->C14N(false, false);

$invoiceHash = base64_encode(hash('sha256', $canonicalXml, true));

$payload = [
    'invoiceHash' => $invoiceHash,
    'uuid'        => $uuid,
    'invoice'     => base64_encode($canonicalXml), // 🔥 هذا هو الحل
];

        // 5. الإرسال
        $response = $this->sendToZatcaManual($payload, $authData);
        
        return response()->json($response);

    } catch (\Exception $e) {
        return response()->json(['status' => 'Error ⚠️', 'message' => $e->getMessage()], 500);
    }
}
    private function sendToZatcaManual($payload, $authData)
    {
        $client = new Client(['verify' => false, 'http_errors' => false]);

        $url = "https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance/invoices";

        $token  = trim(str_replace(["\r", "\n"], '', $authData['binarySecurityToken'] ?? ''));
        $secret = trim(str_replace(["\r", "\n"], '', $authData['secret'] ?? ''));

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization'   => 'Basic ' . base64_encode($token . ':' . $secret),
                    'Accept-Language' => 'ar',
                    'Accept-Version'  => 'V2',
                    'Content-Type'    => 'application/json',
                ],
                'json' => [
                    'invoiceHash' => $payload['invoiceHash'],
                    'uuid'        => $payload['uuid'],
                    'invoice'     => $payload['invoice'],
                ]
            ]);

            $body = json_decode($response->getBody()->getContents(), true) ?? [];

            \Log::info('ZATCA Compliance Response', [
                'status' => $response->getStatusCode(),
                'body'   => $body
            ]);

            return [
                'http_status' => $response->getStatusCode(),
                'body'        => $body,
                'is_success'  => $response->getStatusCode() === 200
            ];
        } catch (\Exception $e) {
            \Log::error('ZATCA Send Error: ' . $e->getMessage());
            return [
                'http_status' => 500,
                'body'        => ['error' => $e->getMessage()],
                'is_success'  => false
            ];
        }
    }
}