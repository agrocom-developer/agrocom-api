<?php

namespace App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor;

use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;

/**
 * Un filtro del proveedor (ADR 0045, puntos 5 a 7): una preferencia suya, guardada en
 * `neg_preferencias.filtros`, que decide a qué nuevas solicitudes NO quiere que se le avise.
 * Gobierna solo los avisos: no oculta el mapa. La escalera de oleadas nunca lo relaja.
 *
 * Agregar un filtro es agregar una clase, su prueba y registrarla en {@see FiltrosDeProveedor}.
 * No exige migración: los valores viajan en una columna JSON.
 */
interface FiltroDeProveedor
{
    /** Clave estable, la que viaja en el contrato de preferencias (p. ej. `hectareas_minimas`). */
    public function clave(): string;

    /** Tipo del valor para que la app pinte el control: `decimal`, `departamentos` o `tipos_aplicacion`. */
    public function tipo(): string;

    /** Valor que rige cuando el proveedor no declaró nada (no restringe). */
    public function porDefecto(): mixed;

    /**
     * Normaliza el valor recibido, o devuelve `null` si equivale a «sin filtro» (vacío).
     *
     * @throws NegociacionInvalida 422 `filtro_invalido` si el valor no es de este filtro.
     */
    public function normalizar(mixed $valor): mixed;

    /** ¿Este filtro, con este valor ya normalizado, deja pasar la solicitud? */
    public function admite(mixed $valor, SolicitudParaAviso $solicitud): bool;
}
