<?php

namespace App\Dominios\Compartido\Aplicacion\Medios;

/**
 * Cuántos archivos multimedia admite cada entidad. Una sola fuente (`config/medios.php`) para el servidor, que
 * valida, y para las apps, que lo leen en `GET /api/medios/limites` y avisan antes de subir.
 *
 * El límite es POR ENTIDAD DUEÑA y por categoría (fotos de un dron, de un terreno, documentos en revisión de un
 * sujeto, fotos de avance y del chat de un servicio), y `por_subida` acota cuántos archivos trae una sola
 * petición. Cada módulo cuenta lo suyo y rechaza con 422 y su propio mensaje.
 */
final class LimiteDeMedios
{
    public const array CATEGORIAS = ['dron_fotos', 'terreno_fotos', 'documentos', 'servicio_avance', 'servicio_chat'];

    public function porEntidad(string $categoria): int
    {
        return max(1, (int) config("medios.limites.por_entidad.{$categoria}", 10));
    }

    public function porSubida(): int
    {
        return max(1, (int) config('medios.limites.por_subida', 10));
    }

    /** Si agregar `$nuevos` archivos a una entidad que ya tiene `$existentes` pasa el máximo. */
    public function excede(string $categoria, int $existentes, int $nuevos = 1): bool
    {
        return $existentes + $nuevos > $this->porEntidad($categoria);
    }

    /** @return array{por_subida: int, por_entidad: array<string, int>, peso_maximo_kb: int, formatos_imagen: list<string>} */
    public function resumen(): array
    {
        $porEntidad = [];

        foreach (self::CATEGORIAS as $categoria) {
            $porEntidad[$categoria] = $this->porEntidad($categoria);
        }

        return [
            'por_subida' => $this->porSubida(),
            'por_entidad' => $porEntidad,
            'peso_maximo_kb' => max(1, (int) config('seguimiento.evidencia_max_kb', 300)),
            'formatos_imagen' => ['jpg', 'jpeg', 'png', 'webp'],
        ];
    }
}
