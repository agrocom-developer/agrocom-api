<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Campania\Dominio\Excepciones\CampaniaCerradaNoAdmiteImputaciones;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
 * Inmutable salvo baja (misma decisión que `RegistrarAnticipo`, ver docblock
 * de `Gasto`): no hay caso de uso de edición — un gasto cargado no se edita,
 * si está mal se da de baja (`EliminarGasto`) y se recarga. Evita que un
 * gasto ya asociado a una rendición (HU-34, `fin_rendiciones`, todavía sin
 * columna `rendicion_id`) cambie de monto por debajo de una rendición en
 * curso.
 *
 * `campaniaId` (ADR 0015 punto 6, tarea 69) es OPCIONAL — vacío es gasto
 * interno que no pertenece a ninguna campaña — y, si viene, no puede
 * apuntar a una campaña `cerrada` (misma guarda que
 * `Comercial\Aplicacion\CrearContrato`, leyendo `cpn_campanias` con
 * `DB::table`, ADR 0003 regla 3).
 */
final class CrearGasto
{
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
    ): Gasto {
        $this->verificarCampania($campaniaId);

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
        ]);

        if ($comprobante !== null) {
            $this->guardarComprobante($gasto, $comprobante);
        }

        return $gasto->refresh();
    }

    /** @throws CampaniaCerradaNoAdmiteImputaciones si la campaña elegida está `cerrada`. */
    private function verificarCampania(?int $campaniaId): void
    {
        if ($campaniaId === null) {
            return;
        }

        $campania = DB::table('cpn_campanias')->where('id', $campaniaId)->first();

        if ($campania !== null && $campania->estado === 'cerrada') {
            throw CampaniaCerradaNoAdmiteImputaciones::paraCampania($campania->codigo);
        }
    }

    private function guardarComprobante(Gasto $gasto, UploadedFile $comprobante): void
    {
        $hash = (string) hash_file('sha256', $comprobante->getRealPath());
        $extension = $comprobante->extension() ?: 'bin';
        $momento = Carbon::parse($gasto->fecha);

        $ruta = sprintf(
            'gastos/%s/%s/%d.%s',
            $momento->format('Y'),
            $momento->format('m'),
            $gasto->id,
            $extension,
        );

        Storage::disk('r2')->put($ruta, (string) file_get_contents($comprobante->getRealPath()));

        $gasto->update([
            'comprobante_url' => $ruta,
            'comprobante_hash' => $hash,
        ]);
    }
}
