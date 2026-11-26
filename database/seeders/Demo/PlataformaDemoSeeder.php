<?php

namespace Database\Seeders\Demo;

use App\Dominios\Compartido\Aplicacion\GuardarConfiguracion;
use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;
use App\Dominios\Seguridad\Aplicacion\GuardarDatosEmpresa;
use App\Dominios\Seguridad\Aplicacion\GuardarDatosFiscales;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecDatosEmpresa;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecDatosFiscales;
use Illuminate\Database\Seeder;

/**
 * Lo que vive en Seguridad → Organización y Configuración: datos de la
 * empresa, datos fiscales y parámetros del sistema.
 *
 * Cada bloque solo siembra si su tabla está vacía: si el dueño ya cargó los
 * datos de su empresa, no se pisan.
 */
class PlataformaDemoSeeder extends Seeder
{
    public function __construct(
        private readonly GuardarDatosEmpresa $guardarDatosEmpresa,
        private readonly GuardarDatosFiscales $guardarDatosFiscales,
        private readonly GuardarConfiguracion $guardarConfiguracion,
    ) {}

    public function run(): void
    {
        $this->empresa();
        $this->configuracion();
    }

    private function empresa(): void
    {
        if (SecDatosEmpresa::query()->doesntExist()) {
            $this->guardarDatosEmpresa->ejecutar([
                'nombre' => 'Agrocom SRL',
                'rubro' => 'Servicios agrícolas con drones: fumigación, siembra y fertilización',
                'email' => 'contacto@agrocom.demo',
                'telefono' => '+591 3 3456789',
                'direccion' => 'Av. Banzer km 9, Santa Cruz de la Sierra, Bolivia',
            ]);
        }

        if (SecDatosFiscales::query()->doesntExist()) {
            $this->guardarDatosFiscales->ejecutar([
                'razon_social_fiscal' => 'AGROCOM S.R.L.',
                'nit' => '1028374650',
                'domicilio_fiscal' => 'Av. Banzer km 9, Santa Cruz de la Sierra, Bolivia',
                'actividad_economica' => 'Servicios de apoyo a la agricultura',
                'leyenda_pie' => 'Este documento es válido como constancia de servicio prestado. Conserva una copia para tu contabilidad.',
            ]);
        }
    }

    private function configuracion(): void
    {
        if (Configuracion::query()->exists()) {
            return;
        }

        $this->guardarConfiguracion->ejecutar('mapas', [
            'mapas.proveedor_preferido' => 'leaflet',
        ], []);

        // Apunta al Mailpit del compose (servicio `mail`, puerto 1025): los
        // correos de la demo quedan en su bandeja y no salen a ningún lado.
        $this->guardarConfiguracion->ejecutar('correo', [
            'correo.host' => 'mail',
            'correo.puerto' => '1025',
            'correo.usuario' => '',
            'correo.remitente' => 'no-responder@agrocom.demo',
        ], []);
    }
}
