<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Saleh7\Zatca\CertificateBuilder;

class CertificateController extends Controller
{
    /**
     * توليد ملفات الهوية الرقمية لمتجر بجاد (CSR & Private Key)
     */
    public function generateBaseFiles()
    {
        try {
            $path = storage_path('app/zatca');
            
            // التأكد من وجود المجلد
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }

            $privateKeyPath = $path . '/private.pem';
            $csrPath = $path . '/certificate.csr';

            // 1. توليد الملفات باستخدام بيانات بجاد الأناقة
            (new CertificateBuilder())
                ->setOrganizationIdentifier('300642364100003') // الرقم الضريبي
                ->setSerialNumber('BEJAD', 'POS-01', 'BJ-DEV-' . time())
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
                'status' => 'success',
                'message' => '✅ تم توليد ملفات CSR و Private Key بنجاح',
                'files' => [
                    'csr' => 'certificate.csr',
                    'private_key' => 'private.pem',
                    'path' => $path
                ],
                'next_step' => 'الآن استخدم الـ CSR لطلب التوكن باستخدام OTP جديد.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'فشل توليد الملفات: ' . $e->getMessage()
            ], 500);
        }
    }
}