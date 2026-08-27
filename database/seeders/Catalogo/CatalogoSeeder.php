<?php

namespace Database\Seeders\Catalogo;

use Illuminate\Database\Seeder;

/**
 * Orquestador de la familia catálogo (insumos §7.1): corre en todos los
 * entornos, producción incluida.
 *
 * El núcleo comercial (clientes, contratos, campos, lotes, órdenes) no tiene
 * tablas de catálogo: todo su contenido es dato del negocio, no dato semilla.
 * Los seeders de catálogo llegan con sus módulos: roles y permisos sec_*
 * (Seguridad, HU-01), modelos de dron (Recursos), productos y formulaciones
 * (Mezcla), rubros y subrubros (Finanzas), enums operativos y parámetros de
 * negocio.
 */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SeguridadSeeder::class);
        // Depende de sec_permission ya sembrado por SeguridadSeeder (arriba):
        // gatea el ítem "Usuarios" con seguridad.usuario.ver.
        $this->call(SecMenuSeeder::class);
    }
}
