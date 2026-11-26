<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor\FiltrosDeProveedor;
use App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor\SolicitudParaAviso;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Preferencias de aviso de la cuenta (`negociacion-contrato.md` §7): qué categorías recibe y,
 * para los proveedores, qué nuevas solicitudes (los filtros del proveedor, ADR 0045: un mecanismo
 * extensible guardado en `neg_preferencias.filtros`; ver {@see FiltrosDeProveedor}). Sin fila
 * guardada rige el valor por defecto: todo activo y sin filtros. Los filtros solo gobiernan los
 * avisos y nunca se relajan. Las categorías obligatorias no aparecen acá y no se apagan.
 */
final class PreferenciasDeAviso
{
    public const array CATEGORIAS = ['ofertas', 'mensajes', 'avance', 'calificaciones'];

    public function __construct(private readonly FiltrosDeProveedor $filtros) {}

    /** @return array{nuevas_solicitudes: array<string, mixed>, ofertas: bool, mensajes: bool, avance: bool, calificaciones: bool, filtros_disponibles: list<array{clave: string, tipo: string}>} */
    public function de(int $usuarioId): array
    {
        $fila = DB::table('neg_preferencias')->where('usuario_id', $usuarioId)->first();

        if ($fila === null) {
            return $this->porDefecto();
        }

        return [
            'nuevas_solicitudes' => ['activo' => (bool) $fila->nuevas_solicitudes, ...$this->filtros->conValores($this->guardados($fila))],
            'ofertas' => (bool) $fila->ofertas,
            'mensajes' => (bool) $fila->mensajes,
            'avance' => (bool) $fila->avance,
            'calificaciones' => (bool) $fila->calificaciones,
            'filtros_disponibles' => $this->filtros->disponibles(),
        ];
    }

    /**
     * Guarda lo recibido; lo que no se envía conserva su valor. Un filtro enviado como `null` o vacío se quita.
     *
     * @param  array<string, mixed>  $datos
     * @return array{nuevas_solicitudes: array<string, mixed>, ofertas: bool, mensajes: bool, avance: bool, calificaciones: bool, filtros_disponibles: list<array{clave: string, tipo: string}>}
     *
     * @throws NegociacionInvalida 422 si un filtro no existe o su valor no es válido.
     */
    public function guardar(int $usuarioId, array $datos): array
    {
        $fila = DB::table('neg_preferencias')->where('usuario_id', $usuarioId)->first();
        $actual = $this->de($usuarioId);
        $nuevas = is_array($datos['nuevas_solicitudes'] ?? null) ? $datos['nuevas_solicitudes'] : [];
        $recibidos = array_diff_key($nuevas, ['activo' => true]);

        $filtros = $this->filtros->aplicar($fila === null ? [] : $this->guardados($fila), $recibidos);

        $valores = [
            'nuevas_solicitudes' => (bool) ($nuevas['activo'] ?? $actual['nuevas_solicitudes']['activo']),
            'filtros' => $filtros === [] ? null : json_encode($filtros, JSON_THROW_ON_ERROR),
            // Todo vive ya en `filtros`: el valor heredado se apaga para que un filtro quitado no reviva.
            'hectareas_minimas' => null,
            'updated_at' => now(),
        ];

        foreach (self::CATEGORIAS as $categoria) {
            $valores[$categoria] = array_key_exists($categoria, $datos) ? (bool) $datos[$categoria] : $actual[$categoria];
        }

        DB::table('neg_preferencias')->updateOrInsert(['usuario_id' => $usuarioId], [...$valores, 'created_at' => now()]);

        return $this->de($usuarioId);
    }

    /** ¿La cuenta recibe esta categoría de aviso? Sin preferencia guardada, sí. */
    public function permite(int $usuarioId, string $categoria): bool
    {
        return $this->de($usuarioId)[$categoria] ?? true;
    }

    /** ¿Una nueva solicitud así le interesa a la cuenta? Apagada o excluida por un filtro suyo, no. */
    public function coincide(int $usuarioId, SolicitudParaAviso $solicitud): bool
    {
        $fila = DB::table('neg_preferencias')->where('usuario_id', $usuarioId)->first();

        if ($fila === null) {
            return true;
        }

        return (bool) $fila->nuevas_solicitudes && $this->filtros->admite($this->guardados($fila), $solicitud);
    }

    /**
     * Los filtros guardados de una fila. El mínimo de hectáreas de antes del mecanismo vivía en su
     * propia columna: mientras no se vuelva a guardar, sigue rigiendo.
     *
     * @return array<string, mixed>
     */
    private function guardados(object $fila): array
    {
        $guardados = json_decode((string) ($fila->filtros ?? 'null'), true);
        $guardados = is_array($guardados) ? $guardados : [];

        if (! array_key_exists('hectareas_minimas', $guardados) && ($fila->hectareas_minimas ?? null) !== null) {
            $guardados['hectareas_minimas'] = (string) BigDecimal::of((string) $fila->hectareas_minimas)->toScale(4);
        }

        return $guardados;
    }

    /** @return array{nuevas_solicitudes: array<string, mixed>, ofertas: bool, mensajes: bool, avance: bool, calificaciones: bool, filtros_disponibles: list<array{clave: string, tipo: string}>} */
    private function porDefecto(): array
    {
        return [
            'nuevas_solicitudes' => ['activo' => true, ...$this->filtros->conValores([])],
            'ofertas' => true,
            'mensajes' => true,
            'avance' => true,
            'calificaciones' => true,
            'filtros_disponibles' => $this->filtros->disponibles(),
        ];
    }
}
