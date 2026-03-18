<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ZatcaController;
use App\Http\Controllers\Api\ZatcaOnboardingController;
use App\Http\Controllers\Api\ZatcaBridgeController;
use Saleh7\Zatca\CertificateBuilder;
use App\Http\Controllers\Api\ZatcaSetupController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\ProductionController;
// ابحث عن السطر الخاص بـ zatca/run-test وقم بتغييره ليصبح هكذا:
Route::post('/zatca/send-order/{id}', [ProductionController::class, 'sendOrderToZatca']);
Route::get('zatca/run-test', [ProductionController::class, 'reportInvoice']);
//Route::get('/zatca/run-test', [ProductionController::class, 'runComplianceTest']);
Route::get('/zatca/run-test1',[ProductionController::class,'reportInvoice']);
Route::get('/zatca/get-production-csid', [App\Http\Controllers\Api\ProductionController::class, 'getProductionCSID']);
Route::post('/production/submit-invoice', [ProductionController::class, 'submitInvoice']);
Route::get('/zatca/setup-identity', [ZatcaSetupController::class, 'setupIdentity']);
Route::post('/zatca/submit-from-vb6', [ZatcaBridgeController::class, 'submitFromVb6']);
// 1. روابط الربط الحالية
Route::get('/zatca/success', [ZatcaOnboardingController::class, 'showSuccessStatus']);
Route::get('/zatca/final-step', [ZatcaOnboardingController::class, 'finalStepBejad']);
Route::get('/zatca/report-first', [ZatcaOnboardingController::class, 'reportFirstInvoice']);
Route::post('zatca/generate-certificate', [ZatcaOnboardingController::class, 'generateComplianceCertificate']);     
Route::get('/zatca/success', [ZatcaOnboardingController::class, 'showSuccessStatus']);
Route::get('/zatca/final-step', [ZatcaOnboardingController::class, 'finalStepBejad']);
Route::get('/zatca/report-first', [ZatcaOnboardingController::class, 'reportFirstInvoice']);
Route::post('zatca/generate-certificate', [ZatcaOnboardingController::class, 'generateComplianceCertificate']);

// 2. تشغيل الاختبار الشامل (الـ 6 حالات) - هذا الرابط الذي سنستخدمه الآن
Route::post('zatca/run-compliance-test', [ZatcaController::class, 'runFullComplianceTest']);

// 3. رابط الإرسال المنفرد (للتجارب اللاحقة)
Route::post('zatca/onboard-and-report', [ZatcaController::class, 'onboardAndReport']);

