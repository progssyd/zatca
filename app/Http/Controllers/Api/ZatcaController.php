<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Saleh7\Zatca\{
    Address, AdditionalDocumentReference, ClassifiedTaxCategory, Invoice,
    InvoiceLine, InvoiceType, Item, LegalEntity, LegalMonetaryTotal,
    Party, PartyTaxScheme, Price, TaxCategory, TaxScheme, TaxSubTotal,
    TaxTotal, Attachment, GeneratorInvoice, InvoiceSigner
};
use Saleh7\Zatca\Helpers\Certificate;
use Saleh7\Zatca\Mappers\InvoiceMapper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ZatcaController extends Controller
{
//    public function onboardAndReport(Request $request)
// {
//     try {
//         // 1. تأكد يدويًا من وجود مجلد الحفظ في لارافل لتجنب خطأ mkdir
//         $zatcaPath = storage_path('app/zatca');
//         if (!\Illuminate\Support\Facades\File::exists($zatcaPath)) {
//             \Illuminate\Support\Facades\File::makeDirectory($zatcaPath, 0755, true);
//         }

//         // 2. الهيكل الدقيق لمصفوفة البيانات (تم تعديل أماكن الضريبة)
//         $invoiceData = [
//             'uuid' => (string) \Illuminate\Support\Str::uuid(),
//             'id' => 'INV-BEJAD-' . time(),
//             'issueDate' => date('Y-m-d'),
//             'issueTime' => date('H:i:s'),
//             'delivery' => ['actualDeliveryDate' => date('Y-m-d')],
//             'currencyCode' => 'SAR',
//             'taxCurrencyCode' => 'SAR',
//             'languageID' => 'ar',
//             'invoiceType' => [
//                 'invoice' => 'simplified',
//                 'type' => 'invoice',
//             ],
//             'additionalDocuments' => [
//                 ['id' => 'ICV', 'uuid' => '1'],
//                 ['id' => 'PIH', 'attachment' => ['content' => 'MA==']],
//                 ['id' => 'QR'],
//             ],
//             'supplier' => [
//                 'registrationName' => 'Bejad Al Anaqa Mens Fashion',
//                 'taxId' => '300642364100003',
//                 'identificationId' => '300642364100003',
//                 'identificationType' => 'CRN',
//                 'address' => [
//                     'street' => 'Prince Sultan Street',
//                     'buildingNumber' => '1234',
//                     'subdivision' => 'Ar Rawdah',
//                     'city' => 'Jeddah',
//                     'postalZone' => '21577',
//                     'country' => 'SA',
//                 ],
//             ],
//             'customer' => [
//                 'registrationName' => 'Cash Customer',
//                 'identificationId' => '333333333333333',
//                 'identificationType' => 'NAT',
//                 'address' => [
//                     'street' => 'Jeddah Street', 'buildingNumber' => '0000', 'subdivision' => 'Default',
//                     'city' => 'Jeddah', 'postalZone' => '00000', 'country' => 'SA',
//                 ],
//             ],
//             // القيمة الحاسمة للضريبة (مستوى الفاتورة العام)
//             'taxTotal' => [
//                 'taxAmount' => 15.00,
//                 'subTotals' => [
//                     [
//                         'taxableAmount' => 100.00,
//                         'taxAmount' => 15.00,
//                         'taxCategory' => [
//                             'percent' => 15.00,
//                             'taxScheme' => ['id' => 'VAT'],
//                         ],
//                     ],
//                 ],
//             ],
//             'legalMonetaryTotal' => [
//                 'lineExtensionAmount' => 100.00,
//                 'taxExclusiveAmount' => 100.00,
//                 'taxInclusiveAmount' => 115.00,
//                 'payableAmount' => 115.00,
//                 'allowanceTotalAmount' => 0.0,
//             ],
//             'invoiceLines' => [
//                 [
//                     'id' => 1,
//                     'unitCode' => 'PCE',
//                     'quantity' => 1,
//                     'lineExtensionAmount' => 100.00,
//                     'item' => [
//                         'name' => 'ثوب رجالي مطرز - بجاد',
//                         'classifiedTaxCategory' => [
//                             ['percent' => 15.00, 'taxScheme' => ['id' => 'VAT']],
//                         ],
//                     ],
//                     'price' => ['amount' => 100.00, 'unitCode' => 'UNIT'],
//                     'taxTotal' => [
//                         'taxAmount' => 15.00,
//                         'roundingAmount' => 115.00,
//                     ],
//                 ],
//             ],
//         ];

//         // 3. التنفيذ
//         $invoiceMapper = new \Saleh7\Zatca\Mappers\InvoiceMapper;
//         $invoice = $invoiceMapper->mapToInvoice($invoiceData, true, true);
//         $generatorInvoice = \Saleh7\Zatca\GeneratorInvoice::invoice($invoice);

//         // 4. معالجة الشهادة
//         $token = env('ZATCA_TOKEN');
//         $formattedCert = "-----BEGIN CERTIFICATE-----\n" . chunk_split(preg_replace('/[^A-Za-z0-9+\/=]/', '', trim($token)), 64, "\n") . "-----END CERTIFICATE-----";
//         $privateKey = \Illuminate\Support\Facades\File::get(storage_path('keys/private.key'));
        
//         $certificate = new \Saleh7\Zatca\Helpers\Certificate($formattedCert, $privateKey, env('ZATCA_SECRET'));
        
//         // 5. التوقيع
//         $signedInvoice = \Saleh7\Zatca\InvoiceSigner::signInvoice($generatorInvoice->getXML(), $certificate);

//         // 6. الحفظ (يدويًا لتجنب أخطاء المكتبة)
//         \Illuminate\Support\Facades\File::put($zatcaPath . '/Bejad_Final.xml', $signedInvoice->getXML());
          
//         return response()->json([
//             'status' => 'success',
//             'hash' => $signedInvoice->getHash(),
//             'qr' => $signedInvoice->getQRCode()
//         ]);

//     } catch (\Throwable $e) {
//         return response()->json([
//             'status' => 'error',
//             'message' => $e->getMessage(),
//             'line' => $e->getLine()
//         ], 500);
//     }

    
// }

public function onboardAndReport(Request $request)
{
    try {
        // 1. التأكد من مجلد الحفظ
        $zatcaPath = storage_path('app/zatca');
        if (!\Illuminate\Support\Facades\File::exists($zatcaPath)) {
            \Illuminate\Support\Facades\File::makeDirectory($zatcaPath, 0755, true);
        }

        // 2. مصفوفة البيانات (بجاد الأناقة)
        $invoiceData = [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'id' => 'INV-BEJAD-' . time(),
            'issueDate' => date('Y-m-d'),
            'issueTime' => date('H:i:s'),
            'delivery' => ['actualDeliveryDate' => date('Y-m-d')],
            'currencyCode' => 'SAR',
            'taxCurrencyCode' => 'SAR',
            'languageID' => 'ar',
            'invoiceType' => [
                'invoice' => 'simplified',
                'type' => 'invoice',
            ],
            'additionalDocuments' => [
                ['id' => 'ICV', 'uuid' => '1'],
                ['id' => 'PIH', 'attachment' => ['content' => 'MA==']],
                ['id' => 'QR'],
            ],
            'supplier' => [
                'registrationName' => 'Bejad Al Anaqa Mens Fashion',
                'taxId' => '300642364100003',
                'identificationId' => '300642364100003',
                'identificationType' => 'CRN',
                'address' => [
                    'street' => 'Prince Sultan Street', 'buildingNumber' => '1234', 'subdivision' => 'Ar Rawdah',
                    'city' => 'Jeddah', 'postalZone' => '21577', 'country' => 'SA',
                ],
            ],
            'customer' => [
                'registrationName' => 'Cash Customer',
                'identificationId' => '333333333333333',
                'identificationType' => 'NAT',
                'address' => [
                    'street' => 'Jeddah Street', 'buildingNumber' => '0000', 'subdivision' => 'Default',
                    'city' => 'Jeddah', 'postalZone' => '00000', 'country' => 'SA',
                ],
            ],
            'taxTotal' => [
                'taxAmount' => 15.00,
                'subTotals' => [
                    [
                        'taxableAmount' => 100.00,
                        'taxAmount' => 15.00,
                        'taxCategory' => [
                            'percent' => 15.00,
                            'taxScheme' => ['id' => 'VAT'],
                        ],
                    ],
                ],
            ],
            'legalMonetaryTotal' => [
                'lineExtensionAmount' => 100.00,
                'taxExclusiveAmount' => 100.00,
                'taxInclusiveAmount' => 115.00,
                'payableAmount' => 115.00,
                'allowanceTotalAmount' => 0.0,
            ],
            'invoiceLines' => [
                [
                    'id' => 1, 'unitCode' => 'PCE', 'quantity' => 1, 'lineExtensionAmount' => 100.00,
                    'item' => [
                        'name' => 'ثوب رجالي مطرز - بجاد',
                        'classifiedTaxCategory' => [['percent' => 15.00, 'taxScheme' => ['id' => 'VAT']]],
                    ],
                    'price' => ['amount' => 100.00, 'unitCode' => 'UNIT'],
                    'taxTotal' => ['taxAmount' => 15.00, 'roundingAmount' => 115.00],
                ],
            ],
        ];

        // 3. بناء الفاتورة والتوقيع
        $invoiceMapper = new \Saleh7\Zatca\Mappers\InvoiceMapper;
        $invoice = $invoiceMapper->mapToInvoice($invoiceData, true, true);
        $generatorInvoice = \Saleh7\Zatca\GeneratorInvoice::invoice($invoice);
        
        $token = env('ZATCA_TOKEN');
        $formattedCert = "-----BEGIN CERTIFICATE-----\n" . chunk_split(preg_replace('/[^A-Za-z0-9+\/=]/', '', trim($token)), 64, "\n") . "-----END CERTIFICATE-----";
        $privateKey = \Illuminate\Support\Facades\File::get(storage_path('keys/private.key'));
        $certificate = new \Saleh7\Zatca\Helpers\Certificate($formattedCert, $privateKey, env('ZATCA_SECRET'));
        
        $signedInvoice = \Saleh7\Zatca\InvoiceSigner::signInvoice($generatorInvoice->getXML(), $certificate);
        \Illuminate\Support\Facades\File::put($zatcaPath . '/Bejad_Final.xml', $signedInvoice->getXML());

        // 4. تعريف المتغيرات المطلوبة للإرسال (إصلاح الخطأ هنا)
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $credentials = base64_encode(trim(env('ZATCA_TOKEN')) . ':' . trim(env('ZATCA_SECRET')));
        // dd($credentials);
        // 5. طلب الإرسال للهيئة
       // 5. طلب الإرسال للهيئة - تغيير الرابط إلى compliance
$response = $client->post('https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance/invoices', [
    'headers' => [
        'Authorization' => 'Basic ' . $credentials,
        'Accept-Language' => 'ar',
        'Accept-Version' => 'V2',
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'invoiceHash' => $signedInvoice->getHash(),
        'uuid' => $invoiceData['uuid'],
        'invoice' => base64_encode($signedInvoice->getXML()),
    ],
]);
        $result = json_decode($response->getBody()->getContents(), true);

        return response()->json([
            'status' => 'success',
            'zatca_status' => $result['reportingStatus'] ?? 'REPORTED',
            'validation_results' => $result['validationResults'] ?? null,
            'qr' => $signedInvoice->getQRCode(),
            'hash' => $signedInvoice->getHash()
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'line' => $e->getLine()
        ], 500);
    }
}
}