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

## Cómo inicializar el proyecto

```bash
# 1. Crear el proyecto Laravel (si todavía no existe)
composer create-project laravel/laravel unificacion-pagos
cd unificacion-pagos
composer require maatwebsite/excel

# 2. Copiar los archivos de este paquete dentro del proyecto,
#    respetando las mismas carpetas (config/, app/, database/,
#    resources/, routes/). Fusionar carpeta por carpeta, no
#    reemplazar "app/" entero para no perder los archivos base
#    de Laravel (Providers, Controller.php, Models/User.php).

# 3. Migrar la base de datos
php artisan migrate

# 4. Levantar el servidor
php artisan serve
```

Después, entrar a `http://127.0.0.1:8000/pagos/unificacion` para subir el
Excel desde el navegador, o usar el comando de consola:

```bash
php artisan pagos:unificar --source=excel --file="ruta/al/archivo.xlsx" --dry-run
```

> Requisitos: PHP 8.3 (ojo con versiones más nuevas como 8.5: todavía no
> son compatibles con las librerías que leen Excel).

## Cómo funciona, brevemente

1. **Normaliza** cada fila del Excel (montos, fechas, etc.) y calcula una
   "clave de negocio" que identifica que dos filas son del mismo
   turno/prestación (mismo paciente, fecha, hora, prestación...).
2. **Agrupa** las filas por esa clave. Si un grupo tiene una fila de
   cobertura y una de "Bono a Persona", las **unifica**, sumando los
   montos.
3. Si además dos pares comparten exactamente el mismo número de
   comprobante (por ejemplo, una orden de laboratorio con varios
   análisis distintos bajo un mismo número), se **fusionan en una sola
   fila** para no repetir el mismo comprobante en el resultado.
4. Lo que no se puede unificar solo (por ejemplo, grupos con 3 o más
   filas) queda separado para **revisión manual**, sin tocarse.
5. Todo esto primero se puede correr en modo **dry-run** (solo mostrar el
   resultado, sin guardar nada), y recién después persistirse en la base.
6. El resultado final se puede **descargar en un Excel** con todo ya
   unificado.
