# THC Query

Panel web y conjunto de importadores para consultar, consolidar y explotar informacion de `theHunter Classic` en PostgreSQL.

Documentacion principal:
- resumen del proyecto en este `README`
- manual tecnico completo en [docs/MANUAL_TECNICO.md](C:\Users\Usuario\Documents\THC\THC_Query\docs\MANUAL_TECNICO.md)

El proyecto combina:
- importacion de expediciones, mejores marcas, clasificaciones, competiciones, estadisticas publicas y trofeos
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

12. `Anti-trampas`
    - score de riesgo derivado de expediciones
    - subtablas de senales y expediciones asociadas

13. `Logs`
    - solo visible para admin
    - consulta de archivos de log

14. `Consulta Avanzada`
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

Temas disponibles:
- `sober`
- `gaming`
- `arctic`
- `graphite`
- `midnight`
- `ember`
- `skyline`
- `terminal`
- `missions`

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
- `export_best_xml`
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

### Competiciones

```powershell
php src\run_import_competitions.php
```

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
- `refresh_expeditions_all_users`
- `export_best_xml`
- `scrape_kill_urls`
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
