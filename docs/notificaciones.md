# Notificaciones en pantalla

Módulo de recordatorios del planificador diario. Se apoya en la campanita de la cabecera
(después de la fecha y la hora), en un buzón que se despliega de derecha a izquierda y en una
bandeja completa. **La pantalla es una sola**: la persona no ve dos bandejas ni clasifica los
avisos por motor. MySQL guarda el historial y Firestore mantiene el espejo interno que alimenta el
push; ambos usan el mismo `uuid`.

- [1. Qué hace](#1-qué-hace)
- [2. Arquitectura](#2-arquitectura)
- [3. Las reglas del día](#3-las-reglas-del-día)
- [4. El buzón y la pantalla](#4-el-buzón-y-la-pantalla)
- [5. Rutas y respuestas](#5-rutas-y-respuestas)
- [6. Configuración](#6-configuración)
- [7. Comandos y programación](#7-comandos-y-programación)
- [8. Datos y migraciones](#8-datos-y-migraciones)
- [9. Pruebas](#9-pruebas)
- [10. Problemas frecuentes](#10-problemas-frecuentes)

---

## 1. Qué hace

Al comenzar el día, y mientras el día transcurre, el sistema revisa el diario de **hoy**:

1. Si alguno de los tres objetivos principales (**Debo hacer**, **Quiero hacer**, **Algo para mí**)
   no tiene su marca, genera un recordatorio para cumplirlo. Los que ya están marcados no se
   notifican.
2. Si una franja del horario ya pasó su hora (más un margen de gracia) y sigue sin marcarse,
   genera un recordatorio para realizar esa tarea. Las franjas futuras y las ya marcadas no se
   notifican.
3. Si el día transcurre y no hay diario cargado, avisa que falta crearlo (se puede apagar).

El recordatorio llega a la campanita en tiempo real, suena, se puede abrir en un modal y, al
cerrarlo, queda marcado como leído. El estado se replica al espejo de Firestore para que el push
vea lo mismo.

## 2. Arquitectura

```
                   ┌──────────────────────────────────────────────┐
   día que corre   │  GeneradorDeNotificaciones (reglas)          │
   (DailyPlan,     │  objetivos + franjas + diario ausente        │
    PlanGoal,      └───────────────────┬──────────────────────────┘
    ScheduleEntry)                     │ Candidata (clave única)
                                       ▼
                         ┌──────────────────────────┐
                         │  BuzonDual                │
                         │  escribe y replica        │
                         └───────┬──────────┬────────┘
                                 │          │
                    ┌────────────▼──┐   ┌───▼──────────────────────┐
                    │ MySQL         │   │ Firestore (REST)         │
                    │ almacenamiento│   │ espejo para el push      │
                    │ Notificacion  │   │ NotificacionFirestore    │
                    │ BuzonMysql    │   │ BuzonFirestore           │
                    └───────┬───────┘   └───┬──────────────────────┘
                            │               │
                    campanita / bandeja ◄───┘
                    (contador + tabla, AJAX cada 15 s)
                    una sola lista, sin separar por motor
```

### SQL (MySQL)

| Pieza | Archivo |
| --- | --- |
| Migración | `database/migrations/2026_10_20_000001_create_notificaciones_table.php` |
| Modelo | `app/Models/Notificacion.php` |
| Buzón | `app/Notificaciones/BuzonMysql.php` |
| Controlador | `app/Http/Controllers/NotificacionController.php` |
| Vistas | `resources/views/notificaciones/index.blade.php` y parciales |
| Seeder | `database/seeders/NotificacionSeeder.php` |
| Rutas | `routes/web.php`, prefijo `/notificaciones` |

### NoSQL (Firestore)

| Pieza | Archivo |
| --- | --- |
| Modelo de documento | `app/Models/NotificacionFirestore.php` |
| Buzón | `app/Notificaciones/BuzonFirestore.php` |
| Cliente REST y códec | `app/Notificaciones/Firestore/ClienteFirestore.php`, `ValoresFirestore.php` |
| Seeder | `database/seeders/NotificacionFirestoreSeeder.php` |
| Migraciones | No aplican: la colección se crea sola al guardar el primer documento |
| Controlador y vista | No tiene: se alimenta desde el mismo buzón y no se muestra aparte |

**Firestore no es una bandeja visible.** Es el espejo que el push consulta y que guarda el mismo
documento con el mismo `uuid` y los mismos campos. La persona trabaja siempre sobre la única
bandeja; el panel y la página no separan MySQL de Firestore.

**¿Por qué REST y no el SDK?** `kreait/firebase-php` está instalado, pero Firestore requiere
`google/cloud-firestore`, que en su versión actual exige `ext-grpc` y un PHP más nuevo que el 8.2
del servidor. El cliente propio usa `google/auth` (ya presente) para pedir el token de la cuenta de
servicio y llama a `firestore.googleapis.com`. No hay que tocar Composer ni instalar extensiones.
La colección se llama `notificaciones` y el id de cada documento es el `uuid` de la fila SQL.

## 3. Las reglas del día

`app/Notificaciones/GeneradorDeNotificaciones.php` no guarda nada: calcula una lista de
`Candidata`. `BuzonDual` decide qué falta guardar.

### Regla 1 · Objetivos principales

- Se revisan las tres ranuras (`PlanGoal::SLOT_MUST`, `SLOT_WANT`, `SLOT_FOR_ME`).
- Una ranura **sin marcar** (`is_done = false`) genera un aviso con la clave
  `objetivo:{plan}:{ranura}:{fecha}`.
- Una ranura marcada no genera nada.
- Si una ranura no tiene descripción (dato viejo o incompleto), igual se avisa: el objetivo está
  pendiente.

### Regla 2 · Franjas del horario

- Se revisan las franjas de **hoy** con actividad escrita.
- Si la hora de inicio + `margen_minutos` ya pasó y `is_done = false`, se genera un aviso con la
  clave `franja:{schedule_entry}:{fecha}`.
- Las franjas futuras, las marcadas y las que no tienen actividad no se notifican.
- La prioridad sube a `alta` si la franja lleva dos horas o más sin marcarse.

### Regla 3 · Día sin diario

- Si no hay `DailyPlan` para hoy y ya pasó `hora_diario_ausente` (por defecto 08:00), se genera un
  único aviso con la clave `diario-ausente:{fecha}`.
- Se puede apagar con `NOTIFICACIONES_AVISAR_DIARIO_AUSENTE=false`.

### Sin duplicados

La columna `clave` es única. La primera vez que una regla se cumple se crea el aviso; las
siguientes evaluaciones del mismo día encuentran la clave y no crean otra fila. Si el aviso se
archiva, la fila queda con `descartada_en` y la clave sigue reservada: no reaparece.

**Los avisos se resuelven solos.** Si un objetivo o una franja se marca después de haber sido
recordado, la siguiente sincronización lo marca como leído (deja de contar en la campanita). Lo
mismo pasa con el aviso de diario ausente en cuanto el diario del día existe. El aviso queda en el
historial como leído; no se archiva solo.

Las fechas se calculan en la zona de la aplicación (`APP_TIMEZONE`, por defecto
`America/Caracas`).

## 4. El buzón y la pantalla

- **Campanita** en la cabecera, después de la fecha y la hora. Muestra el globo naranja con las no
  leídas y se mece cuando llega algo nuevo.
- **Panel** con el componente Offcanvas de Bootstrap, que se desliza de derecha a izquierda como
  el menú del sistema (`#buzonNotificaciones`).
- **Una sola lista** con DataTables: no hay pestañas ni dos bandejas. Cada fila muestra el tipo,
  el título, un resumen y la fecha/hora.
- **Columnas**: checkbox de selección, notificación y fecha/hora.
- **Acciones por lote**: marcar leídas y archivar las filas seleccionadas.
- **Modal de Bootstrap** al tocar una fila. Al cerrarlo, la notificación se marca como leída; el
  globo, la bandeja y el espejo interno se actualizan.
- **Sonido**: un timbre corto de dos notas sintetizado con Web Audio (no requiere archivos). Se
  activa o silencia desde el pie del panel y la preferencia queda en `localStorage`.
- **Avisos del escritorio**: opcionales; piden permiso al navegador y muestran una notificación del
  sistema cuando la pestaña está en segundo plano.
- **Tarjetas emergentes**: una por lote, con “+N más” cuando llegan varias a la vez.
- **Bandeja completa**: `/notificaciones`, con filtros por estado, tipo y texto.

El JavaScript vive en `public/js/notificaciones.js`; los estilos, en
`public/css/notificaciones.css`. La configuración (rutas, intervalo, sonido) se lee de los
atributos `data-*` de `#campanaNotificaciones`, no está escrita a mano en el JS.

### Tiempo real

- El navegador consulta `/notificaciones/contador` cada `NOTIFICACIONES_INTERVALO_MS` (15 s por
  defecto). Cada respuesta trae las no leídas, el total y las últimas cinco.
- El contador aprovecha para evaluar las reglas, como máximo una vez cada
  `NOTIFICACIONES_SEGUNDOS_ENTRE_GENERACIONES` (55 s) mediante una clave de caché. Así el aviso
  aparece aunque el cron no esté corriendo.
- Cuando la pestaña está oculta no se consulta; al volver, se consulta de inmediato.
- El comando `notificaciones:generar` hace el mismo trabajo cada minuto sin depender del navegador.

## 5. Rutas y respuestas

Todas pasan por el middleware `web` (sesión y CSRF) y devuelven el sobre
`{ ok, message, data }` / `{ ok, message, errors }`.

| Método | URI | Nombre | Qué hace |
| --- | --- | --- | --- |
| GET | `/notificaciones` | `notificaciones.index` | Bandeja única |
| GET | `/notificaciones/contador` | `notificaciones.contador` | Evalúa reglas (con caché) y devuelve los números |
| GET | `/notificaciones/tabla` | `notificaciones.tabla` | JSON de la tabla (`estado`, `tipo`, `q`) |
| GET | `/notificaciones/estado` | `notificaciones.estado` | Diagnóstico interno de MySQL y Firestore |
| POST | `/notificaciones/sincronizar` | `notificaciones.sincronizar` | Evalúa las reglas ahora |
| PATCH | `/notificaciones/lote/leidas` | `notificaciones.lote.leidas` | Marca un lote (`uuids[]`) |
| DELETE | `/notificaciones/lote` | `notificaciones.lote.descartar` | Archiva un lote |
| GET | `/notificaciones/{uuid}` | `notificaciones.mostrar` | Detalle para el modal |
| PATCH | `/notificaciones/{uuid}/leida` | `notificaciones.leida` | Marca una como leída |
| DELETE | `/notificaciones/{uuid}` | `notificaciones.descartar` | Archiva una |

Los `{uuid}` están restringidos con `Route::whereUuid()`.

## 6. Configuración

### `config/notificaciones.php` (`.env`)

| Variable | Por defecto | Uso |
| --- | --- | --- |
| `NOTIFICACIONES_HABILITADAS` | `true` | Interruptor general |
| `NOTIFICACIONES_MARGEN_MINUTOS` | `10` | Gracia antes de recordar una franja |
| `NOTIFICACIONES_AVISAR_DIARIO_AUSENTE` | `true` | Regla 3 |
| `NOTIFICACIONES_HORA_DIARIO_AUSENTE` | `08:00` | Desde qué hora se avisa que falta el diario |
| `NOTIFICACIONES_SEGUNDOS_ENTRE_GENERACIONES` | `55` | Frecuencia mínima de evaluación desde el navegador |
| `NOTIFICACIONES_INTERVALO_MS` | `15000` | Sondeo de la campanita |
| `NOTIFICACIONES_SONIDO` | `true` | Sonido inicial (luego manda la preferencia guardada) |
| `NOTIFICACIONES_ESCRITORIO` | `false` | Avisos del escritorio iniciales |
| `NOTIFICACIONES_LIMITE_LISTADO` | `300` | Filas máximas del listado |
| `NOTIFICACIONES_MAXIMO_LOTE` | `100` | Tope de las acciones por lote |

### `config/firebase.php` (`.env`)

| Variable | Por defecto | Uso |
| --- | --- | --- |
| `FIREBASE_CREDENTIALS` | `firebase-credentials.json` | JSON de la cuenta de servicio en la raíz |
| `FIREBASE_PROJECT_ID` | vacío | Si se omite, se toma del JSON |
| `FIREBASE_COLECCION_NOTIFICACIONES` | `notificaciones` | Colección del espejo |
| `FIREBASE_HABILITADO` | `true` | Si es `false`, sólo se usa MySQL |
| `FIREBASE_TIEMPO_ESPERA` | `15` | Segundos por llamada |
| `FIREBASE_SEGUNDOS_DIAGNOSTICO` | `300` | Caché del diagnóstico que muestra el panel |

Si Firestore falla, el buzón **no se rompe**: la fila SQL guarda el error en `error_firestore`, la
pantalla avisa que el espejo está caído y la siguiente sincronización vuelve a intentarlo.

## 7. Comandos y programación

```bash
# Evaluar las reglas ahora
php artisan notificaciones:generar

# Probar otro momento del día sin esperar
php artisan notificaciones:generar --ahora="2026-10-20 10:30:00"

# Notificaciones de ejemplo en MySQL + Firestore
php artisan db:seed --class=NotificacionSeeder

# Sólo en Firestore (para probar el espejo/push sin tocar MySQL)
php artisan db:seed --class=NotificacionFirestoreSeeder

# Volver a copiar todas las notificaciones vigentes al espejo de Firestore
php artisan notificaciones:generar --todo
```

`routes/console.php` agenda `notificaciones:generar` **cada minuto** con
`withoutOverlapping()`. En Linux o un hosting con cron alcanza con el `schedule:run` habitual.

En Windows (XAMPP) el Programador de tareas puede ejecutar cada minuto:

```
C:\xampp\php\php.exe artisan notificaciones:generar
```

El sondeo del navegador cubre el caso de que esa tarea no exista: con la pantalla abierta, las
reglas se evalúan igual.

## 8. Datos y migraciones

Tabla `notificaciones`:

| Columna | Tipo | Para qué |
| --- | --- | --- |
| `id` | bigint | Clave local |
| `uuid` | uuid único | Identidad compartida con Firestore |
| `clave` | string único | Huella de la regla (antiduplicados) |
| `tipo` | string | `objetivo`, `franja`, `diario_ausente`, `sistema` |
| `titulo`, `mensaje` | string/text | Contenido |
| `prioridad` | string | `alta`, `media`, `baja` |
| `fecha_referencia` | date | Día del diario |
| `fecha_hora` | datetime | Momento del aviso |
| `leida_en`, `descartada_en` | timestamp | Estado del aviso |
| `daily_plan_id`, `plan_goal_id`, `schedule_entry_id` | FK opcional | Origen del recordatorio |
| `meta` | JSON | Ranura, hora, actividad, retraso… |
| `sincronizada_en`, `error_firestore` | timestamp/text | Estado del espejo |

En Firestore el documento usa los mismos nombres de campo; `fecha_referencia` se guarda como texto
(`AAAA-MM-DD`) y las demás fechas como `timestamp`. El id del documento es el `uuid`.

## 9. Pruebas

```bash
php artisan test --filter="Notificaciones|ValoresFirestore"
```

- `tests/Feature/NotificacionesTest.php`: reglas 1, 2 y 3, margen de gracia, diario ausente,
  idempotencia, contador, tabla, marcado individual y por lote, archivado y páginas.
- `tests/Feature/NotificacionesFirestoreTest.php`: cliente y buzón NoSQL con la API falsificada,
  commit por lote y marcado cruzado Firestore → MySQL.
- `tests/Unit/ValoresFirestoreTest.php`: ida y vuelta de tipos (texto, entero, decimal, booleano,
  nulo, fecha, mapa, lista y vacíos).

Firestore se apaga en las pruebas de MySQL (`firebase.habilitado = false`) y se falsifica con
`Http::fake()` en las del espejo; ninguna prueba sale a internet ni usa las credenciales reales.

## 10. Problemas frecuentes

**“No se encontró firebase-credentials.json”.** El archivo debe estar en la raíz del proyecto y
tener `private_key`, `client_email` y `project_id`. La ruta se puede cambiar con
`FIREBASE_CREDENTIALS`.

**“Firestore respondió 403 / 404”.** La cuenta de servicio necesita permiso sobre Firestore y el
proyecto debe tener la base `(default)` creada. El diagnóstico del panel muestra el mensaje exacto.

**No aparece ninguna notificación.** Revisa que haya un diario para **hoy**, que la hora de la
franja ya haya pasado (más el margen) y que la campanita esté consultando. Puedes forzar la
evaluación con `php artisan notificaciones:generar` o con el botón **Revisar ahora** del panel.

**El sonido no suena.** Los navegadores no permiten audio hasta que la persona interactúa con la
página: el primer clic o tecla lo habilita. Revisa también el interruptor **Sonido** del panel.

**El espejo quedó desactualizado.** Ejecuta `php artisan notificaciones:generar --todo` para volver a
copiar todas las notificaciones vigentes de MySQL a Firestore. No hay una bandeja separada que
reconstruir desde la pantalla: el buzón es uno solo.

**Errores intermitentes de memoria en XAMPP.** Si Apache registra `VirtualAlloc() failed` o
`Out of memory`, el problema es del proceso (límite de memoria o archivo de paginación), no del
módulo. Reinicia Apache y evita muchas pestañas o automatizaciones simultáneas; el sondeo del
buzón es liviano y no hace escrituras salvo que haya avisos nuevos.
