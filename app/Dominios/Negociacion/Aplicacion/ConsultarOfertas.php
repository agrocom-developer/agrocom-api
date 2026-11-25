<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Flota\Contratos\LecturaDrones;
use App\Dominios\Flota\Contratos\LecturaIntegrantesDeEquipo;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\FotosDePerfil;
use App\Dominios\Solicitudes\Contratos\LecturaZonas;
use App\Dominios\Solicitudes\Contratos\PasosDeSolicitud;

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
        private readonly LecturaZonas $zonas,
    ) {}

    /**
     * El comparador del cliente: una fila por negociación (la última oferta de cada proveedor) con su historial.
     *
     * @return list<array<string, mixed>>
     *
     * @throws NegociacionInvalida
     */
    public function delCliente(int $usuarioId, int $solicitudId): array
    {
        $solicitud = $this->solicitudes->datos($solicitudId);

        if ($solicitud === null || $solicitud->clienteUsuarioId !== $usuarioId) {
            throw NegociacionInvalida::ajeno('solicitud_ajena');
        }

        $porProveedor = Oferta::query()->where('solicitud_id', $solicitudId)->orderBy('id')->get()->groupBy('proveedor_id');

        return $porProveedor->map(function ($ofertas, $proveedorId): array {
            /** @var Oferta $ultima */
            $ultima = $ofertas->last();

            return [
                ...Presentacion::oferta($ultima),
                'proveedor' => $this->tarjetaDelProveedor((int) $proveedorId, $ultima->equipo_id),
                'historial' => $ofertas->map(fn (Oferta $o): array => Presentacion::oferta($o))->values()->all(),
            ];
        })->values()->sortBy(fn (array $fila): int => $fila['estado'] === 'vigente' ? 0 : 1)->values()->all();
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
     * La zona y el lote de la solicitud para el proveedor dueño de la oferta. La zona es
     * pública en el mercado; el nombre del lote es dato del cliente y solo se muestra
     * una vez adjudicada la solicitud a ese proveedor.
     *
     * @return array{zona: ?string, zona_id: ?int, lote: ?string}
     */
    private function ubicacionParaProveedor(Oferta $oferta): array
    {
        $solicitud = $this->solicitudes->datos($oferta->solicitud_id);
        $adjudicada = $solicitud !== null
            && $solicitud->proveedorId === $oferta->proveedor_id
            && ! in_array($solicitud->estado, ['abierta', 'con_ofertas', 'expirada', 'cancelada'], true);

        return [
            'zona' => $solicitud?->zonaId === null ? null : $this->zonas->nombre($solicitud->zonaId),
            'zona_id' => $solicitud?->zonaId,
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
