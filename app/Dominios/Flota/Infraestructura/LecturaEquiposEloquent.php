<?php

namespace App\Dominios\Flota\Infraestructura;

use App\Dominios\Flota\Contratos\DatosEquipoApto;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Flota\Dominio\AptitudDeEquipo;
use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Proveedores\Contratos\VerificacionDocumental;
use DateTimeImmutable;

/**
 * Calcula qué equipos de un proveedor pueden recibir ofertas hoy. Los estados
 * de proveedor, piloto y auxiliar salen del contrato de `Proveedores`; los
 * documentos se verifican en vivo (no solo por el estado), de modo que un
 * documento que venció esta madrugada deja al equipo fuera antes de que corra el
 * proceso diario que suspende.
 */
final class LecturaEquiposEloquent implements LecturaEquipos
{
    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly VerificacionDocumental $documentos,
    ) {}

    public function aptosDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
    {
        $hoy ??= new DateTimeImmutable('today');
        $fecha = $hoy->format('Y-m-d');

        $proveedor = $this->proveedores->proveedor($proveedorId);

        if ($proveedor === null || ! $proveedor->estaAprobado()) {
            return [];
        }

        $aptos = [];

        $equipos = Equipo::query()
            ->with('dron')
            ->where('proveedor_id', $proveedorId)
            ->whereDate('vigente_desde', '<=', $fecha)
            ->where(fn ($consulta) => $consulta->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha))
            ->orderBy('id')
            ->get();

        foreach ($equipos as $equipo) {
            $piloto = $this->proveedores->piloto($equipo->piloto_id);
            $auxiliar = $equipo->auxiliar_id === null ? null : $this->proveedores->auxiliar($equipo->auxiliar_id);

            if ($piloto === null || ($equipo->auxiliar_id !== null && $auxiliar === null)) {
                continue;
            }

            $apto = AptitudDeEquipo::esApto(
                $proveedor->estado,
                $piloto->estado,
                $equipo->dron->estado->value,
                $auxiliar?->estado,
                $this->documentos->faltantes('piloto', $piloto->id) === [],
                $this->documentos->faltantes('dron', $equipo->dron_id) === [],
                $equipo->vigencia(),
                $fecha,
            );

            if ($apto) {
                $aptos[] = new DatosEquipoApto(
                    $equipo->id,
                    $proveedorId,
                    $equipo->piloto_id,
                    $equipo->dron_id,
                    $equipo->auxiliar_id,
                    $equipo->dron->capacidad_tanque_l,
                    $equipo->dron->ancho_pasada_m,
                );
            }
        }

        return $aptos;
    }

    public function equiposDeCuenta(int $usuarioId, bool $soloVigentes = true, ?DateTimeImmutable $hoy = null): array
    {
        $registros = $this->proveedores->registrosDeCuenta($usuarioId);

        if ($registros['pilotos'] === [] && $registros['auxiliares'] === []) {
            return [];
        }

        $fecha = ($hoy ?? new DateTimeImmutable('today'))->format('Y-m-d');

        return array_map('intval', Equipo::query()
            ->where(fn ($consulta) => $consulta
                ->whereIn('piloto_id', $registros['pilotos'])
                ->orWhereIn('auxiliar_id', $registros['auxiliares']))
            ->when($soloVigentes, fn ($consulta) => $consulta
                ->whereDate('vigente_desde', '<=', $fecha)
                ->where(fn ($vigencia) => $vigencia->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha)))
            ->orderBy('id')
            ->pluck('id')
            ->all());
    }

    public function vigentesDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
    {
        $fecha = ($hoy ?? new DateTimeImmutable('today'))->format('Y-m-d');

        return array_map('intval', Equipo::query()
            ->where('proveedor_id', $proveedorId)
            ->whereDate('vigente_desde', '<=', $fecha)
            ->where(fn ($consulta) => $consulta->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha))
            ->orderBy('id')
            ->pluck('id')
            ->all());
    }

    public function tieneEquipoApto(int $proveedorId, ?DateTimeImmutable $hoy = null): bool
    {
        return $this->aptosDe($proveedorId, $hoy) !== [];
    }
}
