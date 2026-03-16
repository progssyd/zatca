<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ZatcaController;
use App\Http\Controllers\Api\ZatcaOnboardingController;

use Saleh7\Zatca\CertificateBuilder;
use App\Http\Controllers\Api\ZatcaSetupController;
use App\Http\Controllers\Api\CertificateController;
Route::get('/zatca/setup-identity', [ZatcaSetupController::class, 'setupIdentity']);

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

