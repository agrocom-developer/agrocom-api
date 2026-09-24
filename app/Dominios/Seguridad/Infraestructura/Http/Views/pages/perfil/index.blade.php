{{--
    Page: perfil/index (GET/PUT /panel/perfil, panel.perfil.edit/update)
    Tarea 66: autoservicio del guard `interno` — correo y cambio de contraseña
    propio (contraseña actual + nueva + confirmación). Un solo `<form>`, sin
    selector de tipo ni de roles (a diferencia de
    `usuarios/_formulario.blade.php`, que administra cuentas AJENAS): el
    sujeto es siempre quien está logueado.

    El NOMBRE se muestra pero no se edita (9/9/2026, decisión del dueño): la
    bitácora guarda solo `user_id` y resuelve el nombre del actor por join
    contra el valor vigente, así que renombrarse reescribía la autoría de
    toda la historia. Lo cambia un administrador desde Seguridad › Usuarios,
    donde el cambio queda auditado con autor. El `readonly` de acá es la
    mitad visible; la server-side es que `ActualizarPerfilRequest` ya no
    declara `name` y el controlador usa `validated()`.

    `password_actual`/`password`/`password_confirmation` NUNCA se repueblan
    con `old()` — igual criterio que el resto del panel con contraseñas.

    Además de la cuenta, la página muestra (solo lectura):
    - $accesos (list<{href, icono, titulo, meta}>): los accesos rápidos del
      rol activo, sacados de su menú (`Presentacion/AccesosRapidos`). Vacío =
      no hay sección.
    - $persona: los datos de la persona vinculada a la cuenta, en la columna
      lateral. Una persona no edita sus datos acá: los ve. Si su rol puede
      editar personas (`personal.persona.editar`), la tarjeta ofrece ir a
      Personal; si no, dice que lo corrige un administrador. Sin persona
      vinculada, un estado vacío lo explica.
--}}
<x-templates.panel-shell :title="__('seguridad.perfil.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('seguridad.perfil.titulo')"
    >
        @php
            $email = old('email', $usuario->email ?? '');
        @endphp

        <form method="POST" action="{{ route('panel.perfil.update') }}" class="ag-perfil-form" novalidate>
            @csrf
            @method('PUT')

            <x-organisms.page-header
                :title="__('seguridad.perfil.titulo')"
                :subtitle="__('seguridad.perfil.subtitulo_panel')"
            >
                <x-slot:actions>
                    <x-atoms.button type="submit" variant="primary">
                        {{ __('ui.action.save') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($accesos !== [])
                <section class="ag-perfil__accesos-seccion">
                    <x-molecules.section-head :title="__('seguridad.perfil.accesos_titulo')" />
                    <div class="ag-perfil__accesos">
                        @foreach ($accesos as $acceso)
                            <div class="ag-perfil__acceso">
                                <x-molecules.link-row
                                    :href="$acceso['href']"
                                    :icon="$acceso['icono']"
                                    :title="$acceso['titulo']"
                                    :meta="$acceso['meta']"
                                />
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <x-molecules.form-layout>
                <x-molecules.form-section :title="__('seguridad.perfil.seccion_datos')">
                    {{-- Sin `name`: no viaja en el POST y no hay nada que
                         `old()` tenga que repoblar, así que el `id` va explícito
                         (de ahí sale el `for` del label). `readonly` y no
                         `disabled` para que siga siendo enfocable, copiable y
                         legible por un lector de pantalla. --}}
                    <x-atoms.input
                        type="text"
                        id="perfil-nombre"
                        :label="__('seguridad.perfil.campo_name')"
                        :value="$usuario->name"
                        :help="__('seguridad.perfil.campo_name_ayuda')"
                        readonly
                    />

                    <x-atoms.input
                        type="email"
                        name="email"
                        :label="__('seguridad.perfil.campo_email')"
                        :value="$email"
                        :error="$errors->first('email')"
                    />
                </x-molecules.form-section>

                <x-molecules.form-section :title="__('seguridad.perfil.seccion_password')">
                    <p class="ag-form-section__field--full">{{ __('seguridad.perfil.seccion_password_ayuda') }}</p>

                    <x-atoms.input
                        type="password"
                        name="password_actual"
                        :label="__('seguridad.perfil.campo_password_actual')"
                        :error="$errors->first('password_actual')"
                    />

                    <x-atoms.input
                        type="password"
                        name="password"
                        :label="__('seguridad.perfil.campo_password_nueva')"
                        :help="__('seguridad.perfil.campo_password_nueva_ayuda')"
                        :error="$errors->first('password')"
                    />

                    <x-atoms.input
                        type="password"
                        name="password_confirmation"
                        :label="__('seguridad.perfil.campo_password_confirmacion')"
                    />
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('seguridad.perfil.estado_form')">
                    <x-slot:actions>
                        <x-atoms.button type="submit" variant="primary">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.form-actions-bar>

                <x-slot:aside>
                    @if ($persona['tieneDatos'])
                        <x-molecules.summary-card :title="$persona['titulo']" :items="$persona['items']">
                            @if ($persona['editarHref'])
                                <x-slot:action>
                                    <x-atoms.button :href="$persona['editarHref']" variant="outline" icon="edit" block>
                                        {{ __('seguridad.perfil.persona_editar') }}
                                    </x-atoms.button>
                                </x-slot:action>
                            @endif
                        </x-molecules.summary-card>
                        <p class="ag-perfil__nota">{{ $persona['nota'] }}</p>
                    @else
                        <x-molecules.empty-state
                            icon="badge"
                            :title="$persona['vacioTitulo']"
                            :detail="$persona['vacioDetalle']"
                        />
                    @endif
                </x-slot:aside>
            </x-molecules.form-layout>
        </form>
    </x-templates.panel-layout>
</x-templates.panel-shell>
