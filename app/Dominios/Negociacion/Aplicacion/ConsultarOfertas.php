<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Flota\Contratos\LecturaDrones;
use App\Dominios\Flota\Contratos\LecturaIntegrantesDeEquipo;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\FotosDePerfil;
use App\Dominios\Solicitudes\Contratos\PasosDeSolicitud;
use Brick\Math\BigDecimal;

/**
 * Lo que cada parte ve de las ofertas (`negociacion-contrato.md` §3.4). El cliente ve,
 * por proveedor, la oferta vigente más reciente con el proveedor (nombre, calificación,
 * tasa de cancelación, distancia, dron y fotos aprobadas) para comparar; el proveedor
 * ve solo las ofertas de su propio proveedor. El alcance parte de la cuenta: una
 * solicitud u oferta ajena responde 404 (invariante 5 de CLAUDE.md). Los precios de las
 * ofertas de otros proveedores no llegan jamás a un proveedor.
 */
final class ConsultarOfertas
{
    public function __construct(
        private readonly PasosDeSolicitud $solicitudes,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaIntegrantesDeEquipo $integrantes,
        private readonly LecturaDrones $drones,
        private readonly ReputacionDelProveedor $reputacion,
        private readonly Negociar $negociar,
        private readonly FotosDePerfil $fotosDePerfil,
        private readonly ParametrosDeNegociacion $parametros,
    ) {}

    /**
     * El comparador del cliente: una fila por negociación (la última oferta de cada proveedor) con su historial.
     * Las vigentes van primero; dentro de cada grupo rige `orden` (por defecto `recomendado`, ADR 0045 punto 24)
     * y los filtros opcionales solo reducen la lista (ADR 0049). Nada de lo que se filtra agrega datos de contacto.
     *
     * @param  array{orden?: ?string, precio_ha_min?: mixed, precio_ha_max?: mixed, monto_max?: mixed, calificacion_min?: mixed, tipo_proveedor?: ?string, capacidad_tanque_min?: mixed}  $criterios
     * @return list<array<string, mixed>>
     *
     * @throws NegociacionInvalida
     */
    public function delCliente(int $usuarioId, int $solicitudId, array $criterios = []): array
    {
        $solicitud = $this->solicitudes->datos($solicitudId);

        if ($solicitud === null || $solicitud->clienteUsuarioId !== $usuarioId) {
            throw NegociacionInvalida::ajeno('solicitud_ajena');
        }

        $porProveedor = Oferta::query()->where('solicitud_id', $solicitudId)->orderBy('id')->get()->groupBy('proveedor_id');

        $filas = $porProveedor->map(function ($ofertas, $proveedorId): array {
            /** @var Oferta $ultima */
            $ultima = $ofertas->last();

            return [
                ...Presentacion::oferta($ultima),
                'proveedor' => $this->tarjetaDelProveedor((int) $proveedorId, $ultima->equipo_id),
                'historial' => $ofertas->map(fn (Oferta $o): array => Presentacion::oferta($o))->values()->all(),
            ];
        })->values()->all();

        $filas = array_values(array_filter($filas, fn (array $fila): bool => $this->cumple($fila, $criterios)));
        usort($filas, $this->comparador($criterios['orden'] ?? 'recomendado'));

        return $filas;
    }

    /**
     * Los filtros del comparador, con decimal exacto (nunca `float`). Un filtro de dron deja fuera
     * las ofertas sin equipo; uno de calificación, las de proveedores sin promedio suficiente.
     *
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $criterios
     */
    private function cumple(array $fila, array $criterios): bool
    {
        $precio = BigDecimal::of((string) $fila['precio_ha']);
        $proveedor = $fila['proveedor'];

        if (isset($criterios['precio_ha_min']) && $precio->isLessThan(BigDecimal::of((string) $criterios['precio_ha_min']))) {
            return false;
        }
        if (isset($criterios['precio_ha_max']) && $precio->isGreaterThan(BigDecimal::of((string) $criterios['precio_ha_max']))) {
            return false;
        }
        if (isset($criterios['monto_max']) && BigDecimal::of((string) $fila['monto_estimado'])->isGreaterThan(BigDecimal::of((string) $criterios['monto_max']))) {
            return false;
        }
        if (isset($criterios['calificacion_min']) && BigDecimal::of((string) ($proveedor['calificacion_promedio'] ?? '0'))->isLessThan(BigDecimal::of((string) $criterios['calificacion_min']))) {
            return false;
        }
        if (isset($criterios['tipo_proveedor']) && ($proveedor['tipo'] ?? null) !== $criterios['tipo_proveedor']) {
            return false;
        }
        if (isset($criterios['capacidad_tanque_min'])) {
            $capacidad = $proveedor['dron']['capacidad_tanque_l'] ?? null;
            if ($capacidad === null || BigDecimal::of((string) $capacidad)->isLessThan(BigDecimal::of((string) $criterios['capacidad_tanque_min']))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Las vigentes primero y, dentro del grupo, el orden pedido. Todos desempatan por la oferta más antigua.
     *
     * @return callable(array<string, mixed>, array<string, mixed>): int
     */
    private function comparador(string $orden): callable
    {
        $minimas = $this->parametros->calificacionesMinimasParaContar();
        $calificacion = fn (array $f): BigDecimal => BigDecimal::of((string) ($f['proveedor']['calificacion_promedio'] ?? '0'));
        $cuenta = fn (array $f): bool => (int) ($f['proveedor']['calificaciones_conteo'] ?? 0) >= $minimas;
        $precio = fn (array $f): BigDecimal => BigDecimal::of((string) $f['precio_ha']);
        $vigente = fn (array $f): int => $f['estado'] === 'vigente' ? 0 : 1;

        return function (array $a, array $b) use ($orden, $calificacion, $cuenta, $precio, $vigente): int {
            $porCalificacion = fn (): int => ($cuenta($b) <=> $cuenta($a)) ?: $calificacion($b)->compareTo($calificacion($a));

            $clave = match ($orden) {
                'precio' => $precio($a)->compareTo($precio($b)),
                'calificacion' => $porCalificacion() ?: $precio($a)->compareTo($precio($b)),
                'reciente' => $b['id'] <=> $a['id'],
                default => ((($b['proveedor']['tipo'] ?? null) === 'propio') <=> (($a['proveedor']['tipo'] ?? null) === 'propio'))
                    ?: $porCalificacion()
                    ?: $precio($a)->compareTo($precio($b)),
            };

            return ($vigente($a) <=> $vigente($b)) ?: $clave ?: ($a['id'] <=> $b['id']);
        };
    }

    /**
     * Las ofertas del proveedor de la cuenta, de la más nueva a la más vieja.
     *
     * @return list<array<string, mixed>>
     *
     * @throws NegociacionInvalida
     */
    public function delProveedor(int $usuarioId, ?string $estado = null, ?int $solicitudId = null): array
    {
        $proveedor = $this->proveedores->proveedorDeLaCuenta($usuarioId) ?? throw NegociacionInvalida::ajeno('sin_proveedor');

        return Oferta::query()
            ->where('proveedor_id', $proveedor->id)
            ->when($estado !== null, fn ($q) => $q->where('estado', $estado))
            ->when($solicitudId !== null, fn ($q) => $q->where('solicitud_id', $solicitudId))
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Oferta $o): array => [...Presentacion::oferta($o), ...$this->ubicacionParaProveedor($o)])
            ->all();
    }

    /**
     * El departamento y el lote de la solicitud para el proveedor dueño de la oferta. El
     * departamento es público en el mercado; el nombre del lote es dato del cliente y solo
     * se muestra una vez adjudicada la solicitud a ese proveedor.
     *
     * @return array{departamento_codigo: ?string, lote: ?string}
     */
    private function ubicacionParaProveedor(Oferta $oferta): array
    {
        $solicitud = $this->solicitudes->datos($oferta->solicitud_id);
        $adjudicada = $solicitud !== null
            && $solicitud->proveedorId === $oferta->proveedor_id
            && ! in_array($solicitud->estado, ['abierta', 'con_ofertas', 'cancelada'], true);

        return [
            'departamento_codigo' => $solicitud?->departamentoCodigo,
            'lote' => $adjudicada ? $solicitud->terrenoNombre : null,
        ];
    }

    /**
     * Una oferta, para cualquiera de sus dos partes.
     *
     * @return array<string, mixed>
     *
     * @throws NegociacionInvalida
     */
    public function una(string $lado, int $usuarioId, int $ofertaId): array
    {
        $oferta = Oferta::query()->find($ofertaId) ?? throw NegociacionInvalida::ajeno('oferta_ajena');
        $this->negociar->autorizar($lado, $usuarioId, $oferta);

        $fila = Presentacion::oferta($oferta);

        return $lado === Negociar::LADO_CLIENTE
            ? [...$fila, 'proveedor' => $this->tarjetaDelProveedor($oferta->proveedor_id, $oferta->equipo_id)]
            : [...$fila, ...$this->ubicacionParaProveedor($oferta)];
    }

    /** @return array<string, mixed> */
    public function tarjetaDelProveedor(int $proveedorId, ?int $equipoId): array
    {
        $proveedor = $this->proveedores->proveedor($proveedorId);
        $integrantes = $equipoId === null ? null : $this->integrantes->integrantes($equipoId);
        $dron = $integrantes === null ? null : $this->drones->dron($integrantes->dronId);

        $piloto = $integrantes === null ? null : $this->proveedores->piloto($integrantes->pilotoId);
        $titularId = $proveedor?->usuarioTitularId;
        $fotoUrl = ($piloto === null ? null : $this->fotosDePerfil->urlDe($piloto->usuarioId))
            ?? ($titularId === null ? null : $this->fotosDePerfil->urlDe($titularId));

        return [
            'id' => $proveedorId,
            'nombre' => $proveedor?->razonSocial,
            'tipo' => $proveedor?->tipo,
            'foto_url' => $fotoUrl,
            'calificacion_promedio' => $proveedor?->calificacionPromedio,
            'calificaciones_conteo' => $proveedor?->calificacionesConteo,
            'tasa_cancelacion' => $this->reputacion->tasa($proveedorId)['tasa_cancelacion'],
            'dron' => $dron === null ? null : [
                'marca' => $dron->marca,
                'modelo' => $dron->modelo,
                'anio' => $dron->anio,
                'capacidad_tanque_l' => $dron->capacidadTanqueL,
                'fotos' => array_map(fn (array $foto): array => [...$foto, 'url' => "/api/drones/fotos/{$foto['id']}/archivo"], $this->drones->fotosAprobadas($dron->id)),
            ],
        ];
    }
}
