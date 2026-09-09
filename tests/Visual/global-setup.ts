import { execFileSync } from 'node:child_process';
import { readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

/**
 * Garantiza, ANTES de la primera prueba, que el panel está arriba y
 * seedeado: compose-arriba + migrate-seed. Un `webServer.command` de
 * Playwright con `reuseExistingServer: true` no alcanza por sí solo — si el
 * contenedor ya está corriendo (compartido con otra sesión, ver memoria
 * "git en working tree compartido") pero sin seedear, Playwright nunca
 * ejecutaría ese comando y las vistas saldrían vacías.
 *
 * Ambos pasos son idempotentes por diseño:
 * - `docker compose up -d` no reinicia lo que ya está corriendo.
 * - `migrate --seed --force` es seguro de repetir: los seeders de demo
 *   (CatalogoSeeder, Demo\DemostracionSeeder) son idempotentes.
 *
 * Nunca `migrate:fresh`/`migrate:refresh`/`db:wipe`: además de estar
 * bloqueados por el guardarraíl (.claude/hooks/guardarrail-bash.sh),
 * destruirían la base compartida con otra sesión.
 *
 * Después del seed corre TODOS los fixtures de `tests/Visual/fixtures/`, en
 * orden alfabético. Cada spec sigue corriendo el suyo en su `beforeAll`
 * (ahí queda declarada la dependencia, y son idempotentes), pero un fixture
 * también deja datos que ven otras pantallas — `combustible-demo.php`
 * siembra una base que aparece en `bases/index`, `baterias/index` y los
 * filtros de `gastos` — y como los specs corren en orden alfabético,
 * `bases.spec.ts` llegaba antes que `combustible.spec.ts`: sobre una base
 * recién creada capturaba SIN esa base y sobre una ya usada, CON ella.
 * Sembrar todo acá deja el estado de la base igual en ambos casos, y las
 * capturas de referencia se toman siempre con el conjunto completo.
 */
const BASE_URL = 'http://localhost:8000';
const DIRECTORIO_FIXTURES = fileURLToPath(new URL('./fixtures', import.meta.url));
const RUTA_FIXTURES_EN_CONTENEDOR = '/var/www/html/tests/Visual/fixtures';
const INTENTOS_SALUD = 60;
const ESPERA_SALUD_MS = 2000;

function ejecutar(comando: string, args: string[]): void {
    execFileSync(comando, args, { stdio: 'inherit' });
}

async function esperarServidorVivo(): Promise<void> {
    for (let intento = 1; intento <= INTENTOS_SALUD; intento += 1) {
        try {
            const respuesta = await fetch(`${BASE_URL}/login`);
            if (respuesta.ok) {
                return;
            }
        } catch {
            // El servidor todavía no acepta conexiones — reintentar.
        }

        await new Promise((resuelve) => setTimeout(resuelve, ESPERA_SALUD_MS));
    }

    throw new Error(
        `El panel no respondió en ${BASE_URL}/login tras ${INTENTOS_SALUD} intentos. ` +
            '¿docker compose up -d falló, o el contenedor "app" no llegó a levantar php artisan serve?',
    );
}

export default async function globalSetup(): Promise<void> {
    ejecutar('docker', ['compose', 'up', '-d']);

    await esperarServidorVivo();

    ejecutar('docker', ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'migrate', '--seed', '--force']);

    sembrarFixtures();
}

function sembrarFixtures(): void {
    const fixtures = readdirSync(DIRECTORIO_FIXTURES)
        .filter((nombre) => nombre.endsWith('.php'))
        .sort();

    for (const fixture of fixtures) {
        ejecutar('docker', ['compose', 'exec', '-T', 'app', 'php', `${RUTA_FIXTURES_EN_CONTENEDOR}/${fixture}`]);
    }
}
