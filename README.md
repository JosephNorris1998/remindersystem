# Sistema de Recordatorios de Citas (RMS)

Plugin de WordPress para registrar citas médicas y enviar recordatorios automáticos por correo electrónico.

## Características

- Formulario de registro de citas vía shortcode `[reminder_form]`.
- Correo de confirmación inmediato al registrar una cita.
- Recordatorio configurable (por defecto 24 h antes de la cita).
- Recordatorio fijo de 48 h antes de la cita.
- Panel de administración para ver, buscar y gestionar citas.

---

## Cómo funcionan los recordatorios automáticos (WP-Cron)

### Lógica de envío

Los recordatorios usan la siguiente lógica **resistente a retrasos**:

- **Recordatorio configurable (ej. 24 h):** se envía cuando ya llegó el momento (`appointment_date - X horas <= ahora`) y la cita todavía es futura (`appointment_date > ahora`). El campo `reminder_sent` evita envíos duplicados.
- **Recordatorio de 48 h:** igual pero con umbral fijo de 48 horas, usando la columna `reminder_48h_sent`.

Esta lógica **no depende de una ventana exacta de tiempo**: aunque el cron llegue tarde, el próximo disparo encontrará la cita pendiente y la enviará.

### WP-Cron depende del tráfico

WP-Cron **no es un cron real de servidor**: se dispara únicamente cuando alguien visita el sitio web. Si no hay visitantes, los recordatorios no se envían a tiempo.

**Solución recomendada: usar UptimeRobot (o similar) para hacer pings periódicos.**

### Configuración con UptimeRobot Free (cada 5 minutos)

1. Crea un monitor HTTP en [uptimerobot.com](https://uptimerobot.com/) apuntando a:
   ```
   https://TU-DOMINIO.com/wp-cron.php?doing_wp_cron
   ```
2. (Recomendado) Agrega en `wp-config.php` para que el cron solo dependa del ping:
   ```php
   define('DISABLE_WP_CRON', true);
   ```
3. Con el plan **Free** los checks son cada **5 minutos**, por lo que los recordatorios pueden enviarse con un margen de hasta 5 minutos respecto a la hora ideal. Esto es aceptable para la mayoría de los casos.

### Configuración con LiteSpeed Cache

Si usas LiteSpeed Cache, asegúrate de excluir `wp-cron.php` del caché para que UptimeRobot ejecute realmente el cron:

**LiteSpeed Cache → Cache → Excludes → Do Not Cache URIs**
```
/wp-cron.php
```

---

## Estructura del plugin

```
reminder.php                  — Archivo principal del plugin
includes/
  class-rms-db.php            — Acceso a base de datos (install, upgrade, consultas)
  class-rms-email.php         — Envío de correos (confirmación, recordatorio, recordatorio 48h)
  class-rms-cron.php          — Registro del evento WP-Cron y procesamiento de recordatorios
  class-rms-admin.php         — Panel de administración
  class-rms-shortcode.php     — Shortcode [reminder_form]
assets/
  css/                        — Estilos frontend y admin
  js/                         — Scripts frontend y admin
```

---

## Esquema de base de datos

La tabla `{prefix}rms_appointments` incluye:

| Columna               | Descripción                                      |
|-----------------------|--------------------------------------------------|
| `reminder_sent`       | `1` si el recordatorio configurable fue enviado  |
| `reminder_sent_at`    | Fecha/hora en que se envió                       |
| `reminder_48h_sent`   | `1` si el recordatorio de 48 h fue enviado       |
| `reminder_48h_sent_at`| Fecha/hora en que se envió el de 48 h            |

Si el sitio se instaló antes de que se agregaran las columnas `reminder_48h_*`, el plugin las agrega automáticamente en la próxima carga (`maybe_upgrade()`).

---

## Depuración (WP_DEBUG)

Cuando `WP_DEBUG` está activo en `wp-config.php`, el plugin escribe en el log de errores mensajes como:

```
[RMS] process_reminders() iniciado. now (Panamá)=2025-01-15 09:00:00 | objetivo=24h | objetivo_48h=48h
[RMS] Recordatorios 24h pendientes encontrados: 2
[RMS] Recordatorio enviado: cita ID 5 (paciente@email.com).
[RMS] Recordatorios 48h pendientes encontrados: 1
[RMS] Recordatorio 48h enviado: cita ID 3 (otro@email.com).
```
