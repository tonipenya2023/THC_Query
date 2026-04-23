# THC Query

Panel web y conjunto de importadores para consultar, consolidar y explotar informacion de `theHunter Classic` en PostgreSQL.

Documentacion principal:
- resumen del proyecto en este `README`
- manual tecnico completo en [docs/MANUAL_TECNICO.md](C:\Users\Usuario\Documents\THC\THC_Query\docs\MANUAL_TECNICO.md)
- manual de usuario en [docs/MANUAL_USUARIO.md](C:\Users\Usuario\Documents\THC\THC_Query\docs\MANUAL_USUARIO.md)

El proyecto combina:
- importacion de expediciones, mejores marcas, clasificaciones, competiciones, estadisticas publicas y trofeos
- importacion de galerias de usuarios
- vistas SQL modulares sobre el esquema `gpt`
- panel web con filtros avanzados, subtablas, exportacion y tareas programadas
- utilidades de soporte como traduccion de descripciones y scraping de URLs de muertes

## Estado actual

El codigo esta orientado a:
- Windows
- Apache/XAMPP
- PHP CLI en `C:\xampp\php\php.exe`
- PostgreSQL con esquema `gpt`

No usa Composer ni framework. La aplicacion principal vive en [public/index.php](C:\Users\Usuario\Documents\THC\THC_Query\public\index.php) y el soporte comun en [src/web_bootstrap.php](C:\Users\Usuario\Documents\THC\THC_Query\src\web_bootstrap.php).

## Funcionalidad principal

El panel ofrece actualmente estas consultas:

1. `Panel`
   - ejecucion manual de procesos
   - seguimiento de tareas
   - configuracion de tareas programadas

2. `Expediciones`
   - filtro por usuario, reserva, fechas, especie, muerte y disparo
   - subtabla de muertes y disparos
   - resumen de totales de expediciones, muertes y foto/taxidermia

3. `Mejores Marcas`
   - ranking y mejores registros por especie
   - filtro por jugador y especie
   - acceso a `Comparativa Mejores Marcas` mediante boton interno

4. `Comparativa Mejores Marcas`
   - lectura del XML exportado
   - comparativa entre jugadores por puntuacion y distancia
   - vista accesible desde `Mejores Marcas`, no desde el menu lateral

5. `Estadisticas`
   - estadisticas publicas de usuarios
   - subtablas de especies, armas, coleccionables y misiones

6. `Competiciones`
   - consulta de competiciones, tipos, especies y premios
   - traduccion de descripcion corta a `description_es`

7. `Tablas Clasificacion`
   - ranking actual por especie y tipo
   - acceso a `Tablas Clasificacion Hist.` mediante boton interno

8. `Tablas Clasificacion Hist.`
   - comparacion entre snapshots historicos
   - vista accesible desde `Tablas Clasificacion`, no desde el menu lateral

9. `Especies PPFT`
   - consulta auxiliar de especies con campos agregados de fotos y taxidermias

10. `Salones Fama`
    - consulta parametrica sobre la tabla o vista configurada para hall of fame

11. `Resumen Trofeos`
    - conteo de trofeos `gold`, `silver` y `bronze`
    - despliegue de detalle por usuario y medalla

12. `Galerias Usuarios`
    - consulta de imagenes de la galeria publica por usuario
    - miniaturas, enlaces a imagen original y acceso a la muerte asociada

13. `Inscripciones Competiciones`
    - historico de intentos de inscripcion en competiciones
    - filtro por jugador, competicion, estado, metodo y parametro usado
    - acceso directo a la competicion

14. `Detalle Muertes`
    - detalle scrapeado de la ficha publica/autenticada de muerte
    - campos extraidos como cazador, arma, visor, municion, distancia, estado, parte del cuerpo, postura, plataforma y lugar del disparo
    - acceso directo a la ficha de muerte

15. `Anti-trampas`
    - score de riesgo derivado de expediciones
    - subtablas de senales y expediciones asociadas

16. `Logs`
    - solo visible para admin
    - consulta de archivos de log

17. `Consulta Avanzada`
    - exploracion generica de tablas del esquema con filtro simple por columna

## Patron de interfaz

La UI del proyecto sigue estas reglas generales:

- filtros reordenables por drag and drop
- tooltips descriptivos en filtros y controles
- columnas visibles configurables en tablas y subtablas
- combos multiseleccion con checkbox para filtros enumerados relevantes
- combos simples personalizados para selectores enumerados de una sola opcion
- formatos de fecha visibles en `dd/mm/yyyy hh:mm:ss`
- campos numericos alineados a la derecha
- temas visuales seleccionables
- fuentes seleccionables
- cabeceras de tablas con separadores de campos y flechas de orden en todas las consultas
- redimensionado manual de columnas desde la cabecera de cualquier tabla
- renombrado persistente de cabeceras con `Ctrl+Shift+Click`, compartido entre temas en el mismo navegador

Temas disponibles:
- `sober`
- `aurora`
- `arctic`
- `studio`
- `lagoon`
- `sandstone`
- `skyline`
- `terminal`
- `noir`

Fuentes disponibles:
- `system`
- `modern`
- `classic`
- `serif`
- `mono`

La seleccion de tema y fuente se conserva por cookie.

## Autenticacion y permisos

La aplicacion usa autenticacion basica HTTP.

### Admin

El admin se valida con variables de entorno:
- `THC_PANEL_USER`
- `THC_PANEL_PASS`

El admin puede:
- ver todo el panel
- ejecutar todas las acciones
- modificar tareas programadas
- acceder a la vista de logs

### Usuarios normales

Un jugador puede autenticarse si:
- existe en los catalogos detectados por `app_panel_player_exists()`
- usa la contrasena por defecto definida en `THC_PANEL_DEFAULT_PASS`
- o tiene una contrasena personalizada almacenada en `var/panel_passwords.json`

Catalogos usados para detectar jugadores:
- `users`
- `gpt.users_public`
- `gpt.user_public_stats`

Cambio de contrasena:
- endpoint: [public/change_password.php](C:\Users\Usuario\Documents\THC\THC_Query\public\change_password.php)
- la nueva contrasena debe tener al menos 6 caracteres

### Permisos de ejecucion de tareas para no admin

Segun [src/TaskCatalog.php](C:\Users\Usuario\Documents\THC\THC_Query\src\TaskCatalog.php), un usuario no admin puede ejecutar:
- `refresh_competitions`
- `refresh_leaderboards`
- `refresh_best_all`
- `refresh_public_all`
- `refresh_trophies_all`
- `refresh_gallery_all`
- `join_all_competitions`
- `export_best_xml`
- `refresh_my_expeditions`

No admin no puede ejecutar:
- `refresh_expeditions_all_users`
- `scrape_kill_urls`
- cualquier accion no incluida en la lista anterior
- `refresh_my_expeditions`

No admin no puede ejecutar:
- `refresh_expeditions_all_users`
- `scrape_kill_urls`
- cualquier accion no incluida en la lista anterior

## Requisitos

- Windows
- PHP 8 con extensiones necesarias para:
  - `PDO`
  - `pdo_pgsql`
  - `json`
  - `mbstring`
- PostgreSQL
- acceso HTTP saliente a:
  - `api.thehunter.com`
  - `www.thehunter.com`
  - `translate.googleapis.com`
- Python disponible en `PATH` para la exportacion del XML de mejores marcas

## Configuracion

La configuracion base esta en [src/config.php](C:\Users\Usuario\Documents\THC\THC_Query\src\config.php) y se alimenta por variables de entorno.

### Base de datos

- `THC_DB_DSN`
- `THC_DB_USER`
- `THC_DB_PASSWORD`
- `THC_DB_SCHEMA`

Valores por defecto actuales:
- DSN: `pgsql:host=localhost;port=5432;dbname=test`
- usuario: `postgres`
- password: `system`
- esquema: `gpt`

### Panel

- `THC_PANEL_USER`
- `THC_PANEL_PASS`
- `THC_PANEL_DEFAULT_PASS`

Si `THC_PANEL_DEFAULT_PASS` no esta definido, el valor por defecto es `thcgpt`.

### API

- `THC_API_USER_AGENT`
- `THC_API_TIMEOUT`

### Sesion theHunter para inscripcion en competiciones

El proceso `Inscribirme en Competiciones` usa el endpoint real `https://api.thehunter.com/v1/Competition/join`, que requiere sesion autenticada de theHunter.

Variables soportadas:
- `THC_THEHUNTER_COOKIE`
- `THC_THEHUNTER_COOKIE_<USUARIO>`

Ejemplo:
- `THC_THEHUNTER_COOKIE_NEFASTIX13`

El valor debe ser la cabecera `Cookie` completa de una sesion valida de theHunter, por ejemplo:

```text
session=...; other_cookie=...
```

Validacion aplicada en el panel:
- no acepta texto sin `=`
- no acepta cookies separadas por `,`
- cada parte debe tener formato `nombre=valor`
- si hay varias, deben ir separadas por `;`

Comportamiento:
- el boton del panel lanza el proceso para el usuario autenticado actual
- primero intenta usar la cookie especifica del usuario
- si no existe, usa `THC_THEHUNTER_COOKIE`
- despues intenta registrar al usuario en todas las competiciones activas
- el detalle queda en el log de la tarea
- por defecto, el boton del panel salta las competiciones ya intentadas previamente

### Leaderboards

- `THC_LB_SCORE_TEMPLATES`
- `THC_LB_RANGE_TEMPLATES`

Si no se definen, se usan varias plantillas por defecto para tolerar variantes del API.

## Preparacion inicial

### 1. Aplicar esquema y vistas

```powershell
php src\apply_schema.php
```

Este comando aplica todos los SQL de `ddl\`:
- [ddl/001_schema.sql](C:\Users\Usuario\Documents\THC\THC_Query\ddl\001_schema.sql)
- [ddl/002_module_views.sql](C:\Users\Usuario\Documents\THC\THC_Query\ddl\002_module_views.sql)

### 2. Verificar catalogos base

El proyecto espera disponer de catalogos como:
- `gpt.species`
- `gpt.users`
- `gpt.reservas`

El propio esquema crea alias tipo vista si existen esas tablas:
- `gpt.tab_especies`
- `gpt.tab_usuarios`
- `gpt.tab_reservas`

Muchos importadores consumen `gpt.tab_usuarios`, por lo que ese catalogo debe existir y estar poblado.

### 3. Publicar la carpeta web

Servir la carpeta `public\` desde Apache o equivalente.

Punto de entrada:
- [public/index.php](C:\Users\Usuario\Documents\THC\THC_Query\public\index.php)

## Procesos CLI

### Expediciones

Importar expediciones para todos los usuarios de `gpt.tab_usuarios`:

```powershell
php src\run_import_users.php
php src\run_import_users.php --page-size=40
php src\run_import_users.php --player=TheBubb
php src\run_import_users.php --user-id=12345
php src\run_import_users.php --force
```

Importar expediciones de un solo usuario:

```powershell
php src\run_import_user.php 12345 40
```

### Mejores marcas

```powershell
php src\run_import_best_users.php
php src\run_import_best_users.php --player=TheBubb
php src\run_import_best_users.php --user-id=12345
php src\run_import_best_users.php --season=0
```

### Estadisticas publicas

```powershell
php src\run_import_users_public_stats.php
php src\run_import_users_public_stats.php --player=TheBubb
```

### Trofeos

```powershell
php src\run_import_users_trophies.php
php src\run_import_users_trophies.php --player=TheBubb
php src\run_import_users_trophies.php --user-id=12345
php src\run_import_users_trophies.php --page-size=24
```

### Galerias de usuario

```powershell
php src\run_import_users_gallery.php
php src\run_import_users_gallery.php --player=TheBubb
php src\run_import_users_gallery.php --user-id=12345
php src\run_import_users_gallery.php --page-size=24
php src\run_import_user_gallery.php 12345 24
```

### Competiciones

```powershell
php src\run_import_competitions.php
php src\run_join_all_competitions.php --player=TheBubb
php src\run_join_all_competitions.php --player=TheBubb --skip-attempted
```

Antes de intentar la inscripcion, `run_join_all_competitions.php` valida si el jugador cumple el tramo de muertes de la especie de la competicion usando `gpt.est_animal_stats`.

- `Starter`: `0-50` muertes
- `Intermediate`: `51-500` muertes
- `Elite`: mas de `500` muertes

Si no cumple el tramo, no se llama a la API de alta y el resultado queda guardado en `gpt.comp_join_results` con estado `ineligible` y el motivo exacto.

### Scraper de URLs de muertes

```powershell
php src\run_scrape_kill_urls.php --from=all --pending-only --limit=5000 --sleep-ms=100
php src\run_scrape_kill_urls.php --from=clas --pending-only
php src\run_scrape_kill_urls.php --from=exp --all --limit=1000
```

Notas:

- El modo por defecto es `--pending-only`, para no repetir URLs ya descargadas correctamente.
- Ese modo incremental tambien vuelve a procesar las URLs antiguas que aun no tengan metadatos parseados.
- La tarea del panel usa ese modo incremental y un limite de `5000` por ejecucion.
- El script guarda trazas en `gpt.scrape_kill_urls` y las paginas descargadas en `out/kill_url_scrape/pages`.
- La consulta del panel `Estado Scraper` usa `gpt.v_scrape_kill_urls_latest` y muestra el ultimo estado por URL con resumen de parseo.
- Limitacion tecnica actual: las URLs con hash de theHunter como `#animal/...` o `#profile/.../score/...` devuelven en descarga directa la portada publica/base del sitio. El parser detecta y registra ese caso como `public_home_not_signed_in`.

### Scraper de detalle de muertes

```powershell
php src\run_scrape_kill_details.php --cookie-player=nefastix13 --pending-only --limit=50
php src\run_scrape_kill_details.php --player=TheBubb --cookie-player=nefastix13 --pending-only --limit=20
php src\run_scrape_kill_details.php --cookie-player=nefastix13 --all --limit=10
```

Notas:

- Usa navegador Edge en modo headless para cargar la ficha real autenticada de theHunter.
- Requiere una cookie valida guardada en `var\thehunter_cookies.json`.
- Guarda el resultado en `gpt.kill_detail_scrapes`.
- La vista del panel `Detalle Muertes` usa `gpt.v_kill_detail_scrapes_latest`.
- Si la pagina no trae el texto ya resuelto, aplica respaldo por IDs y enums para arma, municion, visor, estado animal, postura, plataforma y tipo.
- En `Expediciones`, los campos scrapeados se integran asi:
  - `Muertes`: `Tiempo herida`, `Lugar disparo`
  - `Disparos`: `Cazador`, `Arma`, `Visor`, `Municion`, `Distancia disparo`, `Estado animal`, `Parte cuerpo`, `Postura`, `Plataforma`
  - No se duplican en `Muertes` los campos ya existentes como `Peso`, `Tipo`, `Integridad`, `Trophy score` o `Valor de la captura`.
- Existe accion catalogada `scrape_kill_details`.
- La tarea programada soporta `scrape_kill_details` y, cuando se ejecuta con un usuario autenticado, usa su cookie guardada y lanza:
- La tarea programada de `scrape_kill_details` permite configurar un `Jugador` desde el panel. Si se informa, procesa todas las muertes pendientes de ese jugador y usa:
- El scraper de detalle ya no abre un navegador por cada muerte. Procesa las URLs por lotes dentro de una sola sesion de Edge headless para reducir mucho el tiempo total de ejecucion.
- El runner PHP agrupa candidatos en bloques y llama una sola vez al script `src/scrape_kill_detail_browser.mjs` por lote.

```powershell
php src\run_scrape_kill_details.php --player=<usuario> --cookie-player=<usuario> --pending-only
```

- Campos extraidos actualmente:
  - `Cazador`
  - `Arma`
  - `Visor`
  - `Municion`
  - `Distancia del Disparo`
  - `Estado del animal`
  - `Parte del cuerpo`
  - `Postura`
  - `Plataforma`
  - `Lugar donde se realizo el tiro del jugador`
  - `Peso`
  - `Tipo`
  - `Tiempo de la herida`
  - `Integridad del trofeo`
  - `Disparos`
  - `Tiempo de Captura`
  - `Trophy score`
  - `Valor de la captura`

### Traduccion de descripciones de competicion

```powershell
php src\run_translate_competition_descriptions.php
php src\run_translate_competition_descriptions.php --force
php src\run_translate_competition_descriptions.php --from=en --to=es
```

La traduccion se guarda en `gpt.comp_types.description_es`.

### Leaderboards globales

```powershell
php src\run_import_leaderboards.php --type=both --limit=100 --species-source=table
php src\run_import_leaderboards.php --type=score
php src\run_import_leaderboards.php --type=range
php src\run_import_leaderboards.php --species-id=12
```

### Scraper de URLs de muertes

```powershell
php src\run_scrape_kill_urls.php --from=all --sleep-ms=200
php src\run_scrape_kill_urls.php --from=exp
php src\run_scrape_kill_urls.php --from=clas
php src\run_scrape_kill_urls.php --limit=100 --offset=0
```

Origenes admitidos:
- `all`
- `exp`
- `clas`

Salidas:
- `out\kill_url_scrape\results.csv`
- `out\kill_url_scrape\pages\*.html.gz`
- tabla `gpt.scrape_kill_urls`

### Exportar XML de mejores marcas

```powershell
python export_best_excel.py --out out\best_all.xml
```

Descarga desde panel:
- [public/download.php](C:\Users\Usuario\Documents\THC\THC_Query\public\download.php)

## Tareas programadas

El panel soporta ejecucion manual y programada.

Archivos implicados:
- [src/TaskCatalog.php](C:\Users\Usuario\Documents\THC\THC_Query\src\TaskCatalog.php)
- [src/TaskManager.php](C:\Users\Usuario\Documents\THC\THC_Query\src\TaskManager.php)
- [src/TaskScheduleManager.php](C:\Users\Usuario\Documents\THC\THC_Query\src\TaskScheduleManager.php)
- [public/task_runner.php](C:\Users\Usuario\Documents\THC\THC_Query\public\task_runner.php)
- [public/task_schedule_save.php](C:\Users\Usuario\Documents\THC\THC_Query\public\task_schedule_save.php)

Acciones catalogadas actualmente:
- `refresh_competitions`
- `refresh_leaderboards`
- `refresh_best_all`
- `refresh_public_all`
- `refresh_trophies_all`
- `refresh_gallery_all`
- `join_all_competitions`
- `refresh_expeditions_all_users`
- `export_best_xml`
- `scrape_kill_urls`
- `scrape_kill_details`
- `refresh_my_expeditions`

Persistencia:
- configuracion programada: `var\task_schedules.json`
- control de tick: `var\task_schedule_tick.json`
- estado por tarea: `var\tasks\*.json`
- log por tarea: `logs\tasks\*.log`

Notas importantes:
- el guardado de tareas programadas es solo para admin
- la ejecucion asyncrona usa `cmd /c start` y esta orientada a Windows
- el path a PHP CLI esta fijado en varios puntos a `C:\xampp\php\php.exe`
- `join_all_competitions` ya puede ejecutarse tambien como tarea programada
- cuando se programa, usa el usuario autenticado actual y lanza:
  - `php src/run_join_all_competitions.php --player=<usuario> --skip-attempted`

## Estructura del proyecto

```text
THC_Query/
|-- ddl/
|   |-- 001_schema.sql
|   `-- 002_module_views.sql
|-- docs/
|   `-- MANUAL_TECNICO.md
|-- logs/
|   |-- tasks/
|   `-- sessions/
|-- out/
|-- public/
|   |-- assets/
|   |-- change_password.php
|   |-- download.php
|   |-- index.php
|   |-- logout.php
|   |-- style.css
|   |-- task_create.php
|   |-- task_runner.php
|   |-- task_schedule_save.php
|   |-- task_stop.php
|   `-- task_view.php
|-- src/
|   |-- apply_schema.php
|   |-- config.php
|   |-- web_bootstrap.php
|   |-- Importer.php
|   |-- BestImporter.php
|   |-- CompetitionImporter.php
|   |-- GlobalLeaderboardsImporter.php
|   |-- TrophyImporter.php
|   |-- UserPublicStatsImporter.php
|   |-- TaskCatalog.php
|   |-- TaskManager.php
|   `-- TaskScheduleManager.php
`-- var/
```

## Tablas y vistas relevantes

### Tablas base principales

- `gpt.exp_expeditions`
- `gpt.exp_kills`
- `gpt.exp_hits`
- `gpt.best_personal_records`
- `gpt.user_public_stats`
- `gpt.user_trophies`
- `gpt.user_gallery`
- `gpt.est_profiles`
- `gpt.est_animal_stats`
- `gpt.est_weapon_stats`
- `gpt.est_collectables`
- `gpt.est_daily_missions`
- `gpt.comp_types`
- `gpt.comp_competitions`
- `gpt.comp_type_species`
- `gpt.comp_type_prizes`
- `gpt.comp_type_rewards`
- `gpt.clas_rankings_latest`
- `gpt.clas_rankings_history`

### Vistas modulares

- `gpt.v_exp_expediciones`
- `gpt.v_best_records`
- `gpt.v_est_publicas`
- `gpt.v_comp_competiciones`
- `gpt.v_clas_latest`
- `gpt.v_clas_historico`
- `gpt.v_user_trophies_summary`
- `gpt.v_exp_cheat_risk`
- `gpt.v_exp_cheat_signals`
- `gpt.v_exp_cheat_signal_expeditions`

## Logs y salidas

Logs habituales:
- `logs\import_users_errors.log`
- `logs\best_import_errors.log`
- `logs\user_public_stats_errors.log`
- `logs\import_users_trophies_errors.log`
- `logs\import_users_gallery_errors.log`
- `logs\scrape_kill_urls.log`
- `logs\tasks\*.log`

Sesiones:
- `logs\sessions\`

Salidas habituales:
- `out\best_all.xml`
- `out\kill_url_scrape\results.csv`
- `out\kill_url_scrape\pages\`

## Observaciones tecnicas

- El panel trabaja sin framework, con logica PHP renderizada directamente.
- Hay ficheros de respaldo historicos en `public\` que no forman parte del runtime principal.
- Algunas rutas y procesos estan claramente acoplados a entorno local Windows/XAMPP. Si se migra a otro entorno, conviene revisar:
  - paths de PHP
  - lanzado de tareas en background
  - permisos de escritura en `logs\`, `out\` y `var\`

## Regla de mantenimiento documental

Siempre que se haga un cambio funcional o tecnico en el proyecto, se debe actualizar `README.md`.

Ademas:

- si el cambio afecta a arquitectura, permisos, runtime, tareas, despliegue o estructura interna, tambien se actualiza [docs/MANUAL_TECNICO.md](C:\Users\Usuario\Documents\THC\THC_Query\docs\MANUAL_TECNICO.md)
- los cambios de logs deben reflejar rutas bajo `logs\`
- los temporales de sesion deben reflejar rutas bajo `logs\sessions\`

## Comprobacion rapida despues de cambios

```powershell
php -l public\index.php
php -l src\web_bootstrap.php
php -l src\TaskCatalog.php
php -l src\TaskManager.php
php -l src\TaskScheduleManager.php
```

## Proxima limpieza recomendada

Aunque el proyecto funciona, seria sano abordar estas mejoras cuando toque:

1. Centralizar configuracion de paths de PHP y Python.
2. Separar mejor renderizado web, logica de consultas y logica de UI.
3. Consolidar menu y vistas relacionadas para evitar duplicidad conceptual.
4. Agregar un fichero de ejemplo de variables de entorno o script de arranque.

- Manual de usuario editable Word: `docs/MANUAL_USUARIO.docx`
- Manual de usuario PDF: `docs/MANUAL_USUARIO.pdf`
- Manual de usuario revisado Word: `docs/MANUAL_USUARIO_v2.docx`
- Manual de usuario revisado PDF: `docs/MANUAL_USUARIO_v2.pdf`

- La vista de tarea (public/task_view.php) se refresca automaticamente cada 2 segundos mientras la tarea esta en cola o en ejecucion, y el scraper de detalle fuerza flush inmediato del log para que el contenido aparezca durante la ejecucion.


- La barra lateral izquierda queda fija durante el scroll en escritorio y mantiene visible toda la botonera; en movil vuelve a flujo normal.
- Las tablas permiten reordenar columnas arrastrando la cabecera. El orden queda guardado en localStorage por vista/tabla y convive con el redimensionado y el renombrado persistente.


- Cada consulta guarda de forma persistente su estado en el navegador: filtros, columnas visibles, orden de columnas, orden de filtros, ancho de columnas y nombres personalizados.
- El boton Limpiar solo elimina filtros persistidos de la consulta actual. No reinicia columnas visibles, orden, anchos ni renombrados.
- Si el HTML renderizado no coincide con el estado persistido, la pagina se resincroniza automaticamente una sola vez para que checks y tabla queden alineados.


- La persistencia del orden de columnas usa una clave estable por columna y tabla, independiente de la posicion actual, para que el orden arrastrado se mantenga tras recargar o volver a filtrar.


- La persistencia de nombres de cabecera, orden y ancho de columnas se asocia a una firma estable de la tabla (vista, bloque y columnas), no al indice visual de renderizado, para que no se pierda al filtrar o cambiar el numero de filas/subtablas.


- La firma persistente de cada tabla ya no depende del orden visible ni del texto editado de las cabeceras; usa claves estables de columna y firma ordenada para conservar nombres personalizados despues de filtrar o reordenar.


- Se anade la vista tecnica ?view=table_styles_preview para comparar estilos reales de tablas de datos en HTML antes de aplicar uno al tema principal.


- Se aplica el estilo global de tablas Acero compacto: cabeceras acero azul, filas claras alternas, separadores visibles y contraste alto para datos.


- Los botones y enlaces de accion (button y .btn-link) permiten editar texto con Ctrl+Shift+Click; el texto queda persistido en localStorage por vista/formulario igual que las cabeceras.


- La edicion persistente con Ctrl+Shift+Click tambien aplica a los botones/enlaces de navegacion de la barra lateral izquierda.


- El orden de botones de la barra lateral se guarda siempre por data-nav-key, no por texto visible; tambien se persiste al finalizar cualquier arrastre y antes de salir de la pagina.


- La persistencia de textos de botones usa la clave `thc_button_labels_v2`, invalida datos antiguos duplicados y genera identidad unica por navegacion, accion real, preset, tarea, href o control para que cambiar un boton no duplique automaticamente el texto en otros botones.



- La personalizacion de interfaz ya no queda solo en el navegador: textos de botones, textos de cabeceras, anchos de columnas, orden de columnas, orden de botones laterales y columnas visibles se guardan de forma global en `gpt.ui_preferences` mediante `public/ui_preferences.php`, por lo que el cambio aplica a todos los usuarios.
- Los filtros de consulta siguen siendo locales del navegador para no cambiar automaticamente la busqueda del resto de usuarios; el boton Limpiar solo borra filtros.

- Los botones y enlaces de accion se pueden redimensionar arrastrando el tirador de la esquina inferior derecha; el tamano queda guardado de forma global para todos los usuarios en `gpt.ui_preferences`.
- Se puede insertar texto persistente antes de cualquier boton con `Shift+Alt+Click` sobre el boton; dejar el texto vacio elimina el separador. Aplica tambien a la barra lateral.
- Los nombres de tareas programadas y recientes se pueden renombrar con `Ctrl+Shift+Click`; el cambio queda guardado globalmente.

- Los botones redimensionados mantienen el texto centrado vertical y horizontalmente, incluyendo enlaces boton y botones de navegacion lateral.

- El tamano personalizado de un boton se puede restaurar al defecto con doble click en el tirador de redimensionado o con `Ctrl+Alt+Click` sobre el boton.

- En botones redimensionados el texto queda centrado solo en altura; horizontalmente queda alineado a la izquierda.

- Los botones de Procesos centran el texto en altura y anchura.
- El tamano de botones se agrupa por zona: al redimensionar un boton de Procesos se actualizan todos los botones de Procesos; al redimensionar un boton de la barra lateral se actualizan todos los botones de la barra lateral.

- El panel superior muestra mas indicadores de datos procesados: expediciones, muertes, disparos, mejores marcas, usuarios, perfiles EST, trofeos, galeria, competiciones, inscripciones, clasificaciones, historico, URLs de muertes y detalle del scraper.
- Los indicadores del panel se reducen de tamano y se ajustan a contenido para mostrar mas informacion sin ocupar tanto espacio.

- El espaciado entre botones se mantiene fijo al redimensionar: `Procesos` usa flex con `gap` estable y los botones redimensionados no estiran el espacio entre ellos.

- El orden de la botonera lateral se aplica tambien en servidor antes de pintar la pagina, usando `thc_sidebar_nav_order`, para evitar el salto visual donde primero aparecia el orden original y despues el orden guardado.
