<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Finanzas\Aplicacion\Concerns\GuardaComprobanteGasto;
use App\Dominios\Finanzas\Aplicacion\Concerns\VerificaCampaniaAbierta;
use App\Dominios\Finanzas\Dominio\Excepciones\GastoNoEditable;
use App\Dominios\Finanzas\Dominio\PoliticaEdicionGasto;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Edición de un gasto de campaña (tarea 134, queja del dueño del 22/9/2026:
 * "casi nada de Finanzas se puede corregir después de creado"). Mismos datos
 * y mismas guardas de forma que `CrearGasto` (`verificarCampaniaAbierta()`,
 * `guardarComprobante()` vía `Concerns/`, mismo cálculo de `monto` con
 * `Brick\Math\BigDecimal`), más la guarda de fondo que solo aplica a la
 * edición: {@see PoliticaEdicionGasto} — no se toca un gasto cuya rendición
 * ya congeló su `monto` (invariante 2 de CLAUDE.md).
 *
 * La fila se relee con lock (`lockForUpdate`) para no editar un gasto que
 * otro operador acaba de asociar a una rendición que ya se presentó, mismo
 * criterio que `Operaciones/Aplicacion/ActualizarOrden`.
 *
 * Sin comprobante nuevo, se conserva el que ya tenía — el campo es opcional
 * en edición igual que en el alta.
 */
final class ActualizarGasto
{
    use GuardaComprobanteGasto;
    use VerificaCampaniaAbierta;

    public function __construct(private readonly LecturaCampania $lecturaCampania) {}

    /** @throws GastoNoEditable si la rendición asociada ya no está `Abierta`. */
    public function ejecutar(
        Gasto $gasto,
        string $fecha,
        int $rubroId,
        ?int $subrubroId,
        string $cantidad,
        string $precioUnitario,
        ?int $baseId,
        ?int $trabajoId,
        ?int $campaniaId,
        ?UploadedFile $comprobante,
        ?int $equipoTrabajoId = null,
    ): Gasto {
        return DB::transaction(function () use (
            $gasto,
            $fecha,
            $rubroId,
            $subrubroId,
            $cantidad,
            $precioUnitario,
            $baseId,
            $trabajoId,
            $campaniaId,
            $comprobante,
            $equipoTrabajoId,
        ): Gasto {
            $actual = Gasto::query()->lockForUpdate()->findOrFail($gasto->id);
            $rendicion = $actual->rendicion;

            if ($rendicion !== null && ! PoliticaEdicionGasto::admiteEdicion($rendicion->estado)) {
                throw GastoNoEditable::porRendicion($rendicion->id, $rendicion->estado->value);
            }

            $this->verificarCampaniaAbierta($this->lecturaCampania, $campaniaId);

            $monto = (string) BigDecimal::of($cantidad)
                ->multipliedBy($precioUnitario)
                ->toScale(2, RoundingMode::HalfUp);

            $actual->fill([
                'fecha' => $fecha,
                'rubro_id' => $rubroId,
                'subrubro_id' => $subrubroId,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'monto' => $monto,
                'base_id' => $baseId,
                'trabajo_id' => $trabajoId,
                'campania_id' => $campaniaId,
                'equipo_trabajo_id' => $equipoTrabajoId,
            ]);
            $actual->save();

            if ($comprobante !== null) {
                $this->guardarComprobante($actual, $comprobante);
            }

            return $actual->refresh();
        });
    }
}
