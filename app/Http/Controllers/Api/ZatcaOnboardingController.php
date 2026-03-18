<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Saleh7\Zatca\CertificateBuilder;
use Saleh7\Zatca\InvoiceSigner;
use Saleh7\Zatca\GeneratorInvoice;
use Saleh7\Zatca\Mappers\InvoiceMapper;
use Saleh7\Zatca\Helpers\Certificate;
use App\Services\ZatcaService;

class ZatcaOnboardingController extends Controller
{
    /**
     * إكمال الطلب وإرسال الفاتورة (للاستخدام داخل المتجر)
     */
    public function completeOrder($id, ZatcaService $zatcaService)
    {
        try {
            $zatcaInvoice = $zatcaService->sendOrder($id);

            return response()->json([
                'message' => 'تم تأكيد الطلب وإرسال الفاتورة لزاتكا',
                'qr_code' => $zatcaInvoice->qr_code
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * عرض حالة الربط في المتصفح
     */
    public function showSuccessStatus()
    {
        try {
            $zatcaPath = storage_path('app/zatca');
            $authFile = $zatcaPath . '/auth_data.json';

            if (!File::exists($authFile)) {
                return "<div style='font-family: Arial; text-align: center; padding: 50px; direction: rtl;'>
                            <h2 style='color: #dc3545;'>⚠️ لم يتم الربط بعد</h2>
                            <p>يرجى تشغيل الرابط (generate-certificate) مع OTP جديد أولاً.</p>
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

    /**
     * الخطوة الأولى: توليد CSR وطلب التوكن المبدئي
     */
    public function simpleOnboarding(Request $request)
    {
        try {
            $otp = trim($request->otp ?? '');
            if (!$otp) {
                return response()->json(['error' => 'OTP مطلوب'], 400);
            }

            $path = storage_path('app/zatca');
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }

            $privateKeyPath = $path . '/private.pem';
            $csrPath = $path . '/certificate.csr';

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

            $client = new Client(['verify' => false]);
            $response = $client->post('https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance', [
                'headers' => [
                    'Accept-Version' => 'V2',
                    'OTP' => $otp
                ],
                'json' => ['csr' => base64_encode(File::get($csrPath))]
            ]);

            $auth = json_decode($response->getBody()->getContents(), true);
            $auth['onboarded_at'] = now()->toDateTimeString();
            File::put($path . '/auth_data.json', json_encode($auth, JSON_PRETTY_PRINT));

            return response()->json([
                'status' => 'success',
                'message' => '✅ تم الربط المبدئي بنجاح',
                'data' => $auth
            ]);

        } catch (\Exception $e) {
            $msg = $e instanceof \GuzzleHttp\Exception\ClientException ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            return response()->json(['error' => 'فشل عملية الربط', 'message' => $msg], 500);
        }
    }

    /**
     * الدالة المطلوبة في المسار (توليد شهادة الامتثال)
     */
    public function generateComplianceCertificate(Request $request)
    {
        return $this->finalStepBejad($request);
    }

    /**
     * الخطوة النهائية للربط
     */
    public function finalStepBejad(Request $request)
    {
        $otp = trim($request->otp ?? '');
        if (!$otp) return response()->json(['error' => 'OTP مطلوب'], 400);

        $path = storage_path('app/zatca');
        $csrPath = $path . '/certificate.csr';
        $authFile = $path . '/auth_data.json';

        try {
            $client = new Client(['verify' => false]);
            $response = $client->post('https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance', [
                'headers' => ['Accept-Version' => 'V2', 'OTP' => $otp],
                'json' => ['csr' => base64_encode(File::get($csrPath))],
            ]);

            $auth = json_decode($response->getBody()->getContents(), true);
            $auth['onboarded_at'] = now()->toDateTimeString();
            File::put($authFile, json_encode($auth, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return response()->json(['status' => 'success', 'message' => '✅ تم الربط النهائي بنجاح لمتجر بجاد الأناقة']);
        } catch (\Exception $e) {
            $msg = $e instanceof \GuzzleHttp\Exception\ClientException ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            return response()->json(['error' => 'خطأ في الربط', 'message' => $msg], 400);
        }
    }

    /**
     * إرسال أول فاتورة تجريبية
     */
    public function reportFirstInvoice()
    {
        try {
            $path = storage_path('app/zatca');
            $authData = json_decode(File::get($path . '/auth_data.json'), true);
            $privateKey = File::get($path . '/private.pem');

            $originalToken = $authData['binarySecurityToken'];
            $step1 = base64_decode($originalToken, true);
            $derBinary = base64_decode($step1, true) ?: $step1;
            $cleanBase64Cert = base64_encode($derBinary);

            $cleanKey = preg_replace('/[\r\n\s-]|BEGIN|END|PRIVATE|KEY|EC/', '', $privateKey);

            $certificateHelper = new Certificate($cleanBase64Cert, $cleanKey, $authData['secret']);

            $uuid = (string) Str::uuid();
            $invoiceObject = $this->getRealInvoiceXML($uuid, 'BJ-INV-' . time());
            $generator = GeneratorInvoice::invoice($invoiceObject);
            
            $signedInvoice = InvoiceSigner::signInvoice($generator->getXML(), $certificateHelper);

            $client = new Client(['verify' => false]);
            $response = $client->post('https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance/invoices', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($originalToken . ':' . $authData['secret']),
                    'Accept-Version' => 'V2',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'invoiceHash' => $signedInvoice->getHash(),
                    'uuid' => $uuid,
                    'invoice' => base64_encode($signedInvoice->getXML()),
                ],
            ]);

            return response()->json([
                'status' => 'success',
                'qr_code' => $signedInvoice->getQRCode(), 
                'zatca_response' => json_decode($response->getBody()->getContents(), true)
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
            'invoiceType' => [
                'invoice' => 'simplified',
                'type' => 'invoice',
            ],
            'additionalDocuments' => [
                ['id' => 'ICV', 'uuid' => '2'],
                ['id' => 'PIH', 'attachment' => ['content' => 'NWZlY2ViYjdmMWY3ZDVhMTVhNTljY2EzMGY2Y2YwNWYyY2Q3OGYwM2Y5ZTA1YjExZDBiM2JlOGVkZWIwMTY5Yg==']],
            ],
            'supplier' => [
                'registrationName' => 'Bejad Al Anaqa Mens Fashion',
                'taxId' => '300642364100003',
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
            ]],
        ];

        return (new InvoiceMapper)->mapToInvoice($invoiceData);
    }
}