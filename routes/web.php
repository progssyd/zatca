<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ZatcaInvoiceController;
Route::get('/', function () {
    return view('welcome');
});
Route::get('/zatca-test', [ZatcaInvoiceController::class, 'sendTestInvoice']);
// Route::get('/zatca-test', function () {

//     $token = trim(env('ZATCA_TOKEN'));
//     $secret = trim(env('ZATCA_SECRET'));

//     $url = "https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance";

//     $ch = curl_init();

//     curl_setopt_array($ch, [
//         CURLOPT_URL => $url,
//         CURLOPT_RETURNTRANSFER => true,
//         CURLOPT_CUSTOMREQUEST => "GET",
//         CURLOPT_HTTPHEADER => [
//             "Authorization: Basic " . base64_encode($token . ":" . $secret),
//             "Accept: application/json",
//             "Accept-Language: en",
//             "Accept-Version: V2",
//             "Content-Type: application/json"
//         ],
//     ]);

//     $response = curl_exec($ch);
//     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

//     curl_close($ch);

//     return response()->json([
//         "http_code" => $httpCode,
//         "response" => $response
//     ]);
