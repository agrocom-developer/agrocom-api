<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\OrigenCambio;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Proveedores\Aplicacion\MaquinaEstados\MaquinaEstadosDocumento;
use App\Dominios\Proveedores\Contratos\Eventos\DocumentoPorVencer;
use App\Dominios\Proveedores\Contratos\Eventos\DocumentoVencido;
use App\Dominios\Proveedores\Dominio\EstadoDocumento;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Dominio\VencimientoDocumento;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Auxiliar;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Proceso diario de vencimientos (ADR 0032, punto 4; política de verificación,
 * «Vigencia»): avisa 30 y 7 días antes de que venza un documento aprobado y, al
 * vencer, lo marca `vencido` y suspende al sujeto hasta que cargue uno vigente.
 *
 * Idempotente: correrlo dos veces el mismo día no repite avisos (cada aviso deja
 * su fecha en `aviso_30_en`/`aviso_7_en`), no vuelve a marcar un documento ya
 * vencido ni suspende a quien ya está suspendido. Un documento vencido no
 * suspende si el sujeto tiene otro del mismo tipo aprobado y vigente.
 *
 * El dron lo suspende `Flota`, que escucha `DocumentoVencido`.
 */
final class ProcesarVencimientos
{
    public function __construct(
        private readonly ResolverSujeto $sujetos,
        private readonly EstadoDocumentalDeSujeto $estadoDocumental,
        private readonly ResolverAprobacion $aprobacion,
        private readonly MaquinaEstadosDocumento $estados,
    ) {}

    /**
     * @return array{avisos_30: int, avisos_7: int, vencidos: int, suspendidos: int}
     */
    public function ejecutar(?DateTimeImmutable $hoy = null): array
    {
        $hoy ??= new DateTimeImmutable('today');
        $resumen = ['avisos_30' => 0, 'avisos_7' => 0, 'vencidos' => 0, 'suspendidos' => 0];

        Documento::query()
            ->where('estado', EstadoDocumento::Aprobado->value)
            ->whereNotNull('vence_en')
            ->orderBy('id')
            ->each(function (Documento $documento) use ($hoy, &$resumen): void {
                $vence = $documento->vence_en->format('Y-m-d');

                match (VencimientoDocumento::clasificar($vence, $hoy)) {
                    VencimientoDocumento::Vencido => $this->vencer($documento, $hoy, $resumen),
                    VencimientoDocumento::PorVencer7 => $this->avisar($documento, $hoy, $vence, corto: true, resumen: $resumen),
                    VencimientoDocumento::PorVencer30 => $this->avisar($documento, $hoy, $vence, corto: false, resumen: $resumen),
                    VencimientoDocumento::Vigente => null,
                };
            });

        return $resumen;
    }

    /** @param  array{avisos_30: int, avisos_7: int, vencidos: int, suspendidos: int}  $resumen */
    private function avisar(Documento $documento, DateTimeImmutable $hoy, string $vence, bool $corto, array &$resumen): void
    {
        $columna = $corto ? 'aviso_7_en' : 'aviso_30_en';

        if ($documento->{$columna} !== null) {
            return;
        }

        $documento->{$columna} = Carbon::instance($hoy);
        $documento->save();

        $sujeto = $this->sujetos->ejecutar($documento->sujeto_tipo, $documento->sujeto_id);

        event(new DocumentoPorVencer(
            $documento->id,
            $documento->sujeto_tipo->value,
            $documento->sujeto_id,
            $documento->tipo->value,
            $vence,
            VencimientoDocumento::diasHasta($vence, $hoy),
            $sujeto->usuariosAAvisar ?? [],
        ));

        $resumen[$corto ? 'avisos_7' : 'avisos_30']++;
    }

    /** @param  array{avisos_30: int, avisos_7: int, vencidos: int, suspendidos: int}  $resumen */
    private function vencer(Documento $documento, DateTimeImmutable $hoy, array &$resumen): void
    {
        DB::transaction(function () use ($documento, $hoy, &$resumen): void {
            $this->estados->vencer($documento);

            $resumen['vencidos']++;

            $sujeto = $this->sujetos->ejecutar($documento->sujeto_tipo, $documento->sujeto_id);

            event(new DocumentoVencido(
                $documento->id,
                $documento->sujeto_tipo->value,
                $documento->sujeto_id,
                $documento->tipo->value,
                $documento->vence_en->format('Y-m-d'),
                $sujeto->usuariosAAvisar ?? [],
            ));

            if ($documento->sujeto_tipo === TipoSujeto::Dron) {
                return;
            }

            if ($this->suspender($documento, $hoy)) {
                $resumen['suspendidos']++;
            }
        });
    }

    private function suspender(Documento $documento, DateTimeImmutable $hoy): bool
    {
        $tipo = $documento->sujeto_tipo;
        $estado = $this->estadoDeSujeto($tipo, $documento->sujeto_id);

        if ($estado !== EstadoAprobacion::Aprobado) {
            return false;
        }

        $faltantes = $this->estadoDocumental->faltantesDe($tipo, $documento->sujeto_id, $hoy);

        if (! in_array($documento->tipo, $faltantes, true)) {
            return false;
        }

        $this->aprobacion->ejecutar(
            $tipo,
            $documento->sujeto_id,
            AccionAprobacion::Suspender,
            null,
            Texto::de('proveedores.vencimientos.motivo_suspension', [
                'documento' => mb_strtolower(Texto::de('proveedores.documentos.tipo.'.$documento->tipo->value)),
                'fecha' => $documento->vence_en->format('d/m/Y'),
            ]),
            OrigenCambio::Sistema,
        );

        return true;
    }

    private function estadoDeSujeto(TipoSujeto $tipo, int $id): ?EstadoAprobacion
    {
        return match ($tipo) {
            TipoSujeto::Proveedor => Proveedor::query()->find($id)?->estado,
            TipoSujeto::Piloto => Piloto::query()->find($id)?->estado,
            TipoSujeto::Auxiliar => Auxiliar::query()->find($id)?->estado,
            TipoSujeto::Dron => null,
        };
    }
}
