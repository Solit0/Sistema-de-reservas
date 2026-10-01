# DOCUMENTO TÉCNICO OFICIAL — CASO A: SISTEMA DE RESERVAS DE ESPACIOS
## Especificación de Arquitectura, Contratos Base, Jerarquía Polimórfica, Gestor de Reservas, Suite de Pruebas y Migración Web

---

> **Materia:** Desarrollo de Páginas Web con Software Libre / Programación Orientada a Objetos  
> **Proyecto:** Caso A — Sistema de Reservas de Espacios (Complejo Coworking y Deportivo)  
> **Repositorio Oficial:** [https://github.com/Solit0/Sistema-de-reservas](https://github.com/Solit0/Sistema-de-reservas)  
> **Rol del Documentador:** **Integrante 4** — Pruebas Unitarias, Entrypoint E2E (`main.php`), Simulación de Condiciones y Compilación Técnica Oficial  

---

## Tabla de Contenidos
1. [Ficha Técnica y Resumen Ejecutivo](#1-ficha-técnica-y-resumen-ejecutivo)
2. [Distribución Oficial de Tareas, Ramas y Commits por Integrante](#2-distribución-oficial-de-tareas-ramas-y-commits-por-integrante)
   - [Integrante 1: Contratos Base y Modelos de Reserva (Commit 1)](#integrante-1-contratos-base-y-modelos-de-reserva-commit-1)
   - [Integrante 2: Jerarquía de Espacios y Tarifas Polimórficas (Commit 2)](#integrante-2-jerarquía-de-espacios-y-tarifas-polimórficas-commit-2)
   - [Integrante 3: Gestor de Espacios y Reporte del Día (Commit 3)](#integrante-3-gestor-de-espacios-y-reporte-del-día-commit-3)
   - [Integrante 4: Pruebas, Entrypoint y Simulación End-to-End (Commit 4)](#integrante-4-pruebas-entrypoint-y-simulación-end-to-end-commit-4)
3. [Especificación Detallada del Dominio y Polimorfismo Puro](#3-especificación-detallada-del-dominio-y-polimorfismo-puro)
4. [Implementación Detallada de Tareas del Integrante 4 (Pruebas y Entrypoint)](#4-implementación-detallada-de-tareas-del-integrante-4-pruebas-y-entrypoint)
   - [4.1 Configuración de Datos de Prueba (Sala, Escritorio, Cancha)](#41-configuración-de-datos-de-prueba-sala-escritorio-cancha)
   - [4.2 Simulación de Escenarios: Horario Pico, Bloques y Disponibilidad](#42-simulación-de-escenarios-horario-pico-bloques-y-disponibilidad)
   - [4.3 Ejecución del Reporte del Día y Validación en Tiempo de Ejecución](#43-ejecución-del-reporte-del-día-y-validación-en-tiempo-de-ejecución)
   - [4.4 Pruebas de Resiliencia: Encapsulamiento y Manejo de Excepciones](#44-pruebas-de-resiliencia-encapsulamiento-y-manejo-de-excepciones)
   - [4.5 Persistencia de Apoyo: Almacenamiento en Archivo JSON](#45-persistencia-de-apoyo-almacenamiento-en-archivo-json)
5. [Evolución Arquitectónica hacia Fase 2 Web (Persistencia Relacional MySQL)](#5-evolución-arquitectónica-hacia-fase-2-web-persistencia-relacional-mysql)
   - [5.1 Esquema Relacional Single Table Inheritance (STI)](#51-esquema-relacional-single-table-inheritance-sti)
   - [5.2 Fábrica Polimórfica (`EspacioFactory`)](#52-fábrica-polimórfica-espaciofactory)
   - [5.3 Algoritmo Anti-Traslapes en Base de Datos (`ReservaRepositorio`)](#53-algoritmo-anti-traslapes-en-base-de-datos-reservarepositorio)
   - [5.4 Catálogo de las 8 Páginas Web](#54-catálogo-de-las-8-páginas-web)
6. [Flujo Git, Comandos de Terminal y Checklist de Rúbrica](#6-flujo-git-comandos-de-terminal-y-checklist-de-rúbrica)
7. [Guía de Defensa Oral para el Integrante 4 (Banco de Preguntas del Docente)](#7-guía-de-defensa-oral-para-el-integrante-4-banco-de-preguntas-del-docente)

---

## 1. Ficha Técnica y Resumen Ejecutivo

### 1.1 Ficha Técnica
* **Proyecto:** Sistema de Gestión y Reserva de Espacios (Caso A).
* **Repositorio GitHub:** [https://github.com/Solit0/Sistema-de-reservas](https://github.com/Solit0/Sistema-de-reservas)
* **Lenguaje:** PHP 8.2+ con tipado estricto (`declare(strict_types=1);`).
* **Estándar de Autoload:** PSR-4 (`"App\\": "src/"`) gestionado mediante Composer.
* **Paradigma Principal:** Programación Orientada a Objetos (POO) con Abstracción, Encapsulamiento, Herencia, Polimorfismo puro y Principios SOLID (Single Responsibility, Open/Closed).

### 1.2 Resumen Ejecutivo
El sistema modela y administra la tarificación y reserva de tres tipos de espacios físicos dentro de un complejo multiuso:
1. **Salas de Reunión:** Diseñadas para reuniones ejecutivas, tarificadas por hora con un **recargo del 25% en horarios pico**.
2. **Escritorios Individuales:** Orientados a usuarios de coworking, con una **tarifa plana simple por hora**.
3. **Canchas Sintéticas:** Destinadas a actividades deportivas, tarificadas por **bloques cerrados de tiempo** (ej. fracciones de 60 o 90 minutos redondeadas con función techo `ceil()`) y con un **recargo fijo en horario pico o nocturno**.

El desarrollo del proyecto se planificó de forma modular mediante **Conventional Commits** y ramas funcionales independientes por cada integrante, culminando con la construcción del **Runner de Pruebas y Entrypoint E2E** a cargo del **Integrante 4**.

---

## 2. Distribución Oficial de Tareas, Ramas y Commits por Integrante

A continuación se detalla la matriz exacta de responsabilidades, componentes de software, ramas y mensajes de commit asignados:

---

### Integrante 1: Contratos Base y Modelos de Reserva (Commit 1)
* **Objetivo:** Establecer los cimientos abstractos y entidades fundamentales del dominio para garantizar un contrato uniforme sin acoplamiento a implementaciones concretas.
* **Tareas Asignadas:**
  1. Crear la interfaz/contrato `Reservable` definiendo los métodos obligatorios:
     * `verificarDisponibilidad(Horario $horario): bool`
     * `calcularTarifa(Horario $horario, bool $esPico = false): float`
     * `agregarReserva(Reserva $reserva): void`
     * `obtenerReservas(): array`
  2. Crear la clase abstracta base `Espacio` (`id`, `nombre`, `capacidad`), implementando el contrato `Reservable`, encapsulando la colección de reservas y protegiendo el invariante de traslapes en memoria.
  3. Crear la entidad de valor `Reserva` (`id`, `horario`, `titular`, `costoCalculado`, `esPico`) y el objeto de valor inmutable `Horario` (`inicio`, `fin`).
* **Branch sugerida:** `feature/core-interfaces`
* **Mensaje de commit:** `feat(core): define Reservable contract, Espacio base model and Reserva entity`
* **Archivos intervenidos:**
  * `src/Contracts/Reservable.php`
  * `src/Domain/Espacios/Espacio.php`
  * `src/Domain/Horario.php`
  * `src/Domain/Reserva.php`

---

### Integrante 2: Jerarquía de Espacios y Tarifas Polimórficas (Commit 2)
* **Objetivo:** Implementar la especialización de espacios físicos heredando de `Espacio` y resolviendo el cálculo de tarifas y validaciones específicas de forma polimórfica.
* **Tareas Asignadas:**
  1. Implementar `SalaReunion`: Tarifa base por hora ($180.00) + recargo del 25% si la reserva ocurre en horario pico.
  2. Implementar `EscritorioIndividual`: Tarifa plana simple por hora ($75.00), pensada para jornadas de trabajo flexibles.
  3. Implementar `Cancha`: Tarifa por bloques cerrados de tiempo (ej. slots de 60/90 minutos calculados con redondeo hacia arriba `ceil()`), aplicando un recargo fijo por bloque ($35.00) si es horario pico o nocturno.
  4. Implementar la lógica interna de disponibilidad específica para cada tipo de espacio.
* **Branch sugerida:** `feature/espacios-polimorfismo`
* **Mensaje de commit:** `feat(spaces): implement polymorphic fee calculation for Sala, Escritorio and Cancha`
* **Archivos intervenidos:**
  * `src/Domain/Espacios/SalaReunion.php`
  * `src/Domain/Espacios/EscritorioIndividual.php`
  * `src/Domain/Espacios/Cancha.php`

---

### Integrante 3: Gestor de Espacios y Reporte del Día (Commit 3)
* **Objetivo:** Orquestar la colección heterogénea de espacios e implementar el generador de reportes consolidado sin incurrir en antipatrones de inspección de tipos.
* **Tareas Asignadas:**
  1. Crear la clase de servicio `GestorReservas` encargada de registrar y almacenar la lista polimórfica `List<Reservable>` (array de `Reservable[]`).
  2. Implementar el método `crearReserva(Reservable $espacio, Horario $horario, string $titular, bool $esPico)` delegando la validación y el cobro al contrato `Reservable`.
  3. Implementar el método `generarReporteDelDia(DateTimeInterface $fecha)`: debe iterar sobre la colección heterogénea llamando **exclusivamente a los métodos del contrato `Reservable`**, prohibiendo taxativamente el uso de `instanceof`, `is_a()` o condicionales por tipo concreto (`switch($tipo)`).
* **Branch sugerida:** `feature/reporte-reservas`
* **Mensaje de commit:** `feat(manager): add GestorReservas and daily polymorphic reporting logic`
* **Archivos intervenidos:**
  * `src/Services/GestorReservas.php`
  * `src/Services/ReporteConsolaService.php`

---

### Integrante 4: Pruebas, Entrypoint y Simulación End-to-End (Commit 4)
* **Objetivo:** Diseñar el punto de entrada principal del sistema (`main.php`) y la suite de pruebas integradas, configurando un escenario de datos realista que valide en tiempo de ejecución las tarifas, disponibilidades, reportes polimórficos y la resiliencia ante excepciones.
* **Tareas Asignadas:**
  1. **Crear el Entrypoint `main.php`:** Configurar el entorno con autoloading de Composer (`require __DIR__ . '/vendor/autoload.php'`).
  2. **Configuración de Datos de Prueba:** Instanciar al menos 1 sala de reunión (`Sala de Juntas`, cap: 8), 1 escritorio individual (`Escritorio 01`, cap: 1) y 1 cancha sintética (`Cancha Sintética`, cap: 10).
  3. **Simular Reservas en Distintas Condiciones:**
     * Reserva en Sala en horario estándar vs horario pico (+25%).
     * Reserva en Escritorio individual por horas fraccionadas.
     * Reserva en Cancha deportiva calculando bloques cerrados y recargo nocturno.
  4. **Ejecución y Verificación del Reporte del Día:** Ejecutar `generarReporteDelDia()` demostrando que los subtotales y el gran total general se computan en tiempo de ejecución sin conocer las clases concretas.
  5. **Verificación de Disponibilidad en Franja Horaria:** Consultar la disponibilidad simultánea de todos los espacios frente a un nuevo horario de prueba.
  6. **Prueba Defensiva de Encapsulamiento y Manejo de Errores:** Simular una reserva con horario colisionado intencional, verificando que el dominio lance `InvalidArgumentException` y el sistema la capture limpiamente con `try-catch`.
  7. **Persistencia Adicional:** Integración de `ReservaStorageService` para serializar y deserializar el estado del sistema en `reservas.json`.
* **Branch sugerida:** `feature/demo-tests`
* **Mensaje de commit:** `test(demo): add end-to-end reservation flow and report execution`
* **Archivos intervenidos:**
  * `main.php`
  * `src/Services/ReservaStorageService.php`
  * `reservas.json`
  * `.gitignore`
  * `README.md`

---

## 3. Especificación Detallada del Dominio y Polimorfismo Puro

### 3.1 Contrato Fundamental (`Reservable.php`)
```php
<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domain\Horario;
use App\Domain\Reserva;

interface Reservable
{
    public function verificarDisponibilidad(Horario $horario): bool;

    public function calcularTarifa(Horario $horario, bool $esPico = false): float;

    public function agregarReserva(Reserva $reserva): void;

    /**
     * @return Reserva[]
     */
    public function obtenerReservas(): array;

    public function getNombre(): string;

    public function getTipo(): string;

    public function getCapacidad(): int;
}
```

### 3.2 Fórmulas Matemáticas de Tarificación
| Espacio Concreto | Tarifa Base | Regla de Cobro en Horario Normal | Regla de Cobro en Horario Pico / Nocturno |
| :--- | :---: | :--- | :--- |
| **SalaReunion** | \$180.00 / h | $\text{Horas} \times 180.00$ | $(\text{Horas} \times 180.00) \times 1.25$ (+25%) |
| **EscritorioIndividual** | \$75.00 / h | $\text{Horas} \times 75.00$ | $\text{Horas} \times 75.00$ (Tarifa plana fija) |
| **Cancha** | \$120.00 / blq | $\lceil \frac{\text{Min}}{60} \rceil \times 120.00$ | $\lceil \frac{\text{Min}}{60} \rceil \times (120.00 + 35.00)$ |

---

## 4. Implementación Detallada de Tareas del Integrante 4 (Pruebas y Entrypoint)

### 4.1 Configuración de Datos de Prueba en `main.php`
```php
function construirReservas(): GestorReservas
{
    $gestor = new GestorReservas();

    $sala = new SalaReunion('Sala de Juntas', 8);
    $escritorio = new EscritorioIndividual('Escritorio 01', 1);
    $cancha = new Cancha('Cancha Sintética', 10);

    $gestor->registrarEspacio($sala);
    $gestor->registrarEspacio($escritorio);
    $gestor->registrarEspacio($cancha);
```

### 4.2 Simulación de Escenarios en `main.php`
```php
    // Sala normal ($360) vs pico ($450)
    $gestor->crearReserva($sala, new Horario(new DateTimeImmutable('2026-08-19 09:00'), new DateTimeImmutable('2026-08-19 11:00')), 'María López', false);
    $gestor->crearReserva($sala, new Horario(new DateTimeImmutable('2026-08-19 16:00'), new DateTimeImmutable('2026-08-19 18:00')), 'Carlos Ruiz', true);

    // Escritorio por 2.5h ($187.50)
    $gestor->crearReserva($escritorio, new Horario(new DateTimeImmutable('2026-08-19 10:00'), new DateTimeImmutable('2026-08-19 12:30')), 'Ana Gómez', false);

    // Cancha 90 min = 2 bloques ($240) y nocturna 1 bloque ($155)
    $gestor->crearReserva($cancha, new Horario(new DateTimeImmutable('2026-08-19 13:00'), new DateTimeImmutable('2026-08-19 14:30')), 'Equipo Fútbol', false);
    $gestor->crearReserva($cancha, new Horario(new DateTimeImmutable('2026-08-19 18:00'), new DateTimeImmutable('2026-08-19 19:00')), 'Club A', true);

    return $gestor;
}
```

### 4.3 Salida del Reporte del Día en Consola
```text
=== REPORTE DEL DÍA 2026-08-19 ===
+----------------------+----------------------+------------+---------------------+------------------+
| ESPACIO              | TIPO                 | ESTADO     | HORARIO             | MONTO            |
+----------------------+----------------------+------------+---------------------+------------------+
| Sala de Juntas       | Sala de Reunión      | OK         | 09:00-11:00         | $360.00          |
| Sala de Juntas       | Sala de Reunión      | PICO       | 16:00-18:00         | $450.00          |
| SUBTOTAL             |                      |            |                     | $810.00          |
+----------------------+----------------------+------------+---------------------+------------------+
| Escritorio 01        | Escritorio Individual| OK         | 10:00-12:30         | $187.50          |
| SUBTOTAL             |                      |            |                     | $187.50          |
+----------------------+----------------------+------------+---------------------+------------------+
| Cancha Sintética     | Cancha               | OK         | 13:00-14:30         | $240.00          |
| Cancha Sintética     | Cancha               | PICO       | 18:00-19:00         | $155.00          |
| SUBTOTAL             |                      |            |                     | $395.00          |
+----------------------+----------------------+------------+---------------------+------------------+
| TOTAL GENERAL        |                      |            |                     | $1.392,50        |
+----------------------+----------------------+------------+---------------------+------------------+
```

### 4.4 Demostración de Excepción Controlada
```php
try {
    $primerEspacio = $gestor->obtenerEspacios()[0];
    $gestor->crearReserva($primerEspacio, new Horario(new DateTimeImmutable('2026-08-19 10:00'), new DateTimeImmutable('2026-08-19 11:00')), 'Cliente Conflicto');
} catch (InvalidArgumentException $e) {
    echo "✔ Excepción capturada correctamente: " . $e->getMessage() . "\n";
}
```

---

## 5. Evolución Arquitectónica hacia Fase 2 Web (Persistencia Relacional MySQL)

1. **Estrategia Single Table Inheritance (STI):** Tabla `espacios` única con campos nulos para especializaciones y tabla `reservas` con `FOREIGN KEY (espacio_id) REFERENCES espacios(id) ON DELETE CASCADE`.
2. **Fábrica Polimórfica (`EspacioFactory`):** Único punto del sistema donde se evalúa el campo discriminador `tipo` de la base de datos para instanciar las clases concretas.
3. **Algoritmo Anti-Traslape en MySQL:** Consulta parametrizada en `ReservaRepositorio`:
   `WHERE espacio_id = :id AND fecha = :f AND (:hora_inicio < hora_fin AND :hora_fin > hora_inicio)`.
4. **Catálogo de 8 Páginas Web:** Dashboard, Catálogo, Creación (PRG), Edición, Ficha Técnica, Borrado POST, Módulo Reservas y Reporte Financiero Web.

---

## 6. Flujo Git, Comandos de Terminal y Checklist de Rúbrica

```bash
composer dump-autoload
php main.php
php -S localhost:8000 -t public
```

---

## 7. Guía de Defensa Oral para el Integrante 4 (Banco de Preguntas del Docente)

#### Pregunta 1: Como Integrante 4, ¿cuál fue exactamente su responsabilidad técnica en el proyecto?
> **Respuesta:**  
> *"Mi responsabilidad técnica se centró en diseñar e implementar el **Entrypoint y la Suite de Pruebas End-to-End (`main.php`)**. Configuré la instanciación de los tres espacios heterogéneos, simulé escenarios de reserva bajo distintas condiciones operativas (horario regular, recargo del 25% en horario pico para salas, y cálculo de bloques de 90 minutos con redondeo `ceil()` para canchas). Además, ejecuté el reporte del día validando la tarificación polimórfica en tiempo de ejecución, implementé la prueba de captura de excepciones para demostrar el encapsulamiento y gestioné la persistencia en archivos JSON con `ReservaStorageService`."*

#### Pregunta 2: ¿Dónde ocurre polimorfismo real en su archivo `main.php`?
> **Respuesta:**  
> *"Ocurre en la línea donde el gestor crea la reserva y calcula el costo:  
> `$costo = $espacio->calcularTarifa($horario, $esPico);`  
> En esa única instrucción, PHP resuelve dinámicamente si debe invocar la fórmula con recargo porcentual de `SalaReunion`, la tarifa plana de `EscritorioIndividual` o la matemática de bloques discretos de `Cancha`, sin que el código ejecutor tenga un solo `instanceof` ni `switch`."*

#### Pregunta 3: ¿Cómo probó que el encapsulamiento del sistema realmente funciona?
> **Respuesta:**  
> *"En la sección 4 de `main.php`, diseñé un caso de prueba defensivo: forcé una colisión horaria intentando reservar la Sala de Juntas de 10:00 a 11:00, franja que ya estaba tomada de 09:00 a 11:00. El método `agregarReserva()` en `Espacio.php` evalúa `seSolapaCon()` y dispara un `InvalidArgumentException`. En `main.php` capturo esta excepción con un `try-catch`, demostrando que el estado interno del objeto es inviolable."*

#### Pregunta 4: ¿Por qué propiedades como `$inicio`, `$fin` y `$costoCalculado` se definieron como `readonly`?
> **Respuesta:**  
> *"Para garantizar el principio de **Inmutabilidad**. Una vez que un horario ha sido validado y una reserva registrada con su costo pactado, ninguna rutina externa debe poder alterar esos valores. Esto previene efectos secundarios indeseados y bugs de concurrencia."*

---

**Fin del Documento Técnico — Caso A: Sistema de Reservas de Espacios**  
*Compilado y certificado por el Integrante 4 (Harry Damian — `damian-sh`).*
