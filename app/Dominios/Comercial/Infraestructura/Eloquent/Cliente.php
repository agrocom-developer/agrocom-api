<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Comercial\Dominio\TipoPersonaCliente;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cliente de Agrocom (espec §4.1, tabla com_clientes).
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md, HU-22): el alta, edición y
 * baja de un cliente es una mutación de negocio con autor y momento
 * auditables, aunque el esquema no la marque como catálogo de rol/permiso
 * (`tests/Unit/BitacoraAuditoriaTest.php` no la exige — se adopta igual,
 * mismo criterio que `DevengoPersonal` en Finanzas).
 *
 * `tipo_persona` (ADR 0018, punto 3): física (unipersonal) o jurídica
 * (sociedad) — {@see TipoPersonaCliente}.
 *
 * `ubicacion_oficina`/`logo_path` (HU-75, tarea 91): dónde queda la oficina
 * central del cliente y su logo. `logo_path` deliberadamente NO es
 * `fillable` (mismo criterio que `SecDatosEmpresa.logo_path`, ADR 0019):
 * solo `CrearCliente`/`ActualizarCliente` lo escriben, con asignación
 * directa tras guardar el archivo — nunca vía `fill()` de datos crudos del
 * request.
 *
 * `nombre_comercial` (tarea "resumen de cliente"): solo tiene sentido para
 * `tipo_persona = juridica` (una sociedad puede operar bajo un nombre
 * distinto al de su razón social) — el formulario lo oculta para persona
 * física, pero la columna no lleva CHECK cruzado: es dato de presentación.
 *
 * `tipo_persona` cast a {@see TipoPersonaCliente} (bug encontrado 15/9/2026,
 * tarea "resumen de cliente"): faltaba desde el alta original (HU-22/ADR
 * 0018) — sin cast, `$cliente->tipo_persona` es un string plano, y
 * `_formulario.blade.php` ya asumía un enum (`$cliente?->tipo_persona?->value`).
 * `->value` sobre un string dispara un warning silencioso y da `null`, así
 * que en TODA edición el `<select>` de tipo de persona nunca reflejaba el
 * valor real guardado — el navegador mostraba la primera opción por
 * default, sin que nada quedara realmente seleccionado. Pasó desapercibido
 * hasta que "Nombre comercial" (que depende de leer `tipo_persona`
 * correctamente) lo hizo visible.
 *
 * @property int $id
 * @property string $razon_social
 * @property string|null $nombre_comercial
 * @property string|null $nit
 * @property TipoPersonaCliente $tipo_persona
 * @property string|null $ubicacion_oficina
 * @property string|null $logo_path
 */
class Cliente extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'com_clientes';

    /** @var list<string> */
    protected $fillable = [
        'razon_social',
        'nombre_comercial',
        'nit',
        'tipo_persona',
        'ubicacion_oficina',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo_persona' => TipoPersonaCliente::class,
        ];
    }

    /** @return HasMany<ClienteContacto, $this> */
    public function contactos(): HasMany
    {
        return $this->hasMany(ClienteContacto::class, 'cliente_id');
    }

    /** @return HasMany<Contrato, $this> */
    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'cliente_id');
    }

    /**
     * Propiedades del cliente (ADR 0018): el nivel de terreno que cuelga
     * directo de `Cliente` ahora es `Propiedad`, no `Campo` — un campo
     * físico cuelga de su propiedad, no del cliente.
     *
     * @return HasMany<Propiedad, $this>
     */
    public function propiedades(): HasMany
    {
        return $this->hasMany(Propiedad::class, 'cliente_id');
    }
}
