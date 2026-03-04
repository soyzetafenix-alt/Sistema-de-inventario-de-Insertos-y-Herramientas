# 📬 Sistema de Insertos VALMET - Guía de Uso
## Flujo de Solicitudes y Notificaciones (Inbox)
### Para Usuarios 
#### 1️⃣ Buscar Insertos
- Ir a **Buscar insertos** en el dashboard
- Buscar por código o condiciones de corte
- Ver stock disponible y foto del inserto

#### 2️⃣ Agregar al Carrito
- Seleccionar cantidad y agregar al carrito
- Ver contador de items en el carrito

#### 3️⃣ Crear Solicitud
- Ir a **Mi carrito**
- Revisar los insertos seleccionados
- Completar:
  - **Fecha de entrega**: Cuándo necesitas los insertos
  - **Fecha de devolución aproximada**: Cuándo los vas a devolver
- Confirmar solicitud

#### 4️⃣ Recibir Notificaciones en tu Inbox
- **Dashboard**: Ver badge 📬 con notificaciones sin leer
- **Mi Notificaciones**: Ver tu bandeja de entrada completa con:
  - ✓ Solicitud aceptada
  - ✗ Solicitud rechazada
  - 📋 Solicitud pendiente
  - 📝 Confirmación de envío

#### 5️⃣ Gestionar Notificaciones
- **Marcar como leído**: Hacer clic en "Leer"
- **Marcar todas como leídas**: Botón en la parte superior
- **Eliminar**: Hacer clic en "Eliminar"

---

### Para Administrador

#### 1️⃣ Gestionar Usuarios
- Ir a **Gestionar usuarios** en el dashboard
- Buscar usuarios por nombre de usuario o DNI
- **Activar/Desactivar** usuarios según sea necesario

#### 2️⃣ Revisar Solicitudes
- Ir a **Solicitudes** en el dashboard
- Ver tres pestañas:
  - **Pendientes**: Solicitudes esperando aprobación
  - **Aceptadas**: Solicitudes aprobadas
  - **Rechazadas**: Solicitudes rechazadas

#### 3️⃣ Aprobar o Rechazar Solicitudes
- **Aceptar**: Confirmar la solicitud
  - ✓ Se genera notificación para el usuario: "Tu solicitud #X fue aceptada"
  - Se resta stock automáticamente
  
- **Rechazar**: Rechazar con motivo
  - ✗ Se genera notificación para el usuario: "Tu solicitud #X fue rechazada: [motivo]"
  - No se modifica el stock

#### 4️⃣ Ver Reportes
- **Reportes**: Ver uso por inserto y usuario
- Filtrar por fecha y estado

#### 5️⃣ Gestionar Stock
- **Buscar insertos**: Administrar stock de cada inserto
- **Historial**: Ver movimientos de stock
- Ver qué usuario retiró qué cantidad

---

## 📧 Sistema de Notificaciones (Inbox)

### Tipos de Notificaciones

| Tipo | Icono | Descripción |
|------|-------|-------------|
| Solicitud Creada | 📝 | Usuario envió una nueva solicitud |
| Solicitud Aceptada | ✓ | Admin aprobó la solicitud |
| Solicitud Rechazada | ✗ | Admin rechazó la solicitud |
| Solicitud Pendiente | 📋 | La solicitud sigue pendiente |
| Información | ℹ️ | Otros mensajes del sistema |

### Características del Inbox

✅ **Notificaciones sin leer**: Se muestran con fondo azul claro y punto indicador
✅ **Marca como leído**: Cambia el estilo visual
✅ **Elimina notificaciones**: Borralas cuando ya no las necesites
✅ **Marca todas como leídas**: Opción rápida para desmarcar todo
✅ **Badge en navbar**: Muestra cantidad de notificaciones sin leer
✅ **Hora de creación**: Cada notificación muestra cuándo se creó

---
## 🔐 Credenciales de Prueba

```
Usuario Admin:
  Usuario: admin
  Contraseña: admin
  Rol: Administrador

Usuario Regular:
  Usuario: user
  Contraseña: user
  Rol: Usuario
```

---

## 🗄️ Diagrama de Flujo

```
USUARIO                          SISTEMA                    ADMIN
  │
  ├─ Busca Insertos ─────────────────────────────────────────┐
  │                                                            │
  ├─ Agrega al Carrito ──────────────────────────────────────┤
  │                                                            │
  ├─ Confirma Solicitud ───┬──────────────────────────────────┤
  │                        │ Crea Solicitud en BD             │
  │                        │ Crea Notificación                │
  │                        │ (request_created)                │
  │                        └──────────────────────────────────┤
  │                                                            │
  ├─ Ve en Inbox ✓ "Solicitud enviada"                       │
  │                                                            │
  │                                                   ┌─ Ve Solicitud
  │                                                   │ en Pendientes
  │                                                   │
  │                                                   ├─ Acepta/Rechaza
  │                                                   │ (Stored Procedure)
  │                                                   │
  │                                                   └─ Crea Notificación:
  │                                                      ✓ Aceptada
  │                                                      ✗ Rechazada
  │
  └─ Ve en Inbox la respuesta ✓ / ✗
```

---

## 📝 Notas Importantes

⚠️ **Notificaciones en tiempo real**: Las notificaciones se crean automáticamente cuando:
   - Usuario envía una solicitud
   - Admin acepta/rechaza la solicitud

⚠️ **Sin cambios en la Base de Datos**: El sistema usa la tabla `notifications` existente

⚠️ **Persistencia**: Las notificaciones se guardan en la BD permanentemente

⚠️ **Activación de Usuarios**: El admin puede desactivar usuarios si es necesario

---

## 🚀 Próximas Mejoras (Futuro)

- Integración real con email (usando email_helper.php)
- Devoluciones de insertos con confirmación
- Recordatorios automáticos de devolución
- Reportes avanzados con gráficos
