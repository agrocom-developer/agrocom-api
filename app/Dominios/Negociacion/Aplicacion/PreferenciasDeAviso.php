<?php

namespace App\Dominios\Negociacion\Aplicacion;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Preferencias de aviso de la cuenta (`negociacion-contrato.md` §7): qué categorías
 * recibe y, para los proveedores, qué nuevas solicitudes (hectáreas mínimas).
 * Sin fila guardada rige el valor por defecto: todo activo, sin mínimo. Las categorías obligatorias no aparecen acá y no se apagan.
 */
final class PreferenciasDeAviso
{
    public const array CATEGORIAS = ['ofertas', 'mensajes', 'avance', 'calificaciones'];

    /** @return array{nuevas_solicitudes: array{activo: bool, hectareas_minimas: string|null}, ofertas: bool, mensajes: bool, avance: bool, calificaciones: bool} */
    public function de(int $usuarioId): array
    {
        $fila = DB::table('neg_preferencias')->where('usuario_id', $usuarioId)->first();

        if ($fila === null) {
            return $this->porDefecto();
        }

        return [
            'nuevas_solicitudes' => [
                'activo' => (bool) $fila->nuevas_solicitudes,
                'hectareas_minimas' => $fila->hectareas_minimas === null ? null : (string) BigDecimal::of((string) $fila->hectareas_minimas)->toScale(4),
            ],
            'ofertas' => (bool) $fila->ofertas,
            'mensajes' => (bool) $fila->mensajes,
            'avance' => (bool) $fila->avance,
            'calificaciones' => (bool) $fila->calificaciones,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{nuevas_solicitudes: array{activo: bool, hectareas_minimas: string|null}, ofertas: bool, mensajes: bool, avance: bool, calificaciones: bool}
     */
    public function guardar(int $usuarioId, array $datos): array
    {
        $actual = $this->de($usuarioId);
        $nuevas = is_array($datos['nuevas_solicitudes'] ?? null) ? $datos['nuevas_solicitudes'] : [];

        $minimas = array_key_exists('hectareas_minimas', $nuevas) ? $nuevas['hectareas_minimas'] : $actual['nuevas_solicitudes']['hectareas_minimas'];

        $valores = [
            'nuevas_solicitudes' => (bool) ($nuevas['activo'] ?? $actual['nuevas_solicitudes']['activo']),
            'hectareas_minimas' => $minimas === null || $minimas === '' ? null : (string) $minimas,
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

    /** ¿Una nueva solicitud con estas hectáreas le interesa a la cuenta? */
    public function coincide(int $usuarioId, string $hectareas): bool
    {
        $nuevas = $this->de($usuarioId)['nuevas_solicitudes'];

        if (! $nuevas['activo']) {
            return false;
        }

        return $nuevas['hectareas_minimas'] === null || BigDecimal::of($hectareas)->isGreaterThanOrEqualTo(BigDecimal::of($nuevas['hectareas_minimas']));
    }

    /** @return array{nuevas_solicitudes: array{activo: bool, hectareas_minimas: string|null}, ofertas: bool, mensajes: bool, avance: bool, calificaciones: bool} */
    private function porDefecto(): array
    {
        return [
            'nuevas_solicitudes' => ['activo' => true, 'hectareas_minimas' => null],
            'ofertas' => true,
            'mensajes' => true,
            'avance' => true,
            'calificaciones' => true,
        ];
    }
}
