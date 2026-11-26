<?php

namespace Tests\Soporte;

use Illuminate\Support\Facades\DB;

/**
 * El catálogo de ubicaciones (ADR 0045, punto 3) para las pruebas que abren
 * solicitudes: levanta las tablas reales y deja un departamento y dos provincias
 * (Santa Cruz con Andrés Ibáñez y Warnes, Cochabamba con Cercado). Quien necesite
 * el catálogo completo corre `UbicacionesSeeder`.
 */
final class Ubicaciones
{
    public const DEPARTAMENTO = '07';

    public const PROVINCIA = '0702';

    /** Las migraciones del catálogo y de las columnas de ubicación, para `Esquema::migrar`. */
    public static function migraciones(): array
    {
        return [
            'create_sol_departamentos_table',
            'create_sol_provincias_table',
            'agrega_ubicacion_a_sol_terrenos',
            'agrega_ubicacion_a_sol_solicitudes_servicio',
        ];
    }

    public static function sembrar(): void
    {
        Esquema::migrar(self::migraciones());

        $ahora = now();
        foreach ([['07', 'Santa Cruz'], ['03', 'Cochabamba']] as [$codigo, $nombre]) {
            DB::table('sol_departamentos')->insertOrIgnore(['codigo' => $codigo, 'nombre' => $nombre, 'created_at' => $ahora, 'updated_at' => $ahora]);
        }
        foreach ([['07', '0701', 'Andrés Ibáñez'], ['07', '0702', 'Warnes'], ['03', '0301', 'Cercado']] as [$departamento, $codigo, $nombre]) {
            DB::table('sol_provincias')->insertOrIgnore(['departamento_codigo' => $departamento, 'codigo' => $codigo, 'nombre' => $nombre, 'created_at' => $ahora, 'updated_at' => $ahora]);
        }
    }
}
