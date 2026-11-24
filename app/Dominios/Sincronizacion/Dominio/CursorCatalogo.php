<?php

namespace App\Dominios\Sincronizacion\Dominio;

/**
 * Cursor opaco de `GET /api/sync/catalogo?desde={cursor}` (espec §2.1, punto
 * 6). Un solo parámetro `desde` en la URL, pero internamente compuesto: cada
 * sección (`personas`, `servicios_asignados`, `servicios_retirados`) avanza a su propio ritmo, así que
 * mezclar sus posiciones en una sola tupla `(updated_at, id)` compararía
 * filas de tablas distintas entre sí — cada sección lleva la suya.
 *
 * Formato de serialización: `base64(json_encode(['personas' => ['u' =>
 * <updated_at ISO8601>, 'id' => <int>], 'servicios_asignados' => [...],
 * 'servicios_retirados' => [...]]))`. Una sección ausente (nunca hubo registros,
 * o el `desde` no decodifica) se trata como "sin posición": esa sección trae
 * todo lo vigente desde el principio — nunca un error. Un cursor corrupto en
 * un cliente offline no debe romper la sincronización, el peor caso
 * aceptable es una resincronización completa.
 *
 * `SERVICIOS`: solicitudes aceptadas que el equipo del operario lleva al lote
 * (ADR 0037) — ver `Solicitudes\Contratos\LecturaServiciosAsignados`.
 *
 * `SERVICIOS_RETIRADOS`: lo que el dispositivo ya no debe mostrar (cancelado,
 * devuelto a asignación o ya ejecutado). Mismo formato que las demás; un cursor
 * anterior simplemente no las trae.
 *
 * Clase pura: sin Eloquent, sin `Illuminate\Database` (verificado por
 * `tests/Unit/ArquitecturaModulosTest.php`).
 */
final readonly class CursorCatalogo
{
    public const string PERSONAS = 'personas';

    /** Solicitudes aceptadas que el equipo del operario lleva al lote (ADR 0037). */
    public const string SERVICIOS = 'servicios_asignados';

    /** Servicios que el dispositivo ya no debe mostrar: cancelados, devueltos a asignación o ya ejecutados. */
    public const string SERVICIOS_RETIRADOS = 'servicios_retirados';

    /** @var list<string> */
    private const array SECCIONES = [
        self::PERSONAS, self::SERVICIOS, self::SERVICIOS_RETIRADOS,
    ];

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

    /**
     * ¿Ninguna sección tiene posición? Es la primera sincronización del
     * dispositivo (o una resincronización completa): no tiene nada que
     * retirar.
     */
    public function esVacio(): bool
    {
        foreach ($this->posiciones as $posicion) {
            if ($posicion !== null) {
                return false;
            }
        }

        return true;
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
