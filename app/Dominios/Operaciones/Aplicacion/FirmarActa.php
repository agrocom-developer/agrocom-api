<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosActa;
use App\Dominios\Operaciones\Dominio\EstadoActa;
use App\Dominios\Operaciones\Dominio\Excepciones\FirmaActaNoDisponible;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * `POST /api/actas/{uuid_cliente}/firmar` (HU-17, tarea 24): registra la
 * firma del agrónomo sobre un acta `pendiente`, referenciando una evidencia
 * ya subida por `POST /api/evidencias` (tarea 19) — nunca un blob binario
 * acá (ver "Qué NO hacer" del prompt de la tarea). La firma en pantalla real
 * (o la foto del acta física) es responsabilidad de `agrocom-field`; este
 * caso de uso solo recibe la referencia.
 *
 * Idempotencia de la MUTACIÓN (a diferencia de `GenerarActaTrabajo`, acá sí
 * hay un "evento" que puede repetirse, mismo criterio que
 * `EscrituraSincronizacionEloquent::cerrarTrabajo()`): un acta ya `firmada`
 * con la MISMA evidencia es un reintento del mismo evento → se devuelve tal
 * cual, sin reescribir nada; con OTRA evidencia es un conflicto → se
 * rechaza. No hay un `firma_uuid_cliente` propio: el `evidencia_firma_id`
 * ya es único por acta (índice parcial de la migración), así que sirve como
 * huella del evento sin agregar una columna más.
 *
 * El `catch (QueryException)` cubre lo que el `lockForUpdate()` por acta NO
 * serializa: dos firmas concurrentes de DOS actas distintas con la MISMA
 * evidencia (hallazgo de la revisión crítica de esta tarea) — cada
 * transacción bloquea su propia fila de `Acta`, así que ambas pueden pasar
 * el chequeo `$yaUsadaPorOtraActa` antes de que la otra confirme.
 *
 * `$generarReporte` (HU-18, tarea 25): sin evento de dominio `ActaFirmada`
 * que enganchar (la tarea 24 no lo dejó — `MaquinaEstadosActa::firmar()` solo
 * muta y guarda, verificado en el código, no asumido), este caso de uso es
 * el único punto donde la transición `pendiente → firmada` ocurre de
 * verdad — así que es también el único lugar correcto para disparar la
 * generación automática del reporte técnico, dentro de la MISMA transacción
 * (ver runs/25.md). Se llama solo en la rama que transiciona de verdad,
 * nunca en el reintento idempotente de arriba (`return $actaLock;`) ni en el
 * `catch` de conflicto concurrente: en esos dos casos el reporte ya lo generó
 * la firma que ganó la carrera.
 */
final class FirmarActa
{
    public function __construct(
        private readonly MaquinaEstadosActa $maquina,
        private readonly GenerarReporteTecnico $generarReporte,
    ) {}

    /**
     * @throws FirmaActaNoDisponible si la evidencia no sirve, o el acta ya está firmada con otra.
     */
    public function ejecutar(Acta $acta, string $evidenciaFirmaUuidCliente, string $firmante, string $fechaFirma): Acta
    {
        try {
            return DB::transaction(function () use ($acta, $evidenciaFirmaUuidCliente, $firmante, $fechaFirma): Acta {
                /** @var Acta $actaLock */
                $actaLock = Acta::query()->whereKey($acta->id)->lockForUpdate()->firstOrFail();

                $evidencia = Evidencia::query()->where('uuid_cliente', $evidenciaFirmaUuidCliente)->first();

                if ($evidencia === null || $evidencia->tipo !== TipoEvidencia::FirmaActa) {
                    throw FirmaActaNoDisponible::porEvidenciaInexistenteOTipoInvalido();
                }

                if ($actaLock->estado === EstadoActa::Firmada) {
                    if ((int) $actaLock->evidencia_firma_id === $evidencia->id) {
                        return $actaLock;
                    }

                    throw FirmaActaNoDisponible::porActaYaFirmadaConOtraEvidencia($actaLock->id);
                }

                $yaUsadaPorOtraActa = Acta::query()
                    ->where('evidencia_firma_id', $evidencia->id)
                    ->whereKeyNot($actaLock->id)
                    ->exists();

                if ($yaUsadaPorOtraActa) {
                    throw FirmaActaNoDisponible::porEvidenciaYaUsada($evidencia->id);
                }

                $actaFirmada = $this->maquina->firmar($actaLock, $evidencia->id, $firmante, $fechaFirma);
                $this->generarReporte->ejecutar($actaFirmada->trabajo);

                return $actaFirmada;
            });
        } catch (QueryException) {
            // El `lockForUpdate()` de arriba serializa reintentos sobre la
            // MISMA acta, pero no dos firmas concurrentes de DOS actas
            // DISTINTAS que referencian la MISMA evidencia (cada una bloquea
            // su propia fila) — ahí choca el índice único parcial
            // `ope_actas_evidencia_firma_id_unico` recién al hacer `save()`
            // (hallazgo de la revisión crítica de esta tarea). Se re-consulta
            // antes de rendirse: si el conflicto fue justo con ESTA acta
            // (perdió la carrera pero terminó con la evidencia correcta),
            // se devuelve como cualquier reintento — nunca un 500 sin motivo.
            $evidencia = Evidencia::query()->where('uuid_cliente', $evidenciaFirmaUuidCliente)->first();
            $actaFresca = $acta->fresh();

            if ($evidencia !== null && $actaFresca !== null
                && $actaFresca->estado === EstadoActa::Firmada
                && (int) $actaFresca->evidencia_firma_id === $evidencia->id) {
                return $actaFresca;
            }

            throw FirmaActaNoDisponible::porConflictoConcurrente();
        }
    }
}
