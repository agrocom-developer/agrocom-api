<?php

namespace App\Dominios\Calificaciones\Aplicacion;

use App\Dominios\Calificaciones\Dominio\AutorCalificacion;
use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Calificaciones\Dominio\PlazoDeCalificacion;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use App\Dominios\Solicitudes\Contratos\DatosServicio;
use DateTimeImmutable;

/**
 * El estado de la calificación de un servicio, para la pantalla de la app: quién
 * se puede calificar, a quién ya se calificó, hasta cuándo y si el plazo sigue
 * abierto. Cada lado ve solo lo suyo: el cliente, lo que él calificó; el proveedor,
 * lo que él calificó al cliente (nunca la calificación que el cliente le dio, que
 * se consulta en sus calificaciones recibidas).
 */
final class ConsultarCalificacionesDelServicio
{
    public function __construct(
        private readonly CalificarComoCliente $comoCliente,
        private readonly CalificarComoProveedor $comoProveedor,
        private readonly SujetosDelServicio $sujetos,
        private readonly EmitirCalificacion $emitir,
        private readonly ParametrosDeCalificacion $parametros,
    ) {}

    /**
     * @return array{puede_calificar: bool, vence_en: ?string, sujetos: list<array{tipo: string, id: int, nombre: string, calificacion: ?Calificacion}>}
     *
     * @throws CalificacionInvalida
     */
    public function paraCliente(int $usuarioId, int $solicitudId): array
    {
        $servicio = $this->comoCliente->delCliente($usuarioId, $solicitudId);
        $hechas = $this->hechas($servicio, AutorCalificacion::Cliente);

        $sujetos = [];
        foreach ($this->sujetos->delLadoProveedor($servicio) as $tipo => $sujeto) {
            $sujetos[] = ['tipo' => $tipo, 'id' => $sujeto['id'], 'nombre' => $sujeto['nombre'], 'calificacion' => $hechas[$tipo.':'.$sujeto['id']] ?? null];
        }

        return $this->estado($servicio, $sujetos);
    }

    /**
     * @return array{puede_calificar: bool, vence_en: ?string, sujetos: list<array{tipo: string, id: int, nombre: string, calificacion: ?Calificacion}>}
     *
     * @throws CalificacionInvalida
     */
    public function paraProveedor(int $usuarioId, int $solicitudId): array
    {
        $servicio = $this->comoProveedor->delProveedor($usuarioId, $solicitudId);
        $hechas = $this->hechas($servicio, AutorCalificacion::Proveedor);

        $sujetos = [['tipo' => 'cliente', 'id' => $servicio->clienteId, 'nombre' => $servicio->clienteNombre, 'calificacion' => $hechas['cliente:'.$servicio->clienteId] ?? null]];

        return $this->estado($servicio, $sujetos);
    }

    /**
     * @param  list<array{tipo: string, id: int, nombre: string, calificacion: ?Calificacion}>  $sujetos
     * @return array{puede_calificar: bool, vence_en: ?string, sujetos: list<array{tipo: string, id: int, nombre: string, calificacion: ?Calificacion}>}
     */
    private function estado(DatosServicio $servicio, array $sujetos): array
    {
        $completada = $servicio->estado === 'completada' && $servicio->completadaEn !== null;
        $vence = $completada ? PlazoDeCalificacion::venceEn(new DateTimeImmutable($servicio->completadaEn), $this->parametros->plazoDias()) : null;

        $abierto = false;
        if ($completada) {
            try {
                $this->emitir->exigirCalificable($servicio);
                $abierto = true;
            } catch (CalificacionInvalida) {
                $abierto = false;
            }
        }

        return [
            'puede_calificar' => $abierto && array_filter($sujetos, fn (array $s): bool => $s['calificacion'] === null) !== [],
            'vence_en' => $vence?->format(DATE_ATOM),
            'sujetos' => $sujetos,
        ];
    }

    /** @return array<string, Calificacion> las del autor sobre el servicio, por `tipo:id` del sujeto. */
    private function hechas(DatosServicio $servicio, AutorCalificacion $autor): array
    {
        $hechas = [];

        foreach (Calificacion::query()->where('solicitud_id', $servicio->id)->where('autor_rol', $autor->value)->get() as $calificacion) {
            $hechas[$calificacion->sujeto_tipo->value.':'.$calificacion->sujeto_id] = $calificacion;
        }

        return $hechas;
    }
}
