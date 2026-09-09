<?php

namespace App\Dominios\Operaciones\Aplicacion\MaquinaEstados;

use App\Dominios\Operaciones\Contratos\Eventos\SesionValidada;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionSesionNoPermitida;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesSesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Única clase que crea/muta el `estado` de `sesion` (invariante 7 de
 * CLAUDE.md).
 *
 * `atributos['trabajo_id']` ya viene resuelto a un id de servidor: la
 * resolución de la referencia por `uuid_cliente` del trabajo (espec §2.1
 * punto 5) es responsabilidad del contrato de escritura de `Operaciones`
 * (TE-05), no de esta clase.
 *
 * `cerrar()` (HU-05, tarea 13) es la contraparte de `abrir()`: consulta
 * {@see TransicionesSesion::permitida()} antes de escribir y lanza
 * {@see TransicionSesionNoPermitida} si la transición no está permitida.
 * `$motivoCierre` es la columna simple del catálogo de la espec §4.3
 * (completado / relevo_piloto / cambio_dron / falla_equipo / clima /
 * fin_jornada / otro) — sin la lógica de relevo de HU-07, que esta tarea no
 * implementa. `$hectareasDeclaradas` es la condición central de la
 * transición (espec §5: "Sesión → cerrada | Hectáreas de la sesión + ...") —
 * pisa el valor de la apertura (que en el caso normal llega en `'0'`, porque
 * el piloto no sabe cuánto va a cubrir antes de volar) con lo realmente
 * cubierto.
 *
 * `validar()` (HU-14, tarea 14) es la contraparte de `cerrar()`: mismo
 * patrón de guarda vía {@see TransicionesSesion}, pero además idempotente
 * (ver su docblock) y dispara `SesionValidada` — el evento de dominio que
 * HU-16 escuchará para generar el devengo.
 */
final class MaquinaEstadosSesion
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function abrir(array $atributos): Sesion
    {
        $atributos['inicio'] = self::normalizarUtc($atributos['inicio'] ?? null);
        $atributos['fin'] = self::normalizarUtc($atributos['fin'] ?? null);

        return Sesion::create([...$atributos, 'estado' => EstadoSesion::Abierto]);
    }

    /**
     * @throws TransicionSesionNoPermitida si `$sesion` no está `abierto`.
     */
    public function cerrar(Sesion $sesion, string $cierreUuidCliente, string $fin, string $motivoCierre, string $hectareasDeclaradas): Sesion
    {
        $desde = $sesion->estado;
        $hasta = EstadoSesion::Cerrado;

        if (! TransicionesSesion::permitida($desde, $hasta)) {
            throw TransicionSesionNoPermitida::entre($desde, $hasta);
        }

        $sesion->estado = $hasta;
        $sesion->cierre_uuid_cliente = $cierreUuidCliente;
        $sesion->fin = self::normalizarUtc($fin);
        $sesion->motivo_cierre = $motivoCierre;
        $sesion->hectareas_declaradas = $hectareasDeclaradas;
        $sesion->save();

        return $sesion;
    }

    /**
     * `validar()` (HU-14, tarea 14): aprueba una sesión `cerrado` desde el
     * panel. La policy "validador ≠ piloto de esa sesión" (invariante 4) NO
     * vive acá — esta clase es solo la que escribe `estado` (invariante 7);
     * la autorización vive en `Aplicacion/ValidarSesion.php`, mismo criterio
     * que `EscrituraSincronizacionEloquent::operarioPuedeCerrarTrabajo()`
     * vive fuera de `MaquinaEstadosTrabajo::cerrar()`.
     *
     * Idempotente (invariante 3): si `$sesion` YA está `validado`, no vuelve
     * a tocar la fila ni a disparar `SesionValidada` — un reintento del
     * mismo click no debe anunciar la validación dos veces. Cualquier OTRO
     * estado de origen (`abierto`, o `cerrado` con la sesión ya anulada por
     * un rechazo — ver `Aplicacion/ValidarSesion.php`, que es quien filtra
     * ese caso) sigue el camino normal de {@see TransicionesSesion} y lanza
     * {@see TransicionSesionNoPermitida} si no está permitido.
     *
     * La mutación y el disparo del evento van dentro de una transacción
     * (HU-16, tarea 16): el oyente real de `SesionValidada`
     * (`Finanzas\Aplicacion\GenerarDevengosSesion`) corre síncrono, dentro de
     * ese `event()`, y puede lanzar
     * `Finanzas\Dominio\Excepciones\PersonaSinTarifaHa` si el piloto o su
     * auxiliar no tienen tarifa configurada. Sin la transacción, esa
     * excepción dejaría la sesión ya guardada como `validado` pero sin su
     * devengo — exactamente el estado a medias que la invariante 6 de
     * CLAUDE.md prohíbe. Con ella, la validación entera se revierte: la
     * sesión sigue `cerrado`, y quien la validó puede reintentar una vez
     * completada la tarifa. Esta clase no importa nada de `Finanzas` (no
     * hace falta: no atrapa la excepción, solo se asegura de que su efecto
     * sea atómico).
     *
     * @throws TransicionSesionNoPermitida si `$sesion` no está `cerrado` ni `validado`.
     */
    public function validar(Sesion $sesion, int $validadorPersonaId): Sesion
    {
        if ($sesion->estado === EstadoSesion::Validado) {
            return $sesion;
        }

        $desde = $sesion->estado;
        $hasta = EstadoSesion::Validado;

        if (! TransicionesSesion::permitida($desde, $hasta)) {
            throw TransicionSesionNoPermitida::entre($desde, $hasta);
        }

        DB::transaction(function () use ($sesion, $validadorPersonaId, $hasta): void {
            $sesion->estado = $hasta;
            $sesion->validado_por = $validadorPersonaId;
            $sesion->fecha_validacion = CarbonImmutable::now();
            $sesion->save();

            event(new SesionValidada($sesion->id));
        });

        return $sesion;
    }

    /**
     * `CarbonImmutable::parse()` conserva el offset original del string
     * entrante (p. ej. `-04:00`) como huso horario del objeto, no lo
     * normaliza — y `ope_sesiones.inicio`/`fin` son `dateTime` sin tz. Sin
     * este `->utc()`, `format()` escribiría la hora LOCAL literal
     * ("16:30:00") y una relectura posterior la interpretaría como UTC,
     * corriendo el instante real por el valor del offset. Mismo mecanismo
     * (y mismo fix) que `MaquinaEstadosActa::firmar()` ya aplica a
     * `fecha_firma` (runs/24.md).
     */
    private static function normalizarUtc(string|\DateTimeInterface|null $valor): ?CarbonImmutable
    {
        return $valor === null ? null : CarbonImmutable::parse($valor)->utc();
    }
}
