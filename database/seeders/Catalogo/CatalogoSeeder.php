<?php

namespace Database\Seeders\Catalogo;

use Illuminate\Database\Seeder;

/**
 * Orquestador de la familia catálogo (insumos §7.1): corre en todos los
 * entornos, producción incluida.
 *
 * Los seeders de catálogo llegan con sus módulos: roles y permisos sec_*
 * (Seguridad, HU-01), el menú del panel, las categorías de insumo
 * (Operaciones), las zonas de asignación y el proveedor propio (marketplace).
 */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SeguridadSeeder::class);
        // Depende de sec_permission ya sembrado por SeguridadSeeder (arriba):
        // gatea el ítem "Usuarios" con seguridad.usuario.ver.
        $this->call(SecMenuSeeder::class);
        // HU-79 (tarea 110): categorías de insumo (sólido/líquido) de la
        // orden de aplicación — sin dependencia de los anteriores.
        $this->call(OperacionesCategoriasInsumoSeeder::class);
        // Marketplace (ADR 0028 y 0036): zonas de asignación y el proveedor
        // propio (Agrocom). El proveedor propio referencia una zona, así que
        // va después.
        $this->call(ZonasSeeder::class);
        $this->call(ProveedorPropioSeeder::class);
    }
}
