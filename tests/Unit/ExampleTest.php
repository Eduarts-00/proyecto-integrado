<?php

namespace Tests\Unit;

use App\Models\Batch;
use App\Models\HealthAgreement;
use App\Models\Medication;
use App\Services\InventoryService;
use App\Services\RutValidator;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CP-01: Ordenamiento de Lotes FEFO (First Expired, First Out).
     * Medicamento con 3 lotes: L1 (vence en 30d), L2 (vence en 10d), L3 (vence en 60d).
     * getFefoBatch() debe retornar L2 en primer lugar y descontar en orden FEFO.
     */
    public function test_cp01_ordenamiento_y_descuento_de_lotes_fefo(): void
    {
        $med = Medication::create([
            'codigo_barras' => '780000999001',
            'nombre_comercial' => 'Amoxicilina Test FEFO',
            'principio_activo' => 'Amoxicilina',
            'laboratorio' => 'Bagó',
            'precio_unitario' => 5000,
            'requiere_receta' => false,
            'tipo_controlado' => false,
        ]);

        $l1 = Batch::create([
            'id_medicamento' => $med->id_medicamento,
            'lote_codigo' => 'L1-NOV',
            'ubicacion' => 'VITRINA',
            'cantidad' => 10,
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $l2 = Batch::create([
            'id_medicamento' => $med->id_medicamento,
            'lote_codigo' => 'L2-OCT',
            'ubicacion' => 'VITRINA',
            'cantidad' => 5,
            'fecha_vencimiento' => now()->addDays(10)->toDateString(),
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $l3 = Batch::create([
            'id_medicamento' => $med->id_medicamento,
            'lote_codigo' => 'L3-DIC',
            'ubicacion' => 'VITRINA',
            'cantidad' => 15,
            'fecha_vencimiento' => now()->addDays(60)->toDateString(),
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $inventoryService = app(InventoryService::class);
        $fefoBatch = $inventoryService->getFefoBatch($med->id_medicamento, 'VITRINA');

        $this->assertNotNull($fefoBatch);
        $this->assertEquals($l2->id_lote, $fefoBatch->id_lote);
        $this->assertEquals('L2-OCT', $fefoBatch->lote_codigo);

        $inventoryService->deductByFefo($med->id_medicamento, 2, 'VITRINA');
        $this->assertEquals(3, $l2->fresh()->cantidad);
        $this->assertEquals(10, $l1->fresh()->cantidad);
        $this->assertEquals(15, $l3->fresh()->cantidad);
    }

    /**
     * CP-02: Cálculo de Copago Convenio GES (80% de cobertura sobre $10.000).
     * Debe liquidar copago Total = $2.000 y descuento de $8.000 preservando IVA.
     */
    public function test_cp02_calculo_de_copago_convenio_ges(): void
    {
        $convenioGes = HealthAgreement::create([
            'nombre_convenio' => 'GES / AUGE',
            'porcentaje_descuento' => 80,
            'activo' => true,
        ]);

        $saleService = app(SaleService::class);
        $liquidacion = $saleService->calculateCopay(10000, $convenioGes);

        $this->assertEquals(10000, $liquidacion['subtotal']);
        $this->assertEquals(8000, $liquidacion['descuento_total']);
        $this->assertEquals(2000, $liquidacion['total']);
        $this->assertGreaterThan(0, $liquidacion['iva']);
    }

    /**
     * Validación Matemática RUN Módulo 11 (Capítulo 7.1).
     */
    public function test_validacion_matematica_rut_modulo_11(): void
    {
        $this->assertTrue(RutValidator::validate('12.345.678-5'));
        $this->assertTrue(RutValidator::validate('11.222.333-4'));
        $this->assertFalse(RutValidator::validate('12.345.678-0'));
    }
}
