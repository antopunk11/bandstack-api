# BandStack Manager - API Backend

Esta es la API RESTful de **BandStack Manager**, el motor principal que da soporte al cliente de Angular para la gestión del inventario, los eventos, la contabilidad y las ventas de mercancía de bandas musicales.

## 🚀 Características (Features)

- **Autenticación y Autorización:**
  - Sistema de seguridad robusto con JWT (JSON Web Tokens).
  - Flujo completo de Access y Refresh Tokens persistentes en base de datos.
  - Control de Acceso Basado en Roles (RBAC): Administradores y Miembros.
  - **Registro de accesos (Audit Logs):** Auditoría de inicios de sesión exitosos y fallidos con IP y User-Agent para mayor seguridad.

- **Inventario y Productos:**
  - Organización jerárquica: Categorías > Productos > Variantes (tallas, colores, precios, stock).
  - Sincronización automática de stock del producto padre basado en la suma de existencias de sus variantes.
  - Registro inmutable de movimientos de stock (compras, ventas, ajustes, regalos).

- **Eventos y Finanzas:**
  - Gestión del ciclo de vida de los eventos (conciertos, festivales, ensayos).
  - Endpoints para Puntos de Venta (POS) con registro detallado de los métodos de pago.
  - **Gastos Recurrentes Automatizados:** Plantillas de gastos (semanales, mensuales, anuales) que generan gastos automáticos cuando se alcanza la fecha de vencimiento.
  - Control de Gastos (dietas, gasolina con kilometraje dinámico, peajes, alquiler de equipo) con filtros avanzados por categoría, creador y texto, y ordenamiento multidimensional.

## 🛠️ Tecnologías

- PHP 8.x
- MySQL 8.0+ / MariaDB 10.4+ (Comunicación segura mediante PDO)
- Arquitectura inspirada en MVC (Modelos, Controladores y Middleware)

## 📦 Instalación y Configuración

1. **Base de Datos:** 
   Crea una base de datos en MySQL/MariaDB y ejecuta el script de inicialización `schema.sql`. Este creará todas las tablas relacionales con sus restricciones y datos semilla.

2. **Configuración del Entorno:**
   Configura las variables de entorno de tu servidor (o usa un archivo `.env` si está implementado) con las credenciales de tu base de datos y tu clave secreta `JWT_SECRET`.

3. **Despliegue Local (XAMPP / Servidor Web):**
   Asegúrate de que el enrutamiento de Apache o Nginx redirija correctamente las peticiones de la API (usualmente a través de un `index.php` principal).

4. **Primer Acceso:**
   La base de datos se inicializa con el usuario `admin@bandstack.local`. ¡Se recomienda actualizar la contraseña inmediatamente después del primer inicio de sesión!