<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaAlertasTemperaturaBateria;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Recarga;

/**
 * Implementación Eloquent del contrato de lectura de alertas de temperatura
 * por batería. Vive fuera de `Infraestructura/Eloquent/` a propósito, mismo
 * criterio que `LecturaSesionValidadaEloquent`: esa subcarpeta está
 * reservada a modelos que extienden `ModeloDominio`
 * (`tests/Unit/ArquitecturaModulosTest.php` lo exige), y esta clase no es
 * un modelo — es el adaptador que el `ServiceProvider` del módulo liga a
 * {@see LecturaAlertasTemperaturaBateria}.
 */
final class LecturaAlertasTemperaturaBateriaEloquent implements LecturaAlertasTemperaturaBateria
{
    public function tuvoAlertaDeTemperatura(string $identificador): bool
    {
        return Recarga::query()
            ->where('bateria_saliente_id', $identificador)
            ->where('alerta_temperatura', true)
            ->exists();
    }
}
