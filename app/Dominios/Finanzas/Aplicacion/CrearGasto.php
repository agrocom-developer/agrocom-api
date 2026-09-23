<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Finanzas\Aplicacion\Concerns\GuardaComprobanteGasto;
use App\Dominios\Finanzas\Aplicacion\Concerns\VerificaCampaniaAbierta;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\UploadedFile;

/**
 * Alta de un gasto de campaña (HU-33, tarea 47): "como encargado, quiero
 * cargar gastos con su categoría y comprobante, para que la campaña tenga
 * costo real". Calcula `monto` con `Brick\Math\BigDecimal` (`cantidad ×
 * precio_unitario`, redondeo `HalfUp` a 2 decimales — mismo criterio que
 * `GenerarDevengosSesion`, invariante 6 de CLAUDE.md: nunca float) y, si vino
 * comprobante, lo guarda en el disco `r2` con su hash SHA-256, mismo criterio
 * de integridad que `Operaciones/Aplicacion/RegistrarEvidencia`.
 *
 * El `INSERT` va siempre antes del `Storage::put()` (mismo orden que
 * `RegistrarEvidencia`): la ruta final usa el `id` del gasto, que solo existe
 * después de crear la fila. A diferencia de esa clase, acá no hay
 * idempotencia por `uuid_cliente` que proteger — un alta humana del panel no
 * se reintenta sola.
 *
 * Editable después (tarea 134, `Aplicacion/ActualizarGasto`) mientras su
 * rendición asociada, si tiene una, siga `Abierta` — ver
 * `Dominio/PoliticaEdicionGasto`. `verificarCampaniaAbierta()`/
 * `guardarComprobante()` viven en `Concerns/` para que la edición los
 * reutilice sin duplicar la regla.
 *
 * `campaniaId` (ADR 0015 punto 6, tarea 69) es OPCIONAL — vacío es gasto
 * interno que no pertenece a ninguna campaña — y, si viene, no puede
 * apuntar a una campaña `cerrada` (misma guarda que
 * `Comercial\Aplicacion\CrearContrato`, leyendo vía
 * `Campania\Contratos\LecturaCampania`, ADR 0003 regla 2 — corrección de
 * arquitectura del 8/9/2026).
 *
 * `equipoTrabajoId` (tarea 73, HU-50) es OPCIONAL — el gasto general (sin
 * trabajo, sin base, sin equipo) sigue existiendo — FK plana a
 * `per_equipos_trabajo` (ADR 0003 regla 3), sin guarda adicional de negocio:
 * a diferencia del recurso de `CrearCombustible`, acá no hay "recurso que
 * tiene que pertenecer al equipo a esa fecha" que validar.
 */
final class CrearGasto
{
    use GuardaComprobanteGasto;
    use VerificaCampaniaAbierta;

    public function __construct(private readonly LecturaCampania $lecturaCampania) {}

    public function ejecutar(
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
        $this->verificarCampaniaAbierta($this->lecturaCampania, $campaniaId);

        $monto = (string) BigDecimal::of($cantidad)
            ->multipliedBy($precioUnitario)
            ->toScale(2, RoundingMode::HalfUp);

        $gasto = Gasto::query()->create([
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

        if ($comprobante !== null) {
            $this->guardarComprobante($gasto, $comprobante);
        }

        return $gasto->refresh();
    }
}
