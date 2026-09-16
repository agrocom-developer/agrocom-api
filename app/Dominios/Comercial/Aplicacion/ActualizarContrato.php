<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Aplicacion\Contrato\VerificadorLotesDelContrato;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaCerrada;
use App\Dominios\Comercial\Dominio\Excepciones\LoteAjenoAlCliente;
use App\Dominios\Comercial\Dominio\Excepciones\LotesDePropiedadAgotados;
use App\Dominios\Comercial\Dominio\Excepciones\VentanasContratoSolapadas;
use App\Dominios\Comercial\Dominio\ValidadorSolapamientoVentanas;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoLote;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoVentana;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Edición de un contrato con sus ventanas horarias y sus lotes en una sola
 * operación (HU-23, tarea 34; lotes agregados en la tarea
 * "contratos-lotes", 16/9/2026): mismo criterio que `ActualizarCliente` —
 * una sola transacción para contrato, ventanas y lotes.
 *
 * El set de ventanas recibido es el COMPLETO y definitivo, no un delta: las
 * que faltan respecto a las actuales se dan de baja (soft delete), las que
 * traen `id` se actualizan y las que no traen `id` se crean — mismo criterio
 * que `ActualizarCliente::sincronizarContactos`. El set de `loteIds` sigue
 * el mismo espíritu (ver {@see self::sincronizarLotes()}), aunque a
 * diferencia de las ventanas no hay `id` de fila que enviar de vuelta: el
 * propio `lote_id` identifica la fila, porque `UNIQUE (contrato_id, lote_id)`
 * parcial ya impide que se repita dentro del mismo contrato.
 *
 * `estado` nunca viaja en `$datosContrato` (invariante 7): esta clase no lo
 * toca — el cambio de estado es responsabilidad exclusiva de
 * `Aplicacion/CambiarEstadoContrato`.
 *
 * Misma guarda de campaña que `CrearContrato` (ADR 0015 punto 1, corregida
 * el 15/9/2026): la campaña elegida no puede estar `cerrada` — ver ese
 * docblock para el criterio de lectura vía `LecturaCampania`.
 *
 * Mismas dos guardas de lotes que `CrearContrato` (ver su docblock), vía
 * {@see VerificadorLotesDelContrato} — con una diferencia: la guarda de
 * "propiedad agotada" excluye acá al propio `$contrato` (invariante de la
 * tarea: "en edición, el propio contrato no se cuenta contra sí mismo"), así
 * que un contrato `vigente` puede reordenar sus propios lotes sin chocar
 * contra la superficie que él mismo ya tiene reservada.
 */
final class ActualizarContrato
{
    public function __construct(private readonly LecturaCampania $lecturaCampania) {}

    /**
     * @param  array<string, mixed>  $datosContrato  sin `estado` ni `monto_total`: este último lo recalcula esta clase.
     * @param  list<array{id: int|null, hora_inicio: string, hora_fin: string}>  $ventanas  set completo y definitivo
     * @param  list<int>  $loteIds  set completo y definitivo de lotes que cubre el contrato
     *
     * @throws VentanasContratoSolapadas si dos ventanas del set final se solapan entre sí.
     * @throws CampaniaCerrada si la campaña elegida está `cerrada`.
     * @throws LoteAjenoAlCliente si algún lote no pertenece a una propiedad del cliente del contrato.
     * @throws LotesDePropiedadAgotados si alguna propiedad de los lotes elegidos ya está 100% cubierta por OTROS contratos vigentes de la misma campaña.
     */
    public function ejecutar(Contrato $contrato, array $datosContrato, array $ventanas, array $loteIds): Contrato
    {
        $this->verificarCampania((int) $datosContrato['campania_id']);

        $solapamiento = ValidadorSolapamientoVentanas::primerSolapamiento($ventanas);

        if ($solapamiento !== null) {
            [$a, $b] = $solapamiento;

            throw VentanasContratoSolapadas::entre($a['hora_inicio'], $a['hora_fin'], $b['hora_inicio'], $b['hora_fin']);
        }

        $this->verificarLotes($loteIds, (int) $datosContrato['cliente_id'], (int) $datosContrato['campania_id'], $contrato->id);

        return DB::transaction(function () use ($contrato, $datosContrato, $ventanas, $loteIds): Contrato {
            $datosContrato['monto_total'] = $this->calcularMontoTotal(
                (string) $datosContrato['hectareas_contratadas'],
                (int) $datosContrato['aplicaciones_previstas'],
                (string) $datosContrato['precio_ha'],
            );

            $contrato->fill($datosContrato);
            $contrato->save();

            $this->sincronizarVentanas($contrato, $ventanas);
            $this->sincronizarLotes($contrato, $loteIds);

            return $contrato->refresh();
        });
    }

    /**
     * @param  list<int>  $loteIds
     *
     * @throws LoteAjenoAlCliente si algún lote no pertenece a una propiedad del cliente.
     * @throws LotesDePropiedadAgotados si alguna propiedad involucrada quedó 100% cubierta por otros contratos vigentes.
     */
    private function verificarLotes(array $loteIds, int $clienteId, int $campaniaId, ?int $contratoIdExcluido): void
    {
        $loteAjeno = VerificadorLotesDelContrato::loteAjenoAlCliente($loteIds, $clienteId);

        if ($loteAjeno !== null) {
            throw LoteAjenoAlCliente::paraLote($loteAjeno);
        }

        $propiedadAgotada = VerificadorLotesDelContrato::propiedadAgotada($loteIds, $campaniaId, $contratoIdExcluido);

        if ($propiedadAgotada !== null) {
            throw LotesDePropiedadAgotados::paraPropiedad($propiedadAgotada['nombre']);
        }
    }

    /**
     * Reconcilia el set completo y definitivo de `$loteIds` contra
     * `$contrato->lotes()` actual: da de baja (soft delete) las filas
     * {@see ContratoLote} cuyo `lote_id` ya no está en la lista nueva, crea
     * las que faltan, y deja INTACTAS (sin `save()`, sin tocar
     * `updated_at`/bitácora) las que siguen en ambos lados — nunca borra y
     * recrea todo, para no ensuciar la auditoría de un lote que no cambió.
     *
     * Mismo patrón de "stamp de auditor antes del soft delete" que
     * `sincronizarVentanas()`/`ActualizarCliente::sincronizarContactos()`.
     *
     * @param  list<int>  $loteIds
     */
    private function sincronizarLotes(Contrato $contrato, array $loteIds): void
    {
        // `whereNotIn` con un array vacío no excluye nada (Laravel: "verdadero
        // para toda fila"): si `$loteIds` llegara vacío esto daría de baja a
        // todos los lotes actuales — no debería pasar (`ActualizarContratoRequest`
        // exige `lotes` con `min:1`), pero el comportamiento es correcto igual:
        // el set enviado reemplaza por completo al actual.
        $contrato->lotes()
            ->whereNotIn('lote_id', $loteIds)
            ->get()
            ->each(function (ContratoLote $contratoLote): void {
                $usuarioId = Auth::id();

                if ($usuarioId !== null) {
                    $contratoLote->updated_by = (int) $usuarioId;
                    $contratoLote->save();
                }

                $contratoLote->delete();
            });

        $idsExistentes = $contrato->lotes()->pluck('lote_id');

        foreach ($loteIds as $loteId) {
            if ($idsExistentes->contains($loteId)) {
                continue;
            }

            $contrato->lotes()->create(['lote_id' => $loteId]);
        }
    }

    /** @param  list<array{id: int|null, hora_inicio: string, hora_fin: string}>  $ventanas */
    private function sincronizarVentanas(Contrato $contrato, array $ventanas): void
    {
        $idsEnviados = array_values(array_filter(array_column($ventanas, 'id')));

        // `whereNotIn` con un array vacío no excluye nada (Laravel: "verdadero
        // para toda fila"): si ninguna ventana enviada trae `id`, esto da de
        // baja a todas las actuales — correcto, el set enviado las reemplaza
        // por completo.
        $contrato->ventanas()
            ->whereNotIn('id', $idsEnviados)
            ->get()
            ->each(function (ContratoVentana $ventana): void {
                $usuarioId = Auth::id();

                if ($usuarioId !== null) {
                    $ventana->updated_by = (int) $usuarioId;
                    $ventana->save();
                }

                $ventana->delete();
            });

        foreach ($ventanas as $datos) {
            $id = $datos['id'];
            unset($datos['id']);

            $ventana = $id !== null
                ? $contrato->ventanas()->whereKey($id)->firstOrFail()
                : new ContratoVentana(['contrato_id' => $contrato->id]);

            $ventana->fill($datos);
            $ventana->save();
        }
    }

    /** @throws CampaniaCerrada si la campaña elegida está `cerrada`. */
    private function verificarCampania(int $campaniaId): void
    {
        $campania = $this->lecturaCampania->obtener($campaniaId);

        if ($campania === null) {
            return;
        }

        if ($campania->cerrada) {
            throw CampaniaCerrada::paraCampania($campania->codigo);
        }
    }

    /**
     * Mismo cálculo que `CrearContrato::calcularMontoTotal()` — `monto_total`
     * se recalcula siempre desde los tres factores de origen (invariante 6),
     * nunca se acepta el valor que venga en el formulario.
     */
    private function calcularMontoTotal(string $hectareas, int $aplicaciones, string $precioHa): string
    {
        return (string) BigDecimal::of($hectareas)
            ->multipliedBy($aplicaciones)
            ->multipliedBy($precioHa)
            ->toScale(2, RoundingMode::HalfUp);
    }
}
