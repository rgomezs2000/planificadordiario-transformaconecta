# Planificador Diario "Transforma-Conecta"

Sistema de Planificación Personal del **Programa de Desarrollo Personal "Transforma-Conecta"**.

> **ORGÁNIZATE · ACTÚA · AVANZA**

Aplicación web hecha con **Laravel 12** que digitaliza la hoja del planificador diario del
programa: el registro del día (objetivos, horario, checklist, procrastinación, bloque de acción
y cierre), su consulta con buscador y filtros por período, la generación de documentos (PDF del
día, imagen para compartir, reporte detallado en Excel y resumen de desempeño en PDF con
gráficos) y la **carga periódica de días desde un libro de Excel**, que el sistema procesa solo
todas las noches.

| | |
| --- | --- |
| **Versión** | **1.5** — planificación periódica (sobre la base 1.0 MVP y la 1.02 de manejo de errores) |
| **Nombre en pantalla** | *Mi Planificador Diario* |
| **Tipo** | Aplicación web monolítica, de un solo usuario, sin autenticación |
| **Estado** | Funcionando y en uso |
| **Rama** | `main` |

**Cómo abrirlo en local (XAMPP):**

```
http://localhost:8088/planificadordiario-transformaconecta/
```

> Este documento es el manual completo del sistema: qué hace, cómo está construido, cómo se
> instala y cómo se usa. Si vas a ponerlo en marcha por primera vez, ve directo a
> [§13 Instalación](#13-instalación-y-puesta-en-marcha); si solo quieres usarlo, a
> [§14 Guía de uso](#14-guía-de-uso).
>
> La licencia del sistema está en [LICENSE](LICENSE) (The Unlicense, dominio público) y se
> resume en [§22 Créditos y licencia](#22-créditos-y-licencia).

---

## Índice

1. [Ficha del proyecto](#1-ficha-del-proyecto)
2. [Qué es el sistema](#2-qué-es-el-sistema)
3. [Las ocho secciones del día](#3-las-ocho-secciones-del-día)
4. [Módulos](#4-módulos)
   - [4.1 Inicio y menú principal](#41-inicio-y-menú-principal)
   - [4.2 Formulario del diario](#42-formulario-del-diario)
   - [4.3 Listado, búsqueda y filtros](#43-listado-búsqueda-y-filtros)
   - [4.4 Línea de tiempo del día](#44-línea-de-tiempo-del-día)
   - [4.5 Documentos del día: PDF e imagen](#45-documentos-del-día-pdf-e-imagen)
   - [4.6 Reporte detallado en Excel](#46-reporte-detallado-en-excel)
   - [4.7 Resumen de desempeño en PDF](#47-resumen-de-desempeño-en-pdf)
   - [4.8 Planificación periódica](#48-planificación-periódica)
   - [4.9 Catálogos](#49-catálogos)
   - [4.10 Página de errores y bitácora](#410-página-de-errores-y-bitácora)
   - [4.11 Front-end y plantilla base](#411-front-end-y-plantilla-base)
5. [Rutas y parámetros](#5-rutas-y-parámetros)
6. [Flujos paso a paso](#6-flujos-paso-a-paso)
7. [Reglas de negocio y validaciones](#7-reglas-de-negocio-y-validaciones)
8. [Motor de análisis del desempeño](#8-motor-de-análisis-del-desempeño)
9. [Modelo de datos](#9-modelo-de-datos)
10. [Arquitectura y decisiones técnicas](#10-arquitectura-y-decisiones-técnicas)
11. [Estructura del proyecto](#11-estructura-del-proyecto)
12. [Stack tecnológico y requisitos](#12-stack-tecnológico-y-requisitos)
13. [Instalación y puesta en marcha](#13-instalación-y-puesta-en-marcha)
14. [Guía de uso](#14-guía-de-uso)
15. [Configuración del entorno](#15-configuración-del-entorno)
16. [Seguridad](#16-seguridad)
17. [Mantenimiento](#17-mantenimiento)
18. [Pruebas](#18-pruebas)
19. [Solución de problemas](#19-solución-de-problemas)
20. [Glosario](#20-glosario)
21. [Hitos de versiones, estado y hoja de ruta](#21-hitos-de-versiones-estado-y-hoja-de-ruta)
22. [Créditos y licencia](#22-créditos-y-licencia)

---

## 1. Ficha del proyecto

| Dato | Valor |
| --- | --- |
| Nombre del sistema | Planificador Diario "Transforma-Conecta" |
| Nombre en pantalla | Mi Planificador Diario |
| Versión actual | **1.5** — planificación periódica (sobre 1.0 MVP y 1.02) |
| Programa | Programa de Desarrollo Personal "Transforma-Conecta" |
| Lema | ORGÁNIZATE · ACTÚA · AVANZA |
| Tipo | Web monolítica (Laravel 12), renderizada en servidor, con mejoras por AJAX |
| Usuarios | Uno solo (la persona que planifica). Sin autenticación |
| Idioma | Español (interfaz, mensajes, catálogos y documentos) |
| Zona horaria | `America/Caracas` (`APP_TIMEZONE`) |
| Base de datos | MySQL, base `planificador-diario` |
| Servidor local | XAMPP: Apache en el puerto **8088**, `DocumentRoot C:/xampp/htdocs` |
| URL local | `http://localhost:8088/planificadordiario-transformaconecta/` |
| Framework | Laravel 12 |
| Front-end | Bootstrap 5, Bootstrap Icons, jQuery, DataTables, bootbox y datepicker por CDN; CSS y JS propios en `public/`. Sin NPM ni compilación |
| Documentos | dompdf (PDF) y PhpSpreadsheet (XLSX); MuPDF (`mutool`) + GD para la imagen JPG |
| Gráficos | GD (PNG en el servidor) y JavaScript propio (línea de tiempo en el navegador) |
| Tareas programadas | `planificacion:importar` todos los días a las 00:00 |
| Pruebas | PHPUnit sobre SQLite en memoria: 29 pruebas |
| Licencia | **The Unlicense** (dominio público) — ver [LICENSE](LICENSE) |

---

## 2. Qué es el sistema

### 2.1 Qué es

Es la **versión digital de la hoja de planificación diaria** que la persona participante del
programa completa cada día. La hoja de papel tiene ocho bloques; el sistema los reproduce como
un formulario web, los guarda en una base de datos y agrega lo que el papel no puede hacer:
buscador, filtros por período, estadísticas de desempeño, gráficos y documentos en PDF y Excel.

No es un gestor de tareas genérico ni un calendario: es el registro de un ritual diario de
organización personal, con una estructura fija y deliberada.

### 2.2 La idea central

1. **Planificar el día antes de vivirlo**: fecha, energía disponible, tres objetivos, el horario
   con sus actividades y el checklist de lo que hace falta tener listo.
2. **Acompañarse durante el día**: cuando aparece la procrastinación, cinco preguntas guía;
   cuando cuesta arrancar, un "bloque de acción" corto (5, 10, 15 o 20 minutos) con un resultado
   esperado.
3. **Cerrar el día con intención**: qué se logró, qué quedó pendiente y cuándo se hará, y de qué
   se siente orgullosa la persona.
4. **Ver la evolución**: leer los días en conjunto para descubrir qué se sostiene, qué se
   posterga, cómo influye la energía y dónde la procrastinación empieza a costar rendimiento.

### 2.3 Problema que resuelve

- La hoja en papel **se pierde o se archiva mal**: no se puede consultar meses después.
- Revisar "cómo me fue este mes" a mano exige **sumar y comparar decenas de hojas**.
- No hay forma práctica de **cruzar la energía con el cumplimiento** ni de detectar **en qué
  franja o en qué actividad** se cae siempre.
- Los reportes de acompañamiento obligan a **rehacer el trabajo de transcripción**.
- Cuando el programa trabaja con varias personas, **cargar los días una por una** en la interfaz
  no es viable: de ahí la carga periódica desde Excel ([§4.8](#48-planificación-periódica)).

### 2.4 Principios de diseño

| Principio | Cómo se expresa |
| --- | --- |
| Fidelidad a la hoja impresa | Las secciones, sus títulos y sus colores siguen el código de color de la hoja: naranja objetivos/procrastinación, turquesa preparación/bloque de acción, azul horario, rojo cierre |
| Un dato se carga una vez | El día de la semana (L M M J V S D) se deduce de la fecha; no se guarda ni se pide |
| Nada se pierde | Todo se guarda en transacción; los documentos se generan desde la base |
| El idioma es del usuario | Fechas, meses, días y mensajes en español, escritos en el código (no dependen de `intl` ni del locale del servidor) |
| Tono neutro | El resumen describe los registros, no juzga a la persona: "aspectos a mejorar", nunca "lo que haces mal" |
| Sin dependencias innecesarias | El front no usa NPM, Vite, Tailwind, Vue ni React: todo por CDN y assets propios |
| Lo que ya está guardado manda | La carga desde Excel **solo crea días que faltan**: nunca pisa un diario existente |

---

## 3. Las ocho secciones del día

El formulario, el PDF del día y el Excel respetan este orden y estos colores.

| # | Sección (título en la hoja) | Contenido | Color | Obligatoria |
| --- | --- | --- | --- | --- |
| 1 | Cabecera del día | Fecha · Día de la semana · Mi energía hoy | Neutro | Sí (fecha y energía) |
| 2 | **Mis 3 objetivos principales de hoy** | 1 Debo hacer · 2 Quiero hacer · 3 Algo para mí, cada uno con "Cumplido" | Naranja | Sí (las tres descripciones) |
| 3 | **Antes de empezar** | ¿Qué necesito tener listo? Checklist con descripción libre por ítem | Turquesa | No |
| 4 | **Mi horario de hoy** | Hora · ¿Qué vas a hacer hoy? · cumplida | Azul | Sí (al menos una franja) |
| 5 | **Si estoy procrastinando me pregunto** | Cinco preguntas con casilla de señal y respuesta corta | Naranja | No |
| 6 | **Bloque de acción** | Voy a trabajar durante · Cuando termine este bloque · ¿En qué vas a trabajar? | Turquesa | No |
| 7 | **Cierre del día** | Lo que logré hoy · Lo que quedó pendiente · ¿Cuándo lo haré? · Hoy estoy orgulloso/a de mí porque | Rojo | Sí (los cuatro campos) |
| 8 | **Notas / recordatorios** | Texto libre multilínea | Azul oscuro | No |

---

## 4. Módulos

### 4.1 Inicio y menú principal

**Qué hace.** Portada del sistema (`/`): muestra el estado del día de hoy y los caminos
principales.

**Archivos.** `HomeController`, `resources/views/home/index.blade.php`, más la plantilla base
`resources/views/layouts/app.blade.php` (cabecera, menú lateral y pie, comunes a todo).

**Cómo funciona.**

1. `DailyPlan::findToday()` busca el día de hoy (según `APP_TIMEZONE`) con todas sus relaciones.
2. Si existe, la tarjeta turquesa muestra el resumen de avance (`progressSummary()`): objetivos
   cumplidos, porcentaje del horario, minutos de foco y si el cierre está escrito, con los
   accesos *Abrir*, *Modificar* e *Imprimir*.
3. Si no existe, invita a crearlo con el botón que va a `/diario/today` (fecha bloqueada).
4. Debajo, las tres tarjetas de acceso, las mismas del menú lateral: *Crear diario*,
   *Consultar diario* y *Planificación periódica* (esta última lleva al módulo que monta el libro
   del período).
5. El menú lateral tiene dos secciones: **Mi diario** (Inicio, Crear diario, Consultar diario) y
   **Planificación** (Planificación periódica, [§4.8](#48-planificación-periódica)).

### 4.2 Formulario del diario

**Qué hace.** Un solo formulario para tres modos: **crear**, **ver** (solo lectura) y
**modificar**. Reproduce las ocho secciones de la hoja.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `DailyController` | Formulario en blanco (`/diario`) y con la fecha de hoy bloqueada (`/diario/today`) |
| `DailyPlanController` | Crear, ver, modificar y eliminar el día |
| `Concerns/DatosDelFormulario` | Catálogos activos y datos comunes de la vista (incluye los tramos del gráfico) |
| `resources/views/diario/formulario.blade.php` | La vista de los tres modos |
| `public/js/script.js` | Fecha y día, filas del horario, casillas, validación y guardado |

**Cómo funciona.**

1. Los catálogos se cargan con `scopeActive()`. El modo cambia el método HTTP, la acción del
   formulario, si la fecha está bloqueada y si los campos son editables (`readonly` para textos,
   `disabled` para casillas y radios).
2. **Mis 3 objetivos**: tres ranuras fijas; cada una lleva su `slot` y su `goal_type_id` en
   campos ocultos, la descripción y la casilla *Cumplido*.
3. **Antes de empezar**: los ítems del catálogo; al marcar un ítem se habilita su descripción
   libre ("PC, cuaderno, calculadora").
4. **Mi horario de hoy**: tabla dinámica (agregar y quitar franjas) con hora libre por fila,
   actividad y casilla de cumplida, más un "marcar todas". Cada cambio redibuja el gráfico.
5. **Bloque de acción**: duración del catálogo, resultado esperado y tarea. El formulario siempre
   envía `action_blocks[0]`; si viene vacío, **no se crea una fila fantasma**.
6. **Cierre del día** y **Notas**: los cuatro campos del cierre y un textarea de notas (cada
   línea no vacía se guarda como una nota con su orden).
7. Al guardar: confirmación con bootbox → AJAX con el testigo CSRF → validación en el servidor →
   alerta de éxito → redirección (al inicio si era nuevo, al listado si era una modificación).

**Reglas de guardado.** `DailyPlan::createDay()` / `updateDay()` corren en una transacción:
crean la cabecera, generan la estructura hija que falte (`refreshDayStructure()`, idempotente) y
sincronizan cada sección (`applyDayData()`). Si algo falla, se deshace todo y el fallo queda en
la bitácora ([§4.10](#410-página-de-errores-y-bitácora)).

### 4.3 Listado, búsqueda y filtros

**Qué hace.** `/diario/listado` muestra los días registrados en una tabla DataTables y permite
buscar y filtrar. Lo que se filtra es exactamente lo que sale en el Excel y en el resumen.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `DailyPlanController@index` | La página con el miniformulario y la tabla vacía |
| `DailyPlanController@list` | `GET /diario/tabla`: los días filtrados en JSON |
| `DailyPlan::allForList()` + `scopeFiltrado()` + `scopeSearch()` | La consulta |
| `App\Filtros\Periodo` | Convierte el período elegido en un par de fechas |
| `resources/views/diario/index.blade.php`, `public/js/app.js` | Filtros, tabla y acciones |

**Búsqueda y filtros.**

| Filtro | Qué hace |
| --- | --- |
| Palabra clave | Filtra mientras se escribe (espera de 350 ms; con Enter, al instante). Busca dentro de objetivos, horario, notas, cierre y bloque de acción. Basta una letra |
| Fecha | Día exacto (dd/mm/aaaa), con calendario y marca del día de la semana |
| Energía | Baja, Media o Alta; volver a pulsar el mismo nivel lo quita |
| Período | Semana, quincena, mes, trimestre, semestre, año o rango de fechas a mano |
| Limpiar | Deja todo en blanco y vuelve a traer todos los días |

Los filtros se combinan entre sí y se aplican sin botón. En cada fila: *Modificar*, *Ver*,
*Documentos* y *Eliminar*.

### 4.4 Línea de tiempo del día

**Qué hace.** Dibuja el día como una barra de tiempo con los tramos del horario, marcando lo
cumplido, lo pendiente y los solapamientos.

- **En pantalla**: SVG dibujado por `public/js/grafico.js` a partir de los tramos que la vista
  entrega como JSON (`_grafico.blade.php`). Se redibuja solo al cambiar el horario, sin ir al
  servidor.
- **En el PDF**: el mismo gráfico, dibujado con **GD** por `App\Graficos\AgendaPng` (los datos
  los prepara `App\Graficos\AgendaDelDia`). Viaja embebido como imagen, porque el PDF no ejecuta
  JavaScript.

### 4.5 Documentos del día: PDF e imagen

**Qué hace.** Desde el listado o desde la vista de un día, el botón *Imprimir PDF/imagen* abre
un modal para elegir el formato, con la casilla opcional de marca de agua `SPECIMEN`.

| Formato | Qué se obtiene |
| --- | --- |
| **PDF** | La hoja institucional en tamaño carta, una página, lista para imprimir o archivar |
| **Imagen** | El mismo documento como JPG (si tiene una sola página) o como ZIP con una imagen por página. Pensado para compartir por mensajería |

**Archivos.** `DailyPlanController@printPdf` y `@image`, `resources/views/diario/pdf.blade.php`
(el HTML que dompdf convierte) y `App\Documentos\DocumentoDelDiario`, que arma, guarda y reutiliza
los archivos.

**Caché de documentos.** A diferencia del Excel y el resumen (que se generan siempre al vuelo),
el PDF y la imagen **se guardan** y se reutilizan:

```
storage/app/private/documentos/pdf/{id}/planificador-diario-FECHA[-muestra].pdf
storage/app/private/documentos/jpg/{id}/planificador-diario-FECHA[-muestra]-v2.jpg
storage/app/private/documentos/jpg/{id}/planificador-diario-FECHA[-muestra]-v2.zip
```

1. Si el archivo pedido ya está, se entrega tal cual.
2. Si falta la imagen, se convierte el PDF guardado; si el PDF tampoco está, se genera primero.
3. Si falta el PDF, se genera desde la base y se guarda.

La variante con marca de agua y la limpia son **dos archivos distintos**: marcar y desmarcar no
se pisan. Cuando un día se modifica o se elimina, `updateDay()` / `deleteDay()` llaman a
`DocumentoDelDiario::limpiar()` y los archivos se rehacen la próxima vez.

**Conversión a JPG.** La hace `mutool` (el ejecutable de **MuPDF**), sin Imagick ni Ghostscript:
es un binario suelto, igual en Windows y en Linux. Se instala con
`php artisan mupdf:instalar` (queda en `storage/app/private/binarios/mupdf`), o se indica su
ruta en `MUPDF_BIN`. La conversión se pide en PNG porque el `mutool` de Windows viene compilado
sin soporte JPEG, y el JPG final lo escribe GD. La resolución (150 ppp), la calidad (92) y el
enfoque suave se ajustan en `config/mupdf.php` o por `.env` (`MUPDF_RESOLUCION`, `MUPDF_CALIDAD`,
`MUPDF_ENFOQUE`).

### 4.6 Reporte detallado en Excel

**Qué hace.** Descarga un `.xlsx` con un diario por fila y todas las secciones desglosadas en
columnas, aplicando los mismos filtros del listado.

**Archivos.** `DailyPlanController@reporte`, `App\Excel\ReporteDiarioExport`,
`DailyPlan::forReport()` y `toReportArray()`.

**Cómo funciona.** Se cargan las relaciones de una vez (una consulta por relación, no por día) y
el libro se escribe **en memoria** (`Xlsx::save('php://output')` con buffer): no deja archivos
temporales. El archivo se llama `reporte-diario[-filtrado]-AAAA-MM-DD.xlsx`. Las 25 columnas
llevan el color de su sección, filtro automático, panel congelado y alto de fila calculado. Si
no hay días que coincidan, el sistema avisa con una alerta en lugar de descargar un archivo
vacío.

> **Si se agrega un ítem o una pregunta al catálogo**, hay que añadir su columna en
> `ReporteDiarioExport::COLUMNAS` **en el mismo orden** que `DailyPlan::toReportArray()`
> (ver [§17](#17-mantenimiento)).

### 4.7 Resumen de desempeño en PDF

**Qué hace.** Genera un PDF con los números del período, lecturas redactadas, tabla día por día
y tres gráficos (rendimiento contra procrastinación, por día de la semana y por energía). Con un
solo día incluye además la línea de tiempo.

**Archivos.** `DailyPlanController@resumen` (+ `agendaDelResumen()`),
`resources/views/diario/resumen.blade.php`, `App\Reportes\AnalisisDeDesempeno`,
`RedaccionDelResumen` y `GraficosDelResumen`.

**Cómo funciona.** Los mismos filtros del listado alimentan el análisis; el resumen declara su
alcance ("el período del 1 al 30 de septiembre de 2026") y explica con texto lo que no puede
afirmar. Los gráficos se dibujan con GD y viajan como imágenes embebidas. El detalle de las
fórmulas está en [§8](#8-motor-de-análisis-del-desempeño).

### 4.8 Planificación periódica

**Qué hace.** Permite cargar en el planificador **un período completo de días** desde un libro de
Excel: la persona (o quien facilita el programa) descarga la planilla, llena una hoja por día, la
monta en el módulo y el sistema crea esos días en la base **en la corrida de las 00:00**.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `App\Excel\PlantillaDelPlanificador` | Genera la planilla en blanco y conoce el mapa de la hoja |
| `App\Planificacion\ImportadorDePlanificacion` | Lee el libro y decide qué hacer con cada hoja |
| `App\Planificacion\ArchivosDePlanificacion` | Las carpetas del disco privado (`excel/...`) |
| `App\Console\Commands\GenerarPlantillaDePlanificacion` | `planificacion:plantilla` (crea la planilla genérica, la de un período y el libro de ejemplo) |
| `App\Console\Commands\ImportarPlanificacion` | `planificacion:importar` (la corrida) |
| `PlanificacionController` | El módulo: descargar la plantilla, montar el libro, quitarlo de la cola |
| `resources/views/planificacion/index.blade.php` | La pantalla del módulo |
| `PlanificacionImportacion` y `PlanificacionHoja` | El registro de cada corrida, hoja por hoja |

**La planilla.** Hay dos formas del mismo formulario, y las dos traen al final la hoja
**INSTRUCCIONES** (que la corrida **ignora por completo**: se puede dejar o borrar, el resultado
es el mismo):

| Planilla | Qué trae |
| --- | --- |
| **Genérica** (`planilla-planificador-diario.xlsx`) | La hoja **DIA**, que se duplica una vez por cada día del período y se renombra con la fecha (`2026-10-04`) |
| **De un período** (`planilla-2026-10-01_2026-10-31.xlsx`) | **Una hoja por día del período, ya nombrada con su fecha**, con la fecha puesta y una hoja oculta **LISTAS** con los días del período. No hay que duplicar ni renombrar nada: se llena y se entrega |

La del período se genera con
`php artisan planificacion:plantilla --desde=2026-10-01 --hasta=2026-10-31`, y el módulo lista
todas las que haya en `excel/formato` para descargarlas (la genérica va primero).

En cada hoja de día cada dato tiene su etiqueta en la columna B y su valor en la C (el detalle en
la D). El lector busca **por etiqueta, no por número de fila**, así que insertar una fila no rompe
la importación.

**Celdas preparadas: lo que se elige, no se escribe.** La hoja va protegida (sin contraseña) para
que las etiquetas no se muevan, y las celdas de valor quedan escribibles con sus listas puestas:

| Celda | Qué trae |
| --- | --- |
| Energía | Lista cerrada: Baja, Media, Alta |
| Todos los Sí/No | Lista cerrada: Sí, No (objetivos, franjas del horario, checklist y preguntas) |
| Hora de cada franja | Lista con las horas del horario del sistema (07:00 a 21:00). Admite otra hora escrita a mano —avisa, pero la acepta—, por ejemplo 07:30 |
| Duración y resultado del bloque | Listas con los valores del catálogo |
| Horario | **20 franjas listas** (las 15 del catálogo y 5 de más), cada una con la lista de horas y el Sí/No. Si se inserta una fila dentro del bloque, Excel le arrastra las mismas listas |
| Fecha (planilla del período) | Lista desplegable con **los días del período**, y la fecha de esa hoja ya puesta |
| Fecha (planilla genérica) | Celda preparada para escribir una fecha (`dd/mm/aaaa`): **Excel no tiene calendario desplegable en un archivo `.xlsx` normal** (eso existe en Google Sheets, o en Excel solo con macros, en un `.xlsm`). Como el nombre de la hoja es el que manda, esta celda puede quedar vacía |

> Las listas salen de los catálogos de la base: si se cambia un ítem, una pregunta, una duración o
> el horario base, hay que **regenerar la planilla** (`php artisan planificacion:plantilla`) para
> que la hoja y el sistema digan lo mismo (ver [§17.5](#175-regenerar-la-planilla-de-planificación)).

**Detalle que se paga una vez:** el atributo `showDropDown` del archivo está invertido (`1` oculta
la flecha del desplegable). PhpSpreadsheet escribe el valor negado, así que la plantilla lo pide
explícitamente: sin eso, las celdas quedan con la lista pero **sin la flecha**, y en pantalla
parecen campos comunes. Está cubierto por una prueba.

**El módulo** (`/planificacion`) ofrece: descargar cualquiera de las planillas (la genérica en
`/planificacion/plantilla` y las de período por su nombre), **montar un libro sin recargar la
página**, ver la cola con el estado y el resultado de cada uno, **eliminar de la cola uno o varios
libros a la vez** (se seleccionan con casillas, se confirma con bootbox y se van a `descartados/`),
ver el detalle de la última corrida (hoja por hoja, con el motivo de cada salto) y **quitar un libro
de la cola** desde su fila. El módulo **solo guarda**: no procesa nada.

**Todo el módulo trabaja por AJAX.** Montar el libro, quitarlo de la cola (de a uno o en lote) y
refrescar la lista no recargan la página: el formulario viaja con `FormData`, el servidor contesta
con el sobre de siempre y el bloque de la cola se vuelve a pedir a `GET /planificacion/cola`, que
devuelve el mismo HTML que se ve en pantalla (la vista parcial `planificacion/_cola`). Si algo
falla, el aviso muestra el error que devolvió el servidor (por ejemplo, si el archivo no es
`.xlsx`); si sale bien, avisa y la cola se actualiza sola. Sin JavaScript, el formulario sigue
funcionando: vuelve al módulo con el aviso en la sesión.

**La cola se mantiene al día sola.** Cada 20 segundos el módulo vuelve a pedir la cola (y también
al volver a la pestaña) y **sólo cambia la pantalla si algo cambió**: el servidor devuelve una
**huella** del bloque y, si es la misma, no se toca el DOM. El refresco **sólo mira**: no dispara la
corrida, que sigue siendo a las 00:00 y sólo a esa hora. Al cambiar la tabla se conservan los
filtros, la página y los libros que estaban seleccionados, y el cartel **En vivo** late como aviso.

**La tabla de la cola es una DataTables** (paginado 5/10/25/50/100, orden por columna, buscador y
pie de registros) con una **fila de filtros por columna**, igual que el buscador de diarios:

| Filtro | Busca en |
| --- | --- |
| Palabra clave (el buscador de DataTables) | Todo el libro: nombre, período, estado, fechas y resultado |
| Nombre del libro | Solo la columna del libro |
| Período | El período que cubre ("1 al 31 de octubre de 2026") |
| Estado | Una de las cinco etiquetas: en espera de la corrida, procesado, parcial, error o descartado (lista) |
| Cubierto hasta | La fecha hasta la que está cubierto sin huecos |
| Última corrida | La fecha y hora de la última corrida |
| Qué pasó | El resumen de la última corrida |

En **cargar el libro** hay un botón **Limpiar** que vacía el campo del archivo (se enciende solo
cuando hay algo elegido, y se apaga solo después de montar), para no tener que abrir el diálogo del
sistema otra vez.

Al eliminar, los archivos **no se borran del disco**: quedan en `excel/descartados`, así que se
pueden recuperar.

**Las carpetas** (todas dentro del disco privado, que no se publica por HTTP):

```
storage/app/private/excel/
├── formato/      la planilla en blanco que se descarga
├── jobs/         lo que se monta y lo que lee la corrida
├── procesados/   los libros cuyas fechas ya están todas cargadas
├── descartados/  los que se sacaron de la cola a mano
├── errores/      los que no se pudieron leer
└── ejemplos/     un libro de ejemplo para probar (no lo mira la corrida)
```

**La corrida (`planificacion:importar`).** Todas las noches a las 00:00. Su regla es una sola:

> **Solo se crean los días que faltan.** Un día que ya existe en la base no se vuelve a cargar
> nunca —sea pasado, de hoy o futuro— y el Excel no pisa nada de lo guardado. Si un día se
> elimina desde el listado, la corrida siguiente lo vuelve a crear con lo que traiga el libro.

Paso a paso:

1. Lee los nombres de las hojas sin abrir el libro entero.
2. La hoja `INSTRUCCIONES` se ignora; las hojas cuyo nombre no sea una fecha se reportan como
   ignoradas (por eso la hoja DIA avisa sola si alguien olvidó renombrarla).
3. Consulta en la base qué fechas del libro ya están guardadas: esas se **omiten**.
4. Solo abre las hojas que faltan y valida cada una. Si le falta un campo obligatorio (energía,
   los 3 objetivos, una franja con hora y actividad, o el cierre del día), **la hoja se salta
   completa** y queda anotada con el motivo: no se carga a medias.
5. Crea cada día con `DailyPlan::createDay()` (solo creación; el comando **no usa**
   `updateDay()`), cada hoja en su propia transacción: si una falla, el resto sigue.
6. Deja el reporte en la base y mueve el libro: si todas sus fechas quedaron cargadas pasa a
   `procesados/`; si alguna quedó saltada, se queda en `jobs/` y se reintenta en la corrida
   siguiente; si no se pudo leer, pasa a `errores/`.

**Checkpoint.** El módulo muestra hasta dónde está cubierto el período sin huecos, hasta dónde
hay días cargados por adelantado y cuántos días del período el libro no trae (aviso, no error).

**Comandos.**

```
php artisan planificacion:plantilla                              # la planilla genérica
php artisan planificacion:plantilla --desde=2026-10-01 --hasta=2026-10-31   # una hoja por día
php artisan planificacion:plantilla --ejemplo                    # además, un libro de ejemplo lleno
php artisan planificacion:importar --dry-run                     # muestra qué haría, sin escribir
php artisan planificacion:importar                               # la corrida (es la que usa el cron)
php artisan planificacion:importar --archivo=planilla-octubre.xlsx
```

`--dry-run` no escribe en la base ni mueve archivos: sirve para revisar un libro antes de
dejarlo en la cola. El detalle del horario y de su activación está en
[§13.3](#133-la-corrida-de-las-0000).

### 4.9 Catálogos

**Qué hace.** Todo lo seleccionable vive en base de datos, con orden (`sort_order`) y activación
(`is_active`): energías, tipos de objetivo, ítems de preparación, franjas horarias, duraciones y
resultados del bloque de acción, y preguntas de reflexión. Se amplían con *seeders* o SQL, sin
tocar las vistas.

`scopeActive()` filtra los activos y los ordena. **Desactivar un ítem lo retira del formulario
sin perder el histórico** (los días que ya lo usaban conservan su referencia). El contenido
sembrado está en [§9.3](#93-catálogos).

### 4.10 Página de errores y bitácora

**Qué hace.** Muestra una página de error propia para los códigos 3xx, 4xx y 5xx, y deja el
detalle de cada fallo en un log diario.

**Archivos.** `App\Errores\CatalogoDeErrores` (texto, icono y color de cada familia y código),
`App\Errores\RegistroDeErrores` (arma y escribe la entrada; devuelve el **código de incidente**),
`ErrorController`, `resources/views/errores/index.blade.php` y los dos manejadores de
`bootstrap/app.php`.

**Cómo funciona.**

| Dirección | Qué muestra |
| --- | --- |
| `/error/{codigo}` | El aviso institucional del código, agrupado por familia: **300** redirecciones (azul), **400** errores del cliente (naranja), **500** errores del servidor (rojo). Responde con **el mismo estado** que explica (`/error/404` contesta 404) |
| Cualquier dirección inventada | La misma página, con el 404 real |
| Un 500 | La página del sistema solo con `APP_DEBUG=false`; con la depuración encendida se conserva la pantalla de Laravel para no perder el detalle mientras se desarrolla |

Lo que **no** cambia: las peticiones que esperan JSON (DataTables, guardado, reporte y resumen)
siguen recibiendo JSON, y la validación conserva su camino.

**Bitácora.** Cada error queda en `storage/logs/errores-AAAA-MM-DD.log` con su código de
incidente, el origen (`manejador`, `controlador` o `modelo`), el código y la familia HTTP, la
excepción, el mensaje, el archivo y la línea, la petición (método, URL, ruta, IP, navegador y
referencia) y las 12 primeras líneas de la traza. Los 4xx se anotan como `WARNING` y los 5xx como
`ERROR`. **No se guarda el cuerpo de la petición** (el formulario tiene el contenido del diario)
y el código que muestra la página es el mismo que encabeza la entrada del log, para poder
cruzarlos. Si el log no se puede escribir, el sistema sigue respondiendo.

### 4.11 Front-end y plantilla base

- **`layouts/app.blade.php`**: cabecera con la fecha y la hora en vivo, menú lateral (Offcanvas),
  pie y carga de las librerías por CDN. Las vistas hijas solo rellenan `@section('contenido')`.
- **`window.Planificador`** (`public/js/app.js`): `Util`, `Alerta`, `Ajax`, `Tabla`, `Impresion`,
  `Reporte`, `Formulario`, `Grafico`, `Menu` y `Reloj`. Las respuestas AJAX usan el sobre
  uniforme `{ ok, message, data }` / `{ ok, message, errors }`.
- **Sin compilación**: los assets propios se sirven desde `public/` y las librerías se cargan por
  CDN. No hay paso de `npm run build` en el despliegue.

---

## 5. Rutas y parámetros

Todas las rutas viven en `routes/web.php` y pasan por el middleware `web` (sesión y CSRF). Las de
AJAX devuelven JSON; las que se navegan, una vista. `php artisan route:list` muestra **21 rutas**:
las 20 de la aplicación y el *health check* `/up` que aporta el framework.

| Método | URI | Nombre | Acción | Respuesta |
| --- | --- | --- | --- | --- |
| GET | `/` | `home` | `HomeController@index` | Portada |
| GET | `/error/{codigo}` | `error` | `ErrorController@index` | Página de error, con el mismo estado que explica |
| GET | `/diario` | `diario.index` | `DailyController@index` | Formulario en blanco |
| GET | `/diario/today` | `diario.today` | `DailyController@today` | Formulario con la fecha de hoy bloqueada |
| GET | `/diario/listado` | `diario.listado` | `DailyPlanController@index` | Listado con filtros y tabla |
| GET | `/diario/tabla` | `diario.tabla` | `DailyPlanController@list` | **JSON** con los días filtrados |
| GET | `/diario/reporte` | `diario.reporte` | `DailyPlanController@reporte` | **XLSX** (o JSON 404 si no hay días) |
| GET | `/diario/resumen` | `diario.resumen` | `DailyPlanController@resumen` | **PDF** del resumen (o JSON 404) |
| POST | `/diario` | `diario.store` | `DailyPlanController@store` | **JSON 201** con el día creado |
| GET | `/diario/{dailyPlan}` | `diario.show` | `DailyPlanController@show` | El día en solo lectura |
| GET | `/diario/{dailyPlan}/detalle` | `diario.detail` | `DailyPlanController@detail` | **JSON** con el día completo |
| GET | `/diario/{dailyPlan}/editar` | `diario.edit` | `DailyPlanController@edit` | Formulario en modo edición |
| PUT/PATCH | `/diario/{dailyPlan}` | `diario.update` | `DailyPlanController@update` | **JSON** con el día actualizado |
| GET | `/diario/{dailyPlan}/imprimir` | `diario.print` | `DailyPlanController@printPdf` | **PDF** del día (`?marca=1` para SPECIMEN) |
| GET | `/diario/{dailyPlan}/imagen` | `diario.image` | `DailyPlanController@image` | **JPG** o **ZIP** con las páginas |
| DELETE | `/diario/{dailyPlan}` | `diario.destroy` | `DailyPlanController@destroy` | **JSON** con el resultado |
| GET | `/planificacion` | `planificacion.index` | `PlanificacionController@index` | Módulo de planificación periódica |
| GET | `/planificacion/plantilla/{archivo?}` | `planificacion.plantilla` | `PlanificacionController@plantilla` | **XLSX** de la planilla (genérica o de un período) |
| POST | `/planificacion` | `planificacion.store` | `PlanificacionController@store` | Monta un libro en la cola (**JSON 201** si la petición espera JSON; si no, redirige con el aviso) |
| GET | `/planificacion/cola` | `planificacion.cola` | `PlanificacionController@cola` | **JSON** con el HTML de la cola, su huella y los nombres en cola, para refrescarla sin recargar (**no dispara la corrida**) |
| DELETE | `/planificacion/cola/lote` | `planificacion.descartar.varios` | `PlanificacionController@descartarVarios` | **JSON**: saca de la cola los libros elegidos |
| DELETE | `/planificacion/{archivo}` | `planificacion.descartar` | `PlanificacionController@descartar` | Saca un libro de la cola (**JSON** si la petición espera JSON) |
| GET | `/up` | — | Laravel (health check) | 200 si la aplicación responde |

**Orden de las rutas.** Las que llevan identificador van al final del grupo y el parámetro está
restringido a números (`Route::whereNumber('dailyPlan')`): así `/today`, `/listado`, `/tabla`,
`/reporte` y `/resumen` no se confunden con un id. Lo mismo con `/error/{codigo}` y con el
nombre de archivo de `/planificacion/{archivo}`.

**Parámetros de los filtros** (los aceptan `diario.tabla`, `diario.reporte` y `diario.resumen`):

| Parámetro | Valores | Uso |
| --- | --- | --- |
| `fecha` | `dd/mm/aaaa` | Día exacto |
| `energia` | `baja`, `media`, `alta` | Nivel de energía |
| `palabra` | texto | Búsqueda libre (basta una letra) |
| `periodo` | `semana`, `quincena`, `mes`, `trimestre`, `semestre`, `anio`, `rango` | Tipo de período |
| `semana` | `AAAA-Wnn` | Semana ISO |
| `mes` | `AAAA-MM` | Mes (o mes de la quincena) |
| `mitad` | `1`, `2` | Primera o segunda quincena |
| `anio` | 1900-2200 | Año (o año del trimestre/semestre) |
| `trimestre` | `1`-`4` | Trimestre |
| `semestre` | `1`, `2` | Semestre |
| `desde`, `hasta` | `dd/mm/aaaa` | Rango a mano |
| `marca` | `1` | Solo en `diario.print` y `diario.resumen`: marca de agua SPECIMEN |

**Códigos de respuesta.** 200 (éxito), 201 (día creado), 404 (no existe, o reporte sin datos),
422 (validación), 500 (error inesperado, reportado al log).

---

## 6. Flujos paso a paso

### 6.1 El recorrido diario de la persona

```
Abrir /  ─►  ¿Ya existe el diario de hoy?
              │
              ├─ NO ─► «Crear el diario de hoy» → /diario/today (fecha bloqueada)
              │        1. Energía  2. Los 3 objetivos  3. Checklist «Antes de empezar»
              │        4. Horario (el gráfico se dibuja solo)  5. Procrastinación
              │        6. Bloque de acción  7. Cierre del día y notas  8. Guardar
              │
              └─ SÍ ─► «Abrir el diario de hoy» → /diario/{id}
                        · Revisar el avance y el gráfico
                        · «Modificar» si hay que ajustar algo
                        · «Imprimir PDF/imagen» para los documentos
```

### 6.2 Guardar un día nuevo

1. `GET /diario` → `datosFormulario(plan: null, modo: 'crear')` con los catálogos activos.
2. El navegador valida (`data-tf-requerido`), confirma con bootbox y envía por AJAX.
3. El controlador valida: si falla, 422 con el detalle por campo y el formulario lo pinta.
4. `DailyPlan::createDay()` (transacción): cabecera → `refreshDayStructure()` → `applyDayData()`.
5. Respuesta 201 → alerta de éxito → redirección.

### 6.3 Modificar y eliminar

- **Modificar**: `GET /diario/{id}/editar` carga el día completo y el formulario viaja con
  `_method=PUT`. El controlador comprueba primero que exista (404) y después valida (422).
  `updateDay()` aplica los cambios y **borra los documentos guardados** del día.
- **Eliminar**: confirmación con el nombre del día → `deleteDay()` (transacción). La base borra
  en cascada objetivos, franjas, bloques, notas, respuestas y el pivote; también se borran el PDF
  y la imagen guardados.

### 6.4 Consultar, filtrar y sacar documentos

1. `GET /diario/listado` → la tabla se llena por AJAX desde `diario.tabla`.
2. Al cambiar un filtro, la tabla se actualiza (y los filtros viajan en todas las peticiones).
3. **Excel**: `forReport($filtros)` → `ReporteDiarioExport::generar()` en memoria → descarga.
4. **Resumen**: `AnalisisDeDesempeno::de()` → redacción → tres gráficos PNG → HTML → dompdf.
5. **PDF del día**: `DocumentoDelDiario::asegurarPdf()` (caché) → `response()->file()` sin caché.
6. **Imagen**: `asegurarImagen()` → JPG (una página) o ZIP (varias), convertido con `mutool`.

### 6.5 Cargar un período desde Excel

```
Descargar la planilla (/planificacion/plantilla)
        │
        ▼
Duplicar la hoja DIA por cada día y renombrarla con la fecha (aaaa-mm-dd)
        │
        ▼
Llenar cada hoja (energía, 3 objetivos, horario, cierre… la hoja de INSTRUCCIONES no se toca)
        │
        ▼
Montarla en /planificacion  ──►  queda «en espera» (no se procesa nada todavía)
        │
        ▼
00:00  ──►  planificacion:importar
              · omite los días que ya están en la base
              · crea los que faltan (pasado, hoy y futuro)
              · salta las hojas incompletas y las anota con el motivo
              · mueve el libro a procesados/ o lo deja en la cola para reintentar
        │
        ▼
Revisar el reporte en el módulo (y los días en Consultar diario)
```

---

## 7. Reglas de negocio y validaciones

### 7.1 Validación del servidor (el formulario)

Definidas en `DailyPlanController::rules()` y aplicadas por `validateDay()` (con `$ignorarId`
para que, al modificar, la propia fecha no cuente como duplicada).

| Campo | Reglas |
| --- | --- |
| `plan_date` | requerido, fecha, **único** en `daily_plans.plan_date` |
| `energy_level_id` | requerido, entero, existe en `energy_levels` |
| `goals` | requerido, arreglo, **exactamente 3** |
| `goals.*.slot` | requerido, entero, entre 1 y 3, sin repetir |
| `goals.*.goal_type_id` | opcional, entero, existe en `goal_types` |
| `goals.*.description` | requerido, texto, máximo 255 |
| `goals.*.is_done` | opcional, booleano |
| `schedule` | requerido, arreglo, **mínimo 1** |
| `schedule.*.id` | opcional, entero, existe en `schedule_entries` |
| `schedule.*.schedule_slot_id` | opcional, entero, existe en `schedule_slots` |
| `schedule.*.start_time` | requerido, formato `H:i` |
| `schedule.*.activity` | requerido, texto, máximo 255 |
| `schedule.*.is_done` | opcional, booleano |
| `achievements`, `pending`, `proud_of` | requeridos, texto, máximo 2000 |
| `pending_when` | requerido, texto, máximo 255 |
| `preparation` | opcional, arreglo |
| `preparation.*.preparation_item_id` | requerido si hay arreglo, entero, existe, sin repetir |
| `preparation.*.is_checked` | opcional, booleano |
| `preparation.*.preparation_items_description` | opcional, texto, máximo 2000 |
| `reflections` | opcional, arreglo |
| `reflections.*.reflection_question_id` | requerido si hay arreglo, entero, existe, sin repetir |
| `reflections.*.is_checked` | opcional, booleano |
| `reflections.*.answer` | opcional, texto, máximo 2000 |
| `action_blocks` | opcional, arreglo |
| `action_blocks.*.id` | opcional, entero, existe en `action_blocks` |
| `action_blocks.*.action_block_duration_id` | opcional, entero, existe en el catálogo |
| `action_blocks.*.action_block_outcome_id` | opcional, entero, existe en el catálogo |
| `action_blocks.*.task` | opcional, texto, máximo 255 |
| `action_blocks.*.started_at` | opcional, fecha |
| `action_blocks.*.finished_at` | opcional, fecha, **posterior o igual** a `started_at` |
| `notes` | opcional, arreglo |
| `notes.*.content` | opcional, texto, máximo 1000 |
| `notes.*.sort_order` | opcional, entero, mínimo 0 |

Los mensajes están en español y son específicos por campo ("Ya existe un diario con esa fecha.",
"Debes registrar exactamente 3 objetivos principales.", "Agrega al menos una franja en tu horario
de hoy.", "El bloque no puede terminar antes de empezar.").

### 7.2 Validación en el navegador

`P.Formulario.validar()` revisa lo mismo antes de enviar, guiándose por `data-tf-requerido`:
`energia` (un nivel elegido), `objetivo` (los 3 textos), `hora` y `actividad` (cada franja),
`cierre` (los 4 campos). Además exige al menos una franja. Los errores se muestran bajo el
formulario, el campo se marca en rojo y el foco va al primero con problema. El formulario nace
con `novalidate` para controlar los mensajes con el estilo propio.

### 7.3 Reglas de la carga desde Excel

| Regla | Detalle |
| --- | --- |
| Fuente de la fecha | El **nombre de la hoja** (aaaa-mm-dd). La celda `FECHA` es opcional; si se escribe, tiene que coincidir con el nombre |
| Requisitos por hoja | Energía del catálogo, los 3 objetivos con descripción, al menos una franja con hora y actividad, y el cierre del día completo |
| Hoja incompleta | Se **salta entera** y queda anotada con el motivo. No se carga a medias |
| Franja a medias | Si hay hora sin actividad (o al revés), la hoja se salta |
| Celdas con lista | La energía, los Sí/No, la hora, la duración y el resultado del bloque se leen tal como los deja la lista desplegable (texto: `Media`, `Sí`, `07:00`, `20 minutos`, `Avancé`). También se aceptan los valores nativos de Excel: fecha y hora como número de serie y Sí/No como verdadero/falso |
| Tipo de objetivo | Se resuelve por el nombre de la fila (`1 · Debo hacer`) y, si no coincide, por la posición de la ranura (1, 2 o 3) |
| Secciones opcionales | Preparación, procrastinación, bloque de acción y notas se toleran: lo que no se reconozca se ignora y queda como aviso |
| Días repetidos | Si dos hojas tienen la misma fecha, la segunda se salta |
| Hojas ignoradas | `INSTRUCCIONES` se ignora siempre; cualquier nombre que no sea una fecha se reporta como ignorado |
| Días ya cargados | Se **omiten** (no se actualizan ni se pisan). Vale para pasado, hoy y futuro |
| Días eliminados | Vuelven a crearse en la corrida siguiente con lo que traiga el libro. Para que no vuelvan, hay que quitar la hoja del Excel y sacar el libro de la cola |
| Días que el libro no trae | Si un día pasado del período no está en el libro ni en la base, se informa como aviso (no es un error) |
| Estado del libro | `en espera`, `procesado` (todas sus fechas cargadas), `parcial` (quedó alguna saltada), `error` (no se pudo leer) o `descartado` |
| Solo base de datos | La corrida **no genera documentos**: el PDF y la imagen se arman cuando se piden |

### 7.4 Invariantes del modelo de datos

- **Un diario por fecha** (índice único en `plan_date`).
- **Una ranura de objetivo por día** (único `daily_plan_id` + `slot`).
- **Una franja por slot del catálogo y día**; varias franjas con `schedule_slot_id` nulo son
  válidas porque MySQL admite varios `NULL` en un índice único.
- **Una respuesta por pregunta y día**, y **una fila de preparación por ítem y día**.
- **Borrado en cascada**: eliminar un `daily_plan` limpia todos sus hijos.
- **Los catálogos no se borran**: se desactivan, así el histórico conserva sus referencias.

### 7.5 Reglas de presentación y cálculo

- El día de la semana se **deriva** de la fecha, nunca se guarda.
- Los minutos de foco del día son la **suma de las duraciones** de los bloques de acción.
- Un día está **cerrado** si tiene algo escrito en logros, pendientes u orgullo.
- En el resumen, un componente sin datos **no resta**: su peso se reparte entre los que sí tienen.
- Las comparaciones del resumen son **descriptivas del propio período** (primera mitad contra
  segunda), no proyecciones.

---

## 8. Motor de análisis del desempeño

`App\Reportes\AnalisisDeDesempeno` calcula todo lo que muestra el resumen. Solo usa lo que la
persona marcó; no infiere nada.

### 8.1 Rendimiento del día (0 a 100)

| Componente | Peso | Se mide con |
| --- | --- | --- |
| Horario | 60 | Franjas cumplidas sobre franjas totales |
| Objetivos | 30 | Objetivos cumplidos sobre objetivos totales |
| Preparativos | 10 | Ítems marcados sobre ítems del checklist |

Para cada componente **con datos** se acumula `peso × (logrado / total)`; al final se divide por
el peso realmente usado y se multiplica por 100. Si falta un componente, **su peso se reparte**
entre los demás: un diario sin checklist no queda castigado por algo que no cargó.

### 8.2 Totales del período (`global`)

Rendimiento promedio, mejor y peor; totales y porcentajes de horario, objetivos y preparativos;
total de señales de procrastinación y promedio por día; y cuántos días tienen el cierre escrito.

### 8.3 Cruces

| Cruce | Cómo se agrupa |
| --- | --- |
| `porEnergia` | Por nivel de energía: días, rendimiento promedio, % del horario y señales promedio |
| `porDiaSemana` | Por día de la semana: días y rendimiento promedio |
| `actividades` | Por nombre de actividad **normalizado**: total, hechas y porcentaje. Es lo que revela "lo que siempre se hace" y "lo que siempre se posterga" |
| `franjas` | Por franja del día: Madrugada (0-5), Mañana (6-11), Tarde (12-17), Noche (18-23) |
| `objetivos` | Por tipo de objetivo: total, cumplidos, porcentaje y hasta 6 pendientes por tipo |
| `procrastinacion` | Por pregunta: respuestas y señales; y días con y sin señales |

### 8.4 Punto crítico de procrastinación

1. Los días se agrupan por **cantidad de señales** y se calcula el rendimiento promedio de cada
   grupo (de menos a más señales).
2. `referencia` = rendimiento promedio del período.
3. `límite` = referencia **− 8 puntos** (`CAIDA_CRITICA`).
4. **A favor**: el último grupo (con más señales) que no baja del límite. **En contra**: el
   primer grupo que sí baja.
5. `caída` = diferencia de rendimiento entre los dos puntos.
6. Si no hay grupos suficientes, el resumen lo dice en lugar de forzar una conclusión.

### 8.5 Redacción y avisos

`RedaccionDelResumen` convierte los números en siete secciones: desempeño del período, aspectos
que se sostuvieron, aspectos a mejorar, metas alcanzadas, relación con la energía,
procrastinación y rendimiento, y evolución en el período. Cuando una sección no tiene material,
en lugar de quedar vacía escribe una frase que explica por qué: ese es el tono del sistema. Los
avisos de contexto (sin señales registradas, días sin franjas, días sin cierre) se agregan sin
calificar el análisis.

---

## 9. Modelo de datos

### 9.1 Diagrama entidad-relación

```text
  energy_levels                 daily_plans                    goal_types
  ┌──────────────┐        ┌───────────────────────┐       ┌──────────────┐
  │ id           │1      *│ id                    │*     1│ id           │
  │ slug (uq)    ├────────┤ plan_date (uq, date)  │       │ slug (uq)    │
  │ name         │        │ energy_level_id (FK)  │       │ name         │
  │ emoji        │        │ achievements          │       │ subtitle     │
  │ sort_order   │        │ pending               │       │ sort_order   │
  │ is_active    │        │ pending_when          │       │ is_active    │
  └──────────────┘        │ proud_of              │       └──────┬───────┘
                          │ timestamps            │              │1
                          └───┬───┬───┬───┬───┬───┘              │
                              │   │   │   │   │                  │
              ┌───────────────┘   │   │   │   └────────────┐     │
              │1                 │1  │1  │1               │1    │
              ▼*                 ▼*  ▼*  ▼*               ▼*    ▼*
        plan_goals        schedule_  plan_   action_   reflection_   (* 1..*)
        ┌──────────┐      entries    notes   blocks    answers
        │ id       │      ┌────────┐ ┌──────┐┌────────┐┌──────────────┐
        │ plan_id  │      │ id     │ │ id   ││ id     ││ id           │
        │ goal_typ │      │ plan_id│ │plan  ││plan_id ││ plan_id      │
        │ slot 1-3 │      │ slot_id│ │cont. ││dur_id  ││ question_id  │
        │ descript.│      │ start_ │ │order ││outc_id ││ is_checked   │
        │ is_done  │      │ time   │ └──────┘│ task   ││ answer       │
        │ completed│      │ activity│        │started │└──────┬───────┘
        │ uq(plan, │      │ is_done │        │finished│       │*
        │    slot) │      │ uq(plan,│        └────────┘       │1
        └──────────┘      │  slot)  │   action_block_   reflection_
                          └─────────┘   durations       questions
                    schedule_slots      action_block_
                                        outcomes

        daily_plan_preparation  (pivote N:M)
        ┌───────────────────────────────────────┐
        │ daily_plan_id  ─► daily_plans         │
        │ preparation_item_id ─► preparation_items
        │ is_checked                            │
        │ preparation_items_description         │
        │ uq(daily_plan_id, preparation_item_id)│
        └───────────────────────────────────────┘

        planificacion_importaciones ──1:N──► planificacion_hojas
        (los libros de Excel montados y lo que pasó con cada hoja)
```

### 9.2 Tablas del dominio

#### `daily_plans` — tabla eje

Un registro por día planificado: cabecera + cierre del día.

| Columna | Tipo | Notas |
| --- | --- | --- |
| `id` | bigint PK | |
| `plan_date` | date, **único** | Un solo diario por fecha. El nombre del día no se guarda: se deduce |
| `energy_level_id` | FK `energy_levels`, nullable | `nullOnDelete` |
| `achievements` | text, nullable | "Lo que logré hoy" |
| `pending` | text, nullable | "Lo que quedó pendiente" |
| `pending_when` | text, nullable | "¿Cuándo lo haré?" |
| `proud_of` | text, nullable | "Hoy estoy orgulloso/a de mí porque" |
| `created_at`, `updated_at` | timestamps | |

#### Hijas del día

| Tabla | Columnas | Reglas y relaciones |
| --- | --- | --- |
| `plan_goals` | `id`, `daily_plan_id` (cascade), `goal_type_id` (null), `slot` tinyint, `description` varchar(255), `is_done` bool, `completed_at` | `unique(daily_plan_id, slot)`. `slot` ∈ 1..3. `completed_at` se sella al marcar cumplido y se limpia al desmarcar |
| `schedule_entries` | `id`, `daily_plan_id` (cascade), `schedule_slot_id` (nullable), `start_time` time nullable, `activity` varchar(255) nullable, `is_done` bool | La hora vive en la propia franja; el `slot_id` solo recuerda de qué franja del catálogo se generó |
| `action_blocks` | `id`, `daily_plan_id` (cascade), `action_block_duration_id` (null), `action_block_outcome_id` (null), `task`, `started_at`, `finished_at` | Uno a muchos por día. La suma de `minutes` es el "foco" del día |
| `plan_notes` | `id`, `daily_plan_id` (cascade), `content` text, `sort_order` tinyint | Ordenadas por `sort_order` |
| `reflection_answers` | `id`, `daily_plan_id` (cascade), `reflection_question_id` (cascade), `is_checked` bool, `answer` text nullable | `unique(daily_plan_id, reflection_question_id)` |
| `daily_plan_preparation` | `id`, `daily_plan_id` (cascade), `preparation_item_id` (cascade), `is_checked` bool, `preparation_items_description` text nullable, timestamps | Pivote N:M con `unique(daily_plan_id, preparation_item_id)` |

### 9.3 Catálogos

| Tabla | Columnas | Contenido sembrado |
| --- | --- | --- |
| `energy_levels` | `id`, `slug` (uq), `name`, `emoji`, `sort_order`, `is_active` | Baja 😞 · Media 😐 · Alta 😃 |
| `goal_types` | `id`, `slug` (uq), `name`, `subtitle`, `sort_order`, `is_active` | Debo hacer (Responsabilidad) · Quiero hacer (Algo que me motiva) · Algo para mí (Autocuidado/Bienestar) |
| `preparation_items` | `id`, `name`, `sort_order`, `is_active` | Materiales · Ropa adecuada · Alimentación · Cargar dispositivos · Espacio organizado · Todo lo necesario para mis actividades |
| `schedule_slots` | `id`, `start_time` time (uq), `sort_order`, `is_active` | 15 franjas: 07:00 a 21:00, una por hora |
| `action_block_durations` | `id`, `minutes` (uq), `label`, `sort_order`, `is_active` | 5, 10, 15 y 20 minutos |
| `action_block_outcomes` | `id`, `slug` (uq), `name`, `sort_order`, `is_active` | Lo terminé · Avancé · Necesito otro bloque · Necesito pedir orientación |
| `reflection_questions` | `id`, `category` (index), `question` text, `input_type`, `sort_order`, `is_active` | 5 preguntas de la categoría `procrastinacion`: ¿Qué estoy evitando? · ¿Por qué lo estoy postergando? · ¿Qué me está distrayendo? · ¿Necesito desglosarlo en pasos más pequeños? · ¿Qué puedo hacer AHORA en 5 minutos? |

### 9.4 Tablas de la planificación periódica

| Tabla | Columnas | Para qué |
| --- | --- | --- |
| `planificacion_importaciones` | `id`, `archivo` (uq), `nombre_original`, `periodo_desde`, `periodo_hasta`, `estado`, `hojas_total`, `hojas_creadas`, `hojas_omitidas`, `hojas_saltadas`, `hojas_ignoradas`, `hojas_error`, `cubierto_hasta`, `en_bd_hasta`, `dias_sin_hoja`, `subido_en`, `procesado_en`, `descartado_en`, `mensaje` | Un registro por libro montado: estado de la última corrida, contadores y checkpoint |
| `planificacion_hojas` | `id`, `importacion_id` (cascade), `hoja`, `fecha`, `accion`, `motivo`, `avisos`, `detalle`, `procesado_en` | Qué pasó con cada hoja: `creado`, `omitido`, `saltado`, `ignorado` o `error`, con su motivo y sus avisos |

Estas tablas **no** guardan los diarios: la verdad de los días vive en `daily_plans`. Sirven para
contar lo que hizo la corrida y para que el módulo muestre el reporte.

### 9.5 Tablas del esqueleto de Laravel (presentes, casi sin uso)

`users` (vacía), `password_reset_tokens`, `sessions` (**en uso**: `SESSION_DRIVER=database`),
`cache` y `cache_locks` (**en uso**: la caché y el candado de `withoutOverlapping`), `jobs`,
`job_batches` y `failed_jobs`.

### 9.6 Estado de la base de datos de desarrollo

- **21 migraciones aplicadas**: 3 del esqueleto, 16 del dominio y 2 de la planificación periódica.
- **7 catálogos sembrados**: 3 energías, 3 tipos de objetivo, 6 ítems de preparación, 15 franjas,
  4 duraciones, 4 resultados y 5 preguntas.
- **9 diarios registrados** (del 28/09/2026 al 06/10/2026).
- Sin libros en la cola de planificación y sin registros de corridas al momento de esta
  redacción.

---

## 10. Arquitectura y decisiones técnicas

### 10.1 Estilo y capas

Aplicación **monolítica Laravel 12** con MVC enriquecido:

- **Rutas** (`routes/web.php`) → los 20 endpoints web detrás del middleware `web`.
- **Controladores** delgados → validan, invocan y arman la respuesta (HTML, JSON, PDF o XLSX).
  Comparten el trait `DatosDelFormulario`.
- **Modelo de dominio** (`App\Models\DailyPlan`) → concentra **todas** las consultas y escrituras
  del planificador (scopes, transacciones, estructura hija, sincronización y resúmenes).
- **Servicios de apoyo** (clases estáticas, sin contenedor propio): `Filtros\Periodo`,
  `Graficos\AgendaDelDia`, `Graficos\AgendaPng`, `Reportes\AnalisisDeDesempeno`,
  `Reportes\RedaccionDelResumen`, `Reportes\GraficosDelResumen`, `Excel\ReporteDiarioExport`,
  `Excel\PlantillaDelPlanificador`, `Documentos\DocumentoDelDiario`,
  `Planificacion\ImportadorDePlanificacion`, `Planificacion\ArchivosDePlanificacion`,
  `Errores\CatalogoDeErrores`, `Errores\RegistroDeErrores` y `Helpers\Helper`.
- **Vistas Blade** → `layouts.app` es la plantilla base; dos vistas son documentos independientes
  para dompdf (`diario/pdf.blade.php` y `diario/resumen.blade.php`).
- **Consola** → `planificacion:plantilla`, `planificacion:importar`, `mupdf:instalar` y el
  horario del cron en `routes/console.php`.

```
┌──────────────────────────────────────────────────────────────────────────┐
│  NAVEGADOR                                                               │
│  Blade + Bootstrap 5 + jQuery + DataTables + bootbox                     │
│  window.Planificador: Formulario · Grafico · Tabla · Impresion · Reporte │
└───────────────┬──────────────────────────────────────────────┬───────────┘
                │ HTTP (HTML / AJAX JSON / descarga)            │
┌───────────────▼──────────────────────────────────────────────▼───────────┐
│  RUTAS · routes/web.php                          (middleware web + CSRF) │
└───────────────┬──────────────────────────────────────────────────────────┘
                │
┌───────────────▼──────────────────────────────────────────────────────────┐
│  CONTROLADORES                                                           │
│  Home · Daily · DailyPlan · Planificacion · Error                        │
│  trait DatosDelFormulario   ·   sobre JSON { ok, message, data|errors }   │
└───────┬──────────────────────────┬───────────────────────────┬───────────┘
        │                          │                           │
┌───────▼────────────┐  ┌──────────▼───────────────┐  ┌────────▼──────────┐
│ MODELO DE DOMINIO  │  │ SERVICIOS DE APOYO       │  │ SALIDAS           │
│ DailyPlan          │  │ Periodo · AgendaDelDia   │  │ Blade (pantalla)  │
│ Planificacion*     │  │ AgendaPng · Analisis     │  │ dompdf (PDF)      │
│  · scopes/filtros  │  │ Redaccion · Graficos     │  │ PhpSpreadsheet    │
│  · transacciones   │  │ ReporteDiarioExport      │  │  (XLSX)           │
│  · sync formulario │  │ PlantillaDelPlanificador │  │ DocumentoDelDiario│
│  · to*Array        │  │ Importador · Helper      │  │  (PDF/JPG/ZIP)    │
└───────┬────────────┘  └──────────────────────────┘  └───────────────────┘
        │ Eloquent
┌───────▼──────────────────────────────────────────────────────────────────┐
│  MySQL · base «planificador-diario»                                      │
│  daily_plans + 6 hijas/pivote + 7 catálogos + 2 de planificación         │
└──────────────────────────────────────────────────────────────────────────┘
```

### 10.2 Flujo de una petición (guardar el diario)

```
1. El navegador envía POST /diario con el formulario serializado y el testigo CSRF.
2. El middleware web verifica CSRF y abre la sesión (driver database).
3. La ruta diario.store llama a DailyPlanController@store.
4. El controlador valida (reglas + mensajes en español). Si falla y la petición espera
   JSON, bootstrap/app.php responde 422 con { ok:false, message, errors }.
5. DailyPlan::createDay($data) abre una transacción:
   a. crea la cabecera (plan_date, energy_level_id, cierre);
   b. refreshDayStructure(): franjas del catálogo, checklist y respuestas vacías
      (idempotente);
   c. applyDayData(): sincroniza objetivos, horario, preparación, reflexiones, notas
      y bloques de acción.
   Si algo falla, se deshace todo y el fallo queda en la bitácora de errores.
6. Responde 201 con { ok:true, message, data:{ plan: … } }.
7. El navegador muestra la alerta de éxito y redirige.
```

### 10.3 Decisiones y su motivo

| Decisión | Motivo |
| --- | --- |
| Lógica de datos en el modelo y controlador delgado | Un único lugar donde se consulta y se escribe; el controlador solo arma respuestas |
| Servicios **estáticos** en `app/*` | No requieren estado ni inyección: se invocan desde modelos, controladores, vistas y comandos |
| Catálogos en base con `is_active` y `sort_order` | Ampliar o retirar ítems sin tocar vistas; el histórico se conserva |
| Un solo formulario para crear, ver y editar | Una sola vista y un solo JS que mantener |
| Hijos generados al crear el día | El día queda completo desde el inicio y el reporte tiene siempre las mismas columnas |
| Gráficos con GD en el servidor para el PDF | El PDF no ejecuta JavaScript: los gráficos viajan como PNG |
| Línea de tiempo en pantalla con JS propio | Se redibuja sin ir al servidor y no agrega dependencias |
| Excel y resumen en memoria (`php://output`) | No dejan archivos temporales ni piden permisos de escritura |
| PDF e imagen **guardados** en caché | Se entregan al instante la segunda vez; se invalidan al modificar o borrar el día |
| `mutool` en vez de Imagick o Ghostscript | Un binario suelto, sin instalador, igual en Windows y Linux |
| Carga desde Excel **solo inserta lo que falta** | El Excel nunca pisa lo que la persona escribió en la interfaz |
| El lector busca **por etiqueta**, no por fila | Insertar o mover una fila no rompe la importación |
| `plan_date` como fuente de la fecha (no la celda) | El nombre de la hoja es explícito y no depende del formato de fecha de Excel |
| Zona horaria `America/Caracas` | "Hoy", el bloqueo de la fecha y las marcas de tiempo hablan en la hora local |
| Assets propios + CDN, sin Vite/NPM | El despliegue es copiar la carpeta en XAMPP: no hay paso de compilación |

### 10.4 Rendimiento

| Tema | Cómo se resolvió |
| --- | --- |
| Listado | Se traen todos los días filtrados con `energyLevel` precargado; DataTables pagina, ordena y busca en el navegador |
| Consultas por fila | El reporte, el resumen y la importación usan *eager loading*: el número de consultas no crece con la cantidad de días |
| Contadores de la tabla | `withCount` en lugar de contar en el navegador |
| Libro de Excel | La corrida lee **primero los nombres de las hojas** y solo abre las que le faltan |
| PDF del día | dompdf con la fuente base Helvetica (no incrustada): archivos de pocos KB y texto seleccionable |
| Imagen | `mutool` a 150 ppp + GD: legible en pantalla y liviana para compartir |
| Caché del navegador | `no-store` en PDF, XLSX y documentos: la URL es fija y debe reflejar el último estado |
| Índices | Únicos en `plan_date` y en los pares (día + hijo); claves foráneas en cascada |
| Seeders | `updateOrCreate` con clave natural: se pueden reejecutar sin duplicar |
| Dobles envíos | El botón de guardado se bloquea mientras hay una petición en curso y el formulario confirma antes de registrar |

---

## 11. Estructura del proyecto

```
planificadordiario-transformaconecta/
│
├── app/
│   ├── Console/Commands/
│   │   ├── GenerarPlantillaDePlanificacion.php   planificacion:plantilla
│   │   ├── ImportarPlanificacion.php             planificacion:importar (el cron)
│   │   └── InstalarMuPdf.php                     mupdf:instalar
│   ├── Documentos/
│   │   └── DocumentoDelDiario.php                PDF e imagen del día, con su caché
│   ├── Errores/
│   │   ├── CatalogoDeErrores.php                 Texto de cada familia y código HTTP
│   │   └── RegistroDeErrores.php                 Bitácora detallada en el log diario
│   ├── Excel/
│   │   ├── PlantillaDelPlanificador.php          La planilla .xlsx y su mapa de etiquetas
│   │   └── ReporteDiarioExport.php               Reporte detallado .xlsx (25 columnas)
│   ├── Filtros/Periodo.php                       Período → par de fechas
│   ├── Graficos/
│   │   ├── AgendaDelDia.php                      Franjas del horario → tramos
│   │   └── AgendaPng.php                         Línea de tiempo dibujada con GD
│   ├── Helpers/Helper.php                        Fechas, cifras, textos y Excel
│   ├── Http/Controllers/
│   │   ├── Concerns/DatosDelFormulario.php       Catálogos y datos de la vista
│   │   ├── DailyController.php                   Formulario en blanco y "hoy"
│   │   ├── DailyPlanController.php               Listar, crear, ver, editar, documentos, eliminar
│   │   ├── ErrorController.php                   /error/{codigo}
│   │   ├── HomeController.php                    Portada
│   │   └── PlanificacionController.php           Módulo de planificación periódica
│   ├── Models/
│   │   ├── DailyPlan.php                         Modelo eje
│   │   ├── PlanificacionImportacion.php          Libros montados y su estado
│   │   ├── PlanificacionHoja.php                 Qué pasó con cada hoja
│   │   └── (catálogos e hijas: ActionBlock, EnergyLevel, GoalType, PlanGoal,
│   │        PlanNote, PreparationItem, ReflectionAnswer, ReflectionQuestion,
│   │        ScheduleEntry, ScheduleSlot, User)
│   ├── Planificacion/
│   │   ├── ArchivosDePlanificacion.php           Carpetas del disco privado
│   │   └── ImportadorDePlanificacion.php         Lector del libro y plan de la corrida
│   ├── Providers/AppServiceProvider.php
│   └── Reportes/
│       ├── AnalisisDeDesempeno.php               Todos los números del resumen
│       ├── GraficosDelResumen.php                Los tres gráficos del resumen
│       └── RedaccionDelResumen.php               Números → frases
│
├── bootstrap/app.php                             Rutas, middleware y manejo de excepciones
├── config/                                       app · auth · cache · database · filesystems
│                                                 logging · mail · mupdf · queue · services · session
├── database/
│   ├── migrations/                               21 migraciones
│   ├── seeders/                                  Los 7 catálogos (idempotentes)
│   └── database.sqlite                           Residuo de la primera etapa (no se usa)
│
├── public/                                       Raíz web real de Laravel
│   ├── css/styles.css                            Paleta institucional y todos los componentes
│   ├── js/{app,script,grafico,menu,reloj}.js
│   ├── .htaccess · index.php · favicon.ico · robots.txt
│
├── resources/views/
│   ├── layouts/app.blade.php                     Cabecera, menú, pie y CDN
│   ├── home/index.blade.php                      Portada
│   ├── diario/
│   │   ├── formulario.blade.php                  El formulario único (crear/ver/editar)
│   │   ├── index.blade.php                       Listado, filtros y reportes
│   │   ├── pdf.blade.php · resumen.blade.php     Documentos para dompdf
│   │   ├── _grafico.blade.php                    Tarjeta de la línea de tiempo
│   │   └── _modal_imprimir.blade.php · _modal_resumen.blade.php
│   ├── planificacion/index.blade.php             El módulo de planificación periódica
│   ├── errores/index.blade.php                   Página de errores 3xx/4xx/5xx
│   └── welcome.blade.php                         Resto del esqueleto (sin ruta)
│
├── routes/
│   ├── web.php                                   Las 20 rutas de la aplicación
│   └── console.php                               El horario del cron de planificación
│
├── storage/
│   ├── app/private/
│   │   ├── binarios/mupdf/                       mutool (lo instala mupdf:instalar)
│   │   ├── documentos/{pdf,jpg}/{id}/             PDF e imagen de cada diario
│   │   └── excel/{formato,jobs,procesados,descartados,errores,ejemplos}/
│   ├── framework/                                Caché de vistas y sesiones
│   └── logs/                                     laravel-AAAA-MM-DD.log · errores-AAAA-MM-DD.log
│
├── tests/
│   ├── Feature/PlanificacionPeriodicaTest.php    27 pruebas del módulo y la corrida
│   ├── Feature/ExampleTest.php · Unit/ExampleTest.php
│   └── TestCase.php
│
├── .htaccess                                     Puente raíz → public/ y bloqueos
├── index.php                                     Reenvía a public/index.php
├── artisan · composer.json · composer.lock
├── phpunit.xml · LICENSE · README.md
└── (artefactos de ejemplo en la raíz: ver §16.3)
```

---

## 12. Stack tecnológico y requisitos

### 12.1 Back-end

| Componente | Versión | Para qué |
| --- | --- | --- |
| PHP | 8.2.12 (requisito `^8.2`) | Lenguaje del servidor |
| laravel/framework | 12.69.3 | MVC, Eloquent, validación, sesiones, migraciones, consola |
| barryvdh/laravel-dompdf | 3.1.2 | PDF (día y resumen) |
| dompdf/dompdf | 3.1.6 | Motor PDF; usa sus fuentes DejaVu Sans para los gráficos |
| phpoffice/phpspreadsheet | 5.10.0 | Reporte `.xlsx` y la planilla de planificación |
| laravel/tinker | 2.11.1 | Consola interactiva para mantenimiento |

Extensiones de PHP necesarias: `gd` (gráficos e imágenes), `pdo_mysql`, `mbstring`, `openssl`,
`fileinfo`, `zip`, `curl`, `dom` y `xml`. **No** se usa `intl`: los nombres de meses y días están
escritos en español dentro de `Helper`.

### 12.2 Desarrollo

`phpunit/phpunit` 11.5.56, `mockery/mockery` 1.6.15, `fakerphp/faker` 1.24.1,
`nunomaduro/collision` 8.9.5, `laravel/pail` 1.2.7, `laravel/pint` 1.30.4 y `laravel/sail` 1.68.0
(instalado, sin `compose.yaml`: el flujo Docker no se usa).

### 12.3 Front-end (por CDN)

| Librería | Versión | Uso |
| --- | --- | --- |
| Bootstrap | 5.3.8 | Rejilla, componentes, Offcanvas del menú, modales |
| Bootstrap Icons | 1.13.1 | Iconografía |
| jQuery | 3.7.1 | Base del JS propio y de las librerías |
| bootbox | 6.0.4 | Alertas y confirmaciones |
| DataTables | 2.3.8 | Tabla del listado |
| bootstrap-datepicker | 1.10.1 (+ `es`) | Calendario de la fecha y de los filtros |

### 12.4 Assets propios

| Archivo | Función |
| --- | --- |
| `public/js/app.js` | Utilidades, alertas, AJAX, DataTables, impresión y descargas |
| `public/js/script.js` | Formulario: fecha, horario, validación y guardado |
| `public/js/grafico.js` | Línea de tiempo del día |
| `public/js/menu.js` · `public/js/reloj.js` | Menú lateral y fecha/hora de la cabecera |
| `public/css/styles.css` | Paleta institucional, layout y todos los componentes (`tc-*`, `tf-*`) |

### 12.5 Infraestructura

- Apache (XAMPP) en el puerto **8088** con `DocumentRoot "C:/xampp/htdocs"`.
- MySQL con la base `planificador-diario` (`utf8mb4`).
- Composer para las dependencias de PHP.
- Permisos de escritura en `storage/` y `bootstrap/cache/`.

---

## 13. Instalación y puesta en marcha

### 13.1 Requisitos previos

- XAMPP (o equivalente) con Apache y MySQL/MariaDB.
- PHP 8.2 o superior con las extensiones de [§12.1](#121-back-end).
- Composer.
- Apache en el puerto 8088 y con `DocumentRoot "C:/xampp/htdocs"`.

### 13.2 Paso a paso

1. **Copiar el proyecto** en `C:\xampp\htdocs\planificadordiario-transformaconecta\`.

2. **Instalar las dependencias de PHP:**

   ```
   composer install
   ```

3. **Crear el `.env`** copiando `.env.example` y completar lo esencial:

   ```
   APP_URL=http://localhost:8088/planificadordiario-transformaconecta
   APP_TIMEZONE=America/Caracas
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=planificador-diario
   DB_USERNAME=root
   DB_PASSWORD=
   SESSION_DRIVER=database
   CACHE_STORE=database
   ```

4. **Generar la clave:**

   ```
   php artisan key:generate
   ```

5. **Crear la base de datos:**

   ```
   mysql -u root -e "CREATE DATABASE `planificador-diario` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

6. **Crear las tablas y sembrar los catálogos:**

   ```
   php artisan migrate --seed
   ```

   (21 migraciones y los 7 catálogos; los seeders son idempotentes.)

7. **Crear la planilla de planificación periódica** (no viaja en el repositorio, porque vive en
   el disco privado):

   ```
   php artisan planificacion:plantilla
   ```

8. **Instalar el conversor de PDF a imagen** (solo si se va a usar el botón *Generar imagen*):

   ```
   php artisan mupdf:instalar
   ```

9. **Iniciar Apache y MySQL** en XAMPP y abrir el sistema:

   ```
   http://localhost:8088/planificadordiario-transformaconecta/
   ```

### 13.3 La corrida de las 00:00

La carga de los libros de Excel la hace el comando `planificacion:importar`, programado todos los
días a la medianoche en `routes/console.php`:

```php
Schedule::command('planificacion:importar')
    ->dailyAt('00:00')            // ImportarPlanificacion::HORA
    ->withoutOverlapping()
    ->description('Carga en el diario los días que falten del Excel de planificación periódica');
```

**Windows (XAMPP).** No hay cron: la corrida la dispara una tarea del Programador de tareas que
ejecuta el comando **una vez al día**, a las 00:00:

```
Nombre:   PlanificadorDiario-PlanificacionPeriodica
Ejecuta:  cmd /c cd /d "C:\xampp\htdocs\planificadordiario-transformaconecta"
              && "C:\xampp\php\php.exe" artisan planificacion:importar
              >> "storage\logs\planificacion-periodica.log" 2>&1
```

Se puede crear desde PowerShell (ajustando la ruta de PHP si no es la de XAMPP):

```powershell
$accion = 'cmd /c cd /d "C:\xampp\htdocs\planificadordiario-transformaconecta" && ' +
          '"C:\xampp\php\php.exe" artisan planificacion:importar >> "storage\logs\planificacion-periodica.log" 2>&1'
schtasks /create /tn "PlanificadorDiario-PlanificacionPeriodica" /sc daily /st 00:00 /f /tr $accion
```

Para revisarla o ejecutarla a mano:

```powershell
schtasks /query  /tn "PlanificadorDiario-PlanificacionPeriodica" /v /fo LIST
schtasks /run    /tn "PlanificadorDiario-PlanificacionPeriodica"
schtasks /delete /tn "PlanificadorDiario-PlanificacionPeriodica" /f
```

> Con `schtasks /run` el comando se ejecuta en el momento (útil para no esperar a la medianoche).
> El resultado de cada corrida, además, queda en `storage/logs/planificacion-periodica.log` y en
> el reporte del módulo.

**Linux o hosting con cron.** Manda el horario de `routes/console.php`, así que basta con dejar
el planificador de Laravel corriendo cada minuto:

```
* * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

> Si se cambia la hora de la corrida, hay que cambiarla en los dos lados: en la tarea de Windows
> y en `ImportarPlanificacion::HORA` (`routes/console.php`).

### 13.4 Instalación en hosting

1. Copiar el proyecto completo (incluida `public/`) dentro de la carpeta del hosting: el
   `.htaccess` de la raíz es relativo y no hay que cambiar rutas.
2. Si el proveedor obliga a que la raíz web sea `public_html`, se puede mover el contenido de
   `public/` a la raíz, o mantener la estructura con el `index.php` y el `.htaccess` de la raíz
   (que es la solución ya prevista).
3. Ajustar `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` real y credenciales.
4. Ejecutar `composer install --no-dev --optimize-autoloader` y
   `php artisan config:cache route:cache view:cache`.
5. Verificar permisos de escritura en `storage/` y `bootstrap/cache/`.
6. Crear la planilla (`php artisan planificacion:plantilla`, y si se quiere una por período
   `php artisan planificacion:plantilla --desde=… --hasta=…`) e instalar `mutool` si hace falta.
7. Si se agregan tipos de archivo estáticos nuevos en `public/`, añadir su extensión a la lista
   del `.htaccess` de la raíz.

### 13.5 Comprobación de que todo está bien

| Comprobación | Resultado esperado |
| --- | --- |
| `php artisan --version` | `Laravel Framework 12.69.3` |
| `php artisan migrate:status` | 21 migraciones en estado `Ran` |
| `php artisan route:list` | 21 rutas (20 de la aplicación + `/up`) |
| `GET /` | 200 con la fecha de hoy y los accesos |
| `GET /diario` | El formulario con 3 objetivos, 6 ítems, 5 preguntas y los catálogos |
| `GET /diario/listado` | 200 con el buscador y la tabla |
| `GET /planificacion` | 200 con las planillas, la cola y las reglas |
| `GET /planificacion/plantilla` | Descarga de `planilla-planificador-diario.xlsx` |
| `GET /planificacion/plantilla/planilla-2026-10-01_2026-10-31.xlsx` | Descarga de la planilla de ese período (o 302 con el aviso si el nombre no existe) |
| `GET /diario/1/imprimir` | El PDF del día (o 404 si ese día no existe) |
| `GET /up` | 200 (`Application up`) |
| `GET /error/404` | 404 con la página de errores del sistema |
| `GET /.env` y `GET /storage/...` | **403** (bloqueados por el `.htaccess` de la raíz) |
| `php artisan planificacion:importar --dry-run` | El plan de la corrida, sin escribir nada |
| `php artisan test` | 29 pruebas correctas |

---

## 14. Guía de uso

### 14.1 Crear el diario de hoy

1. En la portada, pulsar **Crear el diario de hoy** (o en el menú, **Crear diario**).
2. La fecha ya viene con el día de hoy y bloqueada; el día de la semana se marca solo.
3. Elegir **Mi energía hoy**: Baja, Media o Alta.
4. Escribir los **3 objetivos principales**: 1 Debo hacer, 2 Quiero hacer, 3 Algo para mí.
5. Marcar lo que ya está listo en **Antes de empezar**; al marcar, se abre su descripción
   ("PC, cuaderno, calculadora").
6. Cargar **Mi horario de hoy** con **Agregar franja**: hora y actividad. La barra de abajo
   ("Mi día en el tiempo") se dibuja sola. Marcar la casilla de lo cumplido.
7. Si aparece la procrastinación, marcar las preguntas y anotar la respuesta.
8. Registrar el **Bloque de acción**: cuánto vas a trabajar, cómo quieres terminarlo y en qué.
9. Escribir el **Cierre del día** (obligatorio): logros, pendiente, cuándo y orgullo.
10. Agregar **Notas / recordatorios** si hace falta y pulsar **Guardar**.

### 14.2 Consultar, buscar y filtrar

1. Menú → **Consultar diario**. La tabla lista los días; se puede ordenar, buscar y cambiar la
   cantidad de filas por página.
2. **Palabra clave**: filtra mientras se escribe dentro de objetivos, horario, notas, cierre y
   bloque de acción.
3. **Filtrar por → Fecha / Energía / Período**: se aplica solo al elegirlo. Los filtros se
   combinan y **son los que usan el Excel y el resumen**: lo que ves es lo que se exporta.
4. **Limpiar** deja todo en blanco.
5. En cada fila: **Modificar**, **Ver**, **Documentos** y **Eliminar**.

### 14.3 Sacar los documentos de un día

1. Pulsar el botón de documentos (en el listado o dentro del día).
2. Si el diario existe, se abre el modal: elegir **Generar PDF** (para imprimir) o
   **Generar imagen** (para compartir).
3. Marcar la casilla **solo** si se quiere el documento de prueba con la marca de agua
   `SPECIMEN`.
4. La primera vez tarda unos segundos (se genera y se guarda); después se entrega al instante.
5. Si la imagen no se genera, falta el conversor: `php artisan mupdf:instalar`.

### 14.4 Generar el Excel y el resumen

- **Reporte detallado**: aplicar los filtros que se quieran y pulsar **Generar reporte
  detallado**. Se descarga `reporte-diario[-filtrado]-AAAA-MM-DD.xlsx`.
- **Resumen de desempeño**: pulsar **Generar resumen**, marcar la casilla si es un documento de
  muestra y **Generar PDF**. Cubre exactamente lo que está filtrado.

### 14.5 Cargar un período desde Excel

**Para quien llena la planilla:**

1. Descargar la planilla desde el módulo (**Planificación periódica → Descargar la planilla**).
2. Duplicar la hoja **DIA** una vez por cada día del período
   (clic derecho en la pestaña → *Mover o copiar* → *Crear una copia*).
3. Renombrar cada copia con la fecha en formato `aaaa-mm-dd` (por ejemplo `2026-10-04`).
4. Llenar cada hoja: energía, los 3 objetivos, el horario, el checklist, las reflexiones, el
   bloque de acción, el cierre y las notas. **Los campos obligatorios son obligatorios**: si
   falta uno, esa hoja se salta y el sistema lo dice en el reporte.
5. No hace falta tocar la hoja **INSTRUCCIONES** (y se puede borrar: la corrida la ignora).

**Para quien lo monta:**

1. Entrar a **Planificación periódica** y montar el archivo `.xlsx`.
2. El libro queda **en espera**: no se procesa nada en ese momento.
3. Esperar la corrida de las **00:00** (o lanzarla a mano, ver [§13.3](#133-la-corrida-de-las-0000)).
4. Revisar el reporte: qué días se crearon, cuáles ya estaban, cuáles se saltaron y por qué.
5. Si una hoja se saltó, corregirla en el Excel, volver a montar el libro y esperar la corrida
   siguiente: esa hoja se reintenta.
6. Para que un libro deje de participar en las corridas, usar **Quitar de la cola**.

> **Ojo con dos reglas de convivencia entre el Excel y la interfaz:**
> un día que ya está cargado **no se vuelve a cargar nunca** (si se corrige, se edita desde
> *Consultar diario*); y si un día se **elimina** desde el listado, la corrida siguiente lo
> vuelve a crear con lo que traiga el libro, salvo que se quite esa hoja del Excel y el libro de
> la cola.

### 14.6 Modificar y eliminar

- **Modificar**: abre el mismo formulario con los datos cargados; al guardar vuelve al listado y
  los documentos del día se regeneran la próxima vez.
- **Eliminar**: pide confirmación con el nombre del día y avisa que no se puede deshacer. El
  borrado arrastra todas las secciones del día y sus documentos.

---

## 15. Configuración del entorno

Variables de `.env` (valores del entorno local; no se reproducen secretos):

| Variable | Valor local | Para qué |
| --- | --- | --- |
| `APP_NAME` | `Laravel` | Nombre interno; las vistas muestran el suyo. Se usa en la cookie de sesión y en el prefijo de caché |
| `APP_ENV` | `local` | Entorno |
| `APP_DEBUG` | `true` | Detalle de errores (en producción: `false`) |
| `APP_URL` | `http://localhost:8088/planificadordiario-transformaconecta` | Dirección usada por `route()` |
| `APP_TIMEZONE` | `America/Caracas` | **Define "hoy"**, el bloqueo de la fecha, las corridas y el reloj |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `en` / `es` | La interfaz no usa traducciones: todo está escrito en español |
| `LOG_STACK` / `LOG_LEVEL` | `daily` / `debug` | Logs diarios en `storage/logs/laravel-AAAA-MM-DD.log` |
| `LOG_DAILY_DAYS` / `LOG_ERRORES_DAYS` | `30` / `60` | Retención del log general y del de errores |
| `DB_*` | `mysql` · `localhost` · `3306` · `planificador-diario` · `root` · vacío | Base de datos |
| `SESSION_DRIVER` / `SESSION_LIFETIME` | `database` / `120` | Sesiones en la tabla `sessions` |
| `CACHE_STORE` | `database` | Caché y candado de `withoutOverlapping` |
| `MUPDF_BIN` | (vacío) | Ruta de `mutool`; si está vacío se busca en `storage/app/private/binarios/mupdf` y en el `PATH` |
| `MUPDF_RESOLUCION` / `MUPDF_CALIDAD` / `MUPDF_ENFOQUE` | `150` / `92` / `true` | Calidad de la imagen del día |

**Regla de oro de la zona horaria.** Todo lo que se refiere a "hoy" (portada, formulario de hoy,
períodos, la corrida de las 00:00 y los documentos) usa `APP_TIMEZONE`. Cambiarla cambia el
significado de "hoy".

**Detalles que conviene conocer.**

| Tema | Estado actual | Comentario |
| --- | --- | --- |
| `APP_NAME` | `Laravel` | Poner `"Planificador Diario Transforma-Conecta"` alinea la cookie de sesión, el prefijo de caché y `mail.from.name` |
| `SESSION_PATH` / `SESSION_DOMAIN` | `/` / `null` | La cookie se comparte con cualquier aplicación de `localhost`. Al publicar, conviene acotarla |
| `SESSION_SECURE_COOKIE` | No definida | No exige HTTPS (coherente con el uso local). En producción debe activarse |
| Disco `local` | `serve => false` | Decisión de seguridad: esas rutas publicarían `storage/app/private` sin autenticación (ver [§16](#16-seguridad)) |
| Caché de configuración | No generada | `bootstrap/cache/` solo tiene lo base; en producción conviene `config:cache` y `route:cache` |
| Metadatos de `composer.json` | `name: laravel/laravel`, `license: MIT` | El proyecto nunca se renombró y `license` contradice al `LICENSE` real (Unlicense). Ver [§22](#22-créditos-y-licencia) |

---

## 16. Seguridad

### 16.1 Modelo actual

El sistema está pensado como **libreta personal en un entorno local**: no hay autenticación ni
separación de datos. **Cualquiera que alcance la URL puede leer y modificar todos los diarios.**
Es una decisión de alcance, no un descuido; si se publica en internet hay que agregar
autenticación (ver [§21](#21-hitos-de-versiones-estado-y-hoja-de-ruta)).

### 16.2 Controles que existen

| Control | Dónde |
| --- | --- |
| **CSRF** en formularios y AJAX (`@csrf` y `P.token`) | Vistas y `public/js/app.js` |
| **Validación del servidor** de todos los campos | `DailyPlanController::rules()` |
| **Validación de la carga**: `.xlsx`, tamaño máximo 20 MB y nombre saneado | `PlanificacionController@store` |
| **Escape de salida** en Blade y en el JS antes de inyectar HTML | Vistas y `public/js/*.js` |
| **Escapado de comodines `LIKE`** (`%`, `_`, `\`) en la búsqueda | `DailyPlan::scopeSearch()` |
| **Carga desde Excel acotada**: solo crea días ausentes y solo escribe en `daily_plans` vía `createDay()` | `ImportadorDePlanificacion` + `ImportarPlanificacion` |
| **Bloqueo HTTP** de carpetas internas (`app`, `config`, `storage`, `vendor`…) y de archivos ocultos, `composer.json`, `artisan`, `*.log`, `*.sqlite` | `.htaccess` de la raíz |
| **Sin listado de directorios** (`Options -Indexes`) | `.htaccess` de la raíz |
| **Disco privado sin publicación** (`serve => false`): los Excel, los PDF, las imágenes y `mutool` no se sirven por HTTP; todo pasa por los controladores | `config/filesystems.php` |
| **Consultas parametrizadas** por Eloquent | Todo el acceso a datos |
| **Transacciones** con `report()` y sin exponer trazas al usuario | `DailyPlan::enTransaccion()` y los `try/catch` |
| **Documentos sin caché** (`no-store`) y página de error sin datos técnicos | Controladores y `errores.index` |
| **Bitácora sin datos personales**: nunca se guarda el cuerpo del formulario | `RegistroDeErrores` |
| **Manejo de XML del libro**: el lector de PhpSpreadsheet no ejecuta macros y solo se aceptan `.xlsx` | `ImportadorDePlanificacion` |

### 16.3 Hallazgos pendientes

| # | Hallazgo | Riesgo | Acción sugerida |
| --- | --- | --- | --- |
| 1 | La carpeta `.tmp-chrome4` (perfil completo de Chrome con `Cookies`, `Login Data`, historial y `Web Data`) sigue **versionada en git** (71 archivos) y **no está en `.gitignore`** | Alto: puede contener credenciales y sesiones del navegador | `git rm -r --cached .tmp-chrome4`, agregar `/.tmp-chrome4/` al `.gitignore` y, si el repositorio se compartió, limpiar el historial (`git filter-repo` o BFG) |
| 2 | Los artefactos de ejemplo de la raíz (`resumen-muestra.pdf`, `reporte-detallado.xlsx`, `*.png`) son **descargables por HTTP**: el `.htaccess` no bloquea `pdf`, `xlsx` ni `png` | Bajo: solo exponen documentos derivados de datos de prueba | Moverlos a una carpeta interna o agregar esas extensiones al `<FilesMatch>` |
| 3 | Ninguno de los dos `.htaccess` define **cabeceras de seguridad** (`X-Frame-Options`, `X-Content-Type-Options`, HSTS) | Bajo en local, relevante al publicar | Agregarlas en el servidor o en el `.htaccess` |
| 4 | `APP_DEBUG=true` y `LOG_LEVEL=debug` | Medio en producción | `APP_DEBUG=false`, `LOG_LEVEL=warning` |

### 16.4 Antes de publicar en internet

1. **Agregar autenticación** y asociar `daily_plans.user_id` a cada registro (hoy no existe).
2. `APP_ENV=production` y `APP_DEBUG=false`.
3. HTTPS obligatorio y `SESSION_SECURE_COOKIE=true`.
4. Respaldos periódicos de la base (y del `.env`).
5. Revisar el `.htaccess` si el hosting no permite `AllowOverride`.
6. Resolver los hallazgos de [§16.3](#163-hallazgos-pendientes), empezando por `.tmp-chrome4`.

---

## 17. Mantenimiento

### 17.1 Agregar o cambiar un ítem del checklist "Antes de empezar"

1. Editar `database/seeders/PreparationItemSeeder.php`.
2. `php artisan db:seed --class=PreparationItemSeeder`.
3. Los días ya creados no cambian; los nuevos incluyen el ítem. Para que un día existente lo
   incorpore, basta con abrirlo y guardarlo.
4. **Ojo con dos cosas:** el Excel del reporte (agregar la columna en
   `ReporteDiarioExport::COLUMNAS`, en el mismo orden que `toReportArray()`) y **la planilla de
   planificación** (volver a generar con `php artisan planificacion:plantilla`, porque las
   etiquetas de los ítems son las que lee la corrida).

### 17.2 Cambiar o agregar una pregunta de procrastinación

1. Editar `database/seeders/ReflectionQuestionSeeder.php` (categoría
   `ReflectionQuestion::CATEGORY_PROCRASTINATION`).
2. `php artisan db:seed --class=ReflectionQuestionSeeder`.
3. Agregar la columna al reporte Excel si se quiere ahí, y **regenerar la planilla**.
4. Para desactivar una pregunta sin perder el histórico:
   `UPDATE reflection_questions SET is_active = 0 WHERE id = …;`

### 17.3 Ajustar el horario base (franjas de 7:00 a 21:00)

1. Cambiar `FIRST_HOUR` / `LAST_HOUR` en `ScheduleSlotSeeder`.
2. `php artisan db:seed --class=ScheduleSlotSeeder` y regenerar la planilla.
3. Los días nuevos usan el catálogo actualizado; los viejos conservan sus franjas (y en el
   formulario se pueden agregar horas a mano).

### 17.4 Duraciones y resultados del bloque de acción

Editar `ActionBlockDurationSeeder` o `ActionBlockOutcomeSeeder`, resembrar y **regenerar la
planilla**: las listas desplegables de la hoja salen de esos catálogos.

### 17.5 Regenerar la planilla de planificación

Cada vez que cambie un catálogo que aparece en la hoja (ítems, preguntas, duraciones,
resultados, energías), hay que regenerar el archivo:

```
php artisan planificacion:plantilla                                       # la genérica
php artisan planificacion:plantilla --desde=2026-11-01 --hasta=2026-11-30 # la del período
```

El link de la genérica no cambia: sigue siendo el mismo archivo, reemplazado. Las de período se
acumulan en `excel/formato` (el módulo las lista todas) y conviene borrar las viejas a mano. Las
planillas que ya se repartieron siguen funcionando mientras sus etiquetas existan en el catálogo.

### 17.6 Revisar y operar la corrida

| Qué | Cómo |
| --- | --- |
| Ver el plan sin ejecutar | `php artisan planificacion:importar --dry-run` |
| Ejecutar ahora | `schtasks /run /tn "PlanificadorDiario-PlanificacionPeriodica"` o `php artisan planificacion:importar` |
| Ver la bitácora de las corridas | `Get-Content storage\logs\planificacion-periodica.log -Tail 30` |
| Ver el reporte hoja por hoja | El módulo **Planificación periódica** |
| Reintentar un libro | Se queda solo en `jobs/` mientras tenga hojas saltadas; corregir el Excel y volver a montarlo |
| Sacar un libro de la cola | **Quitar de la cola** en el módulo (pasa a `descartados/`) |
| Volver a cargar un día corregido | No lo hace la corrida (nunca pisa): se edita desde *Consultar diario*. Si el día se elimina, la corrida siguiente lo vuelve a crear desde el Excel |

### 17.7 Tocar la paleta o los estilos

- **Pantalla**: variables CSS del bloque 1 de `public/css/styles.css`.
- **PDF del día y resumen**: los colores están escritos en el `<style>` de
  `resources/views/diario/pdf.blade.php` y `resumen.blade.php` (dompdf no entiende variables CSS).
- **Excel**: constantes de color en `ReporteDiarioExport` (y `PlantillaDelPlanificador` para la
  planilla).
- **Gráficos**: paleta en `AgendaDelDia::PALETA`, `GraficosDelResumen` y `grafico.js` (las tres
  deben coincidir para que pantalla y PDF hablen igual).

### 17.8 Comandos útiles

```
php artisan migrate                 # aplicar migraciones pendientes
php artisan migrate:status          # ver el estado
php artisan migrate:fresh --seed    # recrear la base y sembrar (¡borra los datos!)
php artisan db:seed                 # volver a sembrar los catálogos
php artisan planificacion:plantilla # regenerar la planilla estática
php artisan planificacion:importar --dry-run
php artisan mupdf:instalar          # instalar el conversor de PDF a imagen
php artisan config:clear · view:clear · cache:clear
php artisan route:list
php artisan test
php artisan tinker
```

> `php artisan db:show` y otras órdenes que consultan el esquema pueden fallar en servidores MySQL
> sin `performance_schema`; no afecta a la aplicación.

### 17.9 Respaldo y restauración

```
# Respaldo
mysqldump -u root planificador-diario > respaldo-planificador-AAAA-MM-DD.sql

# Restauración
mysql -u root planificador-diario < respaldo-planificador-AAAA-MM-DD.sql
```

Conviene respaldar también el `.env` (o recordar sus valores) y **no** versionarlo. Los archivos
de `storage/app/private` (documentos, planillas, cola de Excel) se pueden respaldar copiando la
carpeta completa; se regeneran solos, salvo la planilla (que se recrea con el comando).

### 17.10 Actualizar dependencias

```
composer update
php artisan migrate
php artisan config:clear
```

Tras actualizar dompdf, PhpSpreadsheet o MuPDF conviene volver a comprobar los documentos (PDF
del día, imagen, Excel, resumen y planilla), porque son las salidas más sensibles a cambios de
librería.

### 17.11 Revisar los logs

| Archivo | Qué mirar |
| --- | --- |
| `storage/logs/errores-AAAA-MM-DD.log` | El detalle de cada error, con su código de incidente. **El primero que conviene abrir** |
| `storage/logs/laravel-AAAA-MM-DD.log` | Log general de Laravel |
| `storage/logs/planificacion-periodica.log` | Lo que imprimió cada corrida del cron |

- Para encontrar un incidente, buscar el **código que muestra la página de error** en
  `errores-*.log`.
- Los 4xx se anotan como `WARNING` y los 5xx como `ERROR`.
- Todo se ajusta en `.env` (`LOG_STACK`, `LOG_DAILY_DAYS`, `LOG_ERRORES_DAYS`, `LOG_LEVEL`) y,
  después, `php artisan config:clear`.

---

## 18. Pruebas

### 18.1 Qué hay

`php artisan test` corre **29 pruebas (244 aserciones)**, todas correctas:

| Archivo | Qué cubre |
| --- | --- |
| `tests/Unit/ExampleTest.php` | Prueba unitaria de ejemplo |
| `tests/Feature/ExampleTest.php` | La portada responde 200 |
| `tests/Feature/PlanificacionPeriodicaTest.php` | **27 pruebas**: la planilla (hojas, etiquetas, protección, celdas escribibles, listas desplegables con su flecha y la planilla de un período con una hoja por día), la corrida (crea solo lo que falta, **lee los campos que se eligen de una lista**, **lee los valores nativos de Excel**, no pisa un día cargado, vuelve a crear un día eliminado, salta la hoja sin cierre, ignora `INSTRUCCIONES` y `LISTAS`), el modo `--dry-run` (no escribe nada) y el módulo (abrir, descargar la planilla genérica y la de un período, montar, reemplazar y quitar de la cola) |

### 18.2 Cómo corren

`phpunit.xml` define dos suites (`Unit` y `Feature`) y un entorno propio: `APP_ENV=testing`,
`APP_URL=http://localhost`, **SQLite en memoria** (`DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:`), caché y sesión en `array`, cola `sync`. Es decir, **las pruebas no tocan
la base de datos de desarrollo**: cada prueba arma su esquema con las migraciones
(`RefreshDatabase`), siembra los catálogos y usa un disco falso (`Storage::fake`) para los
archivos de Excel.

```
php artisan test
# o
vendor/bin/phpunit
```

### 18.3 Estilo de código

`laravel/pint` está instalado (sin `pint.json`: usa el preset de Laravel):

```
vendor/bin/pint --test      # revisa
vendor/bin/pint             # corrige
```

### 18.4 Qué conviene probar cuando se agregue código

1. **Validación del día**: sin objetivos, sin franjas, sin cierre y con fecha duplicada.
2. **Creación**: que `createDay()` genere las 15 franjas, los 6 ítems y las 5 respuestas.
3. **Sincronización**: que quitar una franja la borre, que las notas se reemplacen y que un
   bloque vacío no cree una fila.
4. **Filtros**: fecha, energía, palabra clave y cada tipo de período.
5. **Rendimiento**: la fórmula con pesos y la redistribución cuando falta un componente.
6. **Salidas**: que el reporte devuelva un XLSX válido, el resumen un PDF y la imagen un JPG o
   ZIP; y que avisen por JSON cuando no hay datos.
7. **Importación**: hojas con fecha repetida, nombres inválidos, catálogos desconocidos y libros
   corruptos.

---

## 19. Solución de problemas

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| Estilos o JS no cargan (404 en `css/styles.css`) | La extensión del archivo no está en la lista del `.htaccess` de la raíz | Agregar la extensión a la regla de estáticos |
| "could not find driver" o "Connection refused" | MySQL apagado o credenciales distintas | Iniciar MySQL en XAMPP y revisar `DB_*`; luego `php artisan config:clear` |
| "Base table or view not found" | Faltan migraciones | `php artisan migrate` |
| El formulario queda sin objetivos, checklist ni preguntas | Catálogos sin sembrar o todos desactivados | `php artisan db:seed` y verificar `is_active = 1` |
| "Ya existe un diario con esa fecha." | Ya hay un diario para ese día | Abrirlo y modificarlo, o elegir otra fecha |
| Las fechas se guardan corridas un día | Zona horaria mal configurada | `APP_TIMEZONE=America/Caracas` y `php artisan config:clear` |
| El PDF no muestra el gráfico | GD no está habilitada | Habilitar `extension=gd` y reiniciar Apache |
| **No se genera la imagen del día** | Falta el conversor `mutool` | `php artisan mupdf:instalar` (o indicar `MUPDF_BIN`) |
| La imagen sale borrosa o pesa mucho | Resolución o calidad | Ajustar `MUPDF_RESOLUCION` / `MUPDF_CALIDAD` y borrar las imágenes guardadas del día para que se regeneren |
| El PDF o la imagen salen viejos | Están en la caché de documentos | Se regeneran solos al modificar el día; si no, borrar la carpeta del día en `storage/app/private/documentos/` |
| **No aparece la planilla para descargar** | Nunca se generó | `php artisan planificacion:plantilla` |
| **La corrida dice "No hay libros en la cola"** | No hay nada montado en `excel/jobs` | Montar el libro en el módulo |
| **Una hoja se salta siempre** | Le falta un campo obligatorio | Leer el motivo en el reporte del módulo, corregir esa hoja en el Excel y volver a montar el libro |
| **Un día no se carga y el Excel lo tiene bien** | Ese día ya existe en la base (se omite a propósito) | Editar el día desde *Consultar diario* |
| **Un día eliminado reaparece** | La corrida lo vuelve a crear desde el libro que sigue en la cola | Quitar la hoja del Excel y el libro de la cola |
| **La corrida no se ejecuta de noche** | El equipo estaba apagado, o la tarea no está creada | `schtasks /query /tn "PlanificadorDiario-PlanificacionPeriodica"`; la corrida siguiente rellena el atraso |
| El Excel no trae algunas columnas nuevas | Se agregó un ítem o pregunta sin tocar `COLUMNAS` | Añadir la columna en `ReporteDiarioExport::COLUMNAS` (mismo orden que `toReportArray()`) y regenerar la planilla |
| Error 403 al pedir `/storage/...` o `/.env` | Protección intencional | Es el comportamiento esperado |
| Aparece "La sesión expiró" (419) al guardar | Pasó demasiado tiempo desde que se abrió el formulario | Volver a abrir el diario y guardar; la página tiene el botón *Reintentar* |
| La página de error muestra un "Código de incidente" | Identificador con el que quedó anotado ese fallo | Buscarlo en `storage/logs/errores-AAAA-MM-DD.log` |
| «Class "Helper" not found» al abrir una vista o un parcial | La vista usa `Helper::` (forma corta) sin el `@use('App\Helpers\Helper')` **en su propio archivo**: cada vista compilada necesita el suyo, no lo hereda de la vista madre | Agregar el `@use` al inicio de ese archivo, o usar `\App\Helpers\Helper::` (que es lo que hacen las vistas de `diario/`) |
| Los logs crecen sin control | `LOG_LEVEL=debug` | En producción, `LOG_LEVEL=warning` y rotación de `storage/logs` |
| `php artisan test` falla en la portada | Falta `APP_URL=http://localhost` en `phpunit.xml` | Ya está configurado; si se cambia, las pruebas de HTTP con el proyecto en subcarpeta dan 404 |

---

## 20. Glosario

| Término | Significado en este sistema |
| --- | --- |
| **Diario / día** | Un registro de `daily_plans`: el plan y el cierre de una fecha |
| **Objetivo** | Una de las tres ranuras del día (1 Debo hacer, 2 Quiero hacer, 3 Algo para mí) |
| **Ranura (`slot`)** | Posición 1, 2 o 3 del objetivo dentro del día |
| **Franja del horario** | Una fila de "Mi horario de hoy": hora, actividad y si se cumplió |
| **Antes de empezar** | Checklist de lo que hace falta tener listo, con descripción libre por ítem |
| **Señal de procrastinación** | Pregunta de reflexión marcada en el día |
| **Bloque de acción** | Unidad de foco: duración (5/10/15/20 min), resultado esperado y tarea |
| **Cierre del día** | Los cuatro campos finales: logros, pendiente, cuándo y orgullo |
| **Foco** | Minutos totales de los bloques de acción del día |
| **Rendimiento** | Puntaje de 0 a 100 (horario 60 %, objetivos 30 %, preparativos 10 %) |
| **Punto crítico** | Cantidad de señales a partir de la cual el rendimiento baja respecto al promedio, con un umbral de 8 puntos |
| **Franja del día** | Madrugada (0-5), Mañana (6-11), Tarde (12-17), Noche (18-23) |
| **Marca de muestra** | Marca de agua diagonal `SPECIMEN` de los documentos de prueba |
| **Sobre JSON** | Estructura uniforme de las respuestas AJAX: `{ ok, message, data }` o `{ ok, message, errors }` |
| **Catálogo** | Tabla de valores seleccionables con `sort_order` e `is_active` |
| **Planificación periódica** | El módulo que carga un período de días desde un libro de Excel |
| **Hoja** | Una página del libro de Excel: es **un día**, y su nombre es la fecha |
| **Planilla** | El `.xlsx` en blanco que se descarga y se llena (hoja DIA + INSTRUCCIONES) |
| **Corrida** | La ejecución de `planificacion:importar`, todos los días a las 00:00 |
| **Hueco** | Un día del período que todavía no está en la base |
| **Checkpoint** | Hasta qué fecha el período está cubierto sin huecos y hasta dónde hay días cargados por adelantado |
| **Dry-run** | `--dry-run`: muestra qué haría la corrida sin escribir en la base ni mover archivos |
| **Libro descartado** | Un libro que se sacó de la cola a mano: no vuelve a procesarse |

---

## 21. Hitos de versiones, estado y hoja de ruta

### 21.1 Los hitos, de un vistazo

| Versión | Fecha | Hito | Estado |
| --- | --- | --- | --- |
| **1.0 (MVP)** | 05/10/2026 | La versión base: el planificador diario completo con sus reportes | Entregada |
| **1.02** | 06/10/2026 | Manejo de errores: página propia y bitácora detallada | Entregada |
| **1.5** | Octubre de 2026 | **Planificación periódica** y los documentos del día guardados | **Versión oficial actual** |

### 21.2 V 1.0 (MVP) — la versión base

**Qué trajo.** El sistema completo de registro y consulta, sobre el que se construyó todo lo
demás. Commit que la define: `ece6edd` «se implementa limpieza del grafico» (05/10/2026).

| Área | Entregable |
| --- | --- |
| Registro del día | Formulario único con las **ocho secciones** de la hoja, en tres modos: crear, ver y modificar |
| Mis 3 objetivos | Tres ranuras fijas con su tipo del catálogo, descripción y casilla *Cumplido* (con fecha de cumplimiento) |
| Antes de empezar | Checklist con los 6 ítems del catálogo y descripción libre por ítem |
| Mi horario de hoy | Tabla dinámica: agregar y quitar franjas, hora libre por fila y "marcar todas" |
| Procrastinación | Las 5 preguntas del catálogo con casilla de señal y respuesta |
| Bloque de acción | Duración (5/10/15/20 min), resultado esperado y tarea |
| Cierre del día | Logros, pendiente, cuándo y orgullo (los cuatro obligatorios) |
| Notas | Texto libre; cada línea no vacía es una nota con su orden |
| Alta, baja y modificación | Crear, ver, modificar y eliminar con transacción y borrado en cascada; **un diario por fecha** |
| Validación | Reglas y mensajes en español en el servidor (422 por campo) y en el navegador |
| Listado | DataTables en español, buscador, orden, paginación y cuatro acciones por fila |
| Búsqueda y filtros | Palabra clave, fecha, energía y **siete tipos de período**, combinables |
| Gráfico del día | Línea de tiempo SVG en el navegador y el mismo gráfico en PNG dentro del PDF |
| PDF del día | Hoja institucional en carta, con marca de agua `SPECIMEN` opcional |
| Reporte detallado | `.xlsx` de **25 columnas**, un diario por fila |
| Resumen de desempeño | PDF con números, **7 secciones redactadas**, tabla día por día y **3 gráficos** |
| Catálogos | 7 catálogos con `sort_order` e `is_active`, sembrados de forma idempotente |
| Navegación e identidad | Portada, menú lateral, cabecera con reloj, paleta institucional y diseño responsive |
| Publicación | Acceso desde la raíz del proyecto con `index.php` de reenvío y `.htaccess` con bloqueos |

**Números de la versión.** 14 tablas de dominio (7 catálogos + la eje + 6 hijas o de pivote),
19 migraciones aplicadas, 7 catálogos sembrados y 14 rutas de la aplicación.

### 21.3 V 1.02 — manejo de errores

**Qué trajo.** Una página de error propia para los códigos 3xx, 4xx y 5xx, la captura de los
errores HTTP reales de navegación y el **registro detallado de cada fallo** en un log diario.
Commit que la define: `6f82701` «se agrega manejo de errores» (06/10/2026).

| Cambio | Detalle |
| --- | --- |
| Página propia | `/error/{codigo}`, con el aviso institucional del código, agrupado por familia (300 azul, 400 naranja, 500 rojo) y **respondiendo con el estado que explica** |
| Captura real | Dos manejadores en `bootstrap/app.php`: uno para las excepciones HTTP navegadas y otro para los 500 (que solo usa la página propia con `APP_DEBUG=false`) |
| Bitácora | Canal `errores` con driver `daily`: `storage/logs/errores-AAAA-MM-DD.log`, con incidente, petición, excepción y traza recortada |
| Origen del registro | `manejador`, `controlador` (los `try/catch`) y `modelo` (las transacciones del diario) |
| Logs diarios | `LOG_STACK` pasa de `single` a `daily`, con `LOG_DAILY_DAYS=30` y `LOG_ERRORES_DAYS=60` |
| Sin cambios en AJAX | Las peticiones que esperan JSON siguen recibiendo JSON; la validación conserva su camino |
| Sin migraciones | La actualización no toca la base de datos ni agrega dependencias |

### 21.4 V 1.5 — planificación periódica (versión oficial actual)

**Qué trajo.** La carga de un período completo de días desde un libro de Excel, los documentos
del día guardados y reutilizables, pruebas automatizadas del sistema, un endurecimiento del disco
privado y la documentación completa. Base del código: commit `412743d` «se carga el cron»
(octubre de 2026).

**1. Planificación periódica.**

| Pieza | Qué es |
| --- | --- |
| `App\Excel\PlantillaDelPlanificador` | Genera la planilla `.xlsx` (hoja `DIA` para duplicar + `INSTRUCCIONES` al final) y conoce el mapa de etiquetas |
| `App\Planificacion\ImportadorDePlanificacion` | Lee el libro, resuelve los catálogos y decide qué hacer con cada hoja |
| `App\Planificacion\ArchivosDePlanificacion` | Las carpetas del disco privado (`excel/formato`, `jobs`, `procesados`, `descartados`, `errores`, `ejemplos`) |
| `planificacion:plantilla` | Crea o reemplaza la planilla genérica; con `--desde` y `--hasta`, la de un período (una hoja por día); con `--ejemplo`, un libro de prueba |
| `planificacion:importar` | La corrida: **solo crea los días que faltan**, omite los existentes y salta las hojas incompletas con su motivo |
| `--dry-run` | Muestra el plan de la corrida sin escribir en la base ni mover archivos |
| Módulo `/planificacion` | Descargar la plantilla, montar el libro, ver la cola y el reporte hoja por hoja, quitar un libro de la cola |
| Corrida automática | Todos los días a las 00:00 (`withoutOverlapping`), con la tarea del Programador de tareas en Windows o el cron de Laravel en Linux |
| Tablas nuevas | `planificacion_importaciones` (estado, contadores y checkpoint de cada libro) y `planificacion_hojas` (qué pasó con cada hoja) |

**2. Documentos del día guardados.** El PDF y su versión en imagen se generan una vez y se
reutilizan:

| Pieza | Qué es |
| --- | --- |
| `App\Documentos\DocumentoDelDiario` | Arma, guarda y sirve el PDF y la imagen; invalida la caché cuando el día cambia o se elimina |
| Imagen para compartir | JPG si el documento tiene una página, ZIP con una imagen por página si tiene más |
| `mutool` (MuPDF) | El conversor, instalado con `php artisan mupdf:instalar` y ajustable en `config/mupdf.php` (`MUPDF_RESOLUCION`, `MUPDF_CALIDAD`, `MUPDF_ENFOQUE`) |
| Ruta nueva | `GET /diario/{id}/imagen` y el modal de *Documentos del día* (PDF o imagen, con marca de agua opcional) |
| Formato de la hoja | Se agregó el detalle de los tres objetivos, el checklist, las franjas y el cierre al PDF, al Excel y a la imagen |

**3. Pruebas automatizadas.** Se pasó de "solo los ejemplos del esqueleto" a **29 pruebas (244
aserciones)**, todas correctas: `tests/Feature/PlanificacionPeriodicaTest.php` (17) cubre la
planilla, la corrida, el modo de revisión y el módulo; se corrigieron `phpunit.xml` (`APP_URL`) y
la prueba de la portada (`RefreshDatabase`), que fallaba desde el esqueleto.

**4. Seguridad.** El disco privado dejó de publicarse por HTTP (`serve => false` en
`config/filesystems.php`): los Excel de planificación, los PDF e imágenes de los diarios y el
binario de MuPDF solo salen por los controladores. Quedan pendientes los hallazgos de
[§16.3](#163-hallazgos-pendientes).

**5. Documentación.** Este README reescrito (hitos, módulos, modelo de datos, instalación, guía
de uso, seguridad, pruebas y mantenimiento) y el [LICENSE](LICENSE) del sistema.

**Qué no cambia la V 1.5.** No toca el formato de los datos ni las tablas del planificador (solo
agrega dos tablas nuevas de control), no cambia ninguna dirección que ya funcionaba y no agrega
dependencias de PHP. Lo que ya estaba cargado sigue igual.

### 21.5 Estado actual

**Implementado y funcionando:**

- Registro completo del día con las ocho secciones de la hoja institucional.
- Crear, ver, modificar y eliminar diarios, con validación en navegador y servidor.
- Listado con DataTables, búsqueda libre y filtros por fecha, energía y siete tipos de período.
- Línea de tiempo del día en pantalla y en el PDF.
- PDF del día e imagen para compartir (JPG o ZIP), con marca de agua opcional y caché.
- Reporte detallado en Excel con 25 columnas.
- Resumen de desempeño en PDF con números, redacción neutral, tabla día por día y tres gráficos.
- Planificación periódica: planilla estática, módulo con cola y reporte, corrida a las 00:00 y
  modo de revisión sin escritura.
- Catálogos sembrados y ampliables; publicación en la raíz del proyecto con bloqueos de seguridad.
- Página de errores propia y bitácora detallada.
- 29 pruebas automatizadas.

### 21.6 Deuda técnica conocida (impacto bajo)

- `package.json`, `vite.config.js`, `resources/css/app.css`, `resources/js/app.js`,
  `resources/js/bootstrap.js`, `resources/views/welcome.blade.php` y `database/database.sqlite`
  son restos del esqueleto: el pipeline de Vite no se usa y `welcome` no tiene ruta.
- `User`, `UserFactory` y la tabla `users` existen pero no se usan.
- Hay ayudantes declarados sin uso y dos variables CSS sin uso (`--tc-naranja-claro`,
  `--tc-arena`).
- `laravel/sail` está instalado sin `compose.yaml`, y `allow-plugins` habilita
  `pestphp/pest-plugin` aunque Pest no está instalado.
- Los scripts `composer setup` y `composer dev` invocan `npm install` / `npm run dev`, pasos que
  en este proyecto no aportan nada.
- `public/css/styles.css` pasa de 2000 líneas en un solo archivo; sus comentarios numerados ya
  marcan los cortes naturales si algún día conviene dividirlo.
- `composer.json` conserva los metadatos del esqueleto (ver [§22](#22-créditos-y-licencia)).

### 21.7 Mejoras propuestas (orden sugerido)

| Prioridad | Mejora | Por qué |
| --- | --- | --- |
| Alta | Autenticación y multiusuario (`user_id` en `daily_plans`) | Hoy cualquiera con la URL accede a todo, y la planificación periódica es más útil con varios participantes |
| Alta | Resolver los hallazgos de seguridad pendientes ([§16.3](#163-hallazgos-pendientes)) | `.tmp-chrome4` en git es el más urgente |
| Media | Administración de catálogos desde la interfaz | Evitar editar seeders (y regenerar la planilla) para cambiar una pregunta |
| Media | Más pruebas sobre el formulario y los filtros | El módulo nuevo ya está cubierto; falta el núcleo |
| Media | Aviso cuando ocurre un error 500 | Hoy el detalle queda en el log y nadie se entera en el momento |
| Media | Exportar el resumen también a Excel | Algunos destinatarios prefieren planilla |
| Media | Gráficos de evolución entre períodos | El resumen compara mitades dentro de un período |
| Baja | Recordatorio si el día de hoy no está registrado | Sostener el hábito |
| Baja | Adjuntar imágenes o archivos al día | Evidencia del trabajo |
| Baja | Limpieza de los restos del esqueleto | Menos ruido en el repositorio |
| Baja | Multi-idioma | Solo si el programa se extiende a otros países |

---

## 22. Créditos y licencia

- **Programa:** Programa de Desarrollo Personal "Transforma-Conecta".
- **Sistema:** Planificador Diario "Transforma-Conecta" — *Mi Planificador Diario*.
- **Versión:** 1.5 (sobre la base 1.0 MVP y la 1.02; el detalle de los hitos está en
  [§21](#21-hitos-de-versiones-estado-y-hoja-de-ruta)).
- **Marco de trabajo:** [Laravel 12](https://laravel.com) (licencia MIT). El README original del
  esqueleto fue reemplazado por esta documentación.
- **Librerías principales:** [barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf) ·
  [dompdf](https://github.com/dompdf/dompdf) · [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) ·
  [MuPDF](https://mupdf.com) (`mutool`, AGPL) · [Bootstrap](https://getbootstrap.com) ·
  [Bootstrap Icons](https://icons.getbootstrap.com) · [jQuery](https://jquery.com) ·
  [DataTables](https://datatables.net) · [bootbox.js](https://bootboxjs.com) ·
  [bootstrap-datepicker](https://github.com/uxsolutions/bootstrap-datepicker).
- **Tipografía de los documentos:** DejaVu Sans (gráficos) y Helvetica (texto del PDF).

**Licencia del proyecto.** [LICENSE](LICENSE) contiene la licencia completa del sistema: **The
Unlicense**, que libera el código al **dominio público**. Cualquiera puede copiar, modificar,
publicar, usar, compilar, vender y distribuir este software, para cualquier fin, comercial o no,
sin pedir permiso ni cumplir condiciones. El archivo está organizado en siete puntos:

| Punto | Contenido |
| --- | --- |
| 1. Identificación | Sistema, repositorio, versión, año, **qué cubre** la licencia (el código propio) y qué no (dependencias, `vendor/`, `.env`, los datos de cada persona y los artefactos de ejemplo) |
| 2. The Unlicense | El **texto oficial en inglés**, completo y sin cambios (es el que tiene valor legal) |
| 3. Qué significa | Resumen en español, informativo: libertad total, dominio público, sin condiciones y sin garantías |
| 4. Dependencias | Licencia declarada por cada componente de terceros |
| 5. Cómo aplicarla | Que no hace falta hacer nada para usarlo, cómo redistribuirlo y el aviso sugerido para los archivos fuente |
| 6. Metadatos | La diferencia pendiente: `composer.json` todavía declara `name: laravel/laravel` y `license: MIT` |
| 7. Registro | Cuándo y cómo se documentó la licencia |

> **Atención al redistribuir:** las dependencias mantienen sus propias licencias (Laravel,
> PhpSpreadsheet y Bootstrap son MIT; dompdf es LGPL-2.1; phpunit y mockery son BSD-3-Clause;
> bootstrap-datepicker es Apache-2.0; el binario `mutool` de MuPDF es AGPL y no viaja en el
> repositorio: se instala con `php artisan mupdf:instalar`).

---

<p align="center">
  <strong>MI PLANIFICADOR DIARIO</strong> · ORGÁNIZATE · ACTÚA · AVANZA<br>
  Programa de Desarrollo Personal "Transforma-Conecta"
</p>
