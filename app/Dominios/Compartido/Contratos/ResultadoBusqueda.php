<?php

namespace App\Dominios\Compartido\Contratos;

/**
 * Una fila encontrada por el buscador global, ya lista para pintar: quien la
 * arma resuelve el título, el detalle y el enlace, y quien la muestra no
 * vuelve a tocar el modelo.
 *
 * Es lo que cruza la frontera entre un módulo y la pantalla de resultados
 * (ADR 0003, regla 2), así que NO lleva el Eloquent adentro — si lo llevara,
 * la vista terminaría leyendo tablas ajenas por la puerta de atrás.
 */
final class ResultadoBusqueda
{
    /**
     * @param  string  $titulo  Lo que identifica la fila: razón social,
     *                          identificador de dron, código de lote.
     * @param  string|null  $detalle  Segunda línea, opcional: NIT, ubicación,
     *                                cliente al que pertenece.
     * @param  string|null  $href  Adónde lleva. `null` = sin pantalla propia
     *                             (se muestra sin enlace, no se inventa uno).
     * @param  string|null  $estado  Etiqueta corta de estado, si la entidad
     *                               tiene máquina de estados.
     */
    public function __construct(
        public readonly string $titulo,
        public readonly ?string $detalle = null,
        public readonly ?string $href = null,
        public readonly ?string $estado = null,
    ) {}
}
