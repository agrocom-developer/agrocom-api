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
 * @property int $id
 * @property string $razon_social
 * @property string|null $nit
 * @property string $tipo_persona
 */
class Cliente extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'com_clientes';

    /** @var list<string> */
    protected $fillable = [
        'razon_social',
        'nit',
        'tipo_persona',
    ];

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
