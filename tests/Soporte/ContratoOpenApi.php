<?php

namespace Tests\Soporte;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Lectura del contrato `docs/api/openapi.yaml` y validación de cuerpos JSON
 * contra sus esquemas, sin dependencias nuevas.
 *
 * Cubre el subconjunto de OpenAPI 3 que genera swagger-php en este proyecto:
 * `$ref`, `allOf`, `oneOf`/`anyOf`, `type`, `nullable`, `enum`, `required`,
 * `properties`, `items` y los `format` `date`, `date-time` y `uuid`. Un campo
 * que el esquema no declara es un hallazgo: el contrato es lo que la app puede
 * leer, y un campo sin documentar es un campo en el que nadie debe apoyarse.
 */
final class ContratoOpenApi
{
    private const METODOS = ['get', 'put', 'post', 'delete', 'patch'];

    /** @var array<string, mixed> */
    private array $spec;

    public function __construct(?string $ruta = null)
    {
        $ruta ??= dirname(__DIR__, 2).'/docs/api/openapi.yaml';
        $this->spec = Yaml::parseFile($ruta);
    }

    /** @return array<string, mixed> */
    public function spec(): array
    {
        return $this->spec;
    }

    /**
     * Operaciones por `operationId`: método, ruta y definición.
     *
     * @return array<string, array{metodo: string, ruta: string, operacion: array<string, mixed>}>
     */
    public function operaciones(): array
    {
        $resultado = [];

        foreach ($this->spec['paths'] as $ruta => $definiciones) {
            foreach ($definiciones as $metodo => $operacion) {
                if (in_array($metodo, self::METODOS, true)) {
                    $resultado[$operacion['operationId']] = ['metodo' => strtoupper($metodo), 'ruta' => $ruta, 'operacion' => $operacion];
                }
            }
        }

        return $resultado;
    }

    /**
     * Esquema del cuerpo de la respuesta con ese estado, o `null` si el estado
     * no figura en el contrato. `[]` significa documentado sin cuerpo.
     *
     * @param  array<string, mixed>  $operacion
     * @return array<string, mixed>|null
     */
    public function esquemaDeRespuesta(array $operacion, int $estado): ?array
    {
        $respuesta = $operacion['responses'][(string) $estado] ?? $operacion['responses'][$estado] ?? null;

        if ($respuesta === null) {
            return null;
        }

        return $respuesta['content']['application/json']['schema'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $operacion
     * @return array<string, mixed>|null
     */
    public function esquemaDePeticion(array $operacion): ?array
    {
        $contenido = $operacion['requestBody']['content'] ?? [];

        return ($contenido['application/json'] ?? $contenido['multipart/form-data'] ?? null)['schema'] ?? null;
    }

    /**
     * Hallazgos de un valor contra un esquema; vacío si lo cumple.
     *
     * @param  array<string, mixed>  $esquema
     * @return list<string>
     */
    public function validar(mixed $valor, array $esquema, string $ruta = '$'): array
    {
        $esquema = $this->resolver($esquema);

        if ($esquema === []) {
            return [];
        }

        if ($valor === null) {
            return ($esquema['nullable'] ?? false) === true || ($esquema['type'] ?? null) === 'null'
                ? []
                : ["{$ruta}: es null y el contrato no lo declara nullable"];
        }

        if (isset($esquema['allOf'])) {
            $hallazgos = [];
            foreach ($esquema['allOf'] as $parte) {
                $hallazgos = [...$hallazgos, ...$this->validar($valor, $parte, $ruta)];
            }

            return $hallazgos;
        }

        foreach (['oneOf', 'anyOf'] as $combinador) {
            if (isset($esquema[$combinador])) {
                $mejor = null;
                foreach ($esquema[$combinador] as $alternativa) {
                    $hallazgos = $this->validar($valor, $alternativa, $ruta);
                    if ($hallazgos === []) {
                        return [];
                    }
                    $mejor ??= $hallazgos;
                }

                return $mejor ?? ["{$ruta}: no cumple ninguna alternativa"];
            }
        }

        $hallazgos = [];
        $tipo = $esquema['type'] ?? (isset($esquema['properties']) ? 'object' : (isset($esquema['items']) ? 'array' : null));

        if ($tipo !== null && ! $this->esDeTipo($valor, $tipo)) {
            return ["{$ruta}: se esperaba {$tipo} y llegó ".get_debug_type($valor)];
        }

        if (isset($esquema['enum']) && ! in_array($valor, $esquema['enum'], true)) {
            $hallazgos[] = "{$ruta}: ".json_encode($valor, JSON_UNESCAPED_UNICODE).' no está en el enum ('.implode(', ', array_map('strval', $esquema['enum'])).')';
        }

        if (is_string($valor) && isset($esquema['format'])) {
            $formato = $this->validarFormato($valor, $esquema['format']);
            if ($formato !== null) {
                $hallazgos[] = "{$ruta}: {$formato}";
            }
        }

        if ($tipo === 'object' && is_array($valor)) {
            $declaradas = $esquema['properties'] ?? [];

            foreach ($esquema['required'] ?? [] as $requerida) {
                if (! array_key_exists($requerida, $valor)) {
                    $hallazgos[] = "{$ruta}: falta el campo requerido {$requerida}";
                }
            }

            foreach ($valor as $clave => $interno) {
                if (! array_key_exists($clave, $declaradas)) {
                    if (is_array($esquema['additionalProperties'] ?? null)) {
                        $hallazgos = [...$hallazgos, ...$this->validar($interno, $esquema['additionalProperties'], "{$ruta}.{$clave}")];

                        continue;
                    }

                    if (($esquema['additionalProperties'] ?? null) === false || $declaradas !== []) {
                        $hallazgos[] = "{$ruta}: el campo {$clave} no está documentado en el contrato";
                    }

                    continue;
                }
                $hallazgos = [...$hallazgos, ...$this->validar($interno, $declaradas[$clave], "{$ruta}.{$clave}")];
            }
        }

        if ($tipo === 'array' && is_array($valor) && isset($esquema['items'])) {
            foreach (array_values($valor) as $indice => $elemento) {
                $hallazgos = [...$hallazgos, ...$this->validar($elemento, $esquema['items'], "{$ruta}[{$indice}]")];
            }
        }

        return $hallazgos;
    }

    /**
     * @param  array<string, mixed>  $esquema
     * @return array<string, mixed>
     */
    private function resolver(array $esquema): array
    {
        $profundidad = 0;

        while (isset($esquema['$ref'])) {
            if (++$profundidad > 20) {
                throw new RuntimeException('Referencia circular en el contrato.');
            }

            $destino = $this->spec;
            foreach (explode('/', ltrim((string) $esquema['$ref'], '#/')) as $segmento) {
                $destino = $destino[$segmento] ?? throw new RuntimeException("Referencia rota: {$esquema['$ref']}");
            }
            $esquema = $destino;
        }

        return $esquema;
    }

    private function esDeTipo(mixed $valor, string $tipo): bool
    {
        return match ($tipo) {
            'string' => is_string($valor),
            'integer' => is_int($valor),
            'number' => is_int($valor) || is_float($valor),
            'boolean' => is_bool($valor),
            'array' => is_array($valor) && array_is_list($valor),
            'object' => is_array($valor) && ($valor === [] || ! array_is_list($valor)),
            default => true,
        };
    }

    private function validarFormato(string $valor, string $formato): ?string
    {
        return match ($formato) {
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? null : "no es una fecha: {$valor}",
            'date-time' => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $valor) === 1 ? null : "no es fecha y hora ISO 8601: {$valor}",
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $valor) === 1 ? null : "no es un uuid: {$valor}",
            default => null,
        };
    }
}
