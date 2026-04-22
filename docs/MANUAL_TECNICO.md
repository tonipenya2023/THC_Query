# Manual Tecnico THC Query

## Objetivo

THC Query es una aplicacion web en PHP orientada a consolidar y consultar informacion de `theHunter Classic` en PostgreSQL. El sistema combina:

- importadores CLI para cargar datos desde APIs, paginas publicas y perfiles
- un panel web de consulta con filtros, subtablas, exportacion y tareas
- una capa SQL en el esquema `gpt`
- procesos programados y utilidades auxiliares

Este documento describe la arquitectura, los modulos, el runtime y los criterios de mantenimiento.

## Arquitectura general

La aplicacion esta organizada en cuatro bloques:

1. `public/`
   - interfaz web
   - autenticacion
   - ejecucion de tareas desde el panel
   - recursos visuales y estilos

2. `src/`
   - bootstrap comun
   - acceso a configuracion
   - importadores CLI
   - gestion de tareas programadas y ejecuciones manuales

3. `ddl/`
   - definicion del esquema `gpt`
   - vistas modulares consumidas por el panel

4. `logs/`, `out/`, `var/`
   - `logs/`: logs y sesiones
   - `out/`: salidas generadas por exportaciones o scrapers
   - `var/`: ficheros de estado interno y persistencia ligera

## Punto de entrada web

El punto de entrada principal es [public/index.php](C:\Users\Usuario\Documents\THC\THC_Query\public\index.php).

Responsabilidades principales:

- validar autenticacion con `app_require_panel_auth()`
- resolver `view`
- construir filtros y ordenar resultados
- renderizar consultas principales y subtablas
- delegar utilidades comunes a [src/web_bootstrap.php](C:\Users\Usuario\Documents\THC\THC_Query\src\web_bootstrap.php)

El panel no usa framework. El renderizado es server-side PHP con HTML directo.

## Bootstrap comun

Archivo principal: [src/web_bootstrap.php](C:\Users\Usuario\Documents\THC\THC_Query\src\web_bootstrap.php)

Responsabilidades:

- carga de configuracion
- acceso PDO
- helpers HTML
- autenticacion del panel
- gestion de cookies de tema y fuente
- utilidades de consultas y listas de filtros
- arranque de sesion
- inicializacion y migracion de artefactos de runtime

### Runtime y directorios

El bootstrap centraliza rutas de trabajo:

- logs de aplicacion en `logs/`
- logs de tareas en `logs/tasks/`
- sesiones en `logs/sessions/`
- salidas de scraping y exportacion en `out/`
- ficheros de estado en `var/`

Hay logica de migracion para mover artefactos antiguos desde `var/` a `logs/`.

## Modulos funcionales del panel

### 1. Panel

Vista de control operativo.

Incluye:

- botones de ejecucion manual de procesos
- tabla de tareas programadas
- guardado de activacion e intervalo

Restricciones:

- solo admin puede modificar la programacion
- solo admin puede ver logs
- ciertos procesos no se muestran a usuarios no admin

Archivos implicados:

- [public/index.php](C:\Users\Usuario\Documents\THC\THC_Query\public\index.php)
- [src/TaskCatalog.php](C:\Users\Usuario\Documents\THC\THC_Query\src\TaskCatalog.php)
- [src/TaskManager.php](C:\Users\Usuario\Documents\THC\THC_Query\src\TaskManager.php)
- [src/TaskScheduleManager.php](C:\Users\Usuario\Documents\THC\THC_Query\src\TaskScheduleManager.php)
- [public/task_runner.php](C:\Users\Usuario\Documents\THC\THC_Query\public\task_runner.php)
- [public/task_schedule_save.php](C:\Users\Usuario\Documents\THC\THC_Query\public\task_schedule_save.php)

### 2. Expediciones

Consulta principal sobre expediciones, muertes y disparos.

Caracteristicas:

- filtros reordenables
- combos multiseleccion checkbox para especies y otros catalogos enumerados
- tabla principal de expediciones
- despliegue de detalle de muertes y disparos
- iconos de especies y genero
- totales superiores con recuadro resaltado

Tablas principales:

- `gpt.exp_expeditions`
- `gpt.exp_kills`
- `gpt.exp_hits`

### 3. Mejores Marcas

Consulta de records personales.

Caracteristicas:

- filtros por jugador, especie, rank y metricas
- top por puntuacion y distancia
- comparativa integrada como boton interno
- enlaces a ficha de muerte cuando la URL esta disponible

Tabla principal:

- `gpt.best_personal_records`

### 4. Comparativa Mejores Marcas

Vista basada en `out/best_all.xml`.

Caracteristicas:

- lectura del XML exportado
- comparacion de varios jugadores
- icono principal de especie
- enlaces a ficha de muerte al pulsar valores

Ya no aparece como opcion independiente del menu lateral. Se accede desde `Mejores Marcas`.

### 5. Estadisticas

Consulta de estadisticas publicas de jugadores.

Incluye subtablas de:

- especies
- armas
- coleccionables
- misiones

Tablas principales:

- `gpt.est_profiles`
- `gpt.est_animal_stats`
- `gpt.est_weapon_stats`
- `gpt.est_collectables`
- `gpt.est_daily_missions`

### 6. Competiciones

Consulta de competiciones, tipos, especies y premios.

Caracteristicas:

- detalle principal debajo
- subtablas laterales para especies y recompensas
- descripcion traducida en `description_es`
- filtros reordenables y columnas visibles por subtabla

Tablas principales:

- `gpt.comp_types`
- `gpt.comp_competitions`
- `gpt.comp_type_species`
- `gpt.comp_type_rewards`
- `gpt.comp_type_prizes`

### 7. Tablas Clasificacion

Consulta del ranking actual.

Caracteristicas:

- filtros por tipo, especie, jugador e identificadores
- enlace a perfil en columnas de jugador y usuario
- acceso a historico mediante boton interno

Tabla principal:

- `gpt.clas_rankings_latest`

### 8. Tablas Clasificacion Historico

Comparativa entre snapshots.

Caracteristicas:

- seleccion de snapshot actual y snapshot de comparacion
- filtros por cambios
- columnas delta

Se accede desde `Tablas Clasificacion`, no desde el menu lateral.

Tabla principal:

- `gpt.clas_rankings_history`

### 9. Especies PPFT

Consulta auxiliar sobre especies con datos agregados de foto y taxidermia.

### 10. Salones Fama

Consulta parametrica para la tabla o vista configurada como Hall of Fame.

### 11. Resumen Trofeos

Resumen por usuario:

- `gold`
- `silver`
- `bronze`

El detalle se despliega en subtabla al pulsar la cantidad correspondiente.

Tabla principal:

- `gpt.user_trophies`

### 12. Anti-trampas

Vista de senales derivadas de expediciones y actividad.

Vistas principales:

- `gpt.v_exp_cheat_risk`
- `gpt.v_exp_cheat_signals`
- `gpt.v_exp_cheat_signal_expeditions`

### 13. Logs

Consulta administrativa de logs.

Visible solo para admin.

### 14. Consulta Avanzada

Exploracion generica de tablas del esquema.

## Patron comun de interfaz

El proyecto intenta mantener una estructura consistente en todas las consultas:

- filtros en una unica banda superior
- campos reordenables por drag and drop
- tooltip por campo
- combos multiseleccion tipo checkbox para catalogos enumerados
- combos simples personalizados para selectores de una sola opcion
- campos visibles configurables en tabla principal y subtablas
- columnas numericas alineadas a la derecha
- cabeceras de tablas con separadores visibles y flechas de orden
- formato de fecha `dd/mm/yyyy hh:mm:ss`
- temas y fuentes seleccionables

## Navegacion

La barra lateral es reordenable por drag and drop.

Vistas integradas dentro de otras:

- `Comparativa Mejores Marcas` dentro de `Mejores Marcas`
- `Tablas Clasificacion Hist.` dentro de `Tablas Clasificacion`

La persistencia del orden del menu se guarda en navegador.

## Autenticacion y permisos

### Admin

Variables de entorno:

- `THC_PANEL_USER`
- `THC_PANEL_PASS`

Permisos:

- acceso completo
- ejecucion de todas las acciones
- modificacion de tareas programadas
- acceso a logs

### Usuarios no admin

Pueden autenticarse si existen en catalogos de jugadores y usan:

- la contrasena por defecto
- o una contrasena personalizada en `var/panel_passwords.json`

Permisos funcionales:

- pueden usar consultas y filtros
- pueden ejecutar solo las acciones permitidas por `TaskCatalog`
- no ven botones de procesos no autorizados
- no pueden guardar cambios de programacion de tareas

## Importadores y procesos CLI

### Importadores principales

- [src/run_import_users.php](C:\Users\Usuario\Documents\THC\THC_Query\src\run_import_users.php)
- [src/run_import_best_users.php](C:\Users\Usuario\Documents\THC\THC_Query\src\run_import_best_users.php)
- [src/run_import_users_public_stats.php](C:\Users\Usuario\Documents\THC\THC_Query\src\run_import_users_public_stats.php)
- [src/run_import_users_trophies.php](C:\Users\Usuario\Documents\THC\THC_Query\src\run_import_users_trophies.php)
- [src/run_import_competitions.php](C:\Users\Usuario\Documents\THC\THC_Query\src\run_import_competitions.php)
- [src/run_import_leaderboards.php](C:\Users\Usuario\Documents\THC\THC_Query\src\run_import_leaderboards.php)
- [src/run_translate_competition_descriptions.php](C:\Users\Usuario\Documents\THC\THC_Query\src\run_translate_competition_descriptions.php)
- [src/run_scrape_kill_urls.php](C:\Users\Usuario\Documents\THC\THC_Query\src\run_scrape_kill_urls.php)

### Clases de soporte

- [src/Importer.php](C:\Users\Usuario\Documents\THC\THC_Query\src\Importer.php)
- [src/BestImporter.php](C:\Users\Usuario\Documents\THC\THC_Query\src\BestImporter.php)
- [src/CompetitionImporter.php](C:\Users\Usuario\Documents\THC\THC_Query\src\CompetitionImporter.php)
- [src/GlobalLeaderboardsImporter.php](C:\Users\Usuario\Documents\THC\THC_Query\src\GlobalLeaderboardsImporter.php)
- [src/TrophyImporter.php](C:\Users\Usuario\Documents\THC\THC_Query\src\TrophyImporter.php)
- [src/UserPublicStatsImporter.php](C:\Users\Usuario\Documents\THC\THC_Query\src\UserPublicStatsImporter.php)

## Tareas programadas

Persistencia actual:

- `var/task_schedules.json`
- `var/task_schedule_tick.json`
- `var/tasks/*.json`

Logs:

- `logs/tasks/*.log`

Las tareas se lanzan en segundo plano con logica orientada a Windows.

## Modelo de datos relevante

### Tablas principales

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

### Vistas

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

## Ficheros y persistencia

### Logs

- `logs/*.log`
- `logs/tasks/*.log`

### Sesiones

- `logs/sessions/`

### Salidas

- `out/best_all.xml`
- `out/kill_url_scrape/results.csv`
- `out/kill_url_scrape/pages/*.html.gz`

## Criterios de mantenimiento

Normas practicas para no degradar el proyecto:

1. Cualquier cambio funcional debe reflejarse en `README.md`.
2. Si el cambio afecta a arquitectura, procesos, permisos o despliegue, tambien debe actualizarse este manual tecnico.
3. No introducir conversiones de texto innecesarias en runtime. La aplicacion debe trabajar con textos simples y evitar capas extra de transformacion.
4. Mantener el patron comun de interfaz en nuevas consultas y subtablas.
5. Todo log nuevo debe ir a `logs/` o `logs/tasks/`.
6. Todo temporal de sesion debe quedar en `logs/sessions/`.

## Validacion minima tras cambios

Comprobaciones recomendadas:

```powershell
php -l public\index.php
php -l src\web_bootstrap.php
php -l src\TaskCatalog.php
php -l src\TaskManager.php
php -l src\TaskScheduleManager.php
```

Comprobaciones visuales recomendadas:

1. abrir `Panel`
2. abrir `Expediciones`
3. abrir `Mejores Marcas` y acceder a la comparativa desde su boton
4. abrir `Tablas Clasificacion` y acceder al historico desde su boton
5. revisar cabeceras, filtros, tooltips, fechas y alineacion numerica

## Riesgos tecnicos conocidos

- aplicacion acoplada a Windows/XAMPP en varios puntos
- tareas en background dependientes de comandos del sistema
- `public/index.php` concentra mucha logica y conviene seguir modularizando con cuidado
- existen copias historicas en `public/` que no forman parte del runtime principal
