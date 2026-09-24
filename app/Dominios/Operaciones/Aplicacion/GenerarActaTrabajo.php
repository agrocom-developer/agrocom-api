<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosActa;
use App\Dominios\Operaciones\Dominio\EstadoTableroTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\TrabajoNoListoParaActa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * `POST /api/trabajos/{uuid_cliente}/acta` (HU-17, tarea 24): genera el acta
 * de conformidad de un trabajo ya cerrado. Idempotente por REGLA DE NEGOCIO
 * ("un trabajo tiene a lo sumo un acta" — `ope_actas.trabajo_id` es
 * `UNIQUE`), no por comparación de `uuid_cliente` como `cerrarTrabajo()`: acá
 * no hay "evento" que pueda repetirse con datos distintos, solo "¿ya existe
 * el acta de este trabajo, sí o no?" — por eso un segundo pedido, aun con un
 * `uuid_cliente` nuevo, devuelve la fila existente sin tocarla.
 *
 * `lockForUpdate()` sobre `$trabajo` serializa dos pedidos concurrentes del
 * mismo trabajo (mismo patrón que `EscrituraSincronizacionEloquent::cerrarTrabajo()`):
 * sin el lock, dos requests podrían pasar juntos el chequeo "¿existe ya un
 * acta?" y las dos intentarían crear una, chocando recién en el `UNIQUE` de
 * `trabajo_id` — el lock lo evita antes, no lo repara después.
 *
 * Dos guardas de negocio, ambas en `TrabajoNoListoParaActa` (ver runs/24.md
 * para el porqué de la segunda, que no es literal de la espec):
 *   1. el trabajo debe estar `cerrado`;
 *   2. TODAS sus sesiones vigentes deben estar `validado` — reusa
 *      {@see Trabajo::estadoTablero()} (HU-15) en vez de duplicar la regla.
 *
 * El PDF se renderiza y se guarda en disco DESPUÉS del `INSERT` de la fila
 * (guardarraíl de `RegistrarEvidencia`, tarea 19: nunca I/O de archivo antes
 * de que la fila exista) — si el `Storage::put()` fallara, la transacción
 * completa se revierte, sin dejar un acta sin PDF.
 *
 * Ruta `trabajos/{trabajo_id}/actas/{acta_id}.pdf` (ADR 0026, objeto primero,
 * actividad después): las actas ya emitidas con la ruta anterior
 * (`actas/{trabajo_id}/{acta_id}.pdf`) siguen resolviendo por `pdf_path`.
 */
final class GenerarActaTrabajo
{
    public function __construct(private readonly MaquinaEstadosActa $maquina) {}

    /**
     * @throws TrabajoNoListoParaActa si `$trabajo` no está en condiciones de generar su acta,
     *                                o si el `uuid_cliente` pedido choca con el de un acta de OTRO trabajo.
     */
    public function ejecutar(Trabajo $trabajo, string $uuidCliente): Acta
    {
        try {
            return DB::transaction(function () use ($trabajo, $uuidCliente): Acta {
                /** @var Trabajo $trabajoLock */
                $trabajoLock = Trabajo::query()->whereKey($trabajo->id)->lockForUpdate()->firstOrFail();

                $existente = Acta::query()->where('trabajo_id', $trabajoLock->id)->first();

                if ($existente !== null) {
                    return $existente;
                }

                if ($trabajoLock->estado !== EstadoTrabajo::Cerrado) {
                    throw TrabajoNoListoParaActa::porNoEstarCerrado($trabajoLock->id);
                }

                $trabajoLock->loadMissing('sesiones');

                if ($trabajoLock->estadoTablero() !== EstadoTableroTrabajo::Validado) {
                    throw TrabajoNoListoParaActa::porSesionesSinValidar($trabajoLock->id);
                }

                $acta = $this->maquina->generar([
                    'uuid_cliente' => $uuidCliente,
                    'trabajo_id' => $trabajoLock->id,
                    'hectareas_conformadas' => $trabajoLock->hectareas_declaradas,
                ]);

                $pdf = Pdf::loadView('operaciones::pdf.acta', ['trabajo' => $trabajoLock, 'acta' => $acta])->output();
                $ruta = sprintf('trabajos/%d/actas/%d.pdf', $trabajoLock->id, $acta->id);
                Storage::disk('r2')->put($ruta, $pdf);

                $acta->update(['pdf_path' => $ruta]);

                return $acta;
            });
        } catch (QueryException $excepcion) {
            // El `lockForUpdate()` de arriba serializa dos pedidos concurrentes
            // del MISMO trabajo, pero no dos pedidos de trabajos DISTINTOS que
            // por error de cliente comparten el mismo `uuid_cliente` de acta —
            // ahí choca `ope_actas_uuid_cliente_unico` (hallazgo de la revisión
            // crítica de esta tarea). Se re-consulta antes de rendirse: si el
            // conflicto fue justo con el acta de ESTE trabajo (raro, pero
            // posible si el lock leyó una fila obsoleta), se devuelve como
            // cualquier reintento — nunca un 500 sin motivo.
            $existente = Acta::query()->where('trabajo_id', $trabajo->id)->first();

            if ($existente !== null) {
                return $existente;
            }

            throw TrabajoNoListoParaActa::porConflictoDeUuidCliente($trabajo->id);
        }
    }
}
