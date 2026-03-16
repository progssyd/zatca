<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Saleh7\Zatca\CertificateBuilder;
use Saleh7\Zatca\InvoiceSigner;
use Saleh7\Zatca\Helpers\Certificate;
use App\Services\ZatcaService;
class ZatcaOnboardingController extends Controller
{
    use App\Services\ZatcaService;

public function completeOrder($id, ZatcaService $zatcaService)
{
    // ... منطق إكمال الطلب في متجرك ...

    try {
        // سطر واحد فقط يقوم بكل المهمة!
        $zatcaInvoice = $zatcaService->sendOrder($id);

        return response()->json([
            'message' => 'تم تأكيد الطلب وإرسال الفاتورة لزاتكا',
            'qr_code' => $zatcaInvoice->qr_code
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
    public function showSuccessStatus()
    {
        try {
            $zatcaPath = storage_path('app/zatca');
            $authFile = $zatcaPath . '/auth_data.json';

            if (!File::exists($authFile)) {
                return "<div style='font-family: Arial; text-align: center; padding: 50px; direction: rtl;'>
                            <h2 style='color: #dc3545;'>⚠️ لم يتم الربط بعد</h2>
                            <p>يرجى تشغيل الرابط (final-step) مع OTP جديد أولاً.</p>
                        </div>";
            }

            $authData = json_decode(File::get($authFile), true);
            
            return "
                <div style='font-family: Arial; text-align: center; padding: 50px; direction: rtl;'>
                    <h1 style='color: #28a745;'>✅ تم الربط بنجاح لمتجر بجاد الأناقة</h1>
                    <p>النظام متصل الآن بسيرفرات الهيئة (Simulation Mode).</p>
                    <hr style='width: 50%; margin: 20px auto;'>
                    <div style='background: #f8f9fa; display: inline-block; padding: 20px; border-radius: 10px; border: 1px solid #ddd;'>
                        <p><b>رقم طلب الربط (Request ID):</b> " . ($authData['requestID'] ?? 'N/A') . "</p>
                        <p><b>تاريخ الربط:</b> " . ($authData['onboarded_at'] ?? date('Y-m-d')) . "</p>
                        <p style='color: #28a745;'><b>حالة الشهادة:</b> Active</p>
                    </div>
                    <br><br>
                    <button onclick='window.print()' style='padding: 10px 20px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px;'>طباعة تقرير الربط</button>
                </div>
            ";
        } catch (\Exception $e) {
            return "خطأ في العرض: " . $e->getMessage();
        }
    }

  
private function getRealInvoiceXML($uuid, $invoiceId)
{
    $baseAmount = 100.00;
    $taxAmount = 15.00;
    $totalAmount = 115.00;

    $invoiceData = [
        'uuid' => $uuid,
        'id' => $invoiceId,
        'issueDate' => date('Y-m-d'),
        'issueTime' => date('H:i:s'),
        'currencyCode' => 'SAR',
        'taxCurrencyCode' => 'SAR',
        'invoiceType' => [
            'invoice' => 'simplified',
            'type' => 'invoice',
        ],
        // حقول العداد والهاش السابق (لحل أخطاء KSA-13 و KSA-16)
        'additionalDocuments' => [
            [
                'id' => 'ICV',
                'uuid' => '2', // رقم الفاتورة التسلسلي في النظام
            ],
            [
                'id' => 'PIH',
                'attachment' => [
                    'content' => 'NWZlY2ViOTZmY2ZlYmU5ZTRjY2Q1NjljYjc3YmU5ZTRjY2Q1NjljYjc3', // الهاش الافتراضي لأول فاتورة
                ],
            ],
        ],
        'supplier' => [
            'registrationName' => 'Bejad Al Anaqa Mens Fashion',
            'taxId' => '300642364100003',
            'identificationId' => '1010000000', 
            'identificationType' => 'CRN',
            'address' => [
                'street' => 'Abdulmaqsood Khoja',
                'buildingNumber' => '7012',
                'subdivision' => 'Ar Rawdah Dist',
                'city' => 'Jeddah',
                'postalZone' => '23435',
                'country' => 'SA',
            ],
        ],
        'customer' => [
            'registrationName' => 'Cash Customer',
        ],
        'paymentMeans' => [
            'code' => '10', 
        ],
        'taxTotal' => [
            'taxAmount' => $taxAmount,
            'subTotals' => [
                [
                    'taxableAmount' => $baseAmount,
                    'taxAmount' => $taxAmount,
                    'taxCategory' => [
                        'percent' => 15,
                        'taxScheme' => ['id' => 'VAT'],
                    ],
                ],
            ],
        ],
        'legalMonetaryTotal' => [
            'lineExtensionAmount' => $baseAmount,
            'taxExclusiveAmount' => $baseAmount,
            'taxInclusiveAmount' => $totalAmount,
            'payableAmount' => $totalAmount,
        ],
        'invoiceLines' => [
            [
                'id' => 1,
                'quantity' => 1,
                'unitCode' => 'PCE',
                'lineExtensionAmount' => $baseAmount,
                'item' => [
                    'name' => 'Men Thobe - Custom Made',
                    'classifiedTaxCategory' => [
                        [
                            'percent' => 15.0,
                            'taxScheme' => ['id' => 'VAT'],
                        ],
                    ],
                ],
                'price' => [
                    'amount' => $baseAmount,
                ],
                'taxTotal' => [
                    'taxAmount' => $taxAmount,
                    'roundingAmount' => $totalAmount,
                ],
            ],
        ],
    ];

    $invoiceMapper = new \Saleh7\Zatca\Mappers\InvoiceMapper;
    return $invoiceMapper->mapToInvoice($invoiceData, true, true);
}
private function getTestInvoiceXML($uuid, $invoiceNumber)
{
    $issueDate = date('Y-m-d');
    $issueTime = date('H:i:s');

    return '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
    <ext:UBLExtensions>
        <ext:UBLExtension>
            <ext:ExtensionURI>urn:oasis:names:specification:ubl:dsig:enveloped:xades</ext:ExtensionURI>
            <ext:ExtensionContent></ext:ExtensionContent>
        </ext:UBLExtension>
    </ext:UBLExtensions>
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>TRD-1</cbc:CustomizationID>
    <cbc:ProfileID>reporting:1.0</cbc:ProfileID>
    <cbc:ID>' . $invoiceNumber . '</cbc:ID>
    <cbc:UUID>' . $uuid . '</cbc:UUID>
    <cbc:IssueDate>' . $issueDate . '</cbc:IssueDate>
    <cbc:IssueTime>' . $issueTime . '</cbc:IssueTime>
    <cbc:InvoiceTypeCode name="0100000">388</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>SAR</cbc:DocumentCurrencyCode>
    <cbc:TaxCurrencyCode>SAR</cbc:TaxCurrencyCode>
    <cac:AdditionalDocumentReference><cbc:ID>ICV</cbc:ID><cbc:UUID>1</cbc:UUID></cac:AdditionalDocumentReference>
    <cac:AdditionalDocumentReference><cbc:ID>PIH</cbc:ID><cac:Attachment><cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain" encodingCode="Base64" filename="invoice.xml">MA==</cbc:EmbeddedDocumentBinaryObject></cac:Attachment></cac:AdditionalDocumentReference>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyLegalEntity><cbc:RegistrationName>Bejad Al Anaqa Mens Fashion</cbc:RegistrationName><cbc:CompanyID schemeID="CRN">300642364100003</cbc:CompanyID></cac:PartyLegalEntity>
            <cac:PartyTaxScheme><cbc:CompanyID>300642364100003</cbc:CompanyID><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty><cac:Party><cac:PartyLegalEntity><cbc:RegistrationName>Cash Customer</cbc:RegistrationName></cac:PartyLegalEntity></cac:Party></cac:AccountingCustomerParty>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="SAR">15.00</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="SAR">100.00</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="SAR">15.00</cbc:TaxAmount>
            <cac:TaxCategory><cbc:Percent>15.00</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:TaxCategory>
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
        <cac:Item><cbc:Name>Men Thobe - Test</cbc:Name><cac:ClassifiedTaxCategory><cbc:Percent>15.00</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:ClassifiedTaxCategory></cac:Item>
        <cac:Price><cbc:PriceAmount currencyID="SAR">100.00</cbc:PriceAmount></cac:Price>
    </cac:InvoiceLine>
</Invoice>';
}

    /**
     * العملية النهائية - باستخدام XML مصحح
     */

  // شغّل هذه الدالة بعد النجاح (يمكنك عمل route جديد أو زر في الواجهة)
    public function reportFirstInvoice()
{
    try {
        $path = storage_path('app/zatca');
        $authData = json_decode(File::get($path . '/auth_data.json'), true);
        $privateKey = File::get($path . '/private.pem');

        // 1. معالجة التوكن (Double Decode)
      $originalToken = $authData['binarySecurityToken'];
$step1 = base64_decode($originalToken, true);
$derBinary = base64_decode($step1, true) ?: $step1;
$cleanBase64Cert = base64_encode($derBinary);  // ← هذا هو الشكل الصحيح: MIICMjCC... بدون أي شيء إضافي

// 2. تنظيف المفتاح الخاص (هذا الجزء صحيح)
$cleanKey = preg_replace('/[\r\n\s-]|BEGIN|END|PRIVATE|KEY|EC/', '', $privateKey);

// 3. تمرير base64 النقي مباشرة للـ helper
$certificateHelper = new \Saleh7\Zatca\Helpers\Certificate(
    $cleanBase64Cert,           // ← التغيير الحاسم هنا
    $cleanKey,
    $authData['secret']
);
        // باقي الكود كما هو...
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $invoiceObject = $this->getRealInvoiceXML($uuid, 'BJ-INV-' . time());
        $generator = \Saleh7\Zatca\GeneratorInvoice::invoice($invoiceObject);
        $invoiceXML = $generator->getXML();

        $signedInvoice = \Saleh7\Zatca\InvoiceSigner::signInvoice($invoiceXML, $certificateHelper);

        $client = new \GuzzleHttp\Client(['verify' => false]);
        $response = $client->post('https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance/invoices', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($originalToken . ':' . $authData['secret']),
                'Accept-Version' => 'V2',
                'Accept-Language' => 'ar',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'invoiceHash' => $signedInvoice->getHash(),
                'uuid' => $uuid,
                'invoice' => base64_encode($signedInvoice->getXML()),
            ],
        ]);

        $result = json_decode($response->getBody()->getContents(), true);
        
        return response()->json([
            'status' => 'success',
             'qr_code' => $qrCodeBase64, 
            'message' => '🚀 مبروك يا سعد! تم الإرسال بنجاح',
            'zatca_response' => $result
        ]);

    } catch (\Exception $e) {
        $errorMsg = $e instanceof \GuzzleHttp\Exception\ClientException
                    ? $e->getResponse()->getBody()->getContents()
                    : $e->getMessage();

        return response()->json([
            'status' => 'error',
            'details' => json_decode($errorMsg, true) ?: $errorMsg
        ], 500);
    }

}
     public function finalStepBejad(Request $request)
{
    $otp = trim($request->otp ?? '');
    if (!$otp) {
        return response()->json(['error' => 'OTP مطلوب'], 400);
    }

    $path = storage_path('app/zatca');
    $privateKeyPath = $path . '/private.pem';
    $csrPath        = $path . '/certificate.csr';
    $authFile       = $path . '/auth_data.json';

    // 1. التأكد من وجود ملفات الهوية
    if (!File::exists($csrPath) || !File::exists($privateKeyPath)) {
        return response()->json(['error' => 'ملفات CSR أو Private Key غير موجودة. شغّل setup-identity أولاً.'], 400);
    }

    // 2. إرسال الطلب للهيئة (Simulation)
    $client = new \GuzzleHttp\Client(['verify' => false]);
    try {
        $response = $client->post('https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance', [
            'headers' => [
                'Accept-Version' => 'V2',
                'OTP' => $otp,
            ],
            'json' => [
                'csr' => base64_encode(File::get($csrPath))
            ],
        ]);

        $auth = json_decode($response->getBody()->getContents(), true);
    } catch (\Exception $e) {
        $msg = $e instanceof \GuzzleHttp\Exception\ClientException 
            ? $e->getResponse()->getBody()->getContents() 
            : $e->getMessage();
        return response()->json(['error' => 'خطأ من هيئة الزكاة', 'message' => $msg], 400);
    }

    // 3. معالجة الشهادة والسر
    $token = $auth['binarySecurityToken'];
    $secret = $auth['secret'];

    // محاولة فك التشفير (single أو double decode)
    $decoded = base64_decode($token, true);
    if ($decoded === false) {
        return response()->json(['error' => 'فشل base64_decode الأول لـ binarySecurityToken'], 500);
    }

    // جرب double decode (شائع في Simulation)
    $der = base64_decode($decoded, true);
    if ($der === false) {
        // fallback: استخدم الـ decoded الأول مباشرة
        $der = $decoded;
    }

    // بناء PEM من الـ DER / decoded
    $pemCert = "-----BEGIN CERTIFICATE-----\n" .
               chunk_split(base64_encode($der), 64, "\n") .
               "-----END CERTIFICATE-----\n";

    $tempCertPath = storage_path('app/zatca/last_cert.pem');
    File::put($tempCertPath, $pemCert);

    // تنظيف المفتاح الخاص بشكل أكثر أمانًا وتوافقًا
    $privateKeyContent = File::get($privateKeyPath);
    $cleanPrivateKey = trim(
        preg_replace(
            '/\s+|\r|\n|-----BEGIN.*?PRIVATE KEY-----|-----END.*?PRIVATE KEY-----/i',
            '',
            $privateKeyContent
        )
    );

    // اختبار الشهادة
    $certContent = File::get($tempCertPath);
    $resource = @openssl_x509_read($certContent);

    if ($resource === false) {
        $openSslError = openssl_error_string() ?: 'غير معروف';

        // fallback إضافي: إذا كان decoded يحتوي على BEGIN بالفعل
        if (strpos($decoded, '-----BEGIN CERTIFICATE-----') !== false) {
            $pemCert = $decoded;
            File::put($tempCertPath, $pemCert);
            $certContent = $pemCert;
            $resource = @openssl_x509_read($certContent);
        }

        if ($resource === false) {
            return response()->json([
                'error' => 'فشل قراءة الشهادة بعد المعالجة',
                'openssl_error' => $openSslError,
                'cert_preview' => substr($certContent, 0, 300) . (strlen($certContent) > 300 ? '...' : ''),
                'token_length' => strlen($token),
                'decoded_length' => strlen($decoded),
                'der_length' => strlen($der),
                'hint' => 'أرسل cert_preview لمعرفة التنسيق الدقيق (PEM أم DER أم base64 خام)',
            ], 500);
        }
    }

    // إنشاء كائن الشهادة
    try {
        $certificateHelper = new \Saleh7\Zatca\Helpers\Certificate(
            $certContent,
            $cleanPrivateKey,
            $secret
        );

        // توليد فاتورة اختبار وتوقيعها
        $uuid = (string) Str::uuid();
        $invoiceXML = $this->getTestInvoiceXML($uuid, 'BJ-TEST-' . time());
        $signedInvoice = \Saleh7\Zatca\InvoiceSigner::signInvoice($invoiceXML, $certificateHelper);

        // حفظ بيانات الربط النهائية
        $auth['onboarded_at'] = date('Y-m-d H:i:s');
        File::put($authFile, json_encode($auth, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json([
            'status' => 'success',
            'message' => '✅ مبروك يا سعد! تم الربط والتوقيع بنجاح لمتجر بجاد الأناقة',
            'request_id' => $auth['requestID'] ?? 'غير متوفر',
            'note' => 'يمكنك الآن تجربة إرسال الفواتير عبر reportFirstInvoice'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'فشل في مرحلة التوقيع أو الحفظ',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString() // للـ debug فقط - احذفه لاحقًا
        ], 500);
    }
}
    public function simpleOnboarding(Request $request)
    {
        try {
            $otp = $request->otp;
            if (!$otp) {
                return response()->json(['error' => 'OTP مطلوب'], 400);
            }

            $path = storage_path('app/zatca');
            
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }

            $privateKeyPath = $path . '/private.pem';
            $csrPath = $path . '/certificate.csr';

            // توليد الشهادة
            (new CertificateBuilder())
                ->setOrganizationIdentifier('300642364100003')
                ->setSerialNumber('BEJAD', 'POS-01', 'CSR-' . time())
                ->setCommonName('Bejad Al Anaqa Mens Fashion')
                ->setCountryName('SA')
                ->setOrganizationName('Bejad Al Anaqa Mens Fashion')
                ->setOrganizationalUnitName('Main Branch')
                ->setAddress('Prince Sultan St, Jeddah')
                ->setInvoiceType(1100)
                ->setProduction(false)
                ->setBusinessCategory('Retail')
                ->generateAndSave($csrPath, $privateKeyPath);

            // طلب التوكن
            $client = new Client(['verify' => false]);
            $response = $client->post('https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance', [
                'headers' => [
                    'Accept-Version' => 'V2',
                    'OTP' => trim($otp)
                ],
                'json' => ['csr' => base64_encode(File::get($csrPath))]
            ]);

            $auth = json_decode($response->getBody()->getContents(), true);
            $auth['onboarded_at'] = date('Y-m-d H:i:s');
            
            File::put($path . '/auth_data.json', json_encode($auth, JSON_PRETTY_PRINT));

            return response()->json([
                'status' => 'success',
                'message' => '✅ تم الربط بنجاح مع الهيئة',
                'data' => [
                    'request_id' => $auth['requestID'] ?? null,
                    'onboarded_at' => $auth['onboarded_at'],
                ]
            ]);

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            if ($e instanceof \GuzzleHttp\Exception\ClientException) {
                $errorMessage = $e->getResponse()->getBody()->getContents();
            }
            
            return response()->json([
                'error' => 'فشل عملية الربط',
                'message' => $errorMessage
            ], 500);
        }
    }
}