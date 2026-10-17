<?php

namespace App\Http\Controllers;

use App\Models\HealthAgreement;
use Illuminate\Http\Request;

class HealthAgreementController extends Controller
{
    public function index()
    {
        $convenios = HealthAgreement::orderBy('id_convenio', 'asc')->get();

        return view('convenios.index', compact('convenios'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre_convenio' => ['required', 'string', 'max:100'],
            'porcentaje_descuento' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        HealthAgreement::create([
            'nombre_convenio' => trim($data['nombre_convenio']),
            'porcentaje_descuento' => (float) $data['porcentaje_descuento'],
            'activo' => true,
        ]);

        return redirect()->route('convenios.index')
            ->with('success', "Convenio '{$data['nombre_convenio']}' registrado exitosamente.");
    }

    public function update(Request $request, int $idConvenio)
    {
        $data = $request->validate([
            'nombre_convenio' => ['required', 'string', 'max:100'],
            'porcentaje_descuento' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $convenio = HealthAgreement::findOrFail($idConvenio);
        $convenio->update([
            'nombre_convenio' => trim($data['nombre_convenio']),
            'porcentaje_descuento' => (float) $data['porcentaje_descuento'],
        ]);

        return redirect()->route('convenios.index')
            ->with('success', "Convenio '{$convenio->nombre_convenio}' actualizado.");
    }

    public function destroy(int $idConvenio)
    {
        $convenio = HealthAgreement::findOrFail($idConvenio);
        $nombre = $convenio->nombre_convenio;
        $convenio->delete();

        return redirect()->route('convenios.index')
            ->with('success', "Convenio '{$nombre}' eliminado.");
    }
}
