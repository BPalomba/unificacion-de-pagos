# Unificación de Pagos Duplicados

## Para qué sirve

En el sistema de facturación del hospital, cuando un paciente paga una
prestación con una cobertura (obra social/prepaga) que exige un pago
adicional, el sistema genera **dos registros separados** para un mismo
pago: uno para la parte que cubre la cobertura y otro para el "Bono a
Persona" que paga el paciente. Este proyecto detecta esos pares
duplicados, los **unifica en un solo registro consolidado** (sin perder
ninguno de los comprobantes originales), y permite exportar el resultado
a un Excel limpio, listo para conciliar.

Soporta dos formas de cargar los datos: **Excel** (uso principal) o el
**endpoint** del sistema del hospital.

## Importante solo acepta archivos .xlsx

## Cómo inicializar el proyecto

```bash
# 1. Clonar el repositorio
git clone https://github.com/BPalomba/unificacion-de-pagos.git
cd unificacion-de-pagos

# 2. Instalar las dependencias
composer install

# 3. Generar la clave de la aplicación
php artisan key:generate

# 4. Migrar la base de datos
php artisan migrate

# 5. Levantar el servidor
php artisan serve
```

Después, entrar a `http://127.0.0.1:8000/pagos/unificacion` para subir el
Excel desde el navegador, o usar el comando de consola:

```bash
php artisan pagos:unificar --source=excel --file="ruta/al/archivo.xlsx" --dry-run
```

> Requisitos: PHP 8.3.
> No es compatible con versiones superiores

## Cómo funciona

1. **Normaliza** cada fila del Excel (montos, fechas, etc.) y calcula una
   "clave de negocio" que identifica que dos filas son del mismo
   turno/prestación (mismo paciente, fecha, hora, prestación...).
2. **Agrupa** las filas por esa clave. Si un grupo tiene una fila de
   cobertura y una de "Bono a Persona", las **unifica**, sumando los
   montos.
3. Lo que no se puede unificar solo (por ejemplo, grupos con 3 o más
   filas) queda separado para **revisión manual**, sin tocarse.
4. Todo esto primero se puede correr en modo **dry-run** (solo mostrar el
   resultado, sin guardar nada), y recién después persistirse en la base.
5. El resultado final se puede **descargar en un Excel** con todo ya
   unificado.
