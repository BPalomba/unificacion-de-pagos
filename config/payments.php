<?php

return [

    /*
    |--------------------------------------------------------------------
    | Campos que componen la "clave de negocio"
    |--------------------------------------------------------------------
    | Estos campos, combinados, identifican que dos filas corresponden
    | al mismo turno/prestación (y por lo tanto son candidatas a ser
    | el par duplicado cobertura + Bono a Persona).
    | Ajustar esta lista si el relevamiento con el hospital detecta que
    | hace falta agregar o quitar algún campo.
    */
    'business_key_fields' => [
        'paciente',
        'dni',
        'fecha',
        'hora',
        'departamento',
        'especialidad',
        'medico',
        'cobertura',
        'numerobeneficiario',
        'prestacion',
        'codigo',
        'cantidad',
    ],

    /*
    |--------------------------------------------------------------------
    | Valor de TIPO (de atencion) que identifica el lado "paciente" del par
    |--------------------------------------------------------------------
    | Cualquier otro valor de TIPO dentro del mismo grupo se interpreta
    | como el lado "cobertura / obra social".
    */
    'tipo_atencion' => 'Bono a Persona',

    /*
    |--------------------------------------------------------------------
    | Tolerancia temporal (en segundos) para el cruce por NUMERO
    |--------------------------------------------------------------------
    | El campo NUMERO suele incluir una marca de tiempo de generación
    | (formato ...YYYYMMDDHHMMSS + sufijo C/P/S). Esa marca puede diferir
    | levemente entre las dos filas del par porque el sistema las graba
    | en dos escrituras separadas. Validado contra datos reales: 5
    | segundos detectó ~91% de los pares. Ajustar si hace falta con un
    | dataset más grande.
    */
    'numero_time_tolerance_seconds' => 5,

    /*
    |--------------------------------------------------------------------
    | Prioridad de ESTADO para el registro unificado
    |--------------------------------------------------------------------
    | Cuando las dos filas del par tienen ESTADO distinto, se usa el de
    | mayor prioridad (primero en la lista = mayor prioridad).
    | ESTA REGLA ES UN SUPUESTO INICIAL: confirmar con el hospital.
    */
    'estado_priority' => [
        'ESTADO_FACTURADO',
        'ESTADO_CONFIRMADO',
        'ESTADO_PREFACTURADO',
        'ESTADO_NOCONFIRMADO',
    ],

    /*
    |--------------------------------------------------------------------
    | Duración de los informes (en minutos)
    |--------------------------------------------------------------------
    | Cada Excel subido genera un informe que solo ve quien lo subió.
    | Pasado este tiempo deja de estar disponible y se borra de la base
    | junto con sus pagos (son datos de pacientes: no se conservan).
    */
    'report_ttl_minutes' => (int) env('PAYMENTS_REPORT_TTL_MINUTES', 120),

    /*
    |--------------------------------------------------------------------
    | Zona horaria para mostrar horas en pantalla
    |--------------------------------------------------------------------
    | La app guarda todo en UTC (app.timezone); esto solo afecta cómo se
    | muestran la hora de subida y de vencimiento de los informes.
    */
    'display_timezone' => env('PAYMENTS_DISPLAY_TIMEZONE', 'America/Argentina/Buenos_Aires'),

    /*
    |--------------------------------------------------------------------
    | Secreto del cron de limpieza
    |--------------------------------------------------------------------
    | Vercel Cron llama a /cron/purgar-informes con el header
    | "Authorization: Bearer <CRON_SECRET>". Sin secreto, la ruta no existe.
    */
    'cron_secret' => env('CRON_SECRET'),

    /*
    |--------------------------------------------------------------------
    | Endpoint por defecto (cuando la fuente de datos es "endpoint")
    |--------------------------------------------------------------------
    */
    'endpoint_url' => env('PAYMENTS_ENDPOINT_URL'),
    'endpoint_token' => env('PAYMENTS_ENDPOINT_TOKEN'),

];
