<?php

namespace Tests\Soporte;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Levanta el esquema REAL de unas migraciones en la SQLite en memoria de las
 * pruebas (sin llaves foráneas), para ejercitar los casos de uso contra las
 * tablas verdaderas y no contra dobles de la base.
 */
final class Esquema
{
    /** @var array<string, object> */
    private static array $instancias = [];

    /** @param  list<string>  $nombres  sufijos de las migraciones (`create_sol_clientes_table`). */
    public static function migrar(array $nombres): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($nombres as $nombre) {
            $archivos = glob(database_path("migrations/*_{$nombre}.php")) ?: [];

            if (count($archivos) !== 1) {
                throw new RuntimeException("Se esperaba exactamente una migración {$nombre}.");
            }

            self::$instancias[$archivos[0]] ??= require $archivos[0];

            try {
                self::$instancias[$archivos[0]]->up();
            } catch (QueryException $excepcion) {
                if (! str_contains($excepcion->getMessage(), 'already exists')
                    && ! str_contains($excepcion->getMessage(), 'USING btree')
                    && ! str_contains($excepcion->getMessage(), 'duplicate column')) {
                    throw $excepcion;
                }
            }
        }
    }
}
