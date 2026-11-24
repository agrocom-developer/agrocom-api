<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Las solicitudes abiertas que ven los pilotos en el mapa (`negociacion-contrato.md`
 * §8). Solo lo que una solicitud muestra antes de ofertar: nunca nombre,
 * teléfono, documento ni dirección del cliente (§3.4).
 */
interface LecturaMercado
{
    /**
     * Solicitudes `abierta` o `con_ofertas` que cumplen los filtros de contenido
     * (zona, hectáreas, precio, fecha, cultivo y tipo). El encuadre y la distancia
     * los resuelve quien llama, porque dependen del centro de los lotes.
     *
     * @param  array{zona_id?: ?int, zonas?: list<int>, macrorregion?: ?string, hectareas_min?: ?string, hectareas_max?: ?string, precio_ha_min?: ?string, precio_ha_max?: ?string, fecha_hasta?: ?string, cultivo?: ?string, tipo_aplicacion?: ?string, excluir_cliente_usuario_id?: ?int}  $filtros
     * @return list<DatosSolicitudAbierta>
     */
    public function abiertas(array $filtros): array;

    /** Una solicitud del mercado con su detalle previo a ofertar; `null` si no está `abierta` ni `con_ofertas`. */
    public function abierta(int $solicitudId): ?DatosSolicitudAbierta;
}
