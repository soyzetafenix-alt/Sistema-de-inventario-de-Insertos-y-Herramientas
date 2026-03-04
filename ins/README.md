# Sistema básico de Insertos (inicio)

Objetivo: Implementar lo mínimo solicitado: creación de usuarios, creación de insertos y login tanto para usuario como admin.

Archivos añadidos:
- `db.php` : conexión a PostgreSQL y helpers de sesión.
- `index.php` : página inicial.
- `login.php` : formulario de login.
- `dashboard.php` : panel básico según rol.
- `create_user.php` : formulario (admin) para crear usuarios.
- `create_inserto.php` : formulario (admin) para crear insertos.
- `logout.php` : cerrar sesión.

Requisitos:
- PostgreSQL con la base de datos importada desde `insbd.sql` (archivo ya presente en el proyecto).
- PHP 7.4+ con PDO_PGSQL habilitado (XAMPP puede usarse, asegúrese de tener PostgreSQL disponible).

Variables de entorno (opcional):
- `DB_HOST` (por defecto `localhost`)
- `DB_PORT` (por defecto `5432`)
- `DB_NAME` (por defecto `ins`)
- `DB_USER` (por defecto `postgres`)
- `DB_PASS` (por defecto vacío)

Uso rápido:
1. Importar `insbd.sql` a PostgreSQL.
2. Colocar esta carpeta bajo su servidor PHP (ej. `c:\xampp\htdocs\ins`).
3. Ajustar variables de entorno o editar `db.php` para coincidir con la conexión.
4. Abrir en el navegador `http://localhost/ins/` y entrar con los usuarios iniciales `admin` / `user` (las contraseñas se crearon en `insbd.sql` como `admin` y `user`).

Siguientes pasos que puedo implementar ahora si quieres:
- Búsqueda de insertos y carrito para usuarios.
- Gestión de stock, historial y notificaciones para admin.
