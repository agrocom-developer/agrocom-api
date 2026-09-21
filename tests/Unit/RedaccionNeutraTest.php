<?php

/*
 * Compuerta del registro de idioma (CLAUDE.md, convención "tuteo estándar,
 * nunca voseo ni usted"; skill `redaccion-neutra`). AGROCOM opera en Bolivia
 * y los textos los escriben, sobre todo, agentes: el voseo rioplatense
 * ("Seleccioná", "No podés") se cuela solo si nadie lo frena.
 *
 * Revisa los VALORES de `lang/es/*.php` — es donde vive todo texto visible
 * (ADR 0013) — y falla ante:
 *  - imperativos de voseo: palabra terminada en -á/-é/-í tónica ("Ingresá",
 *    "Volvé", "Elegí"), con o sin pronombre pegado ("Fijate" no lleva tilde y
 *    por eso va en la lista explícita);
 *  - segunda persona de voseo: -ás/-és/-ís ("podés", "confirmás", "querés");
 *  - trato de usted: "Seleccione", "Ingrese" al empezar una frase (en medio
 *    de una frase esas mismas formas son subjuntivo legítimo: "en cuanto se
 *    cargue el primero"), "su contraseña", "usted".
 *
 * Es una heurística con lista de excepciones, no un analizador del español:
 * si marca una palabra legítima, se agrega a EXCEPCIONES con criterio. Lo que
 * no se hace es debilitar el patrón para que pase un texto.
 */

$raizProyecto = dirname(__DIR__, 2);

/**
 * Palabras que terminan como una forma de voseo y no lo son: adverbios,
 * sustantivos, interrogativos, subjuntivos y pretéritos de uso real.
 */
const REDACCION_EXCEPCIONES = [
    'está', 'estás', 'esté', 'estés', 'además', 'quizá', 'quizás', 'jamás', 'atrás', 'detrás', 'demás',
    'través', 'después', 'revés', 'interés', 'inglés', 'francés', 'país', 'aquí', 'así', 'allí', 'ahí',
    'sí', 'acá', 'allá', 'más', 'qué', 'quién', 'quiénes', 'cuál', 'cuáles', 'cuándo', 'cuánto', 'cómo', 'dónde',
    'café', 'comité', 'bebé', 'mamá', 'papá', 'sofá', 'maní', 'rubí', 'perú', 'panamá', 'mes', 'vez',
    'dé', 'sé', 'té', 'mí', 'tú', 'él', 'mié',
    // Imperativo de "dar" en tuteo: lleva tilde por el pronombre, no por voseo.
    'dáselo', 'dásela', 'dáselos', 'dáselas', 'dámelo', 'dámela',
];

/** Voseo sin tilde (el pronombre enclítico la desplaza): no lo ve el patrón general. */
const REDACCION_VOSEO_EXPLICITO = [
    'fijate', 'acordate', 'avisame', 'decime', 'pasame', 'mandame', 'contame', 'asegurate', 'quedate',
    'sos', 'vos', 'tenelo', 'ponelo', 'hacelo', 'elegilo',
];

/** Imperativos de usted: solo cuentan al EMPEZAR una frase (o tras "por favor"). */
const REDACCION_USTED_IMPERATIVO = '/(?:^|[.:;¡¿!?]\s+|por favor,?\s+)(seleccione|elija|confirme|ingrese|complete|verifique|revise|indique|escriba|guarde|cargue|agregue|elimine|marque|busque|cancele|corrija|actualice|suba|adjunte|repita|pruebe|intente|espere|vuelva|haga|ponga|tenga|contacte|comuníquese)\b/iu';

const REDACCION_USTED = '/\b(usted|ustedes|su (?:contraseña|correo|cuenta|usuario|sesión|perfil))\b/iu';

/**
 * @param  array<array-key, mixed>  $catalogo
 * @return array<string, string> clave con puntos → texto
 */
function redaccionAplanar(array $catalogo, string $prefijo = ''): array
{
    $plano = [];

    foreach ($catalogo as $clave => $valor) {
        $ruta = $prefijo === '' ? (string) $clave : "{$prefijo}.{$clave}";

        if (is_array($valor)) {
            $plano += redaccionAplanar($valor, $ruta);
        } elseif (is_string($valor)) {
            $plano[$ruta] = $valor;
        }
    }

    return $plano;
}

/**
 * @return list<string> palabras del texto que delatan voseo o trato de usted
 */
function redaccionFugas(string $texto): array
{
    // Los `:marcadores` son nombres de variable, no texto.
    $texto = preg_replace('/:[a-z_]+/i', ' ', $texto) ?? $texto;
    $fugas = [];

    preg_match_all('/[\p{L}]+/u', $texto, $palabras);

    foreach ($palabras[0] as $palabra) {
        $minuscula = mb_strtolower($palabra);

        if (in_array($minuscula, REDACCION_EXCEPCIONES, true)) {
            continue;
        }

        if (in_array($minuscula, REDACCION_VOSEO_EXPLICITO, true)) {
            $fugas[] = $palabra;

            continue;
        }

        // Futuro ("podrás", "tendrá", "enviaré") y pretérito en -ió/-ó no
        // son voseo: el voseo nunca lleva la `r` del infinitivo antes de la
        // vocal tónica.
        $esImperativoVoseo = preg_match('/[^r](á|é|í)(me|te|lo|la|le|los|las|les|nos|melo|selo|sela|telo)?$/u', $minuscula) === 1;
        $esSegundaPersonaVoseo = preg_match('/[^r](ás|és|ís)$/u', $minuscula) === 1;

        if ($esImperativoVoseo || $esSegundaPersonaVoseo) {
            $fugas[] = $palabra;
        }
    }

    if (preg_match_all(REDACCION_USTED_IMPERATIVO, $texto, $imperativos) > 0) {
        array_push($fugas, ...$imperativos[1]);
    }

    if (preg_match_all(REDACCION_USTED, $texto, $usted) > 0) {
        array_push($fugas, ...$usted[0]);
    }

    return $fugas;
}

it('ningún texto de lang/es usa voseo ni trato de usted', function () use ($raizProyecto) {
    $hallazgos = [];

    foreach (glob($raizProyecto.'/lang/es/*.php') ?: [] as $archivo) {
        /** @var array<array-key, mixed> $catalogo */
        $catalogo = require $archivo;

        foreach (redaccionAplanar($catalogo) as $clave => $texto) {
            $fugas = redaccionFugas($texto);

            if ($fugas !== []) {
                $hallazgos[] = sprintf('%s.%s → [%s] en «%s»', basename($archivo, '.php'), $clave, implode(', ', $fugas), $texto);
            }
        }
    }

    expect($hallazgos)->toBe(
        [],
        "Texto fuera del registro (tuteo estándar; ver skill redaccion-neutra):\n  ".implode("\n  ", $hallazgos),
    );
});

it('reconoce el voseo y el trato de usted, y deja pasar el tuteo', function () {
    expect(redaccionFugas('Ingresá tu usuario'))->toBe(['Ingresá'])
        ->and(redaccionFugas('No podés quitarle el permiso'))->toBe(['podés'])
        ->and(redaccionFugas('Volvé a intentarlo'))->toBe(['Volvé'])
        ->and(redaccionFugas('Elegí el contrato'))->toBe(['Elegí'])
        ->and(redaccionFugas('Fijate en el lote'))->toBe(['Fijate'])
        ->and(redaccionFugas('Reasignálos antes de darlo de baja'))->toBe(['Reasignálos'])
        ->and(redaccionFugas('Seleccione una opción'))->toBe(['Seleccione'])
        ->and(redaccionFugas('Por favor, ingrese su contraseña'))->toBe(['ingrese', 'su contraseña'])
        ->and(redaccionFugas('En cuanto se cargue el primero, vas a verlo acá. Dáselo antes a otro rol.'))->toBe([])
        ->and(redaccionFugas('Ingresa tu usuario. Podrás cambiarlo después: está acá, además.'))->toBe([])
        ->and(redaccionFugas('¿Con qué rol vas a trabajar? Necesitarás :cantidad más.'))->toBe([])
        ->and(redaccionFugas('Se enviará un correo. Te avisaré al revés.'))->toBe([]);
});
