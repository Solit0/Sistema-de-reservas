# Guía de Defensa Oral — Primer Avance (Torneo UNI)
> **Materia:** Desarrollo de Páginas Web con Software Libre  
> **Proyecto:** Caso A — Sistema de Reservas de Espacios (Coworking/Complejo)  
> **Expositor Principal:** Harry Damian (`damian-sh`)  
> **Tiempo total estimado:** 6 a 8 minutos  

---

## Estructura Temporal de la Exposición

| Tiempo | Sección | Qué mostrar en pantalla | Objetivo |
| :---: | :--- | :--- | :--- |
| **0:00 - 0:45** | **1. Introducción y Alcance** | Navegador en GitHub | Presentar el problema de negocio y el rol técnico de cada integrante. |
| **0:45 - 3:00** | **2. Mis 4 Commits Técnicos** | Historial de commits en GitHub | Demostrar commits atómicos, Conventional Commits y arquitectura limpia. |
| **3:00 - 4:45** | **3. Evidencia de POO Línea por Línea** | Editor de código (VS Code) | Mostrar contratos, herencia, encapsulamiento y polimorfismo real. |
| **4:45 - 6:00** | **4. Demostración en Vivo** | Terminal CLI | Ejecutar `composer dump-autoload` y `php main.php` con persistencia JSON y excepciones. |
| **6:00 - 7:30** | **5. Preguntas del Docente** | Código y diagramas | Responder con seguridad a las preguntas trampa de la rúbrica. |

---

## 1. Discurso de Apertura (0:00 - 0:45)

> *"Buenas tardes, profesor y compañeros. Nuestro equipo trabajó en el **Caso A: Sistema de Reservas de Espacios** para un complejo de coworking y deportivo.  
> El sistema administra tres tipos de espacios heterogéneos: **Salas de Reunión** ($180/h con +25% en horario pico), **Escritorios Individuales** ($75/h planos) y **Canchas Sintéticas** ($120 por bloque de 1h con recargo fijo de $35 en hora pico).  
> Mientras mis compañeros estructuraron la base inicial del dominio, **mi labor técnica se centró en la refactorización arquitectónica a polimorfismo puro, la implementación de la capa de persistencia en archivos JSON (8 pts de la rúbrica), el diseño de la suite de pruebas defensivas en consola y la documentación integral del proyecto**."*

---

## 2. Exposición de Mis 4 Commits en GitHub (0:45 - 3:00)

> [!NOTE]
> Proyecta la pestaña de **Commits en GitHub** y señala tus 4 commits con Conventional Commits.

```text
* e0f46f8 docs(readme): agregar documentacion del proyecto y guia de ejecucion
* 9802ee2 fix(cli): remover warning y agregar demo de excepciones y persistencia en main.php
* 0fa4d3c feat(storage): implementar ReservaStorageService para persistencia en JSON
* cb4672a refactor(contracts): formalizar gestion de reservas en Reservable y eliminar method_exists
```

### 1. Commit `cb4672a` — Refactorización a Polimorfismo Puro
* **Mensaje:** `refactor(contracts): formalizar gestion de reservas en Reservable y eliminar method_exists`
* **Archivos:** `src/Contracts/Reservable.php`, `src/Services/GestorReservas.php`, `src/Services/ReporteConsolaService.php`
* **Qué decir:**
  > *"En mi primer commit identifiqué un **code smell** importante: los servicios comprobaban la existencia de métodos con `method_exists()`. Refactoricé la interfaz `Reservable` incorporando formalmente `agregarReserva()` y `obtenerReservas()`. Con esto eliminé la reflexión y logré que el sistema opere con **polimorfismo puro y contratos completos**."*

### 2. Commit `0fa4d3c` — Persistencia en Archivos JSON (8 Puntos)
* **Mensaje:** `feat(storage): implementar ReservaStorageService para persistencia en JSON`
* **Archivos:** `src/Services/ReservaStorageService.php`, `.gitignore`
* **Qué decir:**
  > *"En el segundo commit cumplí con el criterio de **Manejo de Archivos** creando la clase de infraestructura `ReservaStorageService` bajo el principio de **Responsabilidad Única (SRP)**. Permite serializar y exportar a disco el estado de las reservas en `reservas.json`, además de recuperarlo con manejo seguro de errores. También configuré el `.gitignore` para no subir datos volátiles al repositorio."*

### 3. Commit `9802ee2` — Runner CLI y Programación Defensiva
* **Mensaje:** `fix(cli): remover warning y agregar demo de excepciones y persistencia en main.php`
* **Archivos:** `main.php`
* **Qué decir:**
  > *"En este commit optimicé el punto de entrada `main.php`. Eliminé advertencias de namespace de PHP 8.2 para asegurar una ejecución en consola 100% limpia y estructuré un bloque `try-catch` que demuestra en vivo cómo el **Encapsulamiento** protege el sistema lanzando y capturando un `InvalidArgumentException` cuando se intenta reservar un horario solapado."*

### 4. Commit `e0f46f8` — Documentación Técnica
* **Mensaje:** `docs(readme): agregar documentacion del proyecto y guia de ejecucion`
* **Archivos:** `README.md`
* **Qué decir:**
  > *"Finalmente, documenté el proyecto con un `README.md` claro que resume el negocio, las reglas de tarificación, la arquitectura por capas bajo PSR-4 y las instrucciones exactas de reproducción."*

---

## 3. Mapeo de Conceptos de POO en Código Real (3:00 - 4:45)

> [!IMPORTANT]
> Abre el editor de código y muestra estos archivos puntuales:

| Concepto | Archivo y Línea Exacta | Qué señalar con el cursor |
| :--- | :--- | :--- |
| **Abstracción** | `src/Contracts/Reservable.php:9-20` | Las firmas de métodos obligatorios sin acoplamiento a clases concretas. |
| **Encapsulamiento** | `src/Domain/Horario.php:13-14`<br>`src/Domain/Espacios/Espacio.php:57-64` | Propiedades `readonly` para inmutabilidad y validación de solapamiento que lanza `InvalidArgumentException`. |
| **Herencia** | `src/Domain/Espacios/SalaReunion.php:16` | Invocación explícita de `parent::__construct($nombre, $capacidad)`. |
| **Polimorfismo** | `src/Domain/Espacios/Cancha.php:24` vs `SalaReunion.php:24`<br>`src/Services/ReporteConsolaService.php:34-45` | Diferentes fórmulas de cobro (por hora vs bloques con `ceil`) y recorrido en bucle de `Reservable[]` sin un solo `instanceof`. |

---

## 4. Demostración en Vivo en Terminal (4:45 - 6:00)

Ejecuta en consola los siguientes comandos en orden:

```bash
composer dump-autoload
php main.php
```

### Puntos a narrar en vivo:
1. **Autoload PSR-4:** *"Verificamos que Composer genera el mapa de clases sin advertencias."*
2. **Tabla de Reporte Polimórfico:** *"Aquí se muestra el consolidado del día ($1.392,50) iterando sobre espacios heterogéneos."*
3. **Persistencia JSON:** *"Aquí mi servicio `ReservaStorageService` exportó el archivo `reservas.json` y confirmó la lectura de los 3 espacios desde el disco."*
4. **Captura de Excepción:** *"Aquí se comprueba la protección de invariantes: al intentar registrar una reserva en la Sala de Juntas en un horario ocupado, el sistema lanzó y capturó limpiamente el error."*

---

## 5. Banco de Respuestas a las Preguntas Guía del Docente

#### Pregunta 1: Señale en su código una línea donde ocurra polimorfismo real, sin usar `instanceof`.
> **Respuesta:** *"Ocurre en `src/Services/GestorReservas.php` línea 46:  
> `$costo = $espacio->calcularTarifa($horario, $esPico);`  
> El gestor recibe la interfaz `Reservable` y PHP resuelve dinámicamente en tiempo de ejecución la tarifa según si es Sala, Escritorio o Cancha."*

#### Pregunta 2: ¿Por qué decidieron declarar tal propiedad como `readonly`? ¿Qué problema evita?
> **Respuesta:** *"En `Horario.php` y `Reserva.php`, propiedades como `$inicio`, `$fin` y `$costoCalculado` son `readonly` para asegurar **inmutabilidad**. Evitan que el estado de una reserva sea alterado externamente una vez validado."*

#### Pregunta 3: Si tuvieran que agregar un espacio nuevo (ej. Auditorio), ¿qué archivos tocan?
> **Respuesta:** *"Por el **Principio Abierto/Cerrado (OCP)**, solo creamos `src/Domain/Espacios/Auditorio.php` extendiendo de `Espacio`. No se modifica ni una sola línea de `GestorReservas`, `ReporteConsolaService` ni `Reservable`."*

#### Pregunta 4: ¿Por qué separaron sus commits de esa forma?
> **Respuesta:** *"Aplicamos **Commits Atómicos y Conventional Commits**: separamos la refactorización de contratos (`refactor:`), la persistencia en JSON (`feat:`), las pruebas en consola (`fix:`) y la documentación (`docs:`), permitiendo trazabilidad y revisiones de código limpias."*

#### Pregunta 5: ¿Qué validación de encapsulamiento pueden demostrar lanzando una excepción?
> **Respuesta:** *"El método `agregarReserva()` en `Espacio.php` comprueba con `seSolapaCon()` si hay colisión de franja horaria. Si existe solapamiento, dispara inmediatamente un `InvalidArgumentException`."*
