<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\HealthAgreement;
use App\Models\IspLog;
use App\Models\Medication;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles RBAC (Capítulo 7.1)
        $rolVendedor = Role::create([
            'codigo_rol' => 'vendedor',
            'nombre_rol' => 'Vendedor / Técnico Farmacéutico',
            'descripcion' => 'Atención de mostrador POS, venta libre (OTC) y captura de recetas.',
        ]);

        $rolQf = Role::create([
            'codigo_rol' => 'quimico_farmaceutico',
            'nombre_rol' => 'Químico Farmacéutico (Director Técnico)',
            'descripcion' => 'Fiscalización sanitaria ISP, autorización y firma criptográfica de recetas retenidas.',
        ]);

        $rolBodeguero = Role::create([
            'codigo_rol' => 'bodeguero',
            'nombre_rol' => 'Bodeguero / Logística FEFO',
            'descripcion' => 'Recepción de lotes físicos, control de caducidad FEFO y traspaso a vitrina.',
        ]);

        $rolAdmin = Role::create([
            'codigo_rol' => 'administrador',
            'nombre_rol' => 'Administrador General',
            'descripcion' => 'Dashboard gerencial, gestión de convenios, usuarios RBAC y auditoría forense.',
        ]);

        // 2. Usuarios Institucionales (Pantalla 1 - Acceso Rápido de Terminal con PIN de 4 dígitos)
        $vendedor = User::create([
            'id_rol' => $rolVendedor->id_rol,
            'rut' => '12.345.678-5',
            'nombre_completo' => 'Ana Torres',
            'email' => 'ana.torres@farmanet.cl',
            'password_hash' => Hash::make('Password123!'),
            'acceso_rapido' => true,
            'pin_acceso' => Hash::make('1234'),
            'activo' => true,
        ]);

        $qf = User::create([
            'id_rol' => $rolQf->id_rol,
            'rut' => '11.222.333-4',
            'nombre_completo' => 'Dr. Carlos Vega',
            'email' => 'carlos.vega@farmanet.cl',
            'password_hash' => Hash::make('Password123!'),
            'pin_qf' => Hash::make('4421'),
            'acceso_rapido' => true,
            'pin_acceso' => Hash::make('1234'),
            'activo' => true,
        ]);

        $bodeguero = User::create([
            'id_rol' => $rolBodeguero->id_rol,
            'rut' => '15.432.876-5',
            'nombre_completo' => 'Luis Pinto',
            'email' => 'luis.pinto@farmanet.cl',
            'password_hash' => Hash::make('Password123!'),
            'acceso_rapido' => true,
            'pin_acceso' => Hash::make('1234'),
            'activo' => true,
        ]);

        $admin = User::create([
            'id_rol' => $rolAdmin->id_rol,
            'rut' => '10.111.222-3',
            'nombre_completo' => 'María Salinas',
            'email' => 'maria.salinas@farmanet.cl',
            'password_hash' => Hash::make('Password123!'),
            'acceso_rapido' => true,
            'pin_acceso' => Hash::make('1234'),
            'activo' => true,
        ]);

        // 3. Convenios de Salud (Pantalla 3)
        $inicioAnio = now()->startOfYear()->toDateString();
        $finVigencia = now()->endOfYear()->addMonths(6)->toDateString();

        $conveniosData = [
            ['nombre_convenio' => 'Sin convenio', 'porcentaje_descuento' => 0, 'fecha_inicio' => null, 'fecha_fin' => null],
            ['nombre_convenio' => 'Fonasa A', 'porcentaje_descuento' => 50, 'fecha_inicio' => $inicioAnio, 'fecha_fin' => $finVigencia],
            ['nombre_convenio' => 'Fonasa B', 'porcentaje_descuento' => 80, 'fecha_inicio' => $inicioAnio, 'fecha_fin' => $finVigencia],
            ['nombre_convenio' => 'Fonasa C', 'porcentaje_descuento' => 60, 'fecha_inicio' => $inicioAnio, 'fecha_fin' => $finVigencia],
            ['nombre_convenio' => 'Fonasa D', 'porcentaje_descuento' => 40, 'fecha_inicio' => $inicioAnio, 'fecha_fin' => $finVigencia],
            ['nombre_convenio' => 'GES / AUGE', 'porcentaje_descuento' => 80, 'fecha_inicio' => $inicioAnio, 'fecha_fin' => $finVigencia],
            ['nombre_convenio' => 'Isapre Cruz Blanca', 'porcentaje_descuento' => 30, 'fecha_inicio' => $inicioAnio, 'fecha_fin' => $finVigencia],
            ['nombre_convenio' => 'Isapre Banmédica', 'porcentaje_descuento' => 25, 'fecha_inicio' => $inicioAnio, 'fecha_fin' => $finVigencia],
            ['nombre_convenio' => 'Isapre Colmena', 'porcentaje_descuento' => 20, 'fecha_inicio' => $inicioAnio, 'fecha_fin' => $finVigencia],
        ];

        $conveniosMap = [];
        foreach ($conveniosData as $c) {
            $conv = HealthAgreement::create($c + ['activo' => true]);
            $conveniosMap[$c['nombre_convenio']] = $conv;
        }

        // 4. Catálogo de Medicamentos y Lotes FEFO (Pantalla 5)
        $medTramadol = Medication::create([
            'codigo_barras' => '780000100101',
            'nombre_comercial' => 'Tramadol 50mg',
            'principio_activo' => 'Tramadol Clorhidrato',
            'laboratorio' => 'Grünenthal',
            'categoria' => 'Analgésico Opioide (Controlado)',
            'precio_unitario' => 8990,
            'requiere_receta' => true,
            'tipo_controlado' => true,
        ]);

        $medOmeprazol = Medication::create([
            'codigo_barras' => '780000100102',
            'nombre_comercial' => 'Omeprazol 20mg',
            'principio_activo' => 'Omeprazol',
            'laboratorio' => 'AstraZeneca',
            'categoria' => 'Gastroenterología',
            'precio_unitario' => 4500,
            'requiere_receta' => false,
            'tipo_controlado' => false,
        ]);

        $medClonazepam = Medication::create([
            'codigo_barras' => '780000100103',
            'nombre_comercial' => 'Clonazepam 2mg',
            'principio_activo' => 'Clonazepam',
            'laboratorio' => 'Roche',
            'categoria' => 'Psicotrópico (Receta Retenida ISP)',
            'precio_unitario' => 14990,
            'requiere_receta' => true,
            'tipo_controlado' => true,
        ]);

        $medParacetamol = Medication::create([
            'codigo_barras' => '780000100104',
            'nombre_comercial' => 'Paracetamol 500mg',
            'principio_activo' => 'Paracetamol',
            'laboratorio' => 'Mintlab',
            'categoria' => 'Analgésico OTC',
            'precio_unitario' => 2490,
            'requiere_receta' => false,
            'tipo_controlado' => false,
        ]);

        $medLorazepam = Medication::create([
            'codigo_barras' => '780000100105',
            'nombre_comercial' => 'Lorazepam 1mg',
            'principio_activo' => 'Lorazepam',
            'laboratorio' => 'Wyeth',
            'categoria' => 'Psicotrópico (Receta Retenida ISP)',
            'precio_unitario' => 11990,
            'requiere_receta' => true,
            'tipo_controlado' => true,
        ]);

        $medAmoxicilina = Medication::create([
            'codigo_barras' => '780000100106',
            'nombre_comercial' => 'Amoxicilina 500mg',
            'principio_activo' => 'Amoxicilina Trihidrato',
            'laboratorio' => 'Bagó',
            'categoria' => 'Antibiótico',
            'precio_unitario' => 6990,
            'requiere_receta' => false,
            'tipo_controlado' => false,
        ]);

        $medLosartan = Medication::create([
            'codigo_barras' => '780000100107',
            'nombre_comercial' => 'Losartán 50mg',
            'principio_activo' => 'Losartán Potásico',
            'laboratorio' => 'Gador',
            'categoria' => 'Cardiovascular',
            'precio_unitario' => 7490,
            'requiere_receta' => false,
            'tipo_controlado' => false,
        ]);

        $medIbuprofeno = Medication::create([
            'codigo_barras' => '780000100108',
            'nombre_comercial' => 'Ibuprofeno 400mg',
            'principio_activo' => 'Ibuprofeno',
            'laboratorio' => 'Chile',
            'categoria' => 'Antiinflamatorio OTC',
            'precio_unitario' => 3290,
            'requiere_receta' => false,
            'tipo_controlado' => false,
        ]);

        // 3 medicamentos adicionales en Quiebre de Stock en Vitrina (disponibles en Bodega para traspaso FEFO)
        $medMetformina = Medication::create([
            'codigo_barras' => '780000100109',
            'nombre_comercial' => 'Metformina 850mg',
            'principio_activo' => 'Metformina Clorhidrato',
            'laboratorio' => 'Saval',
            'categoria' => 'Antidiabético GES',
            'precio_unitario' => 5490,
            'requiere_receta' => false,
            'tipo_controlado' => false,
        ]);

        $medAtorvastatina = Medication::create([
            'codigo_barras' => '780000100110',
            'nombre_comercial' => 'Atorvastatina 20mg',
            'principio_activo' => 'Atorvastatina Cálcica',
            'laboratorio' => 'Pfizer',
            'categoria' => 'Hipolipemiante GES',
            'precio_unitario' => 8290,
            'requiere_receta' => false,
            'tipo_controlado' => false,
        ]);

        $medAzitromicina = Medication::create([
            'codigo_barras' => '780000100111',
            'nombre_comercial' => 'Azitromicina 500mg',
            'principio_activo' => 'Azitromicina',
            'laboratorio' => 'Andrómaco',
            'categoria' => 'Antibiótico',
            'precio_unitario' => 9490,
            'requiere_receta' => false,
            'tipo_controlado' => false,
        ]);

        // 4.1 Vademécum / Petitorio de Medicamentos Adheridos por Convenio (Farmacia Privada Real)
        $medsCenabast = [
            $medOmeprazol->id_medicamento => ['activo' => true],
            $medParacetamol->id_medicamento => ['activo' => true],
            $medAmoxicilina->id_medicamento => ['activo' => true],
            $medLosartan->id_medicamento => ['activo' => true],
            $medIbuprofeno->id_medicamento => ['activo' => true],
            $medMetformina->id_medicamento => ['activo' => true],
            $medAtorvastatina->id_medicamento => ['activo' => true],
            $medAzitromicina->id_medicamento => ['activo' => true],
        ];

        $medsGes = [
            $medOmeprazol->id_medicamento => ['activo' => true],
            $medLosartan->id_medicamento => ['activo' => true],
            $medMetformina->id_medicamento => ['activo' => true],
            $medAtorvastatina->id_medicamento => ['activo' => true],
        ];

        $medsIsapre = [
            $medTramadol->id_medicamento => ['activo' => true],
            $medOmeprazol->id_medicamento => ['activo' => true],
            $medClonazepam->id_medicamento => ['activo' => true],
            $medLorazepam->id_medicamento => ['activo' => true],
            $medAmoxicilina->id_medicamento => ['activo' => true],
            $medLosartan->id_medicamento => ['activo' => true],
            $medMetformina->id_medicamento => ['activo' => true],
            $medAtorvastatina->id_medicamento => ['activo' => true],
            $medAzitromicina->id_medicamento => ['activo' => true],
        ];

        foreach (['Fonasa A', 'Fonasa B', 'Fonasa C', 'Fonasa D'] as $nom) {
            $conveniosMap[$nom]->update(['aplica_todo_catalogo' => false]);
            $conveniosMap[$nom]->medicamentos()->sync($medsCenabast);
        }

        $conveniosMap['GES / AUGE']->update(['aplica_todo_catalogo' => false]);
        $conveniosMap['GES / AUGE']->medicamentos()->sync($medsGes);

        foreach (['Isapre Cruz Blanca', 'Isapre Banmédica', 'Isapre Colmena'] as $nom) {
            $conveniosMap[$nom]->update(['aplica_todo_catalogo' => false]);
            $conveniosMap[$nom]->medicamentos()->sync($medsIsapre);
        }

        // Lotes en VITRINA exactamente como en Pantalla 5 (Página 29)
        $loteTramadol = Batch::create([
            'id_medicamento' => $medTramadol->id_medicamento,
            'lote_codigo' => 'LOT-2024-013',
            'ubicacion' => 'VITRINA',
            'cantidad' => 25,
            'fecha_vencimiento' => now()->addDays(5)->toDateString(),
            'fecha_ingreso' => now()->subMonths(5)->toDateString(),
        ]);

        $loteOmeprazol = Batch::create([
            'id_medicamento' => $medOmeprazol->id_medicamento,
            'lote_codigo' => 'LOT-2024-010',
            'ubicacion' => 'VITRINA',
            'cantidad' => 180,
            'fecha_vencimiento' => now()->addDays(8)->toDateString(),
            'fecha_ingreso' => now()->subMonths(5)->toDateString(),
        ]);

        $loteClonazepam = Batch::create([
            'id_medicamento' => $medClonazepam->id_medicamento,
            'lote_codigo' => 'LOT-2024-005',
            'ubicacion' => 'VITRINA',
            'cantidad' => 30,
            'fecha_vencimiento' => now()->addDays(15)->toDateString(),
            'fecha_ingreso' => now()->subMonths(4)->toDateString(),
        ]);

        $loteParacetamol = Batch::create([
            'id_medicamento' => $medParacetamol->id_medicamento,
            'lote_codigo' => 'LOT-2024-003',
            'ubicacion' => 'VITRINA',
            'cantidad' => 200,
            'fecha_vencimiento' => now()->addDays(20)->toDateString(),
            'fecha_ingreso' => now()->subMonths(4)->toDateString(),
        ]);

        $loteLorazepam = Batch::create([
            'id_medicamento' => $medLorazepam->id_medicamento,
            'lote_codigo' => 'LOT-2024-008',
            'ubicacion' => 'VITRINA',
            'cantidad' => 15,
            'fecha_vencimiento' => now()->addDays(25)->toDateString(),
            'fecha_ingreso' => now()->subMonths(4)->toDateString(),
        ]);

        $loteAmoxicilina = Batch::create([
            'id_medicamento' => $medAmoxicilina->id_medicamento,
            'lote_codigo' => 'LOT-2024-001',
            'ubicacion' => 'VITRINA',
            'cantidad' => 60,
            'fecha_vencimiento' => now()->addDays(180)->toDateString(),
            'fecha_ingreso' => now()->subMonths(2)->toDateString(),
        ]);

        $loteLosartan = Batch::create([
            'id_medicamento' => $medLosartan->id_medicamento,
            'lote_codigo' => 'LOT-2024-011',
            'ubicacion' => 'VITRINA',
            'cantidad' => 95,
            'fecha_vencimiento' => now()->addDays(290)->toDateString(),
            'fecha_ingreso' => now()->subMonths(2)->toDateString(),
        ]);

        $loteIbuprofeno = Batch::create([
            'id_medicamento' => $medIbuprofeno->id_medicamento,
            'lote_codigo' => 'LOT-2024-007',
            'ubicacion' => 'VITRINA',
            'cantidad' => 320,
            'fecha_vencimiento' => now()->addDays(500)->toDateString(),
            'fecha_ingreso' => now()->subMonth()->toDateString(),
        ]);

        // Lotes en BODEGA (para reabastecimiento FEFO - Escenario B)
        Batch::create([
            'id_medicamento' => $medMetformina->id_medicamento,
            'lote_codigo' => 'LOT-2025-101',
            'ubicacion' => 'BODEGA',
            'cantidad' => 150,
            'fecha_vencimiento' => now()->addDays(120)->toDateString(),
            'fecha_ingreso' => now()->subDays(15)->toDateString(),
        ]);

        Batch::create([
            'id_medicamento' => $medAtorvastatina->id_medicamento,
            'lote_codigo' => 'LOT-2025-102',
            'ubicacion' => 'BODEGA',
            'cantidad' => 120,
            'fecha_vencimiento' => now()->addDays(210)->toDateString(),
            'fecha_ingreso' => now()->subDays(12)->toDateString(),
        ]);

        Batch::create([
            'id_medicamento' => $medAzitromicina->id_medicamento,
            'lote_codigo' => 'LOT-2025-103',
            'ubicacion' => 'BODEGA',
            'cantidad' => 80,
            'fecha_vencimiento' => now()->addDays(340)->toDateString(),
            'fecha_ingreso' => now()->subDays(10)->toDateString(),
        ]);

        Batch::create([
            'id_medicamento' => $medParacetamol->id_medicamento,
            'lote_codigo' => 'LOT-2025-204',
            'ubicacion' => 'BODEGA',
            'cantidad' => 500,
            'fecha_vencimiento' => now()->addDays(420)->toDateString(),
            'fecha_ingreso' => now()->subDays(5)->toDateString(),
        ]);

        // 4.2 Catálogo ampliado de Farmacia Privada Chilena (77 especialidades farmacéuticas con lotes y vademécum)
        $catalogMigration = require database_path('migrations/2026_11_15_000004_seed_complete_private_pharmacy_catalog.php');
        $catalogMigration->up();

        // 5. Cola de Validaciones Pendientes en Bandeja Q.F. (Pantalla 4 - Página 28)
        IspLog::create([
            'id_medicamento' => $medClonazepam->id_medicamento,
            'id_lote' => $loteClonazepam->id_lote,
            'id_vendedor' => $vendedor->id_usuario,
            'cantidad' => 1,
            'folio_receta' => 'REC-2026-8841',
            'rut_paciente' => '18.455.120-K',
            'rut_medico' => '09.876.543-2',
            'estado' => 'PENDIENTE',
            'created_at' => now()->setTime(9, 14),
        ]);

        IspLog::create([
            'id_medicamento' => $medLorazepam->id_medicamento,
            'id_lote' => $loteLorazepam->id_lote,
            'id_vendedor' => $vendedor->id_usuario,
            'cantidad' => 1,
            'folio_receta' => 'REC-2026-8842',
            'rut_paciente' => '16.234.890-1',
            'rut_medico' => '08.111.222-3',
            'estado' => 'PENDIENTE',
            'created_at' => now()->setTime(9, 38),
        ]);

        IspLog::create([
            'id_medicamento' => $medTramadol->id_medicamento,
            'id_lote' => $loteTramadol->id_lote,
            'id_vendedor' => $vendedor->id_usuario,
            'cantidad' => 1,
            'folio_receta' => 'REC-2026-8843',
            'rut_paciente' => '19.876.543-4',
            'rut_medico' => '07.654.321-K',
            'estado' => 'PENDIENTE',
            'created_at' => now()->setTime(10, 2),
        ]);

        // 6. Ventas del Día y Rotación (Pantalla 6 - Dashboard Gerencial: $834.000)
        $venta1 = Sale::create([
            'id_usuario' => $vendedor->id_usuario,
            'id_convenio' => $conveniosMap['Fonasa B']->id_convenio,
            'fecha_venta' => now()->subHours(3),
            'subtotal' => 420000,
            'descuento_total' => 186000,
            'iva' => 37361,
            'total' => 234000,
            'estado' => 'COMPLETADA',
        ]);
        SaleDetail::create([
            'id_venta' => $venta1->id_venta,
            'id_lote' => $loteParacetamol->id_lote,
            'id_medicamento' => $medParacetamol->id_medicamento,
            'cantidad' => 145,
            'precio_unitario' => 2490,
            'subtotal_linea' => 234000,
        ]);

        $venta2 = Sale::create([
            'id_usuario' => $vendedor->id_usuario,
            'id_convenio' => $conveniosMap['Isapre Cruz Blanca']->id_convenio,
            'fecha_venta' => now()->subHours(2),
            'subtotal' => 251000,
            'descuento_total' => 64000,
            'iva' => 29857,
            'total' => 187000,
            'estado' => 'COMPLETADA',
        ]);
        SaleDetail::create([
            'id_venta' => $venta2->id_venta,
            'id_lote' => $loteIbuprofeno->id_lote,
            'id_medicamento' => $medIbuprofeno->id_medicamento,
            'cantidad' => 118,
            'precio_unitario' => 3290,
            'subtotal_linea' => 187000,
        ]);

        $venta3 = Sale::create([
            'id_usuario' => $vendedor->id_usuario,
            'id_convenio' => $conveniosMap['GES / AUGE']->id_convenio,
            'fecha_venta' => now()->subHour(),
            'subtotal' => 513000,
            'descuento_total' => 100000,
            'iva' => 65941,
            'total' => 413000,
            'estado' => 'COMPLETADA',
        ]);
        SaleDetail::create([
            'id_venta' => $venta3->id_venta,
            'id_lote' => $loteOmeprazol->id_lote,
            'id_medicamento' => $medOmeprazol->id_medicamento,
            'cantidad' => 98,
            'precio_unitario' => 4500,
            'subtotal_linea' => 213000,
        ]);
        SaleDetail::create([
            'id_venta' => $venta3->id_venta,
            'id_lote' => $loteLosartan->id_lote,
            'id_medicamento' => $medLosartan->id_medicamento,
            'cantidad' => 76,
            'precio_unitario' => 7490,
            'subtotal_linea' => 120000,
        ]);
        SaleDetail::create([
            'id_venta' => $venta3->id_venta,
            'id_lote' => $loteAmoxicilina->id_lote,
            'id_medicamento' => $medAmoxicilina->id_medicamento,
            'cantidad' => 64,
            'precio_unitario' => 6990,
            'subtotal_linea' => 80000,
        ]);

        // 7. Registro Inicial en la Persistencia Documental NoSQL (MongoDB / BSON Audit Log - Página 39)
        $auditService = app(AuditLogService::class);
        $auditService->clearLogs();
        $auditService->recordIspAuthorization(
            $qf,
            [
                'folio_receta' => 'REC-2026-8840',
                'rut_paciente' => '18.455.120-K',
                'rut_medico' => '09.876.543-2',
                'medicamento' => 'Clonazepam 2mg',
                'lote_codigo' => 'LOT-2024-005',
            ],
            '192.168.10.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) FarmaNetPOS/2.4.1',
            'POS-BOX-02'
        );
    }
}
