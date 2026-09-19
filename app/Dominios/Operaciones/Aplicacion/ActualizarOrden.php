<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\Excepciones\CorreccionOrdenNoPermitida;
use App\Dominios\Operaciones\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoEditable;
use App\Dominios\Operaciones\Dominio\PoliticaEdicionOrden;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Edición de una orden de aplicación (HU-25, tarea 38; ampliada por la adenda del
 * 19/9/2026 al ADR 0022, reglas en {@see PoliticaEdicionOrden}).
 *
 * Se corrige una orden ABIERTA (`emitida`, `vigente` o `pausada`). Sobre una ya
 * publicada la corrección exige un motivo, que queda en la orden
 * (`motivo_correccion`, `corregida_at`) y —con los valores antes y después— en la
 * bitácora de auditoría (`RegistraBitacora`, invariante 9); y el insumo (categoría
 * y dosis) no se puede cambiar si la orden ya tiene trabajos. Una `vigente` puede
 * estar en el catálogo de la app de campo: al cambiar `updated_at` la app recibe la
 * corrección en su siguiente sincronización, pero un vuelo en curso sigue con la
 * copia vieja — por eso el motivo y por eso el bloqueo del insumo.
 *
 * Las restricciones viven ACÁ (no solo en la vista — invariante 7), así un `PUT`
 * directo las respeta igual. `estado` nunca viaja en `$atributos`: el cambio de
 * estado es responsabilidad exclusiva de `MaquinaEstadosOrden`.
 *
 * Reforma 19/9/2026 (ADR 0022): el contrato, el número de aplicación y los
 * lotes NO se editan — la orden es la aplicación N de ESE contrato, con todos
 * sus lotes. Si se emitió sobre el contrato equivocado, se elimina y se emite
 * de nuevo. Solo se corrigen los datos de la aplicación (tipo, insumo, dosis,
 * equipos, contacto, fecha, observaciones). El estado se relee con la fila
 * bloqueada, para no editar una orden que otro operador acaba de cerrar.
 */
final class ActualizarOrden
{
    private const array ATRIBUTOS_EDITABLES = [
        'cantidad_equipos_necesarios',
        'tipo_aplicacion',
        'categoria_insumo_id',
        'kilos_por_vuelo',
        'litros_ha',
        'observaciones',
        'emitida_por_contacto_id',
        'fecha_emision',
    ];

    /**
     * @param  array<string, mixed>  $atributos  solo los datos editables; cualquier otra clave (contrato, número, estado) se ignora.
     * @param  string|null  $motivo  por qué se corrige; obligatorio si la orden ya no está `emitida`.
     *
     * @throws OrdenNoEditable si `$orden` está cerrada.
     * @throws MotivoRequerido si corrige una orden publicada sin decir por qué.
     * @throws CorreccionOrdenNoPermitida si cambia el insumo de una orden que ya tiene trabajos.
     */
    public function ejecutar(OrdenAplicacion $orden, array $atributos, ?string $motivo = null): OrdenAplicacion
    {
        return DB::transaction(function () use ($orden, $atributos, $motivo): OrdenAplicacion {
            $actual = OrdenAplicacion::query()->lockForUpdate()->findOrFail($orden->id);

            if (! PoliticaEdicionOrden::admiteEdicion($actual->estado)) {
                throw OrdenNoEditable::porEstado($actual->estado->value);
            }

            $nuevos = Arr::only($atributos, self::ATRIBUTOS_EDITABLES);
            $exigeMotivo = PoliticaEdicionOrden::exigeMotivo($actual->estado);
            $motivo = trim((string) $motivo);

            if ($exigeMotivo && $motivo === '') {
                throw MotivoRequerido::paraCorregir();
            }

            $tieneTrabajos = Trabajo::query()->where('orden_id', $actual->id)->exists();

            if (PoliticaEdicionOrden::bloqueaInsumo($tieneTrabajos) && PoliticaEdicionOrden::cambiaInsumo($actual->only(['categoria_insumo_id', 'kilos_por_vuelo', 'litros_ha']), $nuevos)) {
                throw CorreccionOrdenNoPermitida::insumoConTrabajos();
            }

            $actual->fill($nuevos);

            if ($exigeMotivo) {
                $actual->fill(['motivo_correccion' => $motivo, 'corregida_at' => now()]);
            }

            $actual->save();

            return $actual->refresh();
        });
    }
}
