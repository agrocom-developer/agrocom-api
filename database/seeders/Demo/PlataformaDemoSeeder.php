<?php

namespace Database\Seeders\Demo;

use App\Dominios\Compartido\Aplicacion\GuardarConfiguracion;
use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;
use App\Dominios\Distribucion\Aplicacion\MaquinaEstados\MaquinaEstadosVersionApk;
use App\Dominios\Distribucion\Aplicacion\RegistrarVersionApk;
use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use App\Dominios\Seguridad\Aplicacion\GuardarDatosEmpresa;
use App\Dominios\Seguridad\Aplicacion\GuardarDatosFiscales;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecDatosEmpresa;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecDatosFiscales;
use Illuminate\Database\Seeder;

/**
 * Lo que vive en Seguridad → Organización, Configuración y Versiones APK:
 * datos de la empresa, datos fiscales, parámetros del sistema y las
 * versiones de `agrocom-field`.
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
        private readonly RegistrarVersionApk $registrarVersionApk,
        private readonly MaquinaEstadosVersionApk $maquinaVersionApk,
    ) {}

    public function run(): void
    {
        $this->empresa();
        $this->configuracion();
        $this->versionesApk();
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

    private function versionesApk(): void
    {
        if (VersionApk::query()->exists()) {
            return;
        }

        $catalogo = [
            ['1.2.0', 120, 'https://descargas.agrocom.demo/agrocom-field-1.2.0.apk'],
            ['1.3.0', 130, 'https://descargas.agrocom.demo/agrocom-field-1.3.0.apk'],
            ['1.4.0', 140, 'https://descargas.agrocom.demo/agrocom-field-1.4.0.apk'],
        ];

        $versiones = [];

        foreach ($catalogo as [$version, $codigo, $url]) {
            $versiones[$version] = $this->registrarVersionApk->ejecutar($version, $codigo, $url);
        }

        // La 1.3.0 estuvo autorizada y la 1.4.0 la reemplazó: la máquina de
        // estados devuelve la anterior a pendiente al autorizar la nueva.
        $this->maquinaVersionApk->autorizar($versiones['1.3.0']);
        $this->maquinaVersionApk->autorizar($versiones['1.4.0']);
    }
}
