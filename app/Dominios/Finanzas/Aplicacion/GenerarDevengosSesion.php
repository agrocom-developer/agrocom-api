<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Contratos\LecturaTarifasPago;
use App\Dominios\Finanzas\Contratos\ModalidadPago;
use App\Dominios\Finanzas\Dominio\Excepciones\TrabajoSinCondicionDePago;
use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Operaciones\Contratos\DatosSesionValidada;
use App\Dominios\Operaciones\Contratos\LecturaSesionValidada;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso "generar el devengo de una sesión validada" (espec §4.4, §5;
 * HU-16; ADR 0023 desde el 22/9/2026): lo invoca el listener de
 * `SesionValidada` — nunca nada relacionado con `cerrar()` (invariante 3 de
 * CLAUDE.md, literal).
 *
 * La condición de pago viene CON la sesión (`DatosSesionValidada::$condicionPago`):
 * la resolvió Operaciones desde la Orden de Trabajo del trabajo de la sesión
 * (tarifa elegida o negociada por equipo). Si el trabajo no la tiene —nació
 * antes de la reforma, o fuera de una Orden de Trabajo— se usa la tarifa
 * predeterminada del catálogo; sin ninguna de las dos no hay cómo calcular y
 * se lanza {@see TrabajoSinCondicionDePago}, que revierte la validación
 * entera (ver `MaquinaEstadosSesion::validar()`).
 *
 * Genera el devengo del piloto SIEMPRE, y el del auxiliar solo si
 * `auxiliarId` no es `null` (espec §5), cada uno con el monto de su puesto.
 *
 * Dos modalidades:
 * - **Por hectárea**: `monto = hectareas × tarifa`, una fila por sesión y
 *   persona (`UNIQUE (sesion_id, persona_id)`).
 * - **Por día (jornal)**: `monto = tarifa`, UNA fila por persona y fecha
 *   (`fin_devengos_personal_jornal_unico`); la segunda sesión del día no
 *   suma nada.
 *
 * Día mixto (decisión del dueño, 22/9/2026: «solo el jornal»): si la persona
 * tiene jornal ese día, lo por hectárea de esa fecha queda `absorbido_por_id`
 * → el jornal, en cualquier orden en que lleguen las validaciones. La fila
 * absorbida no se borra ni cambia su monto (invariante 6: sigue siendo
 * recalculable); solo deja de sumar (`DevengoPersonal::pagables()`).
 *
 * Aritmética decimal exacta con `Brick\Math\BigDecimal`, nunca `float`
 * (invariante 6; ver [[bcmath-no-instalado-usar-brick-math]]).
 */
final class GenerarDevengosSesion
{
    public function __construct(
        private readonly LecturaSesionValidada $lecturaSesion,
        private readonly LecturaTarifasPago $tarifas,
    ) {}

    /**
     * No hace nada si `$sesionId` no resuelve a una sesión (defensivo: el
     * evento solo debería dispararse tras persistir la fila).
     *
     * @throws TrabajoSinCondicionDePago si ni el trabajo ni el catálogo dan una condición de pago.
     */
    public function ejecutar(int $sesionId): void
    {
        $sesion = $this->lecturaSesion->obtener($sesionId);

        if ($sesion === null) {
            return;
        }

        $condicion = $sesion->condicionPago ?? $this->tarifas->predeterminada()?->comoCondicion();

        if ($condicion === null) {
            throw TrabajoSinCondicionDePago::paraSesion($sesionId);
        }

        $this->generarPara($sesion, $sesion->pilotoId, $condicion->montoPiloto, $condicion->modalidad);

        if ($sesion->auxiliarId !== null) {
            $this->generarPara($sesion, $sesion->auxiliarId, $condicion->montoAuxiliar, $condicion->modalidad);
        }
    }

    private function generarPara(DatosSesionValidada $sesion, int $personaId, string $tarifa, ModalidadPago $modalidad): void
    {
        match ($modalidad) {
            ModalidadPago::PorDia => $this->generarJornal($sesion, $personaId, $tarifa),
            ModalidadPago::PorHa => $this->generarPorHectarea($sesion, $personaId, $tarifa),
        };
    }

    /**
     * Idempotente por `UNIQUE (sesion_id, persona_id)` (invariante 3,
     * literal): intenta crear, y si la base rechaza por duplicado —segunda
     * capa, detrás de la que ya garantiza `MaquinaEstadosSesion::validar()`
     * no repitiendo el evento— lo trata como "ya aplicado". Cualquier otra
     * `QueryException` se relanza.
     *
     * Si ese día la persona ya cobró jornal, la fila nace absorbida.
     */
    private function generarPorHectarea(DatosSesionValidada $sesion, int $personaId, string $tarifa): void
    {
        $jornal = $this->jornalDelDia($personaId, $sesion->fecha);

        $this->crear([
            'sesion_id' => $sesion->sesionId,
            'trabajo_id' => $sesion->trabajoId,
            'persona_id' => $personaId,
            'modalidad' => ModalidadPago::PorHa,
            'hectareas' => $sesion->hectareasDeclaradas,
            'tarifa' => $tarifa,
            'monto' => $this->calcularMonto($sesion->hectareasDeclaradas, $tarifa),
            'fecha' => $sesion->fecha,
            'absorbido_por_id' => $jornal?->id,
        ]);
    }

    /**
     * Un jornal por persona y fecha: si ya existe, la sesión no agrega nada.
     * El índice único parcial es la segunda capa detrás de esta consulta. Al
     * crearlo, absorbe lo que esa persona ya tuviera por hectárea ese día.
     */
    private function generarJornal(DatosSesionValidada $sesion, int $personaId, string $tarifa): void
    {
        if ($this->jornalDelDia($personaId, $sesion->fecha) !== null) {
            return;
        }

        $jornal = $this->crear([
            'sesion_id' => $sesion->sesionId,
            'trabajo_id' => $sesion->trabajoId,
            'persona_id' => $personaId,
            'modalidad' => ModalidadPago::PorDia,
            'hectareas' => $sesion->hectareasDeclaradas,
            'tarifa' => $tarifa,
            'monto' => BigDecimal::of($tarifa)->toScale(2, RoundingMode::HalfUp)->__toString(),
            'fecha' => $sesion->fecha,
            'absorbido_por_id' => null,
        ]);

        if ($jornal === null) {
            return;
        }

        DevengoPersonal::query()
            ->pagables()
            ->where('persona_id', $personaId)
            ->whereDate('fecha', $sesion->fecha)
            ->where('modalidad', ModalidadPago::PorHa->value)
            ->get()
            ->each(fn (DevengoPersonal $devengo) => $devengo->forceFill(['absorbido_por_id' => $jornal->id])->save());
    }

    private function jornalDelDia(int $personaId, string $fecha): ?DevengoPersonal
    {
        return DevengoPersonal::query()
            ->where('persona_id', $personaId)
            ->whereDate('fecha', $fecha)
            ->where('modalidad', ModalidadPago::PorDia->value)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return DevengoPersonal|null `null` si la base lo rechazó por duplicado (ya aplicado).
     */
    private function crear(array $atributos): ?DevengoPersonal
    {
        try {
            // Transacción anidada = SAVEPOINT: si la base rechaza el INSERT,
            // se vuelve al savepoint y la transacción de la validación (que
            // envuelve a este listener) sigue viva en Postgres; sin él, un
            // duplicado atrapado dejaría la transacción abortada (25P02).
            return DB::transaction(fn (): DevengoPersonal => DevengoPersonal::create($atributos));
        } catch (QueryException $excepcion) {
            if (! $this->esViolacionDeUnicidad($excepcion)) {
                throw $excepcion;
            }

            return null;
        }
    }

    /**
     * `hectareas`/`tarifa` son `DECIMAL(*, 2)`: su producto exacto tiene como
     * mucho 4 decimales. Se redondea al centavo (`HalfUp`), no se trunca:
     * truncar haría que el piloto cobre sistemáticamente de menos. Con
     * `3.33 × 12.35 = 41.1255` el monto correcto es `41.13`.
     */
    private function calcularMonto(string $hectareas, string $tarifa): string
    {
        return (string) BigDecimal::of($hectareas)
            ->multipliedBy($tarifa)
            ->toScale(2, RoundingMode::HalfUp);
    }

    private function esViolacionDeUnicidad(QueryException $excepcion): bool
    {
        $mensaje = $excepcion->getMessage();

        return str_contains($mensaje, 'fin_devengos_personal_sesion_persona_unico')
            || str_contains($mensaje, 'fin_devengos_personal_jornal_unico')
            || str_contains($mensaje, 'fin_devengos_personal.sesion_id');
    }
}
