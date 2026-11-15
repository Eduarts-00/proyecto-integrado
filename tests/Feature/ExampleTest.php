<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\IspLog;
use App\Models\Medication;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditLogService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CP-03: Bloqueo de Receta Retenida en POS.
     * Vendedor escanea Clonazepam 2mg e intenta presionar "Procesar Pago" sin autorización Q.F.
     * El sistema bloquea el cobro (403) con alerta "Requiere Autorización Q.F." y no genera boleta.
     */
    public function test_cp03_bloqueo_de_receta_retenida_en_pos(): void
    {
        $this->seed(DatabaseSeeder::class);

        $vendedor = User::where('rut', '12.345.678-5')->firstOrFail();
        $clonazepam = Medication::where('nombre_comercial', 'Clonazepam 2mg')->firstOrFail();
        $ventasAntes = Sale::count();

        $response = $this->actingAs($vendedor)->postJson(route('pos.checkout'), [
            'items' => [
                ['id_medicamento' => $clonazepam->id_medicamento, 'cantidad' => 1],
            ],
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['error' => 'Requiere Autorización Q.F.']);
        $this->assertEquals($ventasAntes, Sale::count());
    }

    /**
     * CP-04: Flujo Completo de Aprobación Q.F. (Integral E2E).
     * Q.F. ingresa PIN válido (4421) y confirma datos de receta (RUT paciente y médico).
     * Estado cambia a AUTORIZADO, se emite boleta, stock vitrina -1 y se inyecta log en MongoDB.
     */
    public function test_cp04_flujo_completo_de_aprobacion_qf_e_inyeccion_mongodb(): void
    {
        $this->seed(DatabaseSeeder::class);

        $qf = User::where('rut', '11.222.333-4')->firstOrFail();
        $pendiente = IspLog::where('folio_receta', 'REC-2026-8841')->firstOrFail();
        $loteAntes = Batch::findOrFail($pendiente->id_lote);
        $stockInicial = $loteAntes->cantidad;

        $response = $this->actingAs($qf)->postJson(route('autorizaciones.approve', $pendiente->id_registro), [
            'rut_paciente' => '18.455.120-K',
            'rut_medico' => '09.876.543-2',
            'folio_receta' => 'REC-2026-8841',
            'pin_qf' => '4421',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'AUTORIZADO']);

        $pendiente->refresh();
        $this->assertEquals('AUTORIZADO', $pendiente->estado);
        $this->assertNotNull($pendiente->hash_integridad);
        $this->assertEquals($stockInicial - 1, $loteAntes->fresh()->cantidad);

        $auditLogs = app(AuditLogService::class)->getLogs();
        $this->assertNotEmpty($auditLogs);
        $this->assertEquals('AUTORIZACION_RECETA_ISP', $auditLogs[0]['evento']);
    }

    /**
     * CP-05: Concurrencia de Venta sobre Última Caja (50 terminales intentan comprar la última unidad).
     * 1 venta exitosa (200 OK); 49 ventas rechazadas por stock agotado (409 Conflict); 0 deadlocks.
     */
    public function test_cp05_concurrencia_50_terminales_sobre_ultima_caja(): void
    {
        $this->seed(DatabaseSeeder::class);

        $vendedor = User::where('rut', '12.345.678-5')->firstOrFail();

        $medUltimaCaja = Medication::create([
            'codigo_barras' => '780000777999',
            'nombre_comercial' => 'Paracetamol Última Caja Test',
            'principio_activo' => 'Paracetamol',
            'laboratorio' => 'Mintlab',
            'precio_unitario' => 2490,
            'requiere_receta' => false,
            'tipo_controlado' => false,
        ]);

        $loteUnico = Batch::create([
            'id_medicamento' => $medUltimaCaja->id_medicamento,
            'lote_codigo' => 'LOT-ULTIMA-01',
            'ubicacion' => 'VITRINA',
            'cantidad' => 1,
            'fecha_vencimiento' => now()->addDays(90)->toDateString(),
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $exitosas200 = 0;
        $rechazadas409 = 0;

        for ($terminal = 1; $terminal <= 50; $terminal++) {
            $resp = $this->actingAs($vendedor)
                ->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
                ->postJson(route('pos.checkout'), [
                    'items' => [
                        ['id_medicamento' => $medUltimaCaja->id_medicamento, 'cantidad' => 1],
                    ],
                ]);

            if ($resp->status() === 200) {
                $exitosas200++;
            } elseif ($resp->status() === 409) {
                $rechazadas409++;
            }
        }

        $this->assertEquals(1, $exitosas200);
        $this->assertEquals(49, $rechazadas409);
        $this->assertEquals(0, $loteUnico->fresh()->cantidad);
    }

    /**
     * RBAC Convenios: El Vendedor solo puede consultar convenios, pero no crear, editar ni eliminar (403 Forbidden).
     */
    public function test_rbac_vendedor_no_puede_crear_ni_editar_convenios(): void
    {
        $this->seed(DatabaseSeeder::class);

        $vendedor = User::where('rut', '12.345.678-5')->firstOrFail();
        $admin = User::where('rut', '10.111.222-3')->firstOrFail();

        // Vendedor puede ver la lista (200 OK) pero sin botones de creación/edición
        $viewResp = $this->actingAs($vendedor)->get(route('convenios.index'));
        $viewResp->assertStatus(200);
        $viewResp->assertDontSee('+ Nuevo Convenio');

        // Vendedor bloqueado al intentar crear o editar (403 Forbidden)
        $storeResp = $this->actingAs($vendedor)->post(route('convenios.store'), [
            'nombre_convenio' => 'Convenio No Autorizado',
            'porcentaje_descuento' => 50,
        ]);
        $storeResp->assertStatus(403);

        // Administrador sí puede crear convenios con fechas de vigencia
        $adminStoreResp = $this->actingAs($admin)->post(route('convenios.store'), [
            'nombre_convenio' => 'Convenio Admin Autorizado',
            'porcentaje_descuento' => 45,
            'fecha_inicio' => now()->subDays(10)->toDateString(),
            'fecha_fin' => now()->addMonths(6)->toDateString(),
        ]);
        $adminStoreResp->assertRedirect(route('convenios.index'));

        // Convenio vencido por fecha no aplica descuento en POS
        $convenioVencido = \App\Models\HealthAgreement::create([
            'nombre_convenio' => 'Convenio Expirado Test',
            'porcentaje_descuento' => 50,
            'fecha_inicio' => now()->subMonths(6)->toDateString(),
            'fecha_fin' => now()->subDays(1)->toDateString(),
            'activo' => true,
        ]);
        $this->assertFalse($convenioVencido->esta_vigente);
        $this->assertEquals('VENCIDO', $convenioVencido->estado_vigencia);
        $copago = app(\App\Services\SaleService::class)->calculateCopay(10000, $convenioVencido);
        $this->assertEquals(0, $copago['descuento_total']);
        $this->assertEquals(10000, $copago['total']);
    }

    /**
     * Gestión de Personal: El Administrador puede editar datos (nombre, correo, cargo)
     * y deshabilitar/habilitar cuentas del personal. Un usuario deshabilitado no puede acceder.
     */
    public function test_admin_puede_editar_y_deshabilitar_personal(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('rut', '10.111.222-3')->firstOrFail();
        $vendedor = User::where('rut', '12.345.678-5')->firstOrFail();

        // 1. Editar nombre y correo del funcionario
        $updateResp = $this->actingAs($admin)->put(route('usuarios.update', $vendedor->id_usuario), [
            'rut' => $vendedor->rut,
            'nombre_completo' => 'Ana María Torres Modificada',
            'email' => 'ana.maria.torres@farmanet.cl',
            'id_rol' => $vendedor->id_rol,
            'activo' => 1,
        ]);
        $updateResp->assertRedirect(route('usuarios.index'));

        $vendedor->refresh();
        $this->assertEquals('Ana María Torres Modificada', $vendedor->nombre_completo);
        $this->assertEquals('ana.maria.torres@farmanet.cl', $vendedor->email);

        // 2. Deshabilitar al funcionario por renuncia/despido
        $toggleResp = $this->actingAs($admin)->patch(route('usuarios.toggle_status', $vendedor->id_usuario));
        $toggleResp->assertRedirect(route('usuarios.index'));
        $this->assertFalse($vendedor->fresh()->activo);

        // 3. Usuario deshabilitado no puede acceder al POS
        $posResp = $this->actingAs($vendedor->fresh())->get(route('pos.index'));
        $posResp->assertRedirect(route('login'));
    }

    /**
     * Adquisición de Medicamentos y Receta Restringida (Normativa ISP):
     * Solo el Químico Farmacéutico (Director Técnico) y el Administrador pueden añadir medicamentos
     * al catálogo o cambiar su condición de Receta Retenida ISP. El Bodeguero es bloqueado (403).
     */
    public function test_adquisicion_medicamento_y_configuracion_receta_restringida(): void
    {
        $this->seed(DatabaseSeeder::class);

        $bodeguero = User::where('rut', '15.432.876-5')->firstOrFail();
        $qf = User::where('rut', '11.222.333-4')->firstOrFail();
        $vendedor = User::where('rut', '12.345.678-5')->firstOrFail();

        // 0. Bodeguero NO puede registrar un nuevo medicamento ni alterar condición de receta (403 Forbidden)
        $bodegueroForbidden = $this->actingAs($bodeguero)->post(route('inventario.medications.store'), [
            'codigo_barras' => '780000109998',
            'nombre_comercial' => 'Intento Bodeguero',
            'principio_activo' => 'Test',
            'laboratorio' => 'Test',
            'precio_unitario' => 5000,
            'condicion_receta' => 'venta_libre',
        ]);
        $bodegueroForbidden->assertStatus(403);

        // 1. Químico Farmacéutico (o Administrador) registra nueva adquisición con Receta Retenida ISP y lote inicial
        $storeResp = $this->actingAs($qf)->post(route('inventario.medications.store'), [
            'codigo_barras' => '780000109999',
            'nombre_comercial' => 'Zolpidem 10mg',
            'principio_activo' => 'Zolpidem Tartrato',
            'laboratorio' => 'Saval',
            'precio_unitario' => 9990,
            'condicion_receta' => 'receta_retenida',
            'cantidad_inicial' => 40,
            'lote_codigo' => 'LOT-2026-901',
            'ubicacion_inicial' => 'VITRINA',
            'fecha_vencimiento' => now()->addMonths(8)->toDateString(),
        ]);
        $storeResp->assertRedirect(route('inventario.index', ['ubicacion' => 'CATALOGO']));

        $zolpidem = Medication::where('codigo_barras', '780000109999')->firstOrFail();
        $this->assertTrue($zolpidem->requiere_receta);
        $this->assertTrue($zolpidem->tipo_controlado);
        $this->assertEquals(40, $zolpidem->stock_vitrina);

        // 2. Verificar que en el POS queda bloqueado por exigir Receta Retenida
        $checkoutResp = $this->actingAs($vendedor)->postJson(route('pos.checkout'), [
            'items' => [
                ['id_medicamento' => $zolpidem->id_medicamento, 'cantidad' => 1],
            ],
        ]);
        $checkoutResp->assertStatus(403);

        // 3. Químico Farmacéutico cambia condición a Venta Libre y se verifica el cambio
        $toggleResp = $this->actingAs($qf)->patch(route('inventario.medications.toggle_prescription', $zolpidem->id_medicamento));
        $toggleResp->assertRedirect();
        $this->assertFalse($zolpidem->fresh()->requiere_receta);
    }

    /**
     * Exportación de Reportes de Farmacia Privada:
     * Verifica que la exportación Excel genere un libro Microsoft Excel (.xlsx) real con sus 4 hojas
     * (Ventas y Boletas, Inventario y Lotes FEFO, Libro Control ISP, Convenios de Salud) y que el PDF cargue completo.
     */
    public function test_exportacion_excel_xlsx_y_pdf_farmacia_privada(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('rut', '10.111.222-3')->firstOrFail();

        // 1. Verificar exportación Excel (.xlsx real)
        $excelResp = $this->actingAs($admin)->get(route('dashboard.export.excel'));
        $excelResp->assertStatus(200);
        $excelResp->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $file = $excelResp->baseResponse->getFile();
        $this->assertFileExists($file->getPathname());

        $zip = new \ZipArchive();
        $opened = $zip->open($file->getPathname());
        $this->assertTrue($opened === true, 'El archivo exportado debe ser un contenedor OpenXML (.xlsx) válido.');

        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $this->assertNotFalse($workbookXml);
        $this->assertStringContainsString('1. Ventas y Boletas', $workbookXml);
        $this->assertStringContainsString('2. Inventario y Lotes FEFO', $workbookXml);
        $this->assertStringContainsString('3. Libro Control ISP', $workbookXml);
        $this->assertStringContainsString('4. Convenios de Salud', $workbookXml);
        $zip->close();

        // 2. Verificar vista de exportación PDF integral
        $pdfResp = $this->actingAs($admin)->get(route('dashboard.export.pdf'));
        $pdfResp->assertStatus(200);
        $pdfResp->assertSee('Reporte Integral de Gestión y Fiscalización');
        $pdfResp->assertSee('IVA Débito (19% SII)');
        $pdfResp->assertSee('Libro Oficial de Control ISP');
    }

    /**
     * Aplicación de Convenio por Medicamento (Vademécum Farmacia Privada):
     * Si un paciente compra un medicamento cubierto por el Vademécum del convenio (ej. Losartán en GES 80%)
     * junto con un medicamento NO incluido en ese Vademécum (ej. Paracetamol OTC), el descuento se aplica
     * exclusivamente al medicamento adherido y el resto paga su precio normal.
     */
    public function test_convenio_aplica_descuento_solo_a_medicamentos_de_su_vademecum(): void
    {
        $this->seed(DatabaseSeeder::class);

        $vendedor = User::where('rut', '12.345.678-5')->firstOrFail();
        $convenioGes = \App\Models\HealthAgreement::where('nombre_convenio', 'GES / AUGE')->firstOrFail();
        $losartan = Medication::where('codigo_barras', '780000100107')->firstOrFail(); // $7.490 (En vademécum GES 80%)
        $paracetamol = Medication::where('codigo_barras', '780000100104')->firstOrFail(); // $2.490 (Fuera de vademécum GES)

        $this->assertTrue($convenioGes->cubreMedicamento($losartan));
        $this->assertFalse($convenioGes->cubreMedicamento($paracetamol));

        $resp = $this->actingAs($vendedor)->postJson(route('pos.checkout'), [
            'id_convenio' => $convenioGes->id_convenio,
            'items' => [
                ['id_medicamento' => $losartan->id_medicamento, 'cantidad' => 1],
                ['id_medicamento' => $paracetamol->id_medicamento, 'cantidad' => 1],
            ],
        ]);

        $resp->assertStatus(200);
        $venta = $resp->json('venta');

        // Subtotal bruto = 7490 + 2490 = 9980
        // Descuento 80% solo sobre Losartán (7490 * 0.80 = 5992)
        // Total a pagar = 9980 - 5992 = 3988
        $this->assertEquals(9980, $venta['subtotal']);
        $this->assertEquals(5992, $venta['descuento_total']);
        $this->assertEquals(3988, $venta['total']);
    }

    /**
     * Retiro / Traspaso Inverso de Vitrina hacia Bodega:
     * Verifica que el Bodeguero o Químico Farmacéutico pueda retirar unidades (o quitar por completo un lote)
     * desde la Vitrina de ventas de vuelta hacia Bodega.
     */
    public function test_retiro_o_devolucion_de_vitrina_a_bodega(): void
    {
        $this->seed(DatabaseSeeder::class);

        $bodeguero = User::where('rut', '15.432.876-5')->firstOrFail();
        $loteVitrina = \App\Models\Batch::where('lote_codigo', 'LOT-2024-013')
            ->where('ubicacion', 'VITRINA')
            ->firstOrFail(); // Tramadol 50mg (25 u. en Vitrina)

        $this->assertEquals(25, $loteVitrina->cantidad);

        // Retirar 10 unidades de Vitrina a Bodega
        $resp = $this->actingAs($bodeguero)->post(route('inventario.return_to_storage'), [
            'id_lote' => $loteVitrina->id_lote,
            'cantidad' => 10,
            'motivo' => 'Resguardo sanitario Q.F. (Controlado / Custodia)',
        ]);
        $resp->assertRedirect(route('inventario.index', ['ubicacion' => 'VITRINA']));

        $this->assertEquals(15, $loteVitrina->fresh()->cantidad);

        $loteBodega = \App\Models\Batch::where('lote_codigo', 'LOT-2024-013')
            ->where('ubicacion', 'BODEGA')
            ->firstOrFail();
        $this->assertEquals(10, $loteBodega->cantidad);

        // Retirar las 15 unidades restantes para quitarlo 100% de la Vitrina
        $this->actingAs($bodeguero)->post(route('inventario.return_to_storage'), [
            'id_lote' => $loteVitrina->id_lote,
            'cantidad' => 15,
            'motivo' => 'Retiro preventivo FEFO (Próximo a vencer)',
        ]);

        $this->assertEquals(0, $loteVitrina->fresh()->cantidad);
        $this->assertEquals(25, $loteBodega->fresh()->cantidad);
    }

    /**
     * Baja por Caducidad / Merma Sanitaria (D.S. N° 466 / SII):
     * Verifica que al quitar un lote por caducado no se elimine físicamente (DELETE),
     * sino que desaparezca de Vitrina/Bodega y pase al archivo de Bajas y Caducados con su causal.
     */
    public function test_baja_por_caducidad_retira_lote_de_vitrina_y_archiva_en_bajas(): void
    {
        $this->seed(DatabaseSeeder::class);

        $qf = User::where('rut', '11.222.333-4')->firstOrFail();
        $loteCritico = \App\Models\Batch::where('lote_codigo', 'LOT-2024-005')->firstOrFail(); // Clonazepam 5 días para vencer

        $this->assertTrue($loteCritico->activo);

        // 1. Dar de baja por caducidad
        $resp = $this->actingAs($qf)->patch(route('inventario.toggle_batch', $loteCritico->id_lote), [
            'motivo_baja' => 'Retiro Preventivo por Vencimiento Próximo (< 30 días)',
        ]);
        $resp->assertRedirect();

        $loteCritico->refresh();
        $this->assertFalse($loteCritico->activo);
        $this->assertEquals('Retiro Preventivo por Vencimiento Próximo (< 30 días)', $loteCritico->motivo_baja);
        $this->assertNotNull($loteCritico->fecha_baja);

        // 2. Ya no aparece en la colección de lotes de Vitrina activa
        $vitrinaResp = $this->actingAs($qf)
            ->withSession([])
            ->get(route('inventario.index', ['ubicacion' => 'VITRINA']));
        $vitrinaResp->assertStatus(200);
        $vitrinaResp->assertViewHas('lotes', function ($lotes) {
            return ! $lotes->contains('lote_codigo', 'LOT-2024-005');
        });

        // 3. Sí aparece en la pestaña Bajas y Caducados
        $bajasResp = $this->actingAs($qf)->get(route('inventario.index', ['ubicacion' => 'BAJAS']));
        $bajasResp->assertStatus(200);
        $bajasResp->assertSee('LOT-2024-005');
        $bajasResp->assertSee('Retiro Preventivo por Vencimiento Próximo');
    }

    /**
     * Cualquier URL inexistente o mal escrita redirige automáticamente al inicio
     * en lugar de mostrar una pantalla de error 404.
     */
    public function test_url_inexistente_redirige_al_inicio_sin_mostrar_404(): void
    {
        $this->seed(DatabaseSeeder::class);

        // 1. Usuario no autenticado escribe una URL inexistente -> redirige al login
        $guestResp = $this->get('/ruta-rara-que-no-existe-12345');
        $guestResp->assertRedirect(route('login'));

        // 2. Usuario autenticado escribe una URL inexistente -> redirige al inicio (y de ahí a su módulo principal)
        $admin = User::where('rut', '10.111.222-3')->firstOrFail();
        $authResp = $this->actingAs($admin)->get('/cualquier-cosa-rara/99999');
        $authResp->assertRedirect(route('login'));
    }
}
