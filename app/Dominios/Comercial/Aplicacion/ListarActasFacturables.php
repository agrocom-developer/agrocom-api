<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Factura;
use App\Dominios\Operaciones\Contratos\DatosActaConformada;
use App\Dominios\Operaciones\Contratos\LecturaActaConformada;

/**
 * Actas firmadas todavía sin facturar, para el `<select>` de
 * `FacturasController::create()` (HU-31, tarea 45). `LecturaActaConformada`
 * (Operaciones) solo sabe listar actas firmadas — cuáles de esas ya tienen
 * factura es un dato de `com_facturas`, propio de este módulo, así que el
 * filtro final se hace acá, no en Operaciones (ADR 0003, regla 1).
 *
 * Enriquece cada acta con el nombre del cliente (vía `Contrato->cliente`,
 * ambos propios de este módulo) para que el select sea legible — sin eso,
 * el encargado solo vería un `acta_id` crudo.
 */
final class ListarActasFacturables
{
    public function __construct(private readonly LecturaActaConformada $lecturaActa) {}

    /** @return list<array{actaId: int, contratoId: int, hectareasConformadas: string, clienteNombre: string}> */
    public function ejecutar(): array
    {
        $actasFirmadas = $this->lecturaActa->listarFirmadas();

        if ($actasFirmadas === []) {
            return [];
        }

        $actaIdsYaFacturados = Factura::query()->pluck('acta_id')->all();

        $actasDisponibles = array_values(array_filter(
            $actasFirmadas,
            fn (DatosActaConformada $acta): bool => ! in_array($acta->actaId, $actaIdsYaFacturados, true),
        ));

        if ($actasDisponibles === []) {
            return [];
        }

        $contratoIds = array_values(array_unique(array_map(
            fn (DatosActaConformada $acta): int => $acta->contratoId,
            $actasDisponibles,
        )));

        $clientesPorContrato = Contrato::query()
            ->whereIn('id', $contratoIds)
            ->with('cliente:id,razon_social')
            ->get()
            ->mapWithKeys(fn (Contrato $contrato): array => [
                $contrato->id => $contrato->cliente->razon_social,
            ]);

        return array_map(fn (DatosActaConformada $acta): array => [
            'actaId' => $acta->actaId,
            'contratoId' => $acta->contratoId,
            'hectareasConformadas' => $acta->hectareasConformadas,
            'clienteNombre' => $clientesPorContrato[$acta->contratoId] ?? "Contrato #{$acta->contratoId}",
        ], $actasDisponibles);
    }
}
