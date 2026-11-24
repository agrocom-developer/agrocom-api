<?php

namespace App\Dominios\Compartido\Contratos\Eventos;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: un administrador (o el sistema) cambió el estado de
 * aprobación de un proveedor, piloto, auxiliar o dron. Lo emite el módulo dueño
 * del sujeto (`Proveedores` o `Flota`) después de persistir; `Notificaciones`
 * avisa a la cuenta afectada (especificación §7 y §12: `ProveedorAprobado`,
 * `ProveedorSuspendido`) y `Flota` reacciona a los documentos por separado.
 *
 * DTO de primitivos, sin modelos Eloquent (ADR 0003). `usuariosDestinatarios`
 * son las cuentas a las que se avisa: la propia del piloto o auxiliar y el
 * titular de su proveedor, el titular del proveedor, o el del proveedor dueño
 * del dron.
 */
final readonly class EstadoDeAprobacionCambiado implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $sujetoTipo,
        public int $sujetoId,
        public string $nombre,
        public int $historialId,
        public AccionAprobacion $accion,
        public ?string $motivo,
        /** @var list<int> */
        public array $usuariosDestinatarios,
        public bool $porElSistema = false,
    ) {}
}
