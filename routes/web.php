<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthAgreementController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\IspValidationController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// Autenticación y Acceso Rápido Demo por Rol (Pantalla 1)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:30,1')->name('login.submit');
Route::post('/login/demo/{role}', [AuthController::class, 'quickLogin'])->name('login.demo');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::post('/auth/validate-mfa', [AuthController::class, 'validateMFA'])
        ->middleware('throttle:30,1')
        ->name('auth.validate_mfa');

    // Pantalla 2: Terminal Punto de Venta (POS) - Vendedor, Q.F., Administrador (lectura)
    Route::middleware(['role:vendedor,quimico_farmaceutico,administrador'])->group(function () {
        Route::get('/pos', [SaleController::class, 'index'])->middleware('throttle:60,1')->name('pos.index');
        Route::post('/pos/add', [SaleController::class, 'addItem'])->middleware('throttle:60,1')->name('pos.add');
        Route::patch('/pos/item/{idMedicamento}', [SaleController::class, 'updateItemQuantity'])->name('pos.update_qty');
        Route::delete('/pos/remove/{idMedicamento}', [SaleController::class, 'removeItem'])->name('pos.remove');
        Route::post('/pos/discount', [SaleController::class, 'applyHealthDiscount'])->name('pos.discount');
        Route::post('/pos/clear', [SaleController::class, 'clearCart'])->name('pos.clear');
        Route::post('/pos/request-isp', [SaleController::class, 'requestPrescriptionQueue'])->name('pos.request_isp');
        Route::post('/pos/checkout', [SaleController::class, 'processCheckout'])->middleware('throttle:60,1')->name('pos.checkout');
    });

    // Pantalla 3: Módulo de Convenios de Salud y Copagos (Lectura para Vendedor/Q.F./Admin; escritura exclusiva Admin)
    Route::middleware(['role:vendedor,quimico_farmaceutico,administrador'])->group(function () {
        Route::get('/convenios', [HealthAgreementController::class, 'index'])->name('convenios.index');
    });

    Route::middleware(['role:administrador'])->group(function () {
        Route::post('/convenios', [HealthAgreementController::class, 'store'])->name('convenios.store');
        Route::put('/convenios/{idConvenio}', [HealthAgreementController::class, 'update'])->name('convenios.update');
        Route::patch('/convenios/{idConvenio}/estado', [HealthAgreementController::class, 'toggleStatus'])->name('convenios.toggle_status');
        Route::delete('/convenios/{idConvenio}', [HealthAgreementController::class, 'destroy'])->name('convenios.destroy');
    });

    // Pantalla 4: Bandeja de Autorización de Recetas Retenidas (Q.F. y Administrador)
    Route::middleware(['role:quimico_farmaceutico,administrador'])->group(function () {
        Route::get('/autorizaciones', [IspValidationController::class, 'index'])->name('autorizaciones.index');
        Route::get('/api/autorizaciones/pendientes', [IspValidationController::class, 'listPending'])->name('autorizaciones.pending');
        Route::post('/autorizaciones/{idRegistro}/aprobar', [IspValidationController::class, 'authorizePrescription'])->name('autorizaciones.approve');
        Route::post('/autorizaciones/{idRegistro}/rechazar', [IspValidationController::class, 'rejectPrescription'])->name('autorizaciones.reject');
    });

    // Pantalla 5: Panel de Gestión Logística de Inventario (FEFO)
    Route::middleware(['role:bodeguero,quimico_farmaceutico,vendedor,administrador'])->group(function () {
        Route::get('/inventario', [InventoryController::class, 'index'])->name('inventario.index');
        Route::get('/api/inventario/fefo', [InventoryController::class, 'getFefoList'])->name('inventario.fefo');
    });

    Route::middleware(['role:quimico_farmaceutico,administrador'])->group(function () {
        Route::post('/inventario/medicamentos', [InventoryController::class, 'storeMedication'])->name('inventario.medications.store');
        Route::put('/inventario/medicamentos/{idMedicamento}', [InventoryController::class, 'updateMedication'])->name('inventario.medications.update');
        Route::patch('/inventario/medicamentos/{idMedicamento}/receta', [InventoryController::class, 'togglePrescriptionRestriction'])->name('inventario.medications.toggle_prescription');
    });

    Route::middleware(['role:bodeguero,administrador'])->group(function () {
        Route::post('/inventario/remesa', [InventoryController::class, 'registerBatch'])->name('inventario.register');
        Route::post('/inventario/traspaso', [InventoryController::class, 'transferToDisplay'])->name('inventario.transfer');
    });

    Route::middleware(['role:bodeguero,quimico_farmaceutico,administrador'])->group(function () {
        Route::post('/inventario/retiro-vitrina', [InventoryController::class, 'transferToStorage'])->name('inventario.return_to_storage');
    });

    // Baja sanitaria por caducidad o merma: Exclusivo Químico Farmacéutico (Director Técnico ISP)
    Route::middleware(['role:quimico_farmaceutico'])->group(function () {
        Route::patch('/inventario/lotes/{idLote}/estado', [InventoryController::class, 'toggleBatchStatus'])->name('inventario.toggle_batch');
    });

    // Pantalla 6: Dashboard Gerencial y Analítica (Exclusivo Administrador)
    Route::middleware(['role:administrador'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('/dashboard/export/pdf', [DashboardController::class, 'exportPdf'])->name('dashboard.export.pdf');
        Route::get('/dashboard/export/excel', [DashboardController::class, 'exportExcel'])->name('dashboard.export.excel');

        // HU-05: Mantenedor de Usuarios RBAC, Configuración de Cambio Rápido por PIN y Auditoría Forense NoSQL
        Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');
        Route::post('/usuarios/acceso-rapido', [UserController::class, 'updateQuickAccessList'])->name('usuarios.update_quick_access_list');
        Route::put('/usuarios/{idUsuario}', [UserController::class, 'update'])->name('usuarios.update');
        Route::put('/usuarios/{idUsuario}/rol', [UserController::class, 'updateRole'])->name('usuarios.update_role');
        Route::patch('/usuarios/{idUsuario}/estado', [UserController::class, 'toggleStatus'])->name('usuarios.toggle_status');
        Route::patch('/usuarios/{idUsuario}/acceso-rapido', [UserController::class, 'toggleQuickAccess'])->name('usuarios.toggle_quick_access');
    });
});

// Cualquier ruta inexistente escrita en la barra de direcciones redirige automáticamente al inicio
Route::fallback(fn () => redirect()->route('login'));

