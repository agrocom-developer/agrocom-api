<?php

namespace App\Dominios\Compartido\Infraestructura\Eloquent;

/**
 * Configuración del sistema (tarea 78, HU-55): llaves y tokens de
 * infraestructura (mapas, correo, integraciones) — nunca datos de la empresa
 * (eso es `SecDatosFiscales`, en `Seguridad`).
 *
 * `valor` lleva el cast `encrypted` de Laravel: cifrado en reposo con
 * `APP_KEY`, transparente en PHP (leer/escribir el atributo ya cifra/descifra
 * solo) pero texto plano NUNCA llega a la columna. `es_secreto` es un dato
 * de presentación (decide qué oculta `/panel/configuracion`, ver
 * `ConfiguracionController`) — un valor no-secreto (p. ej. el proveedor de
 * mapas preferido) igual viaja cifrado, porque cifrar todo `valor` es más
 * simple y más seguro que decidir columna por fila.
 *
 * {@see columnasSensiblesBitacora()} es la mitad que la bitácora necesita:
 * sin esto, `BitacoraObserver` guardaría el ciphertext de `valor` en
 * `plt_bitacoras.antes/despues` en cada alta o cambio — todavía sería un
 * secreto (cifrado), pero un segundo lugar del que robarlo si alguna vez se
 * compromete `APP_KEY`. La invariante 9 de CLAUDE.md pide valores
 * "antes/después donde aplique"; acá no aplica.
 */
final class Configuracion extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'plt_configuraciones';

    /** @var list<string> */
    protected $fillable = [
        'clave',
        'valor',
        'grupo',
        'descripcion',
        'es_secreto',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'valor' => 'encrypted',
            'es_secreto' => 'boolean',
        ];
    }

    /** @return list<string> */
    public function columnasSensiblesBitacora(): array
    {
        return ['valor'];
    }
}
