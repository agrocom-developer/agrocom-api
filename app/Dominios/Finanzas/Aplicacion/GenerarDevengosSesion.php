<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Dominio\Excepciones\PersonaSinTarifaHa;
use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Operaciones\Contratos\DatosSesionValidada;
use App\Dominios\Operaciones\Contratos\LecturaSesionValidada;
use App\Dominios\Personal\Contratos\LecturaTarifaPersona;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Caso de uso "generar el devengo de una sesión validada" (espec §4.4, §5;
 * HU-16, tarea 16): lo invoca el listener de `SesionValidada` — nunca nada
 * relacionado con `cerrar()` (invariante 3 de CLAUDE.md, literal).
 *
 * Genera el devengo del piloto SIEMPRE, y el del auxiliar solo si
 * `auxiliarId` no es `null` (espec §5: "Genera el devengo de ese piloto y su
 * auxiliar").
 *
 * `monto` con aritmética decimal exacta, nunca `float` (invariante 6 de
 * CLAUDE.md): ver {@see self::calcularMonto()} para por qué es
 * `Brick\Math\BigDecimal` y no la extensión `bcmath` que el prompt de la
 * tarea nombraba — documentado en runs/16.md.
 */
final class GenerarDevengosSesion
{
    public function __construct(
        private readonly LecturaSesionValidada $lecturaSesion,
        private readonly LecturaTarifaPersona $lecturaTarifa,
    ) {}

    /**
     * No hace nada si `$sesionId` no resuelve a una sesión (defensivo: el
     * evento solo debería dispararse tras persistir la fila).
     *
     * @throws PersonaSinTarifaHa si el piloto, o el auxiliar cuando existe,
     *                            no tiene `tarifa_ha` configurada.
     */
    public function ejecutar(int $sesionId): void
    {
        $sesion = $this->lecturaSesion->obtener($sesionId);

        if ($sesion === null) {
            return;
        }

        $this->generarPara($sesion, $sesion->pilotoId);

        if ($sesion->auxiliarId !== null) {
            $this->generarPara($sesion, $sesion->auxiliarId);
        }
    }

    /**
     * Idempotente por `UNIQUE (sesion_id, persona_id)` (invariante 3,
     * literal): intenta crear, y si la base rechaza por duplicado —segunda
     * capa, detrás de la que ya garantiza `MaquinaEstadosSesion::validar()`
     * no repitiendo el evento— lo trata como "ya aplicado", mismo criterio
     * que `EscrituraSincronizacionEloquent::abrirSesion()` con
     * `uuid_cliente`. Cualquier otra `QueryException` se relanza: no es el
     * caso que este método sabe resolver.
     */
    private function generarPara(DatosSesionValidada $sesion, int $personaId): void
    {
        $tarifaHa = $this->lecturaTarifa->tarifaHaDe($personaId);

        if ($tarifaHa === null) {
            throw PersonaSinTarifaHa::paraPersona($personaId);
        }

        try {
            DevengoPersonal::create([
                'sesion_id' => $sesion->sesionId,
                'persona_id' => $personaId,
                'hectareas' => $sesion->hectareasDeclaradas,
                'tarifa_ha' => $tarifaHa,
                'monto' => $this->calcularMonto($sesion->hectareasDeclaradas, $tarifaHa),
                'fecha' => Carbon::now()->toDateString(),
            ]);
        } catch (QueryException $excepcion) {
            if (! $this->esViolacionDeUnicidad($excepcion)) {
                throw $excepcion;
            }
        }
    }

    /**
     * El prompt de esta tarea pedía `bcmath` (`bcmul`, escala 2) — la
     * extensión `ext-bcmath` no está instalada ni en el `Dockerfile` local ni
     * en `.github/workflows/ci.yml` (`shivammathur/setup-php` solo agrega
     * `pgsql, pdo_pgsql`), y ese workflow es zona congelada del turno noche
     * que esta tarea no declaró descongelar (`descongela=tests`, no
     * `=github`). `Brick\Math\BigDecimal` cumple el mismo objetivo —
     * aritmética decimal exacta, nunca `float`— sin esa dependencia externa:
     * ya es una dependencia real del propio Laravel (`HasAttributes::asDecimal()`,
     * el método detrás de cada cast `decimal:N` de este proyecto, incluido
     * `DevengoPersonal::casts()`, ya la usa). Documentado en runs/16.md.
     *
     * `hectareas`/`tarifa_ha` son `DECIMAL(*, 2)`: su producto exacto tiene
     * como mucho 4 decimales, sin pérdida en `multipliedBy()`. El redondeo al
     * centavo (`toScale(2, RoundingMode::HalfUp)`) es deliberado: truncar en
     * vez de redondear significa que el piloto cobra sistemáticamente de
     * menos por fracciones de centavo — sesgado, no "exacto". Con
     * `hectareas = 3.33`, `tarifa_ha = 12.35` (el caso de la tarea): el
     * producto exacto es `41.1255`; el monto correcto es `41.13`, no un
     * `41.12` truncado.
     */
    private function calcularMonto(string $hectareas, string $tarifaHa): string
    {
        return (string) BigDecimal::of($hectareas)
            ->multipliedBy($tarifaHa)
            ->toScale(2, RoundingMode::HalfUp);
    }

    private function esViolacionDeUnicidad(QueryException $excepcion): bool
    {
        $mensaje = $excepcion->getMessage();

        return str_contains($mensaje, 'fin_devengos_personal_sesion_persona_unico')
            || str_contains($mensaje, 'fin_devengos_personal.sesion_id');
    }
}
