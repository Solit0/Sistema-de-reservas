# 🏢 Sistema de Reservas de Espacios

Sistema de gestión y reserva de espacios en PHP 8.2 con Programación Orientada a Objetos (POO), arquitectura en capas y persistencia en JSON.

---

## 📌 ¿Cómo funciona el sistema?

El centro administra 3 tipos de espacios con diferentes tarifas y reglas:

* **Sala de Reunión:** $180 / hora (+25% de recargo si es horario pico).
* **Escritorio Individual:** $75 / hora (tarifa fija).
* **Cancha Sintética:** $120 / bloque de 1 hora (+ $35 por bloque en horario pico).

---

## 📁 Estructura del Proyecto

```text
src/
├── Contracts/
│   └── Reservable.php            # Interfaz base
├── Domain/
│   ├── Espacios/
│   │   ├── Espacio.php           # Clase abstracta base
│   │   ├── SalaReunion.php       # Subclase
│   │   ├── EscritorioIndividual.php # Subclase
│   │   └── Cancha.php            # Subclase
│   ├── Horario.php               # Rango de fechas y horas
│   └── Reserva.php               # Datos de la reserva
└── Services/
    ├── GestorReservas.php        # Lógica para registrar reservas
    ├── ReporteConsolaService.php # Reporte en consola
    └── ReservaStorageService.php # Guardado y lectura en JSON
```

---

## 🧩 Conceptos de POO Aplicados

* **Abstracción:** Interfaz `Reservable` y clase abstracta `Espacio`.
* **Encapsulamiento:** Propiedades `readonly`, visibilidad `private`/`protected` y validaciones que lanzan excepciones si hay horarios repetidos.
* **Herencia:** Clases hijas (`SalaReunion`, `EscritorioIndividual`, `Cancha`) que heredan de `Espacio` usando `parent::__construct()`.
* **Polimorfismo:** Cada espacio calcula su tarifa con `calcularTarifa()` de manera distinta sin necesidad de usar `instanceof` ni `switch`.
* **Manejo de Archivos:** Las reservas se guardan y leen en formato `reservas.json`.

---

## 🚀 Cómo Ejecutar el Proyecto

1. **Cargar el autoload de Composer:**
   ```bash
   composer dump-autoload
   ```

2. **Ejecutar el programa en consola:**
   ```bash
   php main.php
   ```
