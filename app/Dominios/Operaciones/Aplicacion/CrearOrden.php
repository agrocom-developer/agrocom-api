<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Comercial\Contratos\LecturaContrato;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\AplicacionesCompletas;
use App\Dominios\Operaciones\Dominio\Excepciones\ContratoConOrdenAbierta;
use App\Dominios\Operaciones\Dominio\Excepciones\ContratoNoAdmiteOrdenes;
use App\Dominios\Operaciones\Dominio\NumeracionAplicaciones;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Alta de una orden de aplicación desde el panel (HU-25, tarea 38).
 *
 * Reforma 19/9/2026 (pedido del dueño, ADR 0022): la orden es UNA aplicación
 * completa del contrato. Ya no se eligen lotes, hectáreas por lote ni número de
 * aplicación:
 * - el contrato tiene que estar `vigente` ({@see ContratoNoAdmiteOrdenes});
 * - el número lo calcula el servidor, correlativo por contrato, con una sola
 *   aplicación abierta por vez y sin pasar de las previstas
 *   ({@see NumeracionAplicaciones});
 * - los lotes son TODOS los del contrato, copiados con sus hectáreas completas
 *   a `ope_orden_lotes` — la orden conserva el conjunto sobre el que se emitió
 *   y el catálogo de la app de campo no cambia de forma.
 *
 * Toda orden nueva nace `emitida` (invariante 7) vía
 * {@see MaquinaEstadosOrden::crear()}; orden y lotes se escriben en la MISMA
 * transacción. Contra dos altas simultáneas sobre el mismo contrato responden
 * los índices únicos parciales de la base (una abierta por contrato, número
 * único), que se traducen acá a {@see ContratoConOrdenAbierta}.
 */
final class CrearOrden
{
    /** Datos de la orden que sí elige quien la emite; el resto los fija el sistema. */
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

    public function __construct(
        private readonly MaquinaEstadosOrden $maquinaEstados,
        private readonly LecturaContrato $lecturaContrato,
    ) {}

    /**
     * @param  array<string, mixed>  $atributos  solo los datos de la orden (tipo, insumo, dosis, equipos, contacto, fecha, observaciones); `contrato_id`, `nro_aplicacion` y `estado` los fija esta clase.
     *
     * @throws ContratoNoAdmiteOrdenes si el contrato no existe o no está `vigente`.
     * @throws ContratoConOrdenAbierta si el contrato ya tiene una aplicación abierta.
     * @throws AplicacionesCompletas si el contrato ya agotó sus aplicaciones previstas.
     */
    public function ejecutar(int $contratoId, array $atributos): OrdenAplicacion
    {
        $contrato = $this->lecturaContrato->obtenerParaOrden($contratoId);

        if ($contrato === null) {
            throw ContratoNoAdmiteOrdenes::noExiste($contratoId);
        }

        if (! $contrato->vigente) {
            throw ContratoNoAdmiteOrdenes::porEstado($contratoId);
        }

        try {
            return DB::transaction(function () use ($contrato, $contratoId, $atributos): OrdenAplicacion {
                $existentes = OrdenAplicacion::query()
                    ->where('contrato_id', $contratoId)
                    ->get(['id', 'nro_aplicacion', 'estado', 'causa_cancelacion'])
                    ->map(fn (OrdenAplicacion $orden): array => [
                        'nro' => $orden->nro_aplicacion,
                        'estado' => $orden->estado,
                        'causa' => $orden->causa_cancelacion,
                    ])
                    ->all();

                $nroAplicacion = NumeracionAplicaciones::siguiente($existentes, $contrato->aplicacionesPrevistas, $contratoId);

                $orden = $this->maquinaEstados->crear([
                    ...Arr::only($atributos, self::ATRIBUTOS_EDITABLES),
                    'contrato_id' => $contratoId,
                    'nro_aplicacion' => $nroAplicacion,
                ]);

                foreach ($contrato->lotes as $lote) {
                    $orden->ordenLotes()->create([
                        'lote_id' => $lote->loteId,
                        'hectareas_solicitadas' => $lote->hectareas,
                    ]);
                }

                return $orden;
            });
        } catch (UniqueConstraintViolationException) {
            throw ContratoConOrdenAbierta::porAltaSimultanea($contratoId);
        }
    }
}
