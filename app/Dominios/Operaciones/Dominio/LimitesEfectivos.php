<?php

namespace App\Dominios\Operaciones\Dominio;

use App\Dominios\Operaciones\Contratos\RegistroCondiciones;
use Brick\Math\BigDecimal;

/**
 * Límites climáticos y parámetros de vuelo EFECTIVOS de un trabajo: el valor
 * que cargó el jefe de campo en la Orden de Trabajo (cabecera de la tanda) y,
 * si lo dejó en blanco, el parámetro por defecto del sistema. Es la única
 * regla que resuelve esa herencia, y la leen los dos lados que la necesitan
 * (vía `Trabajo::limitesEfectivos()`): el catálogo de la app de campo
 * (`LecturaTrabajosAsignadosEloquent`), que es offline y no puede resolverla
 * por su cuenta, y la validación de `condiciones` del sync
 * ({@see self::admiteCondiciones()}), para que lo que la app muestra y lo que
 * el servidor exige nunca se contradigan.
 *
 * Niveles de la herencia (decisión del dueño, 1/10/2026):
 *
 * 1. La Orden de Trabajo.
 * 2. El contrato NO es un nivel: HU-91 (14/9/2026) le sacó los límites
 *    propios (ver docblock de `Comercial\...\Contrato`).
 * 3. El default del sistema son las constantes de {@see RegistroCondiciones}
 *    (viento, temperatura y humedad máxima) — siguen siendo su única fuente;
 *    acá solo se formatean como DECIMAL en string (invariante 6).
 *
 * `humedad_min_pct`, `altura_vuelo_m`, `velocidad_vuelo_kmh` y
 * `ancho_pasada_m` no tienen default del sistema: quedan `null` cuando la
 * Orden de Trabajo no los trae (o el trabajo no tiene Orden de Trabajo).
 *
 * Regla pura, sin Eloquent (verificado por `tests/Unit/ArquitecturaModulosTest`).
 */
final readonly class LimitesEfectivos
{
    private function __construct(
        public ?string $humedadMinPct,
        public string $humedadMaxPct,
        public string $vientoMaxKmh,
        public string $temperaturaMaxC,
        public ?string $alturaVueloM,
        public ?string $velocidadVueloKmh,
        public ?string $anchoPasadaM,
    ) {}

    /** Sin ningún límite propio: solo los defaults del sistema. */
    public static function porDefecto(): self
    {
        return self::resolver(null, null, null, null, null, null, null);
    }

    /**
     * Cada argumento es el valor de la Orden de Trabajo tal como lo guarda el
     * modelo (DECIMAL como string, o `null`); todos `null` si el trabajo no
     * tiene Orden de Trabajo.
     */
    public static function resolver(
        ?string $humedadMinPct,
        ?string $humedadMaxPct,
        ?string $vientoMaxKmh,
        ?string $temperaturaMaxC,
        ?string $alturaVueloM,
        ?string $velocidadVueloKmh,
        ?string $anchoPasadaM,
    ): self {
        return new self(
            humedadMinPct: self::propio($humedadMinPct),
            humedadMaxPct: self::propio($humedadMaxPct) ?? self::decimal(RegistroCondiciones::HUMEDAD_MAX_PCT),
            vientoMaxKmh: self::propio($vientoMaxKmh) ?? self::decimal(RegistroCondiciones::VIENTO_MAX_KMH),
            temperaturaMaxC: self::propio($temperaturaMaxC) ?? self::decimal(RegistroCondiciones::TEMPERATURA_MAX_C),
            alturaVueloM: self::propio($alturaVueloM),
            velocidadVueloKmh: self::propio($velocidadVueloKmh),
            anchoPasadaM: self::propio($anchoPasadaM),
        );
    }

    /**
     * Espec §5, "condiciones dentro de rango": las mediciones de inicio de
     * sesión caen dentro de ESTOS límites. Viento, temperatura y humedad
     * tienen tope (siempre hay uno: el propio o el default); la humedad
     * mínima solo se exige si la Orden de Trabajo la fijó. Comparación
     * DECIMAL exacta (invariante 6): las mediciones llegan como string
     * numérico ya validado por `RegistroCondiciones`.
     */
    public function admiteCondiciones(string $vientoKmh, string $temperaturaC, string $humedadPct): bool
    {
        // `is_numeric()` (la validación de forma) admite espacios alrededor;
        // `BigDecimal::of()` no.
        $humedad = BigDecimal::of(trim($humedadPct));

        return BigDecimal::of(trim($vientoKmh))->isLessThanOrEqualTo($this->vientoMaxKmh)
            && BigDecimal::of(trim($temperaturaC))->isLessThanOrEqualTo($this->temperaturaMaxC)
            && $humedad->isLessThanOrEqualTo($this->humedadMaxPct)
            && ($this->humedadMinPct === null || $humedad->isGreaterThanOrEqualTo($this->humedadMinPct));
    }

    /** Un valor en blanco (`null` o `''`) es "no cargado": hereda. */
    private static function propio(?string $valor): ?string
    {
        return $valor === null || $valor === '' ? null : $valor;
    }

    /**
     * Las constantes son `float` enteros (17.0, 30.0, 90.0): formatear con dos
     * decimales es exacto y deja la misma forma que el cast `decimal:2`.
     */
    private static function decimal(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }
}
