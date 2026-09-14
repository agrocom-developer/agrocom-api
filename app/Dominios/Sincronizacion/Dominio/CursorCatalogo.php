<?php

namespace App\Dominios\Sincronizacion\Dominio;

/**
 * Cursor opaco de `GET /api/sync/catalogo?desde={cursor}` (espec §2.1, punto
 * 6). Un solo parámetro `desde` en la URL, pero internamente compuesto: cada
 * sección (`ordenes`, `lotes`, `personas`) avanza a su propio ritmo, así que
 * mezclar sus posiciones en una sola tupla `(updated_at, id)` compararía
 * filas de tablas distintas entre sí — cada sección lleva la suya.
 *
 * Formato de serialización: `base64(json_encode(['ordenes' => ['u' =>
 * <updated_at ISO8601>, 'id' => <int>], 'lotes' => [...], 'personas' =>
 * [...], 'trabajos' => [...]]))`. Una sección ausente (nunca hubo registros,
 * o el `desde` no decodifica) se trata como "sin posición": esa sección trae
 * todo lo vigente desde el principio — nunca un error. Un cursor corrupto en
 * un cliente offline no debe romper la sincronización, el peor caso
 * aceptable es una resincronización completa.
 *
 * `TRABAJOS` (HU-70, tarea 85): trabajos abiertos por asignación de equipo
 * desde el panel — ver `Operaciones\Contratos\LecturaTrabajosAsignados`.
 *
 * Clase pura: sin Eloquent, sin `Illuminate\Database` (verificado por
 * `tests/Unit/ArquitecturaModulosTest.php`).
 */
final readonly class CursorCatalogo
{
    public const string ORDENES = 'ordenes';

    public const string LOTES = 'lotes';

    public const string PERSONAS = 'personas';

    public const string TRABAJOS = 'trabajos';

    /** @var list<string> */
    private const array SECCIONES = [self::ORDENES, self::LOTES, self::PERSONAS, self::TRABAJOS];

    /** @param array<string, PosicionCursor|null> $posiciones */
    private function __construct(private array $posiciones) {}

    public static function vacio(): self
    {
        return new self(array_fill_keys(self::SECCIONES, null));
    }

    public static function desde(?string $valor): self
    {
        if ($valor === null || $valor === '') {
            return self::vacio();
        }

        $json = base64_decode($valor, true);
        $datos = $json !== false ? json_decode($json, true) : null;

        if (! is_array($datos)) {
            return self::vacio();
        }

        $posiciones = [];
        foreach (self::SECCIONES as $seccion) {
            $posiciones[$seccion] = self::posicionDesdeFila($datos[$seccion] ?? null);
        }

        return new self($posiciones);
    }

    /**
     * Una fila con forma correcta pero `u` no parseable como fecha (p. ej. un
     * cursor corrupto que sobrevivió al `base64`/`json` decode) se descarta
     * igual que una fila mal formada: "sin posición" para esa sección, nunca
     * un error. Sin esta validación, un `u` corrupto llegaría intacto hasta
     * `Carbon::parse()` en los adaptadores Eloquent y rompería la
     * sincronización con un 500 — exactamente lo que este value object
     * declara que no debe pasar.
     */
    private static function posicionDesdeFila(mixed $fila): ?PosicionCursor
    {
        if (! is_array($fila) || ! isset($fila['u'], $fila['id']) || ! is_scalar($fila['u']) || ! is_scalar($fila['id'])) {
            return null;
        }

        try {
            new \DateTimeImmutable((string) $fila['u']);
        } catch (\Exception) {
            return null;
        }

        return new PosicionCursor((string) $fila['u'], (int) $fila['id']);
    }

    public function posicion(string $seccion): ?PosicionCursor
    {
        return $this->posiciones[$seccion] ?? null;
    }

    public function conPosicion(string $seccion, PosicionCursor $posicion): self
    {
        $posiciones = $this->posiciones;
        $posiciones[$seccion] = $posicion;

        return new self($posiciones);
    }

    public function serializar(): string
    {
        $datos = [];
        foreach ($this->posiciones as $seccion => $posicion) {
            if ($posicion !== null) {
                $datos[$seccion] = ['u' => $posicion->actualizadoEn, 'id' => $posicion->id];
            }
        }

        return base64_encode((string) json_encode($datos));
    }
}
