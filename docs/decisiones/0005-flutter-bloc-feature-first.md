# ADR 0005 — App de campo: Flutter feature-first con BLoC

**Estado:** Aceptada · **Fuente:** `docs/legacy/decisiones_arquitectura_v2.md`, sección 4.

## Contexto

La app de campo (`agrocom-field`, repositorio separado) corre en el RC del dron Agras (piloto) y en un celular Android (auxiliar), sin conectividad en el lote. Necesita flujos con estados explícitos (el checklist bloqueante de mezcla, la sesión con sus transiciones abierta→cerrada) y una estructura que rinda bien con agentes de IA como implementadores.

## Decisión

**BLoC**, con arquitectura feature-first en tres capas por feature — espejo de la del backend (ADR 0003):

```
lib/
├── nucleo/                # compartido entre flavors (~70% del código)
│   ├── db/                # drift: tablas espejo + outbox
│   ├── sync/               # motor: cola, push/pull, estados — no depende de ningún bloc
│   ├── auth/  evidencias/  catalogo/  ui/
│   └── di/                # get_it: registro de lo compartido
├── features/
│   ├── sesion_vuelo/       # flavor piloto: presentation/ (SesionBloc) + domain/ (reglas puras) + data/ (repo → drift + outbox)
│   ├── preparacion_mezcla/ # flavor auxiliar, misma estructura
│   └── recargas/ incidencias/ cierre_lote/ ...
└── main_piloto.dart / main_auxiliar.dart
```

Reglas que sostienen el offline-first:

1. Los blocs leen de drift, no de la API — el repositorio expone `Stream`s de la base local; la UI reacciona igual con o sin señal.
2. Escribir = insertar local + encolar en outbox, en una transacción — el bloc nunca espera a la red para confirmar una acción.
3. El motor de sync vive en `nucleo/`, sin BLoC — corre por conectividad, apertura de app y botón manual; publica su estado por un `Stream` que un `SyncCubit` expone a la UI.
4. Cubit para pantallas simples (lista/detalle); Bloc para flujos con secuencia y transiciones (checklist de mezcla, sesión).
5. DI con `get_it` (registro manual; `injectable` si crece). `domain/` de cada feature es Dart puro, testeable sin emulador.

`piloto/` y `auxiliar/` no se importan entre sí; solo importan `nucleo/`.

## Alternativas descartadas

Ninguna evaluada formalmente — BLoC se adoptó directamente por el encaje entre sus estados explícitos y los flujos de checklist/sesión del dominio.

## Consecuencias

- Los repositorios (`SesionRepository`, etc.) son el único punto que toca drift y outbox; ningún bloc de feature accede a la base local directamente.
- El protocolo de sync completo (idempotencia por UUID, outbox, replay) está descrito en `docs/especificacion/especificacion_funcional_tecnica.md`, sección 2 — este ADR cubre solo cómo se organiza el código Flutter alrededor de él.
