<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Contratos\LecturaCultivoLote;
use App\Dominios\Comercial\Dominio\Excepciones\FiltroInformeIncompleto;
use App\Dominios\Comercial\Dominio\SaldoContrato;
use App\Dominios\Comercial\Dominio\TramoAvance;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

/**
 * Informe de avance de contratos, por cultivo y por cliente (HU-52, tarea 75,
 * espec §9.1). Reusa {@see ObtenerAvanceComercial::paraContratos()} para las
 * tres magnitudes de cada contrato — no vuelve a sumar actas ni facturas: el
 * dashboard, el portal y este informe tienen que decir el mismo número para
 * el mismo contrato.
 *
 * El cultivo no es un atributo del contrato: el contrato es de una campaña
 * entera (todo el campo del cliente — memoria "la campaña es por todo el
 * campo"), y el cultivo vive por lote dentro de esa campaña
 * (`com_lote_campania`, ADR 0015 punto 4). Por eso el cultivo de un contrato
 * sale de {@see LecturaCultivoLote::porCampania()} — los cultivos distintos
 * sembrados en la campaña del contrato — y un mismo contrato puede terminar
 * en más de un grupo de cultivo (ver el docblock de {@see GrupoCultivoInforme}).
 *
 * El porcentaje de esta clase NO se clampea a 100 (a diferencia de
 * `LecturaPanelComercialEloquent::porcentaje()`, que sí lo hace para la
 * barra del dashboard): este informe necesita distinguir el tramo "más de
 * 100%" (espec §9.1), así que reimplementa el redondeo — no la suma de
 * hectáreas, que es lo que la tarea prohíbe duplicar.
 */
final class ObtenerInformeAvanceContratos
{
    public function __construct(
        private readonly ObtenerAvanceComercial $avanceComercial,
        private readonly LecturaCultivoLote $lecturaCultivoLote,
    ) {}

    public function ejecutar(FiltrosInformeAvanceContratos $filtros): InformeAvanceContratos
    {
        if ($filtros->clienteIds === []) {
            throw FiltroInformeIncompleto::porFaltaDeCliente();
        }

        if ($filtros->cultivoIds === []) {
            throw FiltroInformeIncompleto::porFaltaDeCultivo();
        }

        $contratos = $this->contratosFiltrados($filtros);

        if ($contratos->isEmpty()) {
            return new InformeAvanceContratos([]);
        }

        $avancePorContrato = collect($this->avanceComercial->paraContratos($contratos))->keyBy('contratoId');
        $cultivosPorCampania = $this->cultivosPorCampania($contratos, $filtros->cultivoIds);
        $filasPorCultivo = $this->armarFilasPorCultivo($contratos, $avancePorContrato, $cultivosPorCampania, $filtros->saldo);

        $grupos = [];

        foreach ($filasPorCultivo as $cultivoId => $datos) {
            $grupos[] = $this->armarGrupoCultivo($cultivoId, $datos['nombre'], $datos['filas']);
        }

        usort($grupos, fn (GrupoCultivoInforme $a, GrupoCultivoInforme $b) => $a->cultivoNombre <=> $b->cultivoNombre);

        return new InformeAvanceContratos($grupos);
    }

    /** @return Collection<int, Contrato> */
    private function contratosFiltrados(FiltrosInformeAvanceContratos $filtros): Collection
    {
        return Contrato::query()
            ->with('cliente:id,razon_social')
            ->when($filtros->incluirDeshabilitados, fn ($consulta) => $consulta->withTrashed())
            ->whereIn('cliente_id', $filtros->clienteIds)
            ->when($filtros->campaniaIds !== [], fn ($consulta) => $consulta->whereIn('campania_id', $filtros->campaniaIds))
            ->when($filtros->estado !== null, fn ($consulta) => $consulta->where('estado', $filtros->estado))
            ->when(
                $filtros->fechaDesde !== null,
                fn ($consulta) => $consulta->where(
                    fn ($sub) => $sub->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $filtros->fechaDesde)
                )
            )
            ->when($filtros->fechaHasta !== null, fn ($consulta) => $consulta->where('fecha_inicio', '<=', $filtros->fechaHasta))
            ->orderBy('id')
            ->get();
    }

    /**
     * Cultivos distintos sembrados en la campaña de cada contrato, ya
     * filtrados a los pedidos — una sola llamada a `porCampania()` por
     * campaña distinta entre los contratos filtrados, no una por contrato.
     *
     * @param  Collection<int, Contrato>  $contratos
     * @param  list<int>  $cultivoIdsPedidos
     * @return array<int, list<array{id: int, nombre: string}>> indexado por campania_id
     */
    private function cultivosPorCampania(Collection $contratos, array $cultivoIdsPedidos): array
    {
        $resultado = [];

        /** @var int $campaniaId */
        foreach ($contratos->pluck('campania_id')->unique() as $campaniaId) {
            $cultivos = [];

            foreach ($this->lecturaCultivoLote->porCampania($campaniaId) as $siembra) {
                if (! in_array($siembra->cultivoId, $cultivoIdsPedidos, true)) {
                    continue;
                }

                $cultivos[$siembra->cultivoId] = ['id' => $siembra->cultivoId, 'nombre' => $siembra->cultivoNombre];
            }

            $resultado[$campaniaId] = array_values($cultivos);
        }

        return $resultado;
    }

    /**
     * @param  Collection<int, Contrato>  $contratos
     * @param  Collection<int, array{contratoId:int,clienteNombre:string,hectareasContratadas:string,hectareasAplicadas:string,hectareasFacturadas:string,montoFacturado:string}>  $avancePorContrato  keyBy contratoId
     * @param  array<int, list<array{id:int,nombre:string}>>  $cultivosPorCampania
     * @return array<int, array{nombre: string, filas: list<FilaContratoInforme>}>
     */
    private function armarFilasPorCultivo(
        Collection $contratos,
        Collection $avancePorContrato,
        array $cultivosPorCampania,
        ?SaldoContrato $saldo,
    ): array {
        $porCultivo = [];

        foreach ($contratos as $contrato) {
            $avance = $avancePorContrato->get($contrato->id);

            if ($avance === null) {
                continue;
            }

            if (! $this->cumpleSaldo($avance['hectareasContratadas'], $avance['hectareasAplicadas'], $saldo)) {
                continue;
            }

            $fila = $this->armarFila($contrato, $avance);

            foreach ($cultivosPorCampania[$contrato->campania_id] ?? [] as $cultivo) {
                $porCultivo[$cultivo['id']]['nombre'] = $cultivo['nombre'];
                $porCultivo[$cultivo['id']]['filas'][] = $fila;
            }
        }

        return $porCultivo;
    }

    /** @param array{contratoId:int,clienteNombre:string,hectareasContratadas:string,hectareasAplicadas:string,hectareasFacturadas:string,montoFacturado:string} $avance */
    private function armarFila(Contrato $contrato, array $avance): FilaContratoInforme
    {
        $contratadas = BigDecimal::of($avance['hectareasContratadas']);
        $aplicadas = BigDecimal::of($avance['hectareasAplicadas']);
        $porcentaje = $this->porcentaje($aplicadas, $contratadas);

        return new FilaContratoInforme(
            contratoId: $contrato->id,
            clienteId: $contrato->cliente_id,
            clienteNombre: $avance['clienteNombre'],
            hectareasContratadas: $this->aEscalaDos($contratadas),
            hectareasAplicadas: $this->aEscalaDos($aplicadas),
            hectareasAAplicar: $this->aEscalaDos($contratadas->minus($aplicadas)),
            porcentaje: $porcentaje,
            tramo: TramoAvance::desde($porcentaje),
            fechaFin: $contrato->fecha_fin?->toDateString(),
        );
    }

    /** @param  list<FilaContratoInforme>  $filas */
    private function armarGrupoCultivo(int $cultivoId, string $cultivoNombre, array $filas): GrupoCultivoInforme
    {
        $filas = $this->ordenarPorVencimiento($filas);

        [$contratadas, $aplicadas, $aAplicar] = $this->totalizar($filas);
        $porcentaje = $this->porcentaje($aplicadas, $contratadas);

        return new GrupoCultivoInforme(
            cultivoId: $cultivoId,
            cultivoNombre: $cultivoNombre,
            hectareasContratadas: $this->aEscalaDos($contratadas),
            hectareasAplicadas: $this->aEscalaDos($aplicadas),
            hectareasAAplicar: $this->aEscalaDos($aAplicar),
            porcentaje: $porcentaje,
            tramo: TramoAvance::desde($porcentaje),
            contratos: $filas,
            clientes: $this->armarGruposCliente($filas),
        );
    }

    /**
     * @param  list<FilaContratoInforme>  $filas
     * @return list<GrupoClienteInforme>
     */
    private function armarGruposCliente(array $filas): array
    {
        $porCliente = [];

        foreach ($filas as $fila) {
            $porCliente[$fila->clienteId]['nombre'] = $fila->clienteNombre;
            $porCliente[$fila->clienteId]['filas'][] = $fila;
        }

        $grupos = [];

        foreach ($porCliente as $clienteId => $datos) {
            $filasCliente = $this->ordenarPorVencimiento($datos['filas']);
            [$contratadas, $aplicadas, $aAplicar] = $this->totalizar($filasCliente);
            $porcentaje = $this->porcentaje($aplicadas, $contratadas);

            $grupos[] = new GrupoClienteInforme(
                clienteId: $clienteId,
                clienteNombre: $datos['nombre'],
                hectareasContratadas: $this->aEscalaDos($contratadas),
                hectareasAplicadas: $this->aEscalaDos($aplicadas),
                hectareasAAplicar: $this->aEscalaDos($aAplicar),
                porcentaje: $porcentaje,
                tramo: TramoAvance::desde($porcentaje),
                contratos: $filasCliente,
            );
        }

        usort($grupos, fn (GrupoClienteInforme $a, GrupoClienteInforme $b) => $a->clienteNombre <=> $b->clienteNombre);

        return $grupos;
    }

    /**
     * @param  list<FilaContratoInforme>  $filas
     * @return array{0: BigDecimal, 1: BigDecimal, 2: BigDecimal}
     */
    private function totalizar(array $filas): array
    {
        $contratadas = BigDecimal::zero();
        $aplicadas = BigDecimal::zero();
        $aAplicar = BigDecimal::zero();

        foreach ($filas as $fila) {
            $contratadas = $contratadas->plus($fila->hectareasContratadas);
            $aplicadas = $aplicadas->plus($fila->hectareasAplicadas);
            $aAplicar = $aAplicar->plus($fila->hectareasAAplicar);
        }

        return [$contratadas, $aplicadas, $aAplicar];
    }

    /**
     * @param  list<FilaContratoInforme>  $filas
     * @return list<FilaContratoInforme>
     */
    private function ordenarPorVencimiento(array $filas): array
    {
        usort($filas, fn (FilaContratoInforme $a, FilaContratoInforme $b) => ($a->fechaFin ?? '9999-12-31') <=> ($b->fechaFin ?? '9999-12-31'));

        return $filas;
    }

    private function cumpleSaldo(string $contratadas, string $aplicadas, ?SaldoContrato $saldo): bool
    {
        if ($saldo === null) {
            return true;
        }

        $aplicadasDecimal = BigDecimal::of($aplicadas);
        $comparacion = $aplicadasDecimal->compareTo(BigDecimal::of($contratadas));

        return match ($saldo) {
            SaldoContrato::Pendiente => $aplicadasDecimal->isZero(),
            SaldoContrato::AAplicar => ! $aplicadasDecimal->isZero() && $comparacion < 0,
            SaldoContrato::Cumplido => $comparacion >= 0,
        };
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

        return max(0, $bruto);
    }

    private function aEscalaDos(BigDecimal $valor): string
    {
        return (string) $valor->toScale(2, RoundingMode::HalfUp);
    }
}
