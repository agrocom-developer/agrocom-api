<?php

namespace App\Dominios\Sincronizacion\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Mezclas\Contratos\EscrituraMezclas;
use App\Dominios\Mezclas\Contratos\RegistroMezcla;
use App\Dominios\Operaciones\Contratos\AperturaEstadiaHacienda;
use App\Dominios\Operaciones\Contratos\AperturaSesion;
use App\Dominios\Operaciones\Contratos\AperturaTrabajo;
use App\Dominios\Operaciones\Contratos\CierreEstadiaHacienda;
use App\Dominios\Operaciones\Contratos\CierreSesion;
use App\Dominios\Operaciones\Contratos\CierreTrabajo;
use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\RegistroCondiciones;
use App\Dominios\Operaciones\Contratos\RegistroEvidenciaEquipo;
use App\Dominios\Operaciones\Contratos\RegistroIncidencia;
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
 * Solo conoce los contratos de `Operaciones` ({@see EscrituraSincronizacion})
 * y, desde la tarea 94, de `Mezclas` ({@see EscrituraMezclas}) — nunca sus
 * modelos Eloquent (ADR 0003, regla 2). El orden de la respuesta respeta el
 * orden de ENTRADA, no el de procesamiento, para que el cliente pueda
 * correlacionar cada resultado con el registro que envió.
 */
final class SincronizarLote
{
    /**
     * Orden causal fijo (espec §2.1, punto 3, extendido por las tareas 13, 17,
     * 18, 22 y 23): apertura antes que su propio cierre, y `condiciones` e
     * `incidencia` justo después de `sesion` —ambas la referencian por
     * `uuid_cliente`—, para el caso —raro pero posible— de que lleguen en el
     * mismo lote que la apertura (un piloto que registra una incidencia al
     * mismo tiempo que abre la sesión). El orden relativo entre `condiciones`
     * e `incidencia` no importa: ninguna depende de la otra, solo de `sesion`.
     * `recepcion_caldo` (tarea 18) solo depende de `trabajo` —no de
     * `sesion`— así que va justo después: el cliente puede entregar el
     * caldo en el mismo lote en que se abre el trabajo, antes de que exista
     * ninguna sesión todavía. `mezcla` (espec §7, HU-78, tarea 94, revierte
     * CR-01) y `evidencia_equipo` (HU-80, tarea 86) también dependen solo de
     * `trabajo` —"al crear una aplicación" es cuando se abre el trabajo, no
     * hace falta que exista sesión, y lo mismo para adjuntar la evidencia del
     * equipo— así que van junto a `recepcion_caldo`; el orden relativo entre
     * esas tres no importa, ninguna referencia a las otras. `recarga` (tarea
     * 23) depende solo de `sesion`, igual que `condiciones` — el orden
     * relativo entre esas dos no importa, ninguna referencia a la otra.
     * `estadia_entrada`/`estadia_salida` (HU-51, tarea 74) van al final: no
     * son prerrequisito causal de nada — la estadía del equipo en la hacienda
     * es independiente de trabajo/sesión.
     */
    private const array ORDEN_CAUSAL = ['trabajo', 'recepcion_caldo', 'evidencia_equipo', 'mezcla', 'sesion', 'condiciones', 'incidencia', 'recarga', 'cierre_trabajo', 'cierre_sesion', 'estadia_entrada', 'estadia_salida'];

    public function __construct(
        private readonly EscrituraSincronizacion $operaciones,
        private readonly EscrituraMezclas $mezclas,
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
                    ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.tipo_desconocido')),
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
            'evidencia_equipo' => $this->aplicarEvidenciaEquipo($registro),
            'mezcla' => $this->aplicarMezcla($registro),
            'sesion' => $this->aplicarSesion($registro, $operarioPersonaId),
            'condiciones' => $this->aplicarCondiciones($registro),
            'incidencia' => $this->aplicarIncidencia($registro),
            'recarga' => $this->aplicarRecarga($registro),
            'cierre_trabajo' => $this->aplicarCierreTrabajo($registro, $operarioPersonaId),
            'cierre_sesion' => $this->aplicarCierreSesion($registro, $operarioPersonaId),
            'estadia_entrada' => $this->aplicarEstadiaEntrada($registro),
            'estadia_salida' => $this->aplicarEstadiaSalida($registro),
            default => ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.tipo_desconocido')),
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
            ? ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.recepcion_caldo_invalida'))
            : $this->operaciones->registrarRecepcionCaldo($datos);
    }

    /**
     * @param  array<string, mixed>  $registro
     *
     * Sin verificación de pertenencia (mismo criterio que
     * `aplicarRecepcionCaldo()`): la espec no define una noción de "dueño"
     * para este registro.
     */
    private function aplicarEvidenciaEquipo(array $registro): ResultadoSincronizacion
    {
        $datos = RegistroEvidenciaEquipo::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.evidencia_equipo_invalida'))
            : $this->operaciones->registrarEvidenciaEquipo($datos);
    }

    /**
     * @param  array<string, mixed>  $registro
     *
     * Sin verificación de pertenencia (ver docblock de
     * `aplicarRecepcionCaldo()`): la espec no define una noción de "dueño"
     * para este registro — cualquier operario legítimo del trabajo puede
     * transcribir qué se cargó.
     */
    private function aplicarMezcla(array $registro): ResultadoSincronizacion
    {
        $datos = RegistroMezcla::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.mezcla_invalida'))
            : $this->mezclas->registrarMezcla($datos);
    }

    /** @param  array<string, mixed>  $registro */
    private function aplicarTrabajo(array $registro): ResultadoSincronizacion
    {
        $datos = AperturaTrabajo::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.trabajo_invalido'))
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
            return ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.sesion_invalida'));
        }

        if ($operarioPersonaId !== null && $datos->pilotoId !== $operarioPersonaId) {
            return ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.piloto_no_coincide'));
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
            ? ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.condiciones_invalidas'))
            : $this->operaciones->registrarCondiciones($datos);
    }

    /**
     * @param  array<string, mixed>  $registro
     *
     * Sin verificación de pertenencia (ver docblock de
     * `EscrituraSincronizacion::registrarIncidencia()`): la espec no define
     * una noción de "dueño" para este registro.
     */
    private function aplicarIncidencia(array $registro): ResultadoSincronizacion
    {
        $datos = RegistroIncidencia::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.incidencia_invalida'))
            : $this->operaciones->registrarIncidencia($datos);
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
            ? ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.recarga_invalida'))
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
            ? ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.cierre_trabajo_invalido'))
            : $this->operaciones->cerrarTrabajo($datos, $operarioPersonaId);
    }

    /** @param  array<string, mixed>  $registro */
    private function aplicarCierreSesion(array $registro, ?int $operarioPersonaId): ResultadoSincronizacion
    {
        $datos = CierreSesion::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.cierre_sesion_invalido'))
            : $this->operaciones->cerrarSesion($datos, $operarioPersonaId);
    }

    /**
     * @param  array<string, mixed>  $registro
     *
     * Sin verificación de pertenencia (ver docblock de
     * `EscrituraSincronizacion::abrirEstadia()`): la estadía es del equipo,
     * no de una persona — no hay nada que comparar contra el operario del
     * token.
     */
    private function aplicarEstadiaEntrada(array $registro): ResultadoSincronizacion
    {
        $datos = AperturaEstadiaHacienda::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.estadia_invalida'))
            : $this->operaciones->abrirEstadia($datos);
    }

    /**
     * @param  array<string, mixed>  $registro
     *
     * Sin verificación de pertenencia, mismo motivo que `aplicarEstadiaEntrada()`.
     */
    private function aplicarEstadiaSalida(array $registro): ResultadoSincronizacion
    {
        $datos = CierreEstadiaHacienda::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado(Texto::de('sincronizacion.sync.cierre_estadia_invalido'))
            : $this->operaciones->cerrarEstadia($datos);
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
