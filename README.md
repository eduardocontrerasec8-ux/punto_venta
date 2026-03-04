# Sistema Punto de Ventas

Sistema completo de punto de venta desarrollado en PHP, PostgreSQL, JavaScript y Materialize CSS.

## Características

- ✅ Autenticación de usuarios con diferentes niveles (Administrador, Operador, Solo lectura)
- ✅ Punto de venta con carrito de compras
- ✅ Gestión completa de inventario
- ✅ Control de proveedores
- ✅ Corte de caja diario con reportes
- ✅ Gestión de usuarios
- ✅ Interfaz responsiva y amigable
- ✅ Tickets de venta
- ✅ Alertas de bajo stock

## Requisitos

1. Servidor web (Apache recomendado)
2. PHP 7.4 o superior
3. PostgreSQL 12 o superior
4. Navegador web moderno

## Instalación

1. Copiar todos los archivos a la carpeta `htdocs` de XAMPP o `www` de WAMP
2. Acceder a `http://localhost/punto_venta/install.php`
3. Seguir las instrucciones de instalación
4. Configurar los datos de conexión a PostgreSQL
5. Eliminar el archivo `install.php` después de la instalación

## Credenciales por defecto

- Usuario: admin
- Contraseña: 1234

## Estructura de la base de datos

La instalación creará las siguientes tablas:

- `usuario`: Usuarios del sistema
- `proveedor`: Proveedores de productos
- `producto`: Productos en inventario
- `venta`: Registro de ventas
- `detalle_venta`: Detalles de cada venta
- `corte_caja`: Cortes de caja diarios
- `logs`: Registro de actividades

## Uso

1. **Login**: Ingresar con usuario y contraseña
2. **Dashboard**: Ver estadísticas generales
3. **Punto de Venta**: Realizar ventas rápidas
4. **Productos**: Gestionar inventario
5. **Proveedores**: Administrar proveedores
6. **Corte de Caja**: Generar reportes diarios
7. **Usuarios**: Gestionar usuarios del sistema

## Seguridad

- Las contraseñas se almacenan en texto plano (mejorar con hash en producción)
- Validación de sesiones en todas las páginas
- Control de permisos por nivel de usuario
- Protección básica contra inyección SQL

## Mejoras futuras

1. Implementar hash para contraseñas
2. Agregar gráficos de ventas
3. Exportar reportes a Excel
4. API REST para integraciones
5. Módulo de compras a proveedores
6. Notificaciones push
7. Multi-tienda
8. Backups automáticos

## Soporte

Para problemas o preguntas, contactar al desarrollador.

## Licencia

Sistema de código abierto para fines educativos y comerciales.# punto_venta
