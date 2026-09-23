<?php

namespace App\Dominios\Compartido\Aplicacion;

use App\Dominios\Compartido\Contratos\LecturaConfiguracion;

/**
 * Caso de uso: qué proveedor de mapa usa cualquier editor de mapa del panel
 * (tarea 79, HU-56) — primer consumidor real de {@see LecturaConfiguracion}
 * (tarea 78). Vive en Compartido (movido de `Comercial\Aplicacion` en la
 * tarea 132) porque no depende de nada específico de un módulo: es
 * configuración de plataforma (`mapas.*`), consumida hoy por
 * `Comercial\Infraestructura\Http\Controllers\Web\LotesController`,
 * `PropiedadMapaController` y `Personal\Infraestructura\Http\Controllers\Web\BasesController`
 * — módulos que no pueden importarse `Aplicacion` entre sí (ADR 0003, regla 2).
 *
 * Reglas (`config/configuracion.php`, claves `mapas.*`):
 * - `mapas.proveedor_preferido === 'leaflet'` fuerza Leaflet aunque haya
 *   llave cargada — apagador manual, para no facturar en Google Maps
 *   Platform mientras se prueba algo o si se prefiere no usarlo todavía.
 * - Sin ese apagador, `mapas.google_maps_api_key` cargada y no vacía manda a
 *   Google Maps.
 * - Cualquier otro caso (sin llave, o `proveedor_preferido` con cualquier
 *   otro valor sin llave) cae a Leaflet + Esri — el camino de siempre, que
 *   tiene que seguir funcionando completo sin este contrato.
 *
 * La llave SOLO se devuelve cuando el proveedor elegido es Google: es lo que
 * evita que `google_maps_api_key` viaje al HTML cuando el editor va a usar
 * Leaflet igual (test de la tarea 79: "la llave no aparece en el HTML cuando
 * el proveedor configurado es Leaflet").
 */
final class ResolverProveedorMapa
{
    public function __construct(private readonly LecturaConfiguracion $lectura) {}

    /** @return array{proveedor: 'google'|'leaflet', googleMapsApiKey: ?string} */
    public function ejecutar(): array
    {
        $preferido = $this->lectura->valor('mapas.proveedor_preferido');

        if ($preferido === 'leaflet') {
            return ['proveedor' => 'leaflet', 'googleMapsApiKey' => null];
        }

        $llave = $this->lectura->valor('mapas.google_maps_api_key');

        if ($llave !== null && $llave !== '') {
            return ['proveedor' => 'google', 'googleMapsApiKey' => $llave];
        }

        return ['proveedor' => 'leaflet', 'googleMapsApiKey' => null];
    }
}
