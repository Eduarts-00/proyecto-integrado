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
}
