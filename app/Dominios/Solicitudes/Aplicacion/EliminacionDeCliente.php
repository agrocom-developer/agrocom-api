<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Seguridad\Contratos\ParticipanteDeEliminacion;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Lo que Solicitudes aporta a la eliminación de una cuenta de cliente (ADR 0047, puntos 3, 4 y 6): un servicio adjudicado o en
 * ejecución la impide; al pedirla se cancelan las solicitudes abiertas y los borradores (con motivo `cuenta_eliminada`); al
 * ejecutarla se borran los datos de la empresa, los contactos de referencia, los terrenos sin servicio y las fotos, y en los
 * servicios ya registrados se borran el contorno de los lotes, los accesos, las observaciones, el municipio y la comunidad.
 * Los servicios, el precio acordado y las hectáreas se conservan como registro del resultado.
 */
final class EliminacionDeCliente implements ParticipanteDeEliminacion
{
    public function __construct(private readonly CancelarSolicitud $cancelar) {}

    public function impedimentos(int $usuarioId): array
    {
        $enCurso = SolicitudServicio::query()
            ->whereIn('cliente_id', $this->clientes($usuarioId))
            ->whereIn('estado', [EstadoSolicitud::Adjudicada->value, EstadoSolicitud::EnEjecucion->value])
            ->exists();

        return $enCurso ? ['servicio_en_curso'] : [];
    }

    public function alSolicitar(int $usuarioId): void
    {
        SolicitudServicio::query()
            ->whereIn('cliente_id', $this->clientes($usuarioId))
            ->whereIn('estado', [EstadoSolicitud::Borrador->value, EstadoSolicitud::Abierta->value, EstadoSolicitud::ConOfertas->value])
            ->pluck('id')
            ->each(function (int $id) use ($usuarioId): void {
                try {
                    $this->cancelar->porCliente($usuarioId, $id, 'cuenta_eliminada');
                } catch (SolicitudInvalida|TransicionSolicitudNoPermitida $falla) {
                    report($falla);
                }
            });
    }

    public function anonimizar(int $usuarioId): void
    {
        $clientes = $this->clientes($usuarioId);

        if ($clientes === []) {
            return;
        }

        $contactos = DB::table('sol_cliente_contactos')->whereIn('cliente_id', $clientes)->pluck('id')->all();
        $terrenos = DB::table('sol_terrenos')->whereIn('cliente_id', $clientes)->pluck('id')->all();

        DB::table('sol_solicitudes_servicio')
            ->whereIn('cliente_id', $clientes)
            ->update(['contacto_id' => null, 'observaciones' => null]);
        DB::table('sol_cliente_contactos')->whereIn('id', $contactos)->delete();

        foreach (DB::table('sol_terreno_fotos')->whereIn('terreno_id', $terrenos)->get(['id', 'archivo']) as $foto) {
            Storage::disk('local')->delete($foto->archivo);
            DB::table('sol_terreno_fotos')->where('id', $foto->id)->delete();
        }

        foreach ($terrenos as $terrenoId) {
            $conServicio = DB::table('sol_solicitudes_servicio')->where('terreno_id', $terrenoId)->exists();

            if (! $conServicio) {
                DB::table('sol_terrenos')->where('id', $terrenoId)->delete();

                continue;
            }

            DB::table('sol_terrenos')->where('id', $terrenoId)->update([
                'nombre' => __('seguridad.eliminacion.terreno_anonimo'),
                'geometria' => null,
                'referencias_acceso' => null,
                'colindantes_sensibles' => null,
                'municipio' => null,
                'comunidad' => null,
            ]);
        }

        DB::table('sol_clientes')->whereIn('id', $clientes)->update([
            'razon_social' => __('seguridad.eliminacion.nombre_anonimo'),
            'documento_fiscal' => null,
            'telefono' => null,
        ]);
    }

    /** @return list<int> los clientes (fichas) de la cuenta; hoy una sola. */
    private function clientes(int $usuarioId): array
    {
        return Cliente::query()->where('usuario_id', $usuarioId)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }
}
