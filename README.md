# FarmaNet Pro — Sistema Web Farmacia Privada

**Proyecto Integrado — Ingeniería en Ciberseguridad (INACAP)**  
**Autores:** Diego Elgueta, Angel Lara  
**Profesor Guía:** Roberto Alexis Alveal Ortega  

---

## Descripción del Proyecto

**FarmaNet Pro** es una plataforma web integral desarrollada en **Laravel 11 (PHP 8.3)** para la simulación operativa, logística y regulatoria de un establecimiento farmacéutico privado en Chile, concebida bajo el principio de **Seguridad por Diseño (*Security by Design*)** y el paradigma arquitectónico **4+1 Vistas de Philippe Kruchten**.

### Módulos Principales
1. **Inicio de Sesión y Acceso Rápido por Rol (RBAC / NIST SP 800-63B)**: Validación de RUN chileno (Módulo 11), contraseñas hasheadas con Bcrypt/Argon2id y selector rápido de demostración para los 4 perfiles institucionales.
2. **Terminal Punto de Venta (POS)**: Búsqueda reactiva por nombre comercial, principio activo o código de barras, asignación automática de lote por regla **FEFO (*First Expired, First Out*)** con bloqueo pesimista (`SELECT ... FOR UPDATE`), cálculo en vivo de copagos de salud y bloqueo automático ante medicamentos sujetos a control del **ISP (Receta Retenida)**.
3. **Módulo de Convenios de Salud y Copagos**: Gestión de convenios públicos y privados (Fonasa A/B/C/D, Isapres Banmédica, Colmena, Cruz Blanca y garantías GES/AUGE).
4. **Bandeja de Autorización de Recetas Retenidas (Químico Farmacéutico) y Libro Control ISP Digital**: Cola en vivo de validaciones pendientes, verificación de segundo factor mediante **PIN Criptográfico Q.F. (`4421`)** y generación de sello inmutable `SHA-256`.
5. **Panel de Gestión Logística de Inventario (FEFO)**: Segregación física estricta entre **Bodega** y **Vitrina**, semáforo cromático de caducidad (`<20 días` crítico rojo, `20-30 días` alerta ámbar, `>30 días` óptimo verde), recepción de remesas y traspaso asistido Bodega → Vitrina con bloqueo de insumos vencidos.
6. **Dashboard Gerencial y Analítica**: KPIs en tiempo real, gráficos de tendencias de ingresos y rotación Pareto, alertas FEFO y exportación de reportes a **PDF vectorial** y **Excel (CSV)**.
7. **Mantenedor de Usuarios RBAC y Auditoría Forense NoSQL (MongoDB / JSON-BSON)**: Administración de privilegios y visor de documentos inmutables de auditoría forense encadenados con `SHA-256`.

---

## Credenciales de Demostración

| Rol Institucional | Nombre | RUN | Contraseña | PIN Q.F. |
| :--- | :--- | :--- | :--- | :--- |
| **Vendedor (POS)** | Ana Torres | `12.345.678-5` | `Password123!` | — |
| **Químico Farmacéutico** | Dr. Carlos Vega | `11.222.333-4` | `Password123!` | `4421` |
| **Bodeguero (FEFO)** | Luis Pinto | `15.432.876-5` | `Password123!` | — |
| **Administrador General** | María Salinas | `10.111.222-3` | `Password123!` | — |

---

## Instalación y Ejecución Local

```bash
# 1. Instalar dependencias de PHP
composer install

# 2. Configurar variables de entorno y clave de cifrado AES-256
cp .env.example .env
php artisan key:generate

# 3. Crear base de datos SQLite (o configurar MySQL 8.0 en .env) y ejecutar migraciones + seeders
php artisan migrate:fresh --seed

# 4. Iniciar servidor de desarrollo
php artisan serve
```

Acceder en el navegador a: `http://127.0.0.1:8000`

---

## Ejecución del Plan de Pruebas (QA — PHPUnit)

El proyecto incluye los casos de prueba automatizados documentados en el Capítulo 8 (`CP-01` a `CP-05`):

```bash
php artisan test
```
