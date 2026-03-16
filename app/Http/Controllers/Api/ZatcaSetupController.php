<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Saleh7\Zatca\CertificateBuilder;

class ZatcaSetupController extends Controller
{
    /**
     * تأسيس الهوية الرقمية: توليد CSR و Private Key لمتجر بجاد
     */
    public function setupIdentity()
    {
        try {
            $path = storage_path('app/zatca');
            
            // تأمين المجلد وتصفيره لضمان ملفات نظيفة
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            } else {
                File::cleanDirectory($path);
            }

            $privateKeyPath = $path . '/private.pem';
            $csrPath        = $path . '/certificate.csr';

            // بناء الشهادة ببيانات بجاد الأناقة
            (new CertificateBuilder())
                ->setOrganizationIdentifier('300642364100003')
                ->setSerialNumber('BEJAD', 'POS-01', 'BJ-IDENTITY-' . time())
                ->setCommonName('Bejad Al Anaqa Mens Fashion')
                ->setCountryName('SA')
                ->setOrganizationName('Bejad Al Anaqa Mens Fashion')
                ->setOrganizationalUnitName('Main Branch')
                ->setAddress('Prince Sultan St, Jeddah')
                ->setInvoiceType(1100) // مبسطة وضريبية
                ->setProduction(false) // وضع المحاكاة
                ->setBusinessCategory('Retail')
                ->generateAndSave($csrPath, $privateKeyPath);

            return response()->json([
                'status'  => 'success',
                'message' => '✅ تم تأسيس الهوية الرقمية (CSR & Private Key) بنجاح.',
                'details' => [
                    'location' => 'storage/app/zatca/',
                    'files'    => ['certificate.csr', 'private.pem']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'فشل التأسيس: ' . $e->getMessage()
            ], 500);
        }
    }
}