<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use App\Dominios\Operaciones\Contratos\LecturaAlertasTemperaturaBateria;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de baterías con búsqueda y filtro por base/estado
 * (HU-39, tarea 51), con la alerta de "retirar la batería" calculada por
 * fila — nunca persistida (no hay tabla de alertas propia, mismo criterio
 * que `ope_drones`). Dos disparadores, independientes entre sí:
 * - por ciclos: `ciclos_acumulados >= Bateria::UMBRAL_CICLOS_ALERTA`.
 * - por temperatura: alguna recarga de `ope_recargas` marcó
 *   `alerta_temperatura = true` para el `identificador` de esta batería
 *   (vía {@see LecturaAlertasTemperaturaBateria}, el contrato de lectura
 *   hacia `Operaciones`).
 *
 * El resultado de ambos chequeos se deja escrito como atributos NO
 * persistidos (`alerta`, `alerta_motivo`) sobre cada `Bateria` del
 * paginador — así la vista no ejecuta ninguna consulta ni calcula ninguna
 * regla de negocio (checklist §8 de `docs/diseno/guia_pantalla_panel.md`),
 * solo lee un dato ya resuelto.
 */
final class ListarBaterias
{
    public function __construct(private readonly LecturaAlertasTemperaturaBateria $lecturaAlertasTemperatura) {}

    /** @return LengthAwarePaginator<int, Bateria> */
    public function ejecutar(
        ?string $busqueda = null,
        ?int $baseId = null,
        ?string $estado = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        $paginador = Bateria::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => BusquedaTexto::aplicar($consulta, ['identificador'], $busqueda),
            )
            ->when($baseId !== null, fn ($consulta) => $consulta->where('base_id', $baseId))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado))
            ->orderBy('identificador')
            ->paginate($porPagina)
            ->withQueryString();

        $paginador->getCollection()->each(function (Bateria $bateria): void {
            $porCiclos = $bateria->ciclos_acumulados >= Bateria::UMBRAL_CICLOS_ALERTA;
            $porTemperatura = $this->lecturaAlertasTemperatura->tuvoAlertaDeTemperatura($bateria->identificador);

            $bateria->alerta = $porCiclos || $porTemperatura;
            $bateria->alerta_motivo = match (true) {
                $porCiclos && $porTemperatura => 'ciclos_temperatura',
                $porCiclos => 'ciclos',
                $porTemperatura => 'temperatura',
                default => null,
            };
        });

        return $paginador;
    }
}
