# Manual de Usuario THC Query

## 1. Objetivo del manual

Este manual explica el uso diario de `THC Query` desde el punto de vista de un usuario del panel.

Su objetivo es ayudarte a:

- entrar en la aplicacion
- localizar la consulta adecuada
- filtrar informacion de forma rapida
- ordenar y personalizar tablas
- abrir detalles y subtablas
- exportar resultados
- entender que opciones dependen de permisos

`THC Query` centraliza informacion de `theHunter Classic` para consulta, revision y seguimiento.

## 2. Acceso al panel

### 2.1 Inicio de sesion

Para acceder al panel:

1. abre la URL del panel en tu navegador
2. introduce tu usuario
3. introduce tu contrasena
4. confirma el acceso

Si la autenticacion es correcta, se abrira la pantalla principal con el menu lateral izquierdo.

### 2.2 Cambio de contrasena

En la barra lateral existe el bloque `Cambiar contrasena`.

Pasos:

1. despliega el bloque
2. escribe la contrasena actual
3. escribe la nueva contrasena
4. confirma la nueva contrasena
5. pulsa `Guardar contrasena`

## 3. Estructura general de la aplicacion

La pantalla se organiza en las siguientes zonas:

1. barra lateral izquierda
2. cabecera o titulo de la consulta activa
3. zona de filtros
4. botones de accion
5. tabla principal
6. subtablas o paneles de detalle
7. paginacion

### 3.1 Barra lateral

La barra lateral contiene:

- logo de la aplicacion
- usuario conectado
- boton `Salir`
- bloque de cambio de contrasena
- accesos a las consultas
- selector de tema visual
- selector de fuente

### 3.2 Zona central

La zona central muestra la consulta que estas usando en ese momento.

Normalmente incluye:

- titulo
- filtros
- bloque de columnas visibles
- botones como `Filtrar`, `Limpiar` y `Exportar CSV`
- tabla principal
- detalles desplegables

## 4. Permisos y visibilidad

No todos los usuarios ven lo mismo.

Segun el perfil del usuario:

- algunas opciones del panel pueden no aparecer
- algunos botones de procesos pueden ocultarse
- ciertas acciones pueden ser solo de lectura
- la consulta `Logs` puede estar reservada a administradores

Si no ves una opcion, lo normal es que se deba a permisos y no a un error de la pantalla.

## 5. Elementos comunes en todas las consultas

## 5.1 Filtros

Los filtros se usan para reducir el volumen de informacion antes de consultar.

Los tipos mas habituales son:

- texto libre
- numeros
- fechas
- listas desplegables
- combos multiseleccion con checkbox

Uso recomendado:

1. selecciona primero uno o dos filtros principales
2. pulsa `Filtrar`
3. revisa el resultado
4. afina con filtros adicionales si hace falta
5. usa `Limpiar` para volver al estado inicial

## 5.2 Combos con checkbox

Muchos filtros de jugadores, usuarios, especies, reservas, tipos o estados usan combo con checkbox.

Funcionamiento:

1. pulsa sobre el campo
2. marca una o varias opciones
3. usa `Todos` para seleccionar todo
4. usa `Limpiar` para vaciar la seleccion
5. pulsa fuera del combo para cerrarlo
6. aplica el filtro con `Filtrar`

Este tipo de filtro permite trabajar mejor cuando necesitas comparar varios valores a la vez.

## 5.3 Tooltips en filtros

Al pasar el raton por encima de muchos filtros y controles aparece un tooltip con una descripcion breve.

Sirve para aclarar:

- que significa el campo
- que valor espera
- si se trata de un identificador, una fecha o un valor numerico

## 5.4 Columnas visibles

La mayoria de consultas tienen uno o varios bloques `Columnas visibles`.

Desde esos bloques puedes:

- mostrar columnas
- ocultar columnas
- cambiar el orden
- recuperar la configuracion por defecto

Uso:

1. despliega el bloque de columnas
2. marca o desmarca las columnas necesarias
3. reordena si procede
4. pulsa `Restablecer` si quieres volver al diseño original

## 5.5 Ordenacion

Las cabeceras de las tablas son ordenables.

Funcionamiento:

1. pulsa una cabecera para ordenar por esa columna
2. vuelve a pulsar para invertir el orden

Las flechas en cabecera indican que la columna admite ordenacion.

## 5.6 Subtablas

Muchas consultas permiten desplegar detalle dentro de una fila.

Ese detalle puede aparecer como:

- subtabla debajo de la fila principal
- bloque lateral alineado con la fila
- detalle tecnico adicional

Normalmente las subtablas tambien permiten:

- ordenar columnas
- ocultar o mostrar campos
- aplicar filtros propios cuando la pantalla lo soporta

## 5.7 Paginacion

Cuando una consulta devuelve muchos registros, los resultados se dividen en paginas.

Puedes:

- cambiar el numero de filas por pagina
- moverte entre paginas
- ir a una pagina concreta

## 5.8 Exportacion CSV

La mayoria de las consultas permiten exportar el resultado visible a CSV.

Pasos:

1. aplica los filtros
2. revisa las columnas visibles
3. pulsa `Exportar CSV`

Lo exportado respeta el contexto visible de la consulta en ese momento.

## 5.9 Enlaces en jugadores y usuarios

En muchas tablas, los campos `Jugador` o `Usuario` son enlaces.

Al pulsarlos, se abre el perfil del jugador correspondiente.

En algunas consultas tambien hay enlaces hacia:

- ficha de una muerte
- perfil del jugador
- detalle relacionado con el registro

## 6. Panel

`Panel` es la pantalla principal de control.

Sirve para:

- ver contadores globales
- lanzar procesos manuales
- revisar tareas programadas
- consultar ejecuciones recientes

### 6.1 Tarjetas resumen

En la parte superior se muestran tarjetas con contadores generales de la aplicacion.

Ejemplos:

- expediciones
- mejores marcas
- perfiles
- competiciones
- tablas de clasificacion

### 6.2 Bloque Procesos

Este bloque permite lanzar procesos manualmente segun permisos.

Entre ellos puede haber:

- actualizar competiciones
- actualizar mis expediciones
- actualizar expediciones de todos
- actualizar mejores marcas
- actualizar estadisticas
- actualizar trofeos
- scraper de URLs de muertes

Si un boton no aparece, el usuario no tiene permiso para ese proceso.

### 6.3 Bloque Tareas programadas

Muestra la configuracion y el estado de las tareas automáticas.

Campos habituales:

- nombre de la tarea
- activa
- cada cuantos minutos se ejecuta
- ultima ejecucion
- estado
- ejecutar
- guardar

En usuarios sin permisos de administracion, ciertos controles pueden no aparecer.

### 6.4 Bloque Tareas recientes

Permite ver las ultimas ejecuciones registradas, su estado y el acceso al log correspondiente.

## 7. Expediciones

La consulta `Expediciones` es una de las mas completas del panel.

Sirve para trabajar con:

- expediciones
- muertes
- disparos

### 7.1 Filtros principales

Los filtros mas comunes son:

- ID de expedicion
- ID de usuario
- jugador
- reserva
- rango de fechas
- especie
- kill ID
- disparo ID
- marca
- foto o taxidermia
- puntuacion
- distancia
- peso
- integridad
- harvest
- arma
- municion
- organo
- duracion

### 7.2 Lectura de la pantalla

En la zona superior aparecen contadores como:

- total de expediciones
- total de muertes
- total de foto y taxidermia

Debajo se muestra la tabla principal con las expediciones.

### 7.3 Tabla principal

Segun las columnas visibles, se pueden consultar campos como:

- ID de expedicion
- inicio
- fin
- jugador
- reserva
- muertes

### 7.4 Expandir de una expedicion

Cada fila puede desplegar el detalle de muertes asociadas.

Campos habituales del detalle:

- ID de muerte
- jugador
- especie
- icono de especie
- genero
- peso
- etico
- distancia
- puntuacion
- harvest
- integridad
- numero de disparos

### 7.5 Ver disparos de una muerte

En el detalle de muertes se muestra la informacion de disparos integrada o desplegable segun la configuracion de la pantalla.

Campos habituales:

- numero de disparo
- distancia
- arma
- municion
- organo

### 7.6 Uso recomendado

Flujo habitual:

1. filtra por jugador o reserva
2. limita por fechas
3. filtra por especie si buscas una caza concreta
4. abre la expedicion
5. revisa las muertes
6. abre los disparos solo cuando necesites detalle tecnico

## 8. Mejores Marcas

`Mejores Marcas` permite revisar los records personales por especie.

### 8.1 Filtros habituales

- rank
- jugador
- hunter score
- especie
- mejor puntuacion
- best distance

### 8.2 Totales superiores

En la parte superior aparecen los contadores:

- `Top's D`
- `Top's P`

Estos valores resumen los tops de distancia y puntuacion para el contexto filtrado.

### 8.3 Tabla principal

La tabla muestra normalmente:

- rank
- jugador
- hunter score
- especie
- mejor puntuacion
- best distance

### 8.4 Enlaces y acciones

Segun la fila y los datos disponibles:

- el jugador abre el perfil
- algunos valores pueden abrir la ficha de la muerte

### 8.5 Acceso a comparativa

Dentro de esta consulta existe el boton `Comparativa Mejores Marcas`.

Ese boton abre la vista comparativa sin necesidad de ir a otra opcion del menu lateral.

## 9. Comparativa Mejores Marcas

Esta vista compara los records de varios jugadores por especie.

### 9.1 Que muestra

- especie
- icono de especie
- valores por jugador
- marcas de distancia o puntuacion
- resumen de tops por jugador

### 9.2 Para que sirve

Es una pantalla orientada a comparar rapidamente:

- quien tiene mejor puntuacion
- quien tiene mejor distancia
- en que especies destaca cada jugador

### 9.3 Uso recomendado

1. entra desde `Mejores Marcas`
2. selecciona o revisa los jugadores comparados
3. ordena por la columna que te interese
4. pulsa un valor si quieres abrir la ficha de muerte asociada

## 10. Estadisticas

`Estadisticas` muestra informacion publica agregada por jugador.

### 10.1 Filtros habituales

- rank
- jugador
- hunter score
- duracion
- distancia
- campos especificos de especies
- campos especificos de armas
- campos de coleccionables
- campos de misiones

### 10.2 Tabla principal

La tabla principal resume al jugador.

Campos habituales:

- rank
- jugador
- hunter score
- duracion
- distancia recorrida
- acceso a detalle

### 10.3 Subtablas de detalle

Cada jugador puede desplegar subtablas como:

- `Animal stats`
- `Weapon stats`
- `Coleccionables`
- `Misiones diarias`

### 10.4 Uso recomendado

Utiliza esta pantalla cuando quieras:

- ver actividad global de un jugador
- detectar sus especies mas cazadas
- revisar el uso de armas y municiones
- consultar progreso en coleccionables o misiones

## 11. Competiciones

`Competiciones` permite revisar competiciones activas e historicas.

### 11.1 Filtros habituales

- ID
- nombre tipo
- entrants
- tipo
- fechas
- especie
- reward type
- reward define
- puesto premio
- intentos
- tipo de puntuacion

### 11.2 Estructura de la consulta

Cada fila puede mostrar:

- registro principal de la competicion
- detalle de tipo
- especies asociadas
- rewards o premios

### 11.3 Descripciones

Cuando existe traduccion disponible, la columna `Descripcion ES` muestra el texto traducido.

### 11.4 Uso recomendado

1. filtra por fechas o tipo
2. revisa la competicion principal
3. despliega el detalle
4. consulta especies y premios

## 12. Tablas Clasificacion

`Tablas Clasificacion` muestra la clasificacion actual por especie y por tipo.

### 12.1 Filtros habituales

- tipo de clasificacion
- especie
- rank
- jugador
- ID de usuario
- puntuacion
- distancia

### 12.2 Uso principal

Sirve para revisar:

- posicion actual de un jugador
- puntuacion o distancia registrada
- especie y tabla de referencia

### 12.3 Historico

Desde esta pantalla se accede al historico mediante un boton interno.

## 13. Tablas Clasificacion Historico

Esta vista permite comparar snapshots historicos de clasificacion.

### 13.1 Filtros habituales

- snapshot actual
- snapshot de comparacion
- tipo
- especie
- jugador
- rank
- solo cambios

### 13.2 Informacion mostrada

- rank actual
- rank previo
- delta de rank
- delta de puntuacion
- delta de distancia

### 13.3 Uso recomendado

Utiliza esta consulta para detectar:

- subidas y bajadas en el ranking
- mejoras o perdidas de puntuacion
- cambios entre dos capturas historicas

## 14. Especies PPFT

`Especies PPFT` es una consulta auxiliar sobre especies y datos agregados.

Puede incluir, segun configuracion:

- fotos
- taxidermias
- contadores auxiliares
- campos tecnicos o derivados

Es especialmente util para revision interna y control de volumen.

## 15. Salones Fama

`Salones Fama` permite revisar los registros del salon de fama.

### Funciones habituales

- filtrar por especie
- ordenar columnas
- ajustar columnas visibles
- revisar registros destacados

### Uso recomendado

1. filtra por especie
2. ordena por puntuacion, fecha o campo relevante
3. deja visibles solo los campos necesarios

## 16. Resumen Trofeos

`Resumen Trofeos` agrupa trofeos por usuario.

### 16.1 Resumen principal

Por cada usuario se muestran los contadores de:

- `gold`
- `silver`
- `bronze`

Los eventos que no encajan en esos grupos no se contabilizan en esos totales.

### 16.2 Detalle de trofeos

Al pulsar sobre una cantidad, se despliega la subtabla con el detalle del grupo seleccionado.

La subtabla puede tener:

- filtros propios
- columnas visibles propias
- ordenacion propia

### 16.3 Uso recomendado

Esta consulta es util para:

- comparar medallas por usuario
- abrir el detalle de un grupo concreto
- revisar los trofeos que componen cada total

## 17. Anti-trampas

`Anti-trampas` es una consulta de apoyo para revision.

### 17.1 Informacion principal

- score o nivel de riesgo
- senales detectadas
- expediciones vinculadas

### 17.2 Interpretacion

Debe usarse como ayuda de revision.

No sustituye una comprobacion manual del contexto completo del jugador.

### 17.3 Uso recomendado

1. localiza el jugador o la senal
2. revisa la puntuacion o severidad
3. abre las expediciones asociadas
4. contrasta la informacion antes de sacar conclusiones

## 18. Logs

La consulta `Logs` permite revisar archivos de log cuando el usuario tiene permisos suficientes.

Funciones habituales:

- seleccionar un archivo
- leer su contenido
- localizar errores o advertencias

Esta pantalla suele estar reservada a administracion.

## 19. Consulta Avanzada

`Consulta Avanzada` permite explorar tablas de forma generica.

### Funciones habituales

- seleccionar tabla
- filtrar por columnas
- ordenar
- exportar
- guardar o recuperar configuraciones, si la pantalla lo permite

### Uso recomendado

Se recomienda para usuarios que necesiten una consulta mas tecnica o flexible que las pantallas predefinidas.

## 20. Temas y fuentes

Desde la barra lateral puedes cambiar:

- el tema visual
- la fuente de la interfaz

El cambio afecta a la visualizacion general del panel y se conserva para siguientes usos del navegador.

## 21. Buenas practicas de uso

Recomendaciones:

1. empieza por filtros amplios y luego ajusta
2. evita abrir subtablas masivas si no necesitas detalle
3. usa columnas visibles para reducir ruido
4. exporta a CSV cuando necesites trabajar fuera del panel
5. si una consulta tarda, reduce primero el volumen con filtros
6. revisa permisos antes de asumir que falta una opcion

## 22. Problemas habituales

### No veo una opcion del panel

Posibles causas:

- falta de permisos
- opcion solo disponible para administradores
- pantalla integrada dentro de otra consulta

### Un proceso no aparece o no se puede guardar

Posibles causas:

- el usuario no tiene permiso
- la configuracion solo puede modificarla admin

### Una tabla muestra demasiadas columnas

Usa `Columnas visibles` para ocultar las columnas que no necesitas.

### Un combo tiene demasiadas opciones

Usa el combo con checkbox y combina seleccion multiple con filtros adicionales.

### No encuentro un detalle asociado

Revisa si la fila tiene despliegue o subtabla y si el dato depende del contexto filtrado.

## 23. Capturas de pantalla

### Panel principal
![Panel principal](C:\Users\Usuario\Documents\THC\THC_Query\docs\capturas\01-panel.png)

### Consulta de expediciones
![Consulta de expediciones](C:\Users\Usuario\Documents\THC\THC_Query\docs\capturas\02-expediciones.png)

### Consulta de mejores marcas
![Consulta de mejores marcas](C:\Users\Usuario\Documents\THC\THC_Query\docs\capturas\03-mejores-marcas.png)

### Consulta de estadisticas
![Consulta de estadisticas](C:\Users\Usuario\Documents\THC\THC_Query\docs\capturas\04-estadisticas.png)

### Consulta de competiciones
![Consulta de competiciones](C:\Users\Usuario\Documents\THC\THC_Query\docs\capturas\05-competiciones.png)

### Consulta de tablas de clasificacion
![Consulta de tablas de clasificacion](C:\Users\Usuario\Documents\THC\THC_Query\docs\capturas\06-tablas-clasificacion.png)

### Consulta de especies PPFT
![Consulta de especies PPFT](C:\Users\Usuario\Documents\THC\THC_Query\docs\capturas\07-especies-ppft.png)

### Consulta de salones de fama
![Consulta de salones de fama](C:\Users\Usuario\Documents\THC\THC_Query\docs\capturas\08-salones-fama.png)

### Consulta de resumen de trofeos
![Consulta de resumen de trofeos](C:\Users\Usuario\Documents\THC\THC_Query\docs\capturas\09-resumen-trofeos.png)

### Consulta de anti-trampas
![Consulta de anti-trampas](C:\Users\Usuario\Documents\THC\THC_Query\docs\capturas\10-anti-trampas.png)

### Consulta de logs
![Consulta de logs](C:\Users\Usuario\Documents\THC\THC_Query\docs\capturas\11-logs.png)

### Consulta avanzada
![Consulta avanzada](C:\Users\Usuario\Documents\THC\THC_Query\docs\capturas\12-consulta-avanzada.png)
