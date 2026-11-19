<div align="center">

# 🏥 Unificación de Pagos Duplicados

**Sistema de conciliación de copagos para facturación hospitalaria**

[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![Docker](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)](https://www.docker.com/)
[![License](https://img.shields.io/badge/license-privado-lightgrey)](#-licencia)

[Reportar un bug](../../issues) · [Pedir una funcionalidad](../../issues)

</div>

---

## 📋 Índice

- [Para qué sirve](#-para-qué-sirve)
- [Capturas de pantalla](#-capturas-de-pantalla)
- [Características](#-características)
- [Cómo funciona](#-cómo-funciona)
- [Stack tecnológico](#-stack-tecnológico)
- [Instalación](#-instalación)
- [Comandos útiles](#-comandos-útiles)
- [Estructura del proyecto](#-estructura-del-proyecto)
- [Solución de problemas](#-solución-de-problemas)
- [Roadmap](#-roadmap)
- [Colaboradores](#-colaboradores)
- [Licencia](#-licencia)

---

## 🎯 Para qué sirve

En el sistema de facturación del hospital, cuando un paciente paga una prestación con una cobertura (obra social/prepaga) que exige un pago adicional, el sistema genera **dos registros separados** para un mismo pago: uno para la parte que cubre la cobertura y otro para el **"Bono a Persona"** que paga el paciente.

Este proyecto detecta esos pares duplicados, los **unifica en un solo registro consolidado** (sin perder ninguno de los comprobantes originales), y permite exportar el resultado a un Excel limpio, listo para conciliar.

Carga de datos:
- 📊 **Excel** (uso principal — `.xlsx` únicamente)

> ⚠️ **Importante:** solo se aceptan archivos `.xlsx`. Archivos `.xls` (formato antiguo) o `.csv` no están soportados por ahora.

---

## 📸 Capturas de pantalla

<!--
  Reemplazar estas rutas por las imágenes reales. Sugerencia: crear una
  carpeta docs/screenshots/ en el repo y subir ahí los .png, o arrastrar
  las imágenes directo a un Issue/PR de GitHub para obtener una URL
  pública y pegarla acá.
-->

<div align="center">

<table>
<tr>
<td align="center"><b>Pantalla principal</b></td>
<td align="center"><b>Carga de Excel</b></td>
</tr>
<tr>
<td><img src="https://github.com/user-attachments/assets/62c8e6eb-653f-487f-9d11-acc27606ba09" width="100%"></td>
<td><img src="https://github.com/user-attachments/assets/2c4d99d8-50a4-499d-a695-fdc50f1dcb45" width="100%"></td>
</tr>
</table>

</div>

---

## ✨ Características

- ✅ Detección automática de pares duplicados (cobertura + Bono a Persona)
- ✅ Unificación en un solo registro, sumando montos y sin perder trazabilidad de los comprobantes originales
- ✅ Cola de **revisión manual** para los casos ambiguos (3+ registros, combinaciones inesperadas de TIPO)
- ✅ Carga por **Excel** de reportes del hospital
- ✅ Panel web con filtros (médico, paciente, obra social, período) y tabla paginada
- ✅ Exportación a Excel del resultado ya unificado
- ✅ Comando Artisan para correrlo desde consola / automatizarlo
- ✅ Dockerizado, con SQLite listo para desarrollo sin configuración extra

---

## ⚙️ Cómo funciona

1. **Normaliza** cada fila del Excel (montos, fechas, horas) y calcula una *clave de negocio* que identifica que dos filas son del mismo turno/prestación (mismo paciente, fecha, hora, prestación...).
2. **Agrupa** las filas por esa clave. Si un grupo tiene una fila de cobertura y una de "Bono a Persona", las **unifica**, sumando los montos.
3. Lo que no se puede unificar solo (por ejemplo, grupos con 3 o más filas) queda separado para **revisión manual**, sin tocarse.
4. Todo esto primero se puede correr en modo **dry-run** (solo mostrar el resultado, sin guardar nada), y recién después persistirse en la base.
5. El resultado final se puede **descargar en un Excel** con todo ya unificado, o consultarse desde el panel web.

---

## 🛠 Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3 + Laravel 11 |
| Lectura/escritura de Excel | Maatwebsite/Laravel-Excel (PhpSpreadsheet) |
| Base de datos | SQLite (desarrollo) |
| Front-end | Blade + Bootstrap 5 |
| Contenedores | Docker + Docker Compose |

---

## 🚀 Instalación

### Con Docker (recomendado)

```bash
# 1. Clonar el repositorio
git clone https://github.com/BPalomba/unificacion-de-pagos.git
cd unificacion-de-pagos

# 2. Copiar las variables de entorno
cp .env.example .env

# 3. Levantar todo con Docker (instala dependencias, genera la key,
#    crea la base SQLite y corre las migraciones automáticamente)
docker compose up -d --build
```

La app queda corriendo en **http://localhost:8000**, y la pantalla principal en **http://localhost:8000/**.

> ⚠️ **Requisito de PHP: 8.3.** No es compatible con versiones superiores (8.5+), ya que `phpoffice/phpspreadsheet` todavía no las soporta. La imagen Docker ya viene fijada en PHP 8.3, así que si usás Docker no tenés que preocuparte por esto — solo aplica si corrés el proyecto sin contenedores.

<details>
<summary>Instalación manual (sin Docker)</summary>

```bash
git clone https://github.com/BPalomba/unificacion-de-pagos.git
cd unificacion-de-pagos
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

</details>

---

## 🔧 Comandos útiles

```bash
# Correr el comando de unificación por consola, dentro del contenedor
docker compose exec app php artisan pagos:unificar --source=excel --file=/var/www/html/storage/app/pagos.xlsx --dry-run

# Lo mismo, pero persistiendo el resultado en la base
docker compose exec app php artisan pagos:unificar --source=excel --file=/var/www/html/storage/app/pagos.xlsx

# Desde el endpoint del hospital en vez de Excel
docker compose exec app php artisan pagos:unificar --source=endpoint --dry-run

# Entrar a una shell del contenedor
docker compose exec app bash

# Ver logs en vivo
docker compose logs -f app

# Parar todo
docker compose down

# Reconstruir (después de cambiar el Dockerfile o composer.json)
docker compose up -d --build
```

---

## 📁 Estructura del proyecto

```
app/
├── Console/Commands/          # Comando Artisan pagos:unificar
├── Exports/                   # Exportación a Excel del resultado unificado
├── Http/Controllers/          # Controllers del panel web
├── Imports/                   # Lectura del Excel de entrada
├── Models/                    # Payment, UnifiedPayment, PaymentReviewGroup
└── Services/Payments/
    ├── DataSources/           # Fuentes de datos: Excel / Endpoint
    ├── PaymentNormalizer.php       # Parseo de fechas, montos, clave de negocio
    ├── PaymentDeduplicationService.php  # Lógica central de detección/unificación
    └── UnifiedPaymentsQuery.php    # Consulta compartida (tabla web + export)
database/migrations/           # payments, unified_payments, payment_review_groups
resources/views/               # Panel web (Blade + Bootstrap)
docker/                        # Dockerfile, entrypoint.sh
```

---

## 🩹 Solución de problemas

<details>
<summary><strong>Error "could not find driver" (SQLite)</strong></summary>

Falta habilitar la extensión `pdo_sqlite` en tu `php.ini`. Si usás Docker, ya viene resuelto en la imagen.
</details>

<details>
<summary><strong>Composer no puede instalar maatwebsite/excel</strong></summary>

Estás usando una versión de PHP demasiado nueva (8.5+). `phpoffice/phpspreadsheet` requiere PHP `<8.5`. Usá PHP 8.3 (o corré con Docker, que ya lo tiene fijado).
</details>

<details>
<summary><strong>Las columnas FECHA/HORA aparecen vacías o mal</strong></summary>

Excel puede entregar esas celdas en varios formatos (texto, número de serie, con coma decimal en configuración regional argentina). El normalizador (`PaymentNormalizer`) ya contempla estos casos — si encontrás un formato que no cubre, revisá `normalizeFecha()` / `normalizeHora()`.
</details>

<details>
<summary><strong>Un pago que debería unificarse queda "para revisión manual"</strong></summary>

Revisá que ambas filas (cobertura y Bono a Persona) tengan **exactamente** los mismos valores en los campos de la clave de negocio (paciente, DNI, fecha, hora, prestación, código, cantidad, etc. — ver `config/payments.php`). Cualquier diferencia, aunque sea de formato, hace que no se agrupen.
</details>

---

## 🗺 Roadmap

- [ ] Confirmar con el equipo de facturación la prioridad de `ESTADO` cuando cobertura y bono difieren
- [ ] Soporte para `.xls` / `.csv`
- [ ] Exportar respetando los filtros activos en el panel web
- [ ] Mover la persistencia a un Job en cola para archivos muy grandes

---

## 👥 Colaboradores

<!-- Completar con los datos reales de cada colaborador -->

| | Nombre | GitHub | LinkedIn |
|---|---|---|---|
| <img src="https://github.com/BPalomba.png" width="48" style="border-radius:50%"> | Bruno Palombarini | [@BPalomba](https://github.com/BPalomba) | [LinkedIn](https://www.linkedin.com/in/bruno-palombarini-b43850285) |
| <img src="https://github.com/L-LopezMartin.png" width="48" style="border-radius:50%"> | Leandro Lopez | [@L-LopezMartin](https://github.com/L-LopezMartin) | [LinkedIn](https://www.linkedin.com/in/leandro-a-lopez-martin/) |
| <img src="https://github.com/gaab2305.png" width="48" style="border-radius:50%"> | Gabriela Guzmán Muñoz | [@gaab2305](https://github.com/gaab2305) | [LinkedIn](https://www.linkedin.com/in/gabriela-guzm%C3%A1n-mu%C3%B1oz-09a99970/) |
| <img src="https://github.com/EmilianoBarroso.png" width="48" style="border-radius:50%"> | Emiliano  Barroso | [@EmilianoBarroso](https://github.com/EmilianoBarroso) | [LinkedIn](https://www.linkedin.com/in/emiliano-agust%C3%ADn-barroso-verdejo/) |

---

## 📄 Licencia

Proyecto desarrollado para uso interno del Hospital Universitario. <!-- Reemplazar por la licencia real si corresponde (MIT, propietaria, etc.) -->

</div>
