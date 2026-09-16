<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Paleta curada de colores para `com_propiedades.color` (adenda 16/9/2026 a
 * ADR 0018 punto 1) — pedido explícito del dueño: un conjunto FIJO de
 * colores para elegir, no un input de color libre, para que dos propiedades
 * del mismo cliente nunca terminen con tonos casi idénticos.
 *
 * Es un dato de NEGOCIO por registro (qué color identifica a esta
 * propiedad), no un token de theming del panel — no lo alcanza la
 * invariante 11 de CLAUDE.md, que rige colores de la interfaz del panel en
 * sí. Los valores HEX viven acá (una sola fuente de verdad, referenciada por
 * la migración para el CHECK de base, por el Form Request para la regla
 * `Rule::in`, y por `PropiedadesController::coloresDisponibles()` para el
 * átomo `atoms/color-swatch-picker`).
 *
 * Paleta diseñada por `design-ui` (16/9/2026, documentada en
 * `docs/diseno/sistema_diseno_panel.md` §21.1): separación deliberada de
 * matiz (~27-28° entre colores) saltando la franja 60-140° (la del cultivo
 * en vista satelital, donde un tono ahí se perdería contra el fondo), y
 * luminosidad media para no fundirse en ningún tema. El verde es más
 * azulado que el verde de marca a propósito: mejor separación del rojo bajo
 * deuteranopía/protanopía. Marrón/Pizarra (y, en la ampliación de abajo,
 * Musgo/Malva) son deliberadamente desaturados — se distinguen por croma,
 * no por matiz.
 *
 * Ampliada de 13 a 16 el mismo 16/9/2026, tras mover el campo a un modal
 * (pedido directo del dueño: "así mismo aumentamos los colores para
 * escoger"): Carmín ocupa el único hueco de matiz con holgura real por
 * encima del piso de separación de la paleta original (Frambuesa→Rojo);
 * Musgo y Malva aplican la misma técnica de desaturación que ya usaban
 * Marrón/Pizarra a las familias verde y violeta/púrpura, que todavía no
 * tenían versión desaturada.
 *
 * Ampliada de nuevo, 16 a 23, el mismo día: el modal pasó a ser un popup
 * anclado (no compite por espacio en una fila del formulario) y el dueño
 * pidió más colores — "cierto quiero más colores", después "no tenga miedo
 * de usar también el color negro". El anillo de matiz saturado ya no tenía
 * margen real (medido con la fórmula sRGB→HSL completa: el hueco
 * Carmín→Rojo es de ~8°, no los ~17-18° que se había asumido — CERO tonos
 * saturados nuevos en esta vuelta). La ampliación es enteramente por CROMA:
 * seis versiones desaturadas nuevas (Caqui/Salvia/Acero/Aciano/Vino/Terracota),
 * tratando el anillo desaturado como uno PROPIO con un piso de separación
 * más alto (21°, no 17°) porque a menor croma dos matices se confunden más
 * fácil, no menos. Negro (`#121212`, no `#000000` puro — el mismo criterio
 * "ni pastel ni casi negro" del resto de la paleta, y coincide con
 * `--ag-color-gray-950` del propio sistema) es una categoría aparte, neutro
 * sin matiz, no compite por presupuesto del anillo.
 *
 * Ningún HEX de una ronda anterior cambió en ninguna ampliación — siempre
 * aditivo. Detalle completo de las tres rondas en
 * `docs/diseno/sistema_diseno_panel.md` §21.1.
 */
enum ColorPropiedad: string
{
    case Rojo = '#B6202D';
    case Naranja = '#CD5E1D';
    case Ambar = '#AA8C18';
    case VerdeBosque = '#218349';
    case VerdeAzulado = '#1E8F80';
    case Turquesa = '#1F80AD';
    case Azul = '#2B50CA';
    case Indigo = '#4A30A6';
    case Violeta = '#9331C4';
    case Purpura = '#A32995';
    case Frambuesa = '#B92770';
    case Carmin = '#B82343';
    case Marron = '#755238';
    case Pizarra = '#566F81';
    case Musgo = '#467C61';
    case Malva = '#7B4D80';
    case Caqui = '#766B42';
    case Salvia = '#487A73';
    case Acero = '#4E597E';
    case Aciano = '#5B4B81';
    case Vino = '#7C4665';
    case Terracota = '#7E4449';
    case Negro = '#121212';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Rojo => 'Rojo',
            self::Naranja => 'Naranja',
            self::Ambar => 'Ámbar',
            self::VerdeBosque => 'Verde bosque',
            self::VerdeAzulado => 'Verde azulado',
            self::Turquesa => 'Turquesa',
            self::Azul => 'Azul',
            self::Indigo => 'Índigo',
            self::Violeta => 'Violeta',
            self::Purpura => 'Púrpura',
            self::Frambuesa => 'Frambuesa',
            self::Carmin => 'Carmín',
            self::Marron => 'Marrón',
            self::Pizarra => 'Pizarra',
            self::Musgo => 'Musgo',
            self::Malva => 'Malva',
            self::Caqui => 'Caqui',
            self::Salvia => 'Salvia',
            self::Acero => 'Acero',
            self::Aciano => 'Aciano',
            self::Vino => 'Vino',
            self::Terracota => 'Terracota',
            self::Negro => 'Negro',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $color) => $color->value, self::cases());
    }
}
