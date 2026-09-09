# ADR 0001 — Base de datos: PostgreSQL 16

**Estado:** Aceptada · **Reemplaza:** el supuesto abierto "stack a confirmar" de la especificación v1.0 legacy.

## Contexto

El sistema necesita cuatro capacidades concretas, no genéricas: DDL transaccional (se van a correr cientos de migraciones con agentes de IA, y una migración fallida a mitad no puede dejar el esquema a medio aplicar); `INSERT ... ON CONFLICT DO NOTHING` con `RETURNING` limpio (es el corazón de la idempotencia del protocolo de sync — reinsertar un `uuid_cliente` repetido debe ser un no-op que además informe qué pasó); índices parciales y por expresión (`UNIQUE` de "una sola orden vigente por lote", índices solo sobre `estado='pendiente'`); y `CHECK` constraints con tipos estrictos (rangos de viento/temperatura, enums de estado). A esto se suma la necesidad de JSONB indexable para la geometría GeoJSON de los lotes.

## Decisión

**PostgreSQL 16.**

## Alternativas descartadas

- **MySQL 8**: cubre `CHECK` desde 8.0.16, pero `INSERT IGNORE` silencia todos los errores (no solo el duplicado) y no tiene DDL transaccional ni índices parciales reales.
- **MariaDB 11**: mismas carencias que MySQL, y es la que menos rodaje tiene en el ecosistema Laravel moderno — la peor opción de las tres para este caso.

El costo de este cambio es bajo: con Eloquent, el 95% del código no sabe qué motor hay debajo. El 5% restante (el `ON CONFLICT` del sync, los índices parciales) es justo donde Postgres paga la diferencia.

## Consecuencias

- Sin PostGIS en v1 — la geometría de los lotes se guarda y se dibuja, no se consulta espacialmente.
- El protocolo de sync (`docs/especificacion/especificacion_funcional_tecnica.md`, sección 2) depende de `UNIQUE (uuid_cliente)` por tabla operativa como mecanismo real de idempotencia, no solo como validación de aplicación.
