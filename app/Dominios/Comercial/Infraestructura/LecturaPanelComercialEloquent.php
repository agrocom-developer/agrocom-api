<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Comercial\Aplicacion\ObtenerAvanceComercial;
use App\Dominios\Comercial\Contratos\AvanceClientePanel;
use App\Dominios\Comercial\Contratos\EstadoCuentaContratoPanel;
use App\Dominios\Comercial\Contratos\LecturaPanelComercial;
use App\Dominios\Comercial\Contratos\LotePanel;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Implementación Eloquent del contrato de contenido comercial del dashboard
 * (tarea 67). Mismo lugar y motivo que {@see LecturaLotesEloquent}: no es un
 * modelo, es el adaptador que el `ServiceProvider` liga al contrato.
 *
 * `avancePorCliente()` **delega** en {@see ObtenerAvanceComercial} en vez de
 * rehacer la consulta: el avance del dashboard y el del reporte comercial
 * (HU-32) tienen que dar el mismo número, y dos consultas equivalentes se
 * desincronizan a la primera regla que cambie. Este adaptador solo ordena,
 * recorta y agrega el porcentaje para la barra.
 */
final class LecturaPanelComercialEloquent implements LecturaPanelComercial
{
    public function __construct(private readonly ObtenerAvanceComercial $avanceComercial) {}

    public function lotesPorId(): array
    {
        $lotes = Lote::query()
            ->with('propiedad:id,nombre,cliente_id', 'propiedad.cliente:id,razon_social')
            ->orderBy('id')
            ->get();

        $porId = [];

        foreach ($lotes as $lote) {
            $propiedad = $lote->propiedad;

            $porId[$lote->id] = new LotePanel(
                id: $lote->id,
                codigo: $lote->codigo,
                hectareas: (string) $lote->hectareas,
                propiedadId: $lote->propiedad_id,
                propiedadNombre: $propiedad->nombre ?? '',
                clienteId: (int) ($propiedad->cliente_id ?? 0),
                clienteNombre: $propiedad->cliente->razon_social ?? '',
                geometria: $lote->geometria,
                restricciones: $lote->restricciones,
            );
        }

        return $porId;
    }

    public function centroOperativo(): ?array
    {
        $sumaLat = 0.0;
        $sumaLng = 0.0;
        $vertices = 0;

        foreach ($this->lotesPorId() as $lote) {
            foreach ($this->verticesDe($lote->geometria) as [$lng, $lat]) {
                $sumaLng += $lng;
                $sumaLat += $lat;
                $vertices++;
            }
        }

        if ($vertices === 0) {
            return null;
        }

        return [
            'lat' => $sumaLat / $vertices,
            'lng' => $sumaLng / $vertices,
        ];
    }

    public function avancePorCliente(int $limite): array
    {
        $avances = [];

        foreach ($this->avanceComercial->ejecutar() as $fila) {
            $contratadas = BigDecimal::of($fila['hectareasContratadas']);
            $aplicadas = BigDecimal::of($fila['hectareasAplicadas']);

            $avances[] = new AvanceClientePanel(
                contratoId: $fila['contratoId'],
                clienteNombre: $fila['clienteNombre'],
                hectareasContratadas: $fila['hectareasContratadas'],
                hectareasAplicadas: $fila['hectareasAplicadas'],
                hectareasFacturadas: $fila['hectareasFacturadas'],
                montoFacturado: $fila['montoFacturado'],
                porcentaje: $this->porcentaje($aplicadas, $contratadas),
            );
        }

        usort($avances, fn (AvanceClientePanel $a, AvanceClientePanel $b) => $a->porcentaje <=> $b->porcentaje);

        return array_slice($avances, 0, $limite);
    }

    public function estadoDeCuentas(int $limite): array
    {
        $avances = $this->avanceComercial->ejecutar();

        if ($avances === []) {
            return [];
        }

        $contratos = Contrato::query()
            ->whereIn('id', array_column($avances, 'contratoId'))
            ->get(['id', 'monto_total', 'adelanto_monto'])
            ->keyBy('id');

        $estados = [];

        foreach ($avances as $avance) {
            $contrato = $contratos->get($avance['contratoId']);

            if ($contrato === null) {
                continue;
            }

            $montoContratado = BigDecimal::of($contrato->monto_total);
            $montoFacturado = BigDecimal::of($avance['montoFacturado']);
            $saldoPendiente = $montoContratado->minus($montoFacturado);

            $estados[] = new EstadoCuentaContratoPanel(
                contratoId: $avance['contratoId'],
                clienteNombre: $avance['clienteNombre'],
                montoContratado: $this->aEscalaDos($montoContratado),
                montoFacturado: $avance['montoFacturado'],
                adelantoMonto: $this->aEscalaDos($contrato->adelanto_monto !== null ? BigDecimal::of($contrato->adelanto_monto) : BigDecimal::zero()),
                saldoPendiente: $this->aEscalaDos($saldoPendiente->isNegative() ? BigDecimal::zero() : $saldoPendiente),
            );
        }

        usort($estados, fn (EstadoCuentaContratoPanel $a, EstadoCuentaContratoPanel $b) => BigDecimal::of($b->saldoPendiente)->compareTo(BigDecimal::of($a->saldoPendiente)));

        return array_slice($estados, 0, $limite);
    }

    private function aEscalaDos(BigDecimal $valor): string
    {
        return (string) $valor->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * Vértices `[lng, lat]` de un GeoJSON `Polygon`, tolerando un
     * `MultiPolygon` o un anillo mal formado sin reventar: la geometría se
     * carga hoy pegando JSON a mano, así que este método no puede asumir que
     * siempre venga bien.
     *
     * @param  array<string, mixed>|null  $geometria
     * @return list<array{0: float, 1: float}>
     */
    private function verticesDe(?array $geometria): array
    {
        $coordenadas = $geometria['coordinates'] ?? null;

        if (! is_array($coordenadas)) {
            return [];
        }

        $vertices = [];
        $aplanar = function ($nivel) use (&$aplanar, &$vertices): void {
            if (! is_array($nivel)) {
                return;
            }

            if (count($nivel) === 2 && is_numeric($nivel[0] ?? null) && is_numeric($nivel[1] ?? null)) {
                $vertices[] = [(float) $nivel[0], (float) $nivel[1]];

                return;
            }

            foreach ($nivel as $hijo) {
                $aplanar($hijo);
            }
        };

        $aplanar($coordenadas);

        return $vertices;
    }

    private function porcentaje(BigDecimal $parte, BigDecimal $total): int
    {
        if ($total->isZero()) {
            return 0;
        }

        $bruto = $parte->dividedBy($total, 4, RoundingMode::HalfUp)
            ->multipliedBy(100)
            ->toScale(0, RoundingMode::HalfUp)
            ->toInt();

        return max(0, min(100, $bruto));
    }
}
