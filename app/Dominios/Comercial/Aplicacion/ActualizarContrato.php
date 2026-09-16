<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Aplicacion\Contrato\VerificadorLotesDelContrato;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaCerrada;
use App\Dominios\Comercial\Dominio\Excepciones\LoteAjenoAlCliente;
use App\Dominios\Comercial\Dominio\Excepciones\LotesDePropiedadAgotados;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoLote;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Edición de un contrato con sus lotes en una sola operación (HU-23, tarea
 * 34; lotes agregados en la tarea "contratos-lotes", 16/9/2026): mismo
 * criterio que `ActualizarCliente` — una sola transacción para contrato y
 * lotes.
 *
 * Sin ventanas de contrato (retiradas el 16/9/2026, reemplazo completo: ver
 * el docblock de {@see ContratoLote}):
 * el rango horario para fumigar ya no es un dato del contrato completo, es
 * un dato de CADA LOTE (`hora_inicio`/`hora_fin`, ambos NULL = día
 * completo).
 *
 * El set de `$lotes` recibido es el COMPLETO y definitivo, no un delta: los
 * que faltan respecto a los actuales se dan de baja (soft delete), los que
 * faltan en el set actual se crean — mismo criterio que
 * `ActualizarCliente::sincronizarContactos`. A diferencia de los contactos,
 * no hay `id` de fila que enviar de vuelta: el propio `lote_id` identifica
 * la fila, porque `UNIQUE (contrato_id, lote_id)` parcial ya impide que se
 * repita dentro del mismo contrato — ver {@see self::sincronizarLotes()}
 * para el criterio de qué fila se considera "cambiada" (y por lo tanto se
 * guarda) frente a "sigue igual" (y se deja intacta).
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
 * contra la superficie que él mismo ya tiene reservada. Mismo criterio que
 * `CrearContrato` sobre no re-validar acá la consistencia de
 * `hora_inicio`/`hora_fin`: eso queda en `ActualizarContratoRequest`.
 */
final class ActualizarContrato
{
    public function __construct(private readonly LecturaCampania $lecturaCampania) {}

    /**
     * @param  array<string, mixed>  $datosContrato  sin `estado` ni `monto_total`: este último lo recalcula esta clase.
     * @param  list<array{lote_id: int, hora_inicio: ?string, hora_fin: ?string}>  $lotes  set completo y definitivo de lotes que cubre el contrato, cada uno con su rango horario opcional
     *
     * @throws CampaniaCerrada si la campaña elegida está `cerrada`.
     * @throws LoteAjenoAlCliente si algún lote no pertenece a una propiedad del cliente del contrato.
     * @throws LotesDePropiedadAgotados si alguna propiedad de los lotes elegidos ya está 100% cubierta por OTROS contratos vigentes de la misma campaña.
     */
    public function ejecutar(Contrato $contrato, array $datosContrato, array $lotes): Contrato
    {
        $this->verificarCampania((int) $datosContrato['campania_id']);

        $this->verificarLotes(
            array_column($lotes, 'lote_id'),
            (int) $datosContrato['cliente_id'],
            (int) $datosContrato['campania_id'],
            $contrato->id,
        );

        return DB::transaction(function () use ($contrato, $datosContrato, $lotes): Contrato {
            $datosContrato['monto_total'] = $this->calcularMontoTotal(
                (string) $datosContrato['hectareas_contratadas'],
                (int) $datosContrato['aplicaciones_previstas'],
                (string) $datosContrato['precio_ha'],
            );

            $contrato->fill($datosContrato);
            $contrato->save();

            $this->sincronizarLotes($contrato, $lotes);

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
     * Reconcilia el set completo y definitivo de `$lotes` contra
     * `$contrato->lotes()` actual, por `lote_id`: da de baja (soft delete)
     * las filas {@see ContratoLote} cuyo `lote_id` ya no está en el set
     * nuevo, crea las que faltan, y para las que siguen en ambos lados
     * decide si tocarlas comparando su horario.
     *
     * Cambio de criterio respecto a la versión de este método previa a la
     * tarea "contratos-lotes" de horario por lote (16/9/2026): cuando un
     * lote era solo un `lote_id` sin datos propios, "seguir en ambos lados"
     * alcanzaba para dejar la fila intacta — no había nada más en la fila
     * que pudiera cambiar sin que el conjunto de lotes elegidos cambiara.
     * Ahora un lote-en-contrato SÍ tiene datos propios
     * (`hora_inicio`/`hora_fin`) que el usuario puede editar sin agregar ni
     * quitar lotes (por ejemplo: el mismo lote, pero le corrige el horario),
     * así que "seguir en ambos lados" ya no basta para decidir "no tocar":
     * hace falta comparar el horario actual contra el nuevo. Si cambió,
     * `fill()+save()` (toca `updated_at`, es un cambio real que la bitácora
     * tiene que ver); si no cambió, la fila queda intacta — mismo espíritu
     * de "no ensuciar auditoría de lo que no cambió" que ya regía acá, solo
     * que el criterio de "cambió" pasa de ser "existencia" a ser "horario".
     *
     * Comparación por `substr(..., 0, 5)`: la columna `TIME` de Postgres
     * puede devolver `HH:MM:SS`, mientras que lo que llega en `$lotes` viene
     * en `HH:MM` (formato `date_format:H:i` del Request) — mismo recorte que
     * ya usaban las vistas para mostrar una hora sin segundos.
     *
     * @param  list<array{lote_id: int, hora_inicio: ?string, hora_fin: ?string}>  $lotes
     */
    private function sincronizarLotes(Contrato $contrato, array $lotes): void
    {
        $loteIds = array_column($lotes, 'lote_id');

        // `whereNotIn` con un array vacío no excluye nada (Laravel: "verdadero
        // para toda fila"): si `$lotes` llegara vacío esto daría de baja a
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

        $existentes = $contrato->lotes()->get()->keyBy('lote_id');

        foreach ($lotes as $datos) {
            $existente = $existentes->get($datos['lote_id']);

            if ($existente === null) {
                $contrato->lotes()->create($datos);

                continue;
            }

            $horarioCambio = $this->horaCorta($existente->hora_inicio) !== $datos['hora_inicio']
                || $this->horaCorta($existente->hora_fin) !== $datos['hora_fin'];

            if (! $horarioCambio) {
                continue;
            }

            $existente->fill([
                'hora_inicio' => $datos['hora_inicio'],
                'hora_fin' => $datos['hora_fin'],
            ]);
            $existente->save();
        }
    }

    private function horaCorta(?string $hora): ?string
    {
        return $hora === null ? null : substr($hora, 0, 5);
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
