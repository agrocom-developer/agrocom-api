<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosAlerta;
use App\Dominios\Operaciones\Dominio\EstadoCoberturaTrabajo;
use App\Dominios\Operaciones\Dominio\TipoAlerta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Condiciones;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Recarga;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Illuminate\Database\QueryException;

/**
 * Genera las 4 alertas por excepción que HU-19 (tarea 26) implementa,
 * enganchada directo en el punto del código donde el dato que las dispara ya
 * se persiste — nunca a partir de un barrido periódico ni de un endpoint que
 * las calcule bajo demanda. Invocada desde
 * `Infraestructura/EscrituraSincronizacionEloquent.php` (batería caliente,
 * dron sospechoso y condiciones forzadas — dentro de la misma transacción que
 * el registro que las dispara) y desde
 * `recalcularHectareasTrabajo()` de esa misma clase (suma excedida, ver el
 * docblock de {@see self::porSumaExcedidaSiCorresponde()}).
 *
 * Idempotente por el índice único parcial correspondiente de `ope_alertas`
 * (uno por `tipo`, ver la migración): cada método intenta el `INSERT`
 * directo y trata la violación de unicidad como "ya aplicada" — mismo patrón
 * que `Finanzas\Aplicacion\GenerarDevengosSesion::generarPara()`, nunca un
 * `SELECT` previo (invariante 1 de CLAUDE.md, extendida a esta tabla).
 */
final class GenerarAlertaExcepcion
{
    /** CA de HU-19 (espec §10): "3+ baterías sobrecalentadas → revisar motores/ESC". */
    private const int UMBRAL_RECARGAS_DRON_SOSPECHOSO = 3;

    public function __construct(
        private readonly CalcularCoberturaTrabajo $calcularCobertura,
        private readonly MaquinaEstadosAlerta $maquina,
    ) {}

    /**
     * Batería caliente (`ope_recargas.alerta_temperatura = true`, tarea 23) —
     * ya calculada y persistida por `RegistroRecarga::alertaTemperatura()`;
     * esta alerta solo la refleja en la bandeja. Encadena la revisión de
     * dron sospechoso: ambas se disparan desde el mismo punto
     * (`registrarRecarga()`) porque ambas dependen del mismo hecho recién
     * persistido.
     */
    public function porBateriaCaliente(Recarga $recarga, Sesion $sesion): void
    {
        $this->crear([
            'tipo' => TipoAlerta::BateriaCaliente,
            'trabajo_id' => $sesion->trabajo_id,
            'sesion_id' => $sesion->id,
            'recarga_id' => $recarga->id,
            'mensaje' => Texto::de('operaciones.errores.alerta_bateria_caliente', [
                'temperatura' => $recarga->temperatura_bateria_c,
                'secuencia' => $recarga->secuencia,
                'sesion' => $sesion->id,
                'trabajo' => $sesion->trabajo_id,
            ]),
        ]);

        if ($sesion->dron_id !== null) {
            $this->porDronSospechosoSiCorresponde((int) $sesion->dron_id, $recarga);
        }
    }

    /**
     * Dron sospechoso: cuenta recargas con `alerta_temperatura = true` sobre
     * TODAS las sesiones que volaron con este dron (join `ope_recargas` →
     * `ope_sesiones`, no hay FK directa dron↔recarga) y genera la alerta al
     * llegar a {@see self::UMBRAL_RECARGAS_DRON_SOSPECHOSO}. El índice único
     * parcial por `dron_id` (no por `recarga_id`) es lo que evita que la 4ª,
     * 5ª... recarga caliente del mismo dron abra una alerta nueva: cada
     * intento de `INSERT` posterior choca contra la fila ya creada.
     */
    private function porDronSospechosoSiCorresponde(int $dronId, Recarga $recargaDisparadora): void
    {
        $recargasCalientes = Recarga::query()
            ->where('alerta_temperatura', true)
            ->whereIn('sesion_id', Sesion::query()->where('dron_id', $dronId)->select('id'))
            ->count();

        if ($recargasCalientes < self::UMBRAL_RECARGAS_DRON_SOSPECHOSO) {
            return;
        }

        $this->crear([
            'tipo' => TipoAlerta::DronSospechoso,
            'dron_id' => $dronId,
            'recarga_id' => $recargaDisparadora->id,
            'mensaje' => Texto::de('operaciones.errores.alerta_dron_sospechoso', [
                'dron' => $dronId,
                'recargas' => $recargasCalientes,
            ]),
        ]);
    }

    /**
     * Condiciones forzadas: el trabajo arrancó fuera de rango con
     * observación firmada del agrónomo (`ope_condiciones.autorizado = false`
     * — nunca `null`, porque sin observación ese registro ni siquiera llega a
     * crearse, ver `EscrituraSincronizacionEloquent::registrarCondiciones()`).
     */
    public function porCondicionesForzadas(Condiciones $condiciones): void
    {
        $this->crear([
            'tipo' => TipoAlerta::CondicionesForzadas,
            'trabajo_id' => $condiciones->trabajo_id,
            'sesion_id' => $condiciones->sesion_id,
            'condiciones_id' => $condiciones->id,
            'mensaje' => Texto::de('operaciones.errores.alerta_condiciones_forzadas', ['trabajo' => $condiciones->trabajo_id]),
        ]);
    }

    /**
     * Suma excedida: `EstadoCoberturaTrabajo` es una PROYECCIÓN DE LECTURA
     * (tarea 20, `CalcularCoberturaTrabajo`), nunca persistida — así que no
     * hay ninguna fila que "pase a Observado" y dispare un evento por sí
     * sola. El enganche real es este: se recalcula la cobertura cada vez que
     * `EscrituraSincronizacionEloquent::recalcularHectareasTrabajo()` corre
     * (es decir, en cada `cerrar_sesion` aplicado, el único evento que puede
     * mover la suma de hectáreas de las sesiones de un trabajo) y, si el
     * resultado es `Observado`, se genera la alerta. `cerrarTrabajo()`
     * también recalcula `hectareas_declaradas`, pero no hace falta engancharla
     * ahí también: esa recalculación no suma ninguna sesión nueva (las
     * sesiones abiertas aportan `hectareas_declaradas = 0`), así que no puede
     * producir un `Observado` que el último cierre de sesión no haya visto ya.
     */
    public function porSumaExcedidaSiCorresponde(Trabajo $trabajo): void
    {
        if ($this->calcularCobertura->ejecutar($trabajo) !== EstadoCoberturaTrabajo::Observado) {
            return;
        }

        $this->crear([
            'tipo' => TipoAlerta::SumaExcedida,
            'trabajo_id' => $trabajo->id,
            'mensaje' => Texto::de('operaciones.errores.alerta_suma_excedida', ['trabajo' => $trabajo->id]),
        ]);
    }

    /** @param  array<string, mixed>  $atributos  sin `estado`: lo fija {@see MaquinaEstadosAlerta}. */
    private function crear(array $atributos): void
    {
        try {
            $this->maquina->generar($atributos);
        } catch (QueryException $excepcion) {
            if (! $this->esViolacionDeUnicidad($excepcion)) {
                throw $excepcion;
            }
        }
    }

    /** Mismo criterio que `EscrituraSincronizacionEloquent::resultadoDesdeExcepcion()`: el formato difiere por driver. */
    private function esViolacionDeUnicidad(QueryException $excepcion): bool
    {
        $mensaje = $excepcion->getMessage();

        return str_contains($mensaje, 'ope_alertas_bateria_caliente_unico')
            || str_contains($mensaje, 'ope_alertas_condiciones_forzadas_unico')
            || str_contains($mensaje, 'ope_alertas_suma_excedida_unico')
            || str_contains($mensaje, 'ope_alertas_dron_sospechoso_unico')
            || str_contains($mensaje, 'ope_alertas.recarga_id')
            || str_contains($mensaje, 'ope_alertas.condiciones_id')
            || str_contains($mensaje, 'ope_alertas.trabajo_id')
            || str_contains($mensaje, 'ope_alertas.dron_id');
    }
}
