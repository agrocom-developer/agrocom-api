<?php

namespace App\Dominios\Calificaciones\Aplicacion;

use App\Dominios\Calificaciones\Aplicacion\MaquinaEstados\MaquinaEstadosCalificacion;
use App\Dominios\Calificaciones\Contratos\Eventos\CalificacionRegistrada;
use App\Dominios\Calificaciones\Dominio\AutorCalificacion;
use App\Dominios\Calificaciones\Dominio\EtiquetaCalificacion;
use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Calificaciones\Dominio\PlazoDeCalificacion;
use App\Dominios\Calificaciones\Dominio\SujetoCalificacion;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\DatosServicio;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Escribe una calificación: la regla común a las dos direcciones (cliente al
 * proveedor, proveedor al cliente). Exige el servicio `completada` y dentro del
 * plazo, valida las etiquetas contra el catálogo del autor, es idempotente por
 * `uuid_cliente` y no admite una segunda calificación del mismo sujeto
 * (la calificación no se edita: ADR 0031).
 */
final class EmitirCalificacion
{
    public function __construct(
        private readonly MaquinaEstadosCalificacion $estados,
        private readonly ParametrosDeCalificacion $parametros,
        private readonly LecturaProveedores $proveedores,
    ) {}

    /**
     * El servicio admite calificaciones: está `finalizada` o `calificada` y el plazo sigue abierto.
     *
     * @throws CalificacionInvalida
     */
    public function exigirCalificable(DatosServicio $servicio, ?DateTimeImmutable $ahora = null): void
    {
        if (! in_array($servicio->estado, ['finalizada', 'calificada'], true) || $servicio->finalizadaEn === null) {
            throw CalificacionInvalida::servicioNoCompletado();
        }

        if (! PlazoDeCalificacion::estaAbierto(new DateTimeImmutable($servicio->finalizadaEn), $ahora ?? new DateTimeImmutable, $this->parametros->plazoDias())) {
            throw CalificacionInvalida::plazoVencido($this->parametros->plazoDias());
        }
    }

    /**
     * @param  array{uuid_cliente: string, estrellas: int, etiquetas?: list<string>|null, comentario?: string|null}  $datos
     * @return array{0: Calificacion, 1: bool} la calificación y si se creó ahora (`false`: era un reintento).
     *
     * @throws CalificacionInvalida
     */
    public function ejecutar(
        DatosServicio $servicio,
        AutorCalificacion $autor,
        int $autorUsuarioId,
        SujetoCalificacion $sujeto,
        int $sujetoId,
        array $datos,
    ): array {
        $etiquetas = $this->etiquetas($autor, $datos['etiquetas'] ?? []);

        $previa = Calificacion::query()->where('uuid_cliente', $datos['uuid_cliente'])->first();

        if ($previa !== null) {
            if ($previa->solicitud_id !== $servicio->id || $previa->autor_rol !== $autor || $previa->sujeto_tipo !== $sujeto || $previa->sujeto_id !== $sujetoId) {
                throw CalificacionInvalida::uuidEnUso();
            }

            return [$previa, false];
        }

        $yaCalificado = Calificacion::query()
            ->where('solicitud_id', $servicio->id)
            ->where('autor_rol', $autor->value)
            ->where('sujeto_tipo', $sujeto->value)
            ->where('sujeto_id', $sujetoId)
            ->exists();

        if ($yaCalificado) {
            throw CalificacionInvalida::yaCalificado();
        }

        try {
            $calificacion = $this->estados->crear([
                'uuid_cliente' => $datos['uuid_cliente'],
                'solicitud_id' => $servicio->id,
                'proveedor_id' => $servicio->proveedorId,
                'cliente_id' => $servicio->clienteId,
                'autor_usuario_id' => $autorUsuarioId,
                'autor_rol' => $autor->value,
                'sujeto_tipo' => $sujeto->value,
                'sujeto_id' => $sujetoId,
                'estrellas' => $datos['estrellas'],
                'etiquetas' => $etiquetas,
                'comentario' => $this->comentario($datos['comentario'] ?? null),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dos pedidos a la vez sobre el mismo sujeto: el segundo pierde contra el índice único.
            throw CalificacionInvalida::yaCalificado();
        }

        event(new CalificacionRegistrada(
            $calificacion->id,
            $servicio->id,
            $autor->value,
            $sujeto->value,
            $sujetoId,
            $servicio->proveedorId,
            $calificacion->estrellas,
            $autor === AutorCalificacion::Cliente && $servicio->proveedorId !== null ? $this->proveedores->usuariosDeAviso($servicio->proveedorId) : [],
        ));

        return [$calificacion, true];
    }

    /**
     * @param  list<string>  $etiquetas
     * @return list<string> sin repetir y del catálogo del autor.
     *
     * @throws CalificacionInvalida
     */
    private function etiquetas(AutorCalificacion $autor, array $etiquetas): array
    {
        $permitidas = array_map(fn (EtiquetaCalificacion $etiqueta): string => $etiqueta->value, $autor->etiquetas());
        $limpias = [];

        foreach ($etiquetas as $etiqueta) {
            if (! in_array($etiqueta, $permitidas, true)) {
                throw CalificacionInvalida::etiquetaInvalida((string) $etiqueta);
            }

            $limpias[$etiqueta] = $etiqueta;
        }

        return array_values($limpias);
    }

    private function comentario(?string $comentario): ?string
    {
        $comentario = $comentario === null ? '' : trim($comentario);

        return $comentario === '' ? null : mb_substr($comentario, 0, $this->parametros->comentarioMax());
    }
}
