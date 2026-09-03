<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\ActaNoFacturable;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Factura;
use App\Dominios\Operaciones\Contratos\DatosActaConformada;
use App\Dominios\Operaciones\Contratos\LecturaActaConformada;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * `POST /panel/facturas` (HU-31, tarea 45): "como encargado, quiero emitir
 * la factura de un trabajo desde su acta conformada, para cobrar sobre
 * hectáreas ya firmadas". El acta se resuelve por
 * `Operaciones\Contratos\LecturaActaConformada` — este caso de uso nunca
 * importa `Acta`, `Trabajo` ni `OrdenAplicacion` (ADR 0003, regla 2).
 *
 * Dos guardas de negocio, ambas en {@see ActaNoFacturable}: el acta debe
 * existir y estar `firmada`; no puede existir ya una factura viva para esa
 * `acta_id`. A diferencia de `GenerarActaTrabajo`/`GenerarPlanilla` (que son
 * idempotentes y devuelven la fila existente ante un reintento), acá un
 * segundo pedido se RECHAZA explícito: el criterio de aceptación pide "no
 * permite facturar dos veces el mismo trabajo", no "un segundo pedido no
 * hace nada".
 *
 * El chequeo previo (`Factura::where('acta_id', ...)->first()`, bajo
 * `lockForUpdate()`) resuelve el caso común; el `catch (QueryException)` de
 * abajo es la defensa real contra la carrera de dos pedidos concurrentes
 * sobre la MISMA acta, que solo puede resolverse en el `UNIQUE` de
 * `acta_id` — mismo patrón de doble defensa que `GenerarPlanilla`.
 *
 * `monto` se calcula con `Brick\Math\BigDecimal` (invariante 6 de
 * CLAUDE.md, nunca `float`) y se persiste congelado junto con
 * `hectareas_facturadas`/`precio_ha`: no se recalcula después contra el
 * contrato o el acta actuales.
 */
final class EmitirFactura
{
    public function __construct(private readonly LecturaActaConformada $lecturaActa) {}

    /**
     * @throws ActaNoFacturable si el acta no existe, no está firmada, o ya tiene una factura viva.
     */
    public function ejecutar(int $actaId): Factura
    {
        $datosActa = $this->lecturaActa->obtenerPorActaId($actaId);

        if ($datosActa === null) {
            throw ActaNoFacturable::porNoExistir($actaId);
        }

        if (! $datosActa->firmada) {
            throw ActaNoFacturable::porNoEstarFirmada($actaId);
        }

        try {
            return DB::transaction(fn (): Factura => $this->emitirBajoLock($datosActa));
        } catch (QueryException $excepcion) {
            $existente = Factura::query()->where('acta_id', $actaId)->first();

            if ($existente !== null) {
                throw ActaNoFacturable::porYaFacturada($actaId);
            }

            throw $excepcion;
        }
    }

    private function emitirBajoLock(DatosActaConformada $datosActa): Factura
    {
        $existente = Factura::query()->where('acta_id', $datosActa->actaId)->lockForUpdate()->first();

        if ($existente !== null) {
            throw ActaNoFacturable::porYaFacturada($datosActa->actaId);
        }

        /** @var Contrato $contrato */
        $contrato = Contrato::query()->findOrFail($datosActa->contratoId);

        $monto = BigDecimal::of($datosActa->hectareasConformadas)
            ->multipliedBy($contrato->precio_ha)
            ->toScale(2, RoundingMode::HalfUp);

        return Factura::create([
            'contrato_id' => $contrato->id,
            'acta_id' => $datosActa->actaId,
            'hectareas_facturadas' => $datosActa->hectareasConformadas,
            'precio_ha' => $contrato->precio_ha,
            'monto' => (string) $monto,
            'fecha_emision' => Carbon::now()->toDateString(),
        ]);
    }
}
