<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ZatcaInvoice;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Saleh7\Zatca\Helpers\Certificate;
use Saleh7\Zatca\Mappers\InvoiceMapper;
use Saleh7\Zatca\GeneratorInvoice;
use Saleh7\Zatca\InvoiceSigner;

class ZatcaService
{
    protected $path;
    protected $authData;
    protected $privateKey;

    public function __construct()
    {
        $this->path = storage_path('app/zatca');
        if (File::exists($this->path . '/auth_data.json')) {
            $this->authData = json_decode(File::get($this->path . '/auth_data.json'), true);
            $this->privateKey = File::get($this->path . '/private.pem');
        }
    }

      public function submitOrderToZatca($orderId)
{
    try {
        // 1. جلب بيانات الطلب من قاعدة البيانات (افترضنا أن الموديل اسمه Order)
        $order = \App\Models\Order::with('items')->findOrFail($orderId);

        // 2. جلب آخر فاتورة مسجلة للحصول على الهاش والعداد (سلسلة الهاش)
        $lastInvoice = \App\Models\ZatcaInvoice::latest()->first();
        $previousHash = $lastInvoice ? $lastInvoice->invoice_hash : 'NWZlY2ViOTZmY2ZlYmU5ZTRjY2Q1NjljYjc3YmU5ZTRjY2Q1NjljYjc3';
        $nextICV = $lastInvoice ? ($lastInvoice->icv + 1) : 1;

        // 3. قراءة بيانات الربط
        $path = storage_path('app/zatca');
        $authData = json_decode(\File::get($path . '/auth_data.json'), true);
        $privateKey = \File::get($path . '/private.pem');

        // 4. تجهيز الشهادة والمفتاح (الطريقة المختصرة الناجحة التي جربناها)
        $binaryToken = $authData['binarySecurityToken'];
        $cleanKey = preg_replace('/[\r\n\s-]|BEGIN|END|PRIVATE|KEY|EC/', '', $privateKey);
        $certificateHelper = new \Saleh7\Zatca\Helpers\Certificate($binaryToken, $cleanKey, $authData['secret']);

        // 5. بناء بيانات الفاتورة ببيانات الطلب الحقيقية
        $uuid = (string) \Illuminate\Support\Str::uuid();
        
        // ملاحظة: هنا يجب أن تمرر بيانات الـ $order الحقيقية لدالة بناء الـ XML
        $invoiceObject = $this->buildInvoiceFromOrder($order, $uuid, $nextICV, $previousHash);
        
        $invoiceXML = \Saleh7\Zatca\GeneratorInvoice::invoice($invoiceObject)->getXML();
        $signedInvoice = \Saleh7\Zatca\InvoiceSigner::signInvoice($invoiceXML, $certificateHelper);

        // 6. الإرسال للهيئة (رابط الـ Simulation الفعلي)
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $response = $client->post('https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/invoices', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($binaryToken . ':' . $authData['secret']),
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

        // 7. الحفظ في قاعدة البيانات في حال النجاح
        if (isset($result['reportingStatus']) && $result['reportingStatus'] == 'REPORTED') {
            \App\Models\ZatcaInvoice::create([
                'order_id' => $order->id,
                'uuid' => $uuid,
                'icv' => $nextICV,
                'invoice_hash' => $signedInvoice->getHash(),
                'previous_hash' => $previousHash,
                'qr_code' => $signedInvoice->getQrCode(),
                'status' => 'REPORTED',
                'zatca_response' => $result
            ]);
            return true;
        }

        return false;

    } catch (\Exception $e) {
        \Log::error("Zatca Error for Order $orderId: " . $e->getMessage());
        return false;
    }
}
    public function sendOrder($orderId)
    {
        $order = Order::with('items')->findOrFail($orderId);

        // 1. جلب بيانات السلسلة (الهاش والعداد)
        $lastInvoice = ZatcaInvoice::latest()->first();
        $previousHash = $lastInvoice ? $lastInvoice->invoice_hash : 'NWZlY2ViOTZmY2ZlYmU5ZTRjY2Q1NjljYjc3YmU5ZTRjY2Q1NjljYjc3';
        $nextICV = $lastInvoice ? ($lastInvoice->icv + 1) : 1;

        // 2. تجهيز المحرك (Helper)
        $cleanKey = preg_replace('/[\r\n\s-]|BEGIN|END|PRIVATE|KEY|EC/', '', $this->privateKey);
        $certificateHelper = new Certificate($this->authData['binarySecurityToken'], $cleanKey, $this->authData['secret']);

        // 3. بناء الفاتورة وتوقيعها
        $uuid = (string) Str::uuid();
        $invoiceObject = $this->buildInvoiceObject($order, $uuid, $nextICV, $previousHash);
        
        $invoiceXML = GeneratorInvoice::invoice($invoiceObject)->getXML();
        $signedInvoice = InvoiceSigner::signInvoice($invoiceXML, $certificateHelper);

        // 4. الإرسال للهيئة
        $client = new Client(['verify' => false]);
        $response = $client->post('https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/invoices', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->authData['binarySecurityToken'] . ':' . $this->authData['secret']),
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

        // 5. الحفظ في قاعدة البيانات
        if (isset($result['reportingStatus']) && $result['reportingStatus'] == 'REPORTED') {
            return ZatcaInvoice::create([
                'order_id' => $order->id,
                'uuid' => $uuid,
                'icv' => $nextICV,
                'invoice_hash' => $signedInvoice->getHash(),
                'previous_hash' => $previousHash,
                'qr_code' => $signedInvoice->getQrCode(),
                'status' => 'REPORTED',
                'zatca_response' => $result
            ]);
        }

        throw new \Exception("فشل إرسال الفاتورة لزاتكا: " . json_encode($result));
    }

    /**
     * بناء هيكل الفاتورة الداخلي
     */
    private function buildInvoiceObject($order, $uuid, $icv, $previousHash)
    {
        $invoiceData = [
            'uuid' => $uuid,
            'id' => 'INV-' . $order->id,
            'issueDate' => date('Y-m-d'),
            'issueTime' => date('H:i:s'),
            'currencyCode' => 'SAR',
            'taxCurrencyCode' => 'SAR',
            'invoiceType' => ['invoice' => 'simplified', 'type' => 'invoice'],
            'additionalDocuments' => [
                ['id' => 'ICV', 'uuid' => (string) $icv],
                ['id' => 'PIH', 'attachment' => ['content' => $previousHash]],
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
            'taxTotal' => [
                'taxAmount' => $order->tax_amount,
                'subTotals' => [[
                    'taxableAmount' => $order->subtotal,
                    'taxAmount' => $order->tax_amount,
                    'taxCategory' => ['percent' => 15, 'taxScheme' => ['id' => 'VAT']],
                ]],
            ],
            'legalMonetaryTotal' => [
                'lineExtensionAmount' => $order->subtotal,
                'taxExclusiveAmount' => $order->subtotal,
                'taxInclusiveAmount' => $order->total,
                'payableAmount' => $order->total,
            ],
            'invoiceLines' => [],
        ];

        foreach ($order->items as $index => $item) {
            $invoiceData['invoiceLines'][] = [
                'id' => $index + 1,
                'quantity' => $item->quantity,
                'unitCode' => 'PCE',
                'lineExtensionAmount' => $item->price_before_tax * $item->quantity,
                'item' => [
                    'name' => $item->product_name,
                    'classifiedTaxCategory' => [['percent' => 15.0, 'taxScheme' => ['id' => 'VAT']]],
                ],
                'price' => ['amount' => $item->price_before_tax],
                'taxTotal' => [
                    'taxAmount' => $item->tax_amount,
                    'roundingAmount' => $item->total_with_tax,
                ],
            ];
        }

        return (new InvoiceMapper)->mapToInvoice($invoiceData, true, true);
    }
}