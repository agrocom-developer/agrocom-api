<?php

namespace App\Dominios\Sincronizacion\Aplicacion;

use App\Dominios\Operaciones\Contratos\AperturaSesion;
use App\Dominios\Operaciones\Contratos\AperturaTrabajo;
use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\ResultadoSincronizacion;

/**
 * Caso de uso de `POST /api/sync` (espec §2.1, puntos 3 a 5; TE-05, tarea
 * 09). Agrupa el lote por tipo de entidad y lo aplica siempre en el mismo
 * orden causal fijo —`trabajo` antes que `sesion`—, sin importar el orden en
 * que llegó el arreglo del cliente: así, cuando le toca el turno a `sesion`,
 * el `trabajo` que referencia por `uuid_cliente` (si vino en el mismo lote)
 * ya quedó persistido. Es el diseño que evita construir lógica de reintento
 * entre pushes para el caso "en desorden" (ver el prompt de la tarea 09,
 * "Diseño que evita el problema difícil").
 *
 * Solo conoce el contrato de `Operaciones` ({@see EscrituraSincronizacion}) —
 * nunca sus modelos Eloquent (ADR 0003, regla 2). El orden de la respuesta
 * respeta el orden de ENTRADA, no el de procesamiento, para que el cliente
 * pueda correlacionar cada resultado con el registro que envió.
 */
final class SincronizarLote
{
    /** Orden causal fijo (espec §2.1, punto 3): trabajo, luego sesión. */
    private const array ORDEN_CAUSAL = ['trabajo', 'sesion'];

    public function __construct(
        private readonly EscrituraSincronizacion $operaciones,
    ) {}

    /**
     * @param  list<mixed>  $registros
     * @return list<array<string, mixed>>
     */
    public function ejecutar(array $registros): array
    {
        /** @var array<int, array<string, mixed>|null> $resultados */
        $resultados = array_fill(0, count($registros), null);

        foreach (self::ORDEN_CAUSAL as $tipo) {
            foreach ($registros as $indice => $registro) {
                if (! is_array($registro) || ($registro['tipo'] ?? null) !== $tipo) {
                    continue;
                }

                $resultados[$indice] = $this->filaResultado($registro, $this->aplicar($tipo, $registro));
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
    private function aplicar(string $tipo, array $registro): ResultadoSincronizacion
    {
        return $tipo === 'trabajo'
            ? $this->aplicarTrabajo($registro)
            : $this->aplicarSesion($registro);
    }

    /** @param  array<string, mixed>  $registro */
    private function aplicarTrabajo(array $registro): ResultadoSincronizacion
    {
        $datos = AperturaTrabajo::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado('trabajo con datos incompletos o inválidos')
            : $this->operaciones->abrirTrabajo($datos);
    }

    /** @param  array<string, mixed>  $registro */
    private function aplicarSesion(array $registro): ResultadoSincronizacion
    {
        $datos = AperturaSesion::intentarDesdeArreglo($registro);

        return $datos === null
            ? ResultadoSincronizacion::rechazado('sesión con datos incompletos o inválidos')
            : $this->operaciones->abrirSesion($datos);
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
