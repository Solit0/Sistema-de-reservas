# Sistema de Gestión y Reserva de Espacios (Caso A)
> **Versión Oficial:** `v2.0` (Fase 2: Aplicación Web con Persistencia Relacional MySQL y Dominio Polimórfico)  
> **Asignatura:** Desarrollo de Páginas Web con Software Libre / Programación Orientada a Objetos  
> **Repositorio Oficial:** [https://github.com/Solit0/Sistema-de-reservas](https://github.com/Solit0/Sistema-de-reservas)

---

## 1. Resumen del Sistema

Plataforma integral para la gestión y tarificación de tres tipos de espacios físicos dentro de un complejo multiuso, resolviendo la tarificación y disponibilidad mediante **polimorfismo puro** sin condicionales de tipo:

* **Salas de Reunión:** $180.00 / hora (+25% de recargo si el horario inicia entre las 14:00 y las 19:00).
* **Escritorios Individuales:** $75.00 / hora (tarifa plana continua).
* **Canchas Sintéticas:** $120.00 por cada bloque cerrado de 60 minutos (calculado con función techo `ceil()`) + $35.00 fijo por bloque en horario pico/nocturno.

---

## 2. Requisitos del Entorno

* **PHP:** Versión 8.2 o superior con extensiones `pdo`, `pdo_mysql`, `fileinfo` y `mbstring`.
* **Servidor Web:** PHP Built-in Server, Apache o Nginx.
* **Gestor de Dependencias:** Composer (Autoloading PSR-4).
* **Base de Datos:** MySQL 8.0+ o MariaDB 10.5+ (con soporte para Single Table Inheritance).

---

## 3. Guía de Instalación y Despliegue Rápido

### Paso 1: Clonar el Repositorio
```bash
git clone git@github.com:Solit0/Sistema-de-reservas.git
cd Sistema-de-reservas
```

### Paso 2: Generar el Mapa de Clases (Composer)
```bash
composer dump-autoload
```

### Paso 3: Configurar la Base de Datos MySQL
1. Crear una base de datos llamada `sistema_reservas`:
   ```sql
   CREATE DATABASE sistema_reservas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Importar el esquema y los datos iniciales desde `database/schema.sql`:
   ```bash
   mysql -u root -p sistema_reservas < database/schema.sql
   ```
3. Crear el archivo `config/config.php` (este archivo está protegido por `.gitignore`):
   ```php
   <?php
   return [
       'host'     => '127.0.0.1',
       'port'     => 3306,
       'database' => 'sistema_reservas',
       'username' => 'tu_usuario',
       'password' => 'tu_contraseña',
       'charset'  => 'utf8mb4'
   ];
   ```

### Paso 4: Levantar el Servidor de Desarrollo
```bash
php -S 127.0.0.1:8000 -t public
```
Abrir en el navegador: [http://127.0.0.1:8000](http://127.0.0.1:8000)

---

## 4. Catálogo de Rutas Web (8 Páginas Oficiales)

| Página | Ruta Web | Descripción Técnica |
| :--- | :--- | :--- |
| **1. Dashboard** | `/index.php` | Métricas generales, conteo por tipo y accesos rápidos al sistema. |
| **2. Catálogo de Espacios** | `/espacios/index.php` | Tabla con miniaturas, tarifas estándar calculadas dinámicamente (`calcularTarifa(2)`) y acciones. |
| **3. Registro de Espacio** | `/espacios/crear.php` | Formulario con protección CSRF, drag & drop, presets rápidos y validación en servidor (PRG). |
| **4. Edición de Espacio** | `/espacios/editar.php` | Carga de datos existentes y actualización segura con reemplazo de imagen física. |
| **5. Ficha Técnica** | `/espacios/ver.php?id={id}` | Detalle individual del espacio con llamada polimórfica sin `instanceof`. |
| **6. Confirmación de Eliminación**| `/espacios/eliminar.php?id={id}` | Pantalla defensiva procesada estrictamente por método `POST`. |
| **7. Módulo de Reservas** | `/reservas/index.php` y `/crear.php` | Formulario interactivo con cálculo en tiempo real y prevención de traslapes en base de datos. |
| **8. Reporte Financiero** | `/espacios/reporte.php` | Agregación dinámica de facturación y ocupación por tipo de espacio consumiendo datos de PDO. |

---

## 5. Ejecución de Pruebas Automatizadas

El proyecto incluye dos suites de verificación completa:

### 1. Suite de Pruebas Unitarias e Integración (6 Suites)
```bash
composer test
```
Ejecuta automáticamente las 30+ aserciones cubriendo tarifas polimórficas, validador estricto, protección CSRF, gestor de imágenes seguro, algoritmo anti-traslape y persistencia en repositorio.

### 2. Entrypoint y Simulación CLI (Fase 1)
```bash
php main.php
```
Instancia los 3 espacios heterogéneos, simula reservas en horarios pico y bloques, ejecuta el reporte del día en consola, prueba la captura controlada de `InvalidArgumentException` y exporta los datos a `reservas.json` y `reservas.csv`.

---

## 6. Arquitectura y Buenas Prácticas

* **Fábrica Polimórfica:** `src/Factories/EspacioFactory.php` es el único punto autorizado a evaluar el campo discriminador de la base de datos para instanciar las subclases.
* **Invariante Anti-Traslapes:** Algoritmo en `ReservaRepositorio.php` con consulta parametrizada que garantiza que dos reservas no colisionen en el mismo espacio.
* **Seguridad Defensiva:**
  * Mitigación de XSS mediante la función auxiliar de escape `e()`.
  * Protección contra CSRF con tokens criptográficos de 64 caracteres en sesión.
  * Verificación estricta de tipo MIME con Fileinfo para subida de imágenes y mitigación de Path Traversal.
  * Todas las consultas SQL preparadas con placeholders nombrados (cero interpolación de variables).
* **Iconografía:** 100% iconografía vectorial SVG profesional, sobria y accesible (sin emojis).

---

## 7. Distribución del Equipo de Desarrollo

* **Integrante 1:** Contratos Base (`Reservable`), Entidad `Reserva` y Modelo Abstracto `Espacio`.
* **Integrante 2:** Jerarquía Concreta (`SalaReunion`, `EscritorioIndividual`, `Cancha`) y Tarifas Polimórficas.
* **Integrante 3:** Formularios de Espacios, Validación en Servidor y Flujo PRG.
* **Integrante 4 (Harry Damian):** Multimedia (`GestorImagenes`), Módulo de Reservas, Reporte Web Polimórfico, Runner CLI (`main.php`), Pruebas Automatizadas e Informe Técnico Oficial.
