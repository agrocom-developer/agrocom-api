<?php

namespace App\Dominios\Distribucion\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use Database\Factories\VersionApkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Versión del APK distribuido a los dispositivos de campo (HU-20). El
 * `estado` solo lo escribe {@see
 * \App\Dominios\Distribucion\Aplicacion\MaquinaEstados\MaquinaEstadosVersionApk}
 * (invariante 7 de CLAUDE.md).
 *
 * Lleva {@see RegistraBitacora}: quién autorizó qué versión y cuándo es
 * justo la mutación que la bitácora de auditoría (invariante 9) existe para
 * cubrir, aunque la aduana automática de `BitacoraAuditoriaTest` no la exija
 * todavía (hoy solo cubre la categoría "roles/permisos" del ADR 0007).
 *
 * @property int $id
 * @property string $version
 * @property int $version_code
 * @property string $url_apk
 * @property EstadoVersionApk $estado
 */
class VersionApk extends ModeloDominio
{
    /** @use HasFactory<VersionApkFactory> */
    use HasFactory;

    use RegistraBitacora;

    protected $table = 'dis_versiones_apk';

    /** @var list<string> */
    protected $fillable = [
        'version',
        'version_code',
        'url_apk',
        'estado',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version_code' => 'integer',
            'estado' => EstadoVersionApk::class,
        ];
    }

    protected static function newFactory(): VersionApkFactory
    {
        return VersionApkFactory::new();
    }
}
