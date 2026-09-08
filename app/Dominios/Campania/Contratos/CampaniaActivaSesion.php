<?php

namespace App\Dominios\Campania\Contratos;

/**
 * Frontera de lectura de Campania hacia otros módulos (ADR 0003, regla 2):
 * la campaña activa de la sesión actual del panel, sin que el consumidor
 * importe el modelo Eloquent `Campania`. Dos consumidores hoy:
 * - `Seguridad\Infraestructura\Http\Presentacion\CascaraPanel`, para el chip
 *   del header (`etiquetaActiva()`).
 * - `Comercial`/`Finanzas`, para filtrar contratos y gastos por campaña
 *   activa por defecto (`idActivo()`, tarea 69 etapa 4).
 *
 * Sin `Request` en la firma, igual que `Seguridad\Aplicacion\ElegirRolActivo`:
 * lee la sesión del request actual vía el facade `Session`, no necesita que
 * el llamador se la pase.
 */
interface CampaniaActivaSesion
{
    /** `null` si ninguna campaña está activa en la sesión actual. */
    public function idActivo(): ?int;

    /**
     * Código de la campaña activa (el texto del chip), o `null` si ninguna
     * está activa — mismo caso que `idActivo()` devuelve `null`.
     */
    public function etiquetaActiva(): ?string;
}
