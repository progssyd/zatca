<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Saleh7\Zatca\Mappers\InvoiceMapper;
use Saleh7\Zatca\GeneratorInvoice;
use Saleh7\Zatca\Helpers\Certificate;
use Saleh7\Zatca\InvoiceSigner;

class ProductionController extends Controller
{
   public function sendOrderToZatca($orderId)
    {
        try {
            $order = \App\Models\Order::with('items')->findOrFail($orderId);
            
            // 1. بيانات الشهادة والسر (تأكد أنها مطابقة لآخر رد ISSUED وصلك)
            $binarySecurityToken = "TUlJQ01UQ0NBZGlnQXdJQkFnSUdBWno5YjN5bE1Bb0dDQ3FHU000OUJBTUNNQlV4RXpBUkJnTlZCQU1NQ21WSmJuWnZhV05wYm1jd0hoY05Nall3TXpFM01qQXhOREl6V2hjTk16RXdNekUyTWpFd01EQXdXakJ2TVNRd0lnWURWUVFEREJ0Q1pXcGhaQ0JCYkNCQmJtRnhZU0JOWlc1eklFWmhjMmhwYjI0eEpEQWlCZ05WQkFvTUcwSmxhbUZrSUVGc0lFRnVZWEZoSUUxbGJuTWdSbUZ6YUdsdmJqRVVNQklHQTFVRUN3d0xUV0ZwYmlCQ2NtRnVZMmd4Q3pBSkJnTlZCQVlUQWxOQk1GWXdFQVlIS29aSXpqMENBUVlGSzRFRUFBb0RRZ0FFU0VOVUlSaVpGK09UWThpTFJsRnN1Ymg2Zk1UWGZvTTdGVCtRak5IL0NUVFUrcUxSeHN4NDhpdUVxL2ttS0g2aFdteWFSNk1LWTNOZnFNSDk5RTRYNGFPQnZEQ0J1VEFNQmdOVkhSTUJBZjhFQWpBQU1JR29CZ05WSFJFRWdhQXdnWjJrZ1pvd2daY3hNakF3QmdOVkJBUU1LVEV0UWtWS1FVUjhNaTFRVDFNdE1ERjhNeTFDU2kxSlJFVk9WRWxVV1MweE56Y3pOemMzTmpBek1SOHdIUVlLQ1pJbWlaUHlMR1FCQVF3UE16QXdOalF5TXpZME1UQXdNREF6TVEwd0N3WURWUVFNREFReE1UQXdNU0F3SGdZRFZRUWFEQmRRY21sdVkyVWdVM1ZzZEdGdUlGTjBJRXBsWkdSaGFERVBNQTBHQTFVRUR3d0dVbVYwWVdsc01Bb0dDQ3FHU000OUJBTUNBMGNBTUVRQ0lEeURUUTd1bm9NRSs5NHkrUjZ3Mnl4VDJVRTZvU0VoelNsRVZCVXNvYkJ4QWlBTVdVc1BzaU14TUdtZFNyYWlTRzcwdGQrMjZQaTA4RWEyTHUzU0tyOVV2dz09";
            $secret = "TFCf+XI92dVSMwL8FIOVNf/H1JpJGslcimlOKCt5bFc=";
            $privateKey = \File::get(storage_path('app/zatca/private.pem'));

            // 2. تنسيق الشهادة لتكون PEM صالح للقراءة
            $pemCert = "-----BEGIN CERTIFICATE-----\n" . 
                       chunk_split($binarySecurityToken, 64, "\n") . 
                       "-----END CERTIFICATE-----";

            $certificateHelper = new \Saleh7\Zatca\Helpers\Certificate($pemCert, $privateKey, $secret);

            // 3. توليد كائن الفاتورة من الدالة المجهزة (تأكد من تمرير الـ UUID)
            $uuid = (string) Str::uuid();
            $invoiceObject = $this->getRealInvoiceXML($uuid, 'BJ-INV-' . $order->id . '-' . time());

            // 4. التوقيع الرقمي (الحل النهائي لخطأ 221)
            $generator = \Saleh7\Zatca\GeneratorInvoice::invoice($invoiceObject);
            $xmlString = $generator->getXML();
            
            // التوقيع الآن سيجد وسم <cac:Signature> ولن ينهار
            $signedInvoice = \Saleh7\Zatca\InvoiceSigner::signInvoice($xmlString, $certificateHelper);

            // 5. الإرسال لبيئة المحاكاة (Simulation)
            $client = new \GuzzleHttp\Client(['verify' => false]);
            $zatcaUrl = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/invoices/reporting/single';

            $response = $client->post($zatcaUrl, [
                'headers' => [
                    'Accept-Version'   => 'V2',
                    'Accept-Language'  => 'ar',
                    'Authorization'    => 'Basic ' . base64_encode($binarySecurityToken . ':' . $secret),
                    'Content-Type'     => 'application/json',
                ],
                'json' => [
                    'invoiceHash' => $signedInvoice->getHash(),
                    'uuid'        => $uuid,
                    'invoice'     => base64_encode($signedInvoice->getXML()),
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            // تحديث الطلب في قاعدة البيانات بالهاش والـ QR الناتج
            $order->update([
                'zatca_hash' => $signedInvoice->getHash(),
                'qr_code'    => $signedInvoice->getQRCode(),
                'is_reported' => true
            ]);

            return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'line' => $e->getLine()], 500);
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
            // حقل التوقيع الفارغ ضروري جداً لتجنب خطأ 221
            'signature' => [
                'id' => 'urn:oasis:names:specification:ubl:signature:Invoice',
                'signatureMethod' => 'urn:oasis:names:specification:ubl:dsig:enveloped:xades'
            ],
            'additionalDocuments' => [
                ['id' => 'ICV', 'uuid' => '2'],
                ['id' => 'PIH', 'attachment' => ['content' => $this->getLastInvoiceHash()]],
                ['id' => 'QR'], // مكان فارغ للـ QR
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
            'customer' => ['registrationName' => 'Cash Customer'],
            'paymentMeans' => ['code' => '10'],
            'legalMonetaryTotal' => [
                'lineExtensionAmount' => $baseAmount,
                'taxExclusiveAmount' => $baseAmount,
                'taxInclusiveAmount' => $totalAmount,
                'payableAmount' => $totalAmount,
            ],
            'taxTotal' => [
                'taxAmount' => $taxAmount,
                'subTotals' => [[
                    'taxableAmount' => $baseAmount,
                    'taxAmount' => $taxAmount,
                    'taxCategory' => ['percent' => 15, 'taxScheme' => ['id' => 'VAT']],
                ]],
            ],
            'invoiceLines' => [[
                'id' => 1,
                'quantity' => 1,
                'unitCode' => 'PCE',
                'lineExtensionAmount' => $baseAmount,
                'item' => [
                    'name' => 'Men Thobe - Custom Made',
                    'classifiedTaxCategory' => [['percent' => 15.0, 'taxScheme' => ['id' => 'VAT']]],
                ],
                'price' => ['amount' => $baseAmount],
                'taxTotal' => [
                    'taxAmount' => $taxAmount,
                    'roundingAmount' => round($baseAmount + $taxAmount, 2)
                ]
            ]],
        ];

        return (new \Saleh7\Zatca\Mappers\InvoiceMapper)->mapToInvoice($invoiceData);
    }
//      private function getRealInvoiceXML($uuid, $invoiceId)
//     {
//         $baseAmount = 100.00;
//         $taxAmount = 15.00;
//         $totalAmount = 115.00;

//         $invoiceData = [
//             'uuid' => $uuid,
//             'id' => $invoiceId,
//             'issueDate' => date('Y-m-d'),
//             'issueTime' => date('H:i:s'),
//             'currencyCode' => 'SAR',
//         'taxCurrencyCode' => 'SAR',
//             'invoiceType' => [
//                 'invoice' => 'simplified',
//                 'type' => 'invoice',
//             ],
//             'additionalDocuments' => [
//                 ['id' => 'ICV', 'uuid' => '2'],
//                 ['id' => 'PIH', 'attachment' => ['content' => 'NWZlY2ViYjdmMWY3ZDVhMTVhNTljY2EzMGY2Y2YwNWYyY2Q3OGYwM2Y5ZTA1YjExZDBiM2JlOGVkZWIwMTY5Yg==']],
//             ],
//              'supplier' => [
//     'registrationName' => 'Bejad Al Anaqa Mens Fashion',
//     'taxId' => '300642364100003',
//     // --- أضف السطرين التاليين هنا لإصلاح الخطأ ---
//     'identificationId' => '1010000000', // رقم السجل التجاري كمثال
//     'identificationType' => 'CRN',      // نوع المعرف (CRN للسجل التجاري)
//     // ------------------------------------------
//     'address' => [
//         'street' => 'Abdulmaqsood Khoja',
//         'buildingNumber' => '7012',
//         'subdivision' => 'Ar Rawdah Dist',
//         'city' => 'Jeddah',
//         'postalZone' => '23435',
//         'country' => 'SA',
//     ],
// ],
//             'customer' => ['registrationName' => 'Cash Customer'],
//             'legalMonetaryTotal' => [
//                 'lineExtensionAmount' => $baseAmount,
//                 'taxExclusiveAmount' => $baseAmount,
//                 'taxInclusiveAmount' => $totalAmount,
//                 'payableAmount' => $totalAmount,
//             ],
//             'taxTotal' => [
//                 'taxAmount' => $taxAmount,
//                 'subTotals' => [[
//                     'taxableAmount' => $baseAmount,
//                     'taxAmount' => $taxAmount,
//                     'taxCategory' => ['percent' => 15, 'taxScheme' => ['id' => 'VAT']],
//                 ]],
//             ],
//             'invoiceLines' => [[
//                 'id' => 1,
//                 'quantity' => 1,
//                 'unitCode' => 'PCE',
//                 'lineExtensionAmount' => $baseAmount,
//                 'item' => [
//                     'name' => 'Men Thobe - Custom Made',
//                     'classifiedTaxCategory' => [['percent' => 15.0, 'taxScheme' => ['id' => 'VAT']]],
//                 ],
//                 'price' => ['amount' => $baseAmount],
//                 'taxTotal' => [
//         'taxAmount' => $taxAmount,
//         'roundingAmount' => round($baseAmount + $taxAmount, 2)
//     ]
//             ]],
//         ];

//         return (new InvoiceMapper)->mapToInvoice($invoiceData);
//     }
    private function mapOrderItems($items)
    {
        return $items->map(function($item, $index) {
            $price = (float) $item->price;
            $qty = (float) $item->qty;
            $lineExt = round($price * $qty, 2);
            $tax = round($lineExt * 0.15, 2);

            return [
                'id' => (string)($index + 1),
                'unitCode' => 'PCE',
                'quantity' => $qty,
                'lineExtensionAmount' => $lineExt,
                'item' => [
                    'name' => (string) $item->product_name,
                    'classifiedTaxCategory' => [['percent' => 15.00, 'taxScheme' => ['id' => 'VAT']]],
                ],
                'price' => ['amount' => $price],
                'taxTotal' => ['taxAmount' => $tax, 'roundingAmount' => round($lineExt + $tax, 2)]
            ];
        })->toArray();
    }

    private function getLastInvoiceHash()
    {
        $lastOrder = \App\Models\Order::where('is_reported', true)->latest()->first();
        return $lastOrder ? $lastOrder->zatca_hash : 'NWZlY2ViYjdmMWY3ZDVhMTVhNTljY2EzMGY2Y2YwNWYyY2Q3OGYwM2Y5ZTA1YjExZDBiM2JlOGVkZWIwMTY5Yg==';
    }
}