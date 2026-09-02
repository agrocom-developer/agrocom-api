<?php

namespace App\Dominios\Sincronizacion\Aplicacion;

use App\Dominios\Operaciones\Contratos\AperturaSesion;
use App\Dominios\Operaciones\Contratos\AperturaTrabajo;
use App\Dominios\Operaciones\Contratos\CierreSesion;
use App\Dominios\Operaciones\Contratos\CierreTrabajo;
use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\RegistroCondiciones;
use App\Dominios\Operaciones\Contratos\RegistroRecarga;
use App\Dominios\Operaciones\Contratos\RegistroRecepcionCaldo;
use App\Dominios\Operaciones\Contratos\ResultadoSincronizacion;

/**
 * Caso de uso de `POST /api/sync` (espec §2.1, puntos 3 a 5; TE-05/HU-05,
 * tareas 09 y 13). Agrupa el lote por tipo de entidad y lo aplica siempre en
 * el mismo orden causal fijo —`trabajo`, `sesion`, `cierre_trabajo`,
 * `cierre_sesion`—, sin importar el orden en que llegó el arreglo del
 * cliente: así, cuando le toca el turno a `sesion`, el `trabajo` que
 * referencia por `uuid_cliente` (si vino en el mismo lote) ya quedó
 * persistido, y lo mismo para un cierre que llegara en el mismo lote que su
 * apertura. Es el diseño que evita construir lógica de reintento entre
 * pushes para el caso "en desorden" (ver el prompt de la tarea 09, "Diseño
 * que evita el problema difícil").
 *
 * Solo conoce el contrato de `Operaciones` ({@see EscrituraSincronizacion}) —
 * nunca sus modelos Eloquent (ADR 0003, regla 2). El orden de la respuesta
 * respeta el orden de ENTRADA, no el de procesamiento, para que el cliente
 * pueda correlacionar cada resultado con el registro que envió.
 */
final class SincronizarLote
{
    /**
     * Orden causal fijo (espec §2.1, punto 3, extendido por las tareas 13, 17
     * y 18): apertura antes que su propio cierre, y `condiciones` justo
     * después de `sesion` —la referencia por `uuid_cliente`—, para el caso
     * —raro pero posible— de que ambos lleguen en el mismo lote (un piloto
     * que registra las condiciones al mismo tiempo que abre la sesión).
     * `recepcion_caldo` (tarea 18) solo depende de `trabajo` —no de
     * `sesion`— así que va justo después: el cliente puede entregar el
     * caldo en el mismo lote en que se abre el trabajo, antes de que exista
     * ninguna sesión todavía. `recarga` (tarea 23) depende solo de `sesion`,
     * igual que `condiciones` — el orden relativo entre esas dos no importa,
     * ninguna referencia a la otra.
     */
    private const array ORDEN_CAUSAL = ['trabajo', 'recepcion_caldo', 'sesion', 'condiciones', 'recarga', 'cierre_trabajo', 'cierre_sesion'];

    public function __construct(
        private readonly EscrituraSincronizacion $operaciones,
    ) {}

    /**
     * @param  list<mixed>  $registros
     * @param  int|null  $operarioPersonaId  Persona de `Personal` dueña del
     *                                       token que firma el request
     *                                       (`Seguridad\Contratos\IdentidadOperarioToken`),
     *                                       o `null` si esa cuenta no tiene
     *                                       persona asociada. Se usa para
     *                                       rechazar una `sesion` cuyo
     *                                       `piloto_id` declarado no es el
     *                                       propio operario (tarea 12,
     *                                       hallazgo 2) — un `null` no
     *                                       rechaza nada porque no hay con
     *                                       qué comparar.
     * @return list<array<string, mixed>>
     */
    public function ejecutar(array $registros, ?int $operarioPersonaId): array
    {
        /** @var array<int, array<string, mixed>|null> $resultados */
        $resultados = array_fill(0, count($registros), null);

        foreach (self::ORDEN_CAUSAL as $tipo) {
            foreach ($registros as $indice => $registro) {
                if (! is_array($registro) || ($registro['tipo'] ?? null) !== $tipo) {
                    continue;
                }

                $resultados[$indice] = $this->filaResultado($registro, $this->aplicar($tipo, $registro, $operarioPersonaId));
            }
        }

        foreach ($registros as $indice => $registro) {
            if ($resultados[$indice] === null) {
                $resultados[$indice] = $this->filaResultado(
                    $registro,
                    ResultadoSincronizacion::rechazado('tipo de registro desconocido o dato mal formado'),
                );
            }
        }

        /** @var list<array<string, mixed>> */
        return array_values($resultados);
    }

    /** @param  array<string, mixed>  $registro */
    private function aplicar(string $tipo, array $registro, ?int $operarioPersonaId): ResultadoSincronizacion
    {
        return match ($tipo) {
            'trabajo' => $this->aplicarTrabajo($registro),
            'recepcion_caldo' => $this->aplicarRecepcionCaldo($registro),
            'sesion' => $this->aplicarSesion($registro, $operarioPersonaId),
            'condiciones' => $this->aplicarCondiciones($registro),
            'recarga' => $this->aplicarRecarga($registro),
            'cierre_trabajo' => $this->aplicarCierreTrabajo($registro, $operarioPersonaId),
            'cierre_sesion' => $this->aplicarCierreSesion($registro, $operarioPersonaId),
            default => ResultadoSincronizacion::rechazado('tipo de registro desconocido o dato mal formado'),
        };
    }

    /**
     * @param  array<string, mixed>  $registro
     *
     * Sin verificación de pertenencia (mismo criterio que
     * `aplicarCondiciones()`): la espec no define una noción de "dueño" para
     * este registro.
     */
    private function aplicarRecepcionCaldo(array $registro): ResultadoSincronizacion
    {
        $datos = RegistroRecepcionCaldo::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado('recepción de caldo con datos incompletos o inválidos')
            : $this->operaciones->registrarRecepcionCaldo($datos);
    }

    /** @param  array<string, mixed>  $registro */
    private function aplicarTrabajo(array $registro): ResultadoSincronizacion
    {
        $datos = AperturaTrabajo::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado('trabajo con datos incompletos o inválidos')
            : $this->operaciones->abrirTrabajo($datos);
    }

    /**
     * @param  array<string, mixed>  $registro
     *
     * La verificación de pertenencia de `piloto_id` vive acá, antes de
     * invocar el contrato de escritura, y no en
     * `EscrituraSincronizacion::abrirSesion()`: esa firma la ejercita
     * directamente `tests/Feature/EscrituraSincronizacionTest.php` (congelado,
     * PR #46) sin pasarle el operario, y agregarle un parámetro —aunque fuera
     * opcional— dejaría un modo "sin verificar" alcanzable por cualquier
     * consumidor que lo omita por descuido. Acá, en cambio, es imposible
     * invocar `SincronizarLote::ejecutar()` sin decidir explícitamente el
     * operario (tarea 12, hallazgo 2).
     */
    private function aplicarSesion(array $registro, ?int $operarioPersonaId): ResultadoSincronizacion
    {
        $datos = AperturaSesion::intentarDesdeArreglo($registro);

        if ($datos === null) {
            return ResultadoSincronizacion::rechazado('sesión con datos incompletos o inválidos');
        }

        if ($operarioPersonaId !== null && $datos->pilotoId !== $operarioPersonaId) {
            return ResultadoSincronizacion::rechazado('el piloto declarado no corresponde al operario autenticado');
        }

        return $this->operaciones->abrirSesion($datos);
    }

    /**
     * @param  array<string, mixed>  $registro
     *
     * Sin verificación de pertenencia (ver docblock de
     * `EscrituraSincronizacion::registrarCondiciones()`): la espec no define
     * una noción de "dueño" para este registro.
     */
    private function aplicarCondiciones(array $registro): ResultadoSincronizacion
    {
        $datos = RegistroCondiciones::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado('condiciones con datos incompletos o inválidos')
            : $this->operaciones->registrarCondiciones($datos);
    }

    /**
     * @param  array<string, mixed>  $registro
     *
     * Sin verificación de pertenencia (mismo criterio que
     * `aplicarCondiciones()`/`aplicarRecepcionCaldo()`): la espec no define
     * una noción de "dueño" para este registro.
     */
    private function aplicarRecarga(array $registro): ResultadoSincronizacion
    {
        $datos = RegistroRecarga::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado('recarga con datos incompletos o inválidos')
            : $this->operaciones->registrarRecarga($datos);
    }

    /**
     * @param  array<string, mixed>  $registro
     *
     * A diferencia de `aplicarSesion()`, la verificación de pertenencia NO
     * vive acá: el registro de cierre no trae el dato de persona a comparar
     * (solo referencia la fila por `uuid_cliente`), así que resolverla exige
     * leer la fila persistida — responsabilidad de
     * `EscrituraSincronizacion::cerrarTrabajo()/cerrarSesion()`, no de este
     * caso de uso (ver runs/13.md).
     */
    private function aplicarCierreTrabajo(array $registro, ?int $operarioPersonaId): ResultadoSincronizacion
    {
        $datos = CierreTrabajo::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado('cierre de trabajo con datos incompletos o inválidos')
            : $this->operaciones->cerrarTrabajo($datos, $operarioPersonaId);
    }

    /** @param  array<string, mixed>  $registro */
    private function aplicarCierreSesion(array $registro, ?int $operarioPersonaId): ResultadoSincronizacion
    {
        $datos = CierreSesion::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado('cierre de sesión con datos incompletos o inválidos')
            : $this->operaciones->cerrarSesion($datos, $operarioPersonaId);
    }

    /**
     * @return array<string, mixed>
     */
    private function filaResultado(mixed $registro, ResultadoSincronizacion $resultado): array
    {
        $fila = [
            'uuid_cliente' => is_array($registro) && is_string($registro['uuid_cliente'] ?? null) ? $registro['uuid_cliente'] : null,
            'tipo' => is_array($registro) && is_string($registro['tipo'] ?? null) ? $registro['tipo'] : null,
            'estado' => $resultado->estado,
        ];

        if ($resultado->motivo !== null) {
            $fila['motivo'] = $resultado->motivo;
        }

        return $fila;
    }
}
