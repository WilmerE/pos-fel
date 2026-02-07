# Comandos Básicos de Laravel - POS-FEL

## 📋 Guía Rápida de Desarrollo

Este documento contiene todos los comandos esenciales para trabajar con el proyecto POS-FEL.

---

## 🚀 Iniciar el Servidor de Desarrollo

```bash
# Navegar al proyecto
cd /Users/p3l1co/laravel/pos-fel

# Iniciar el servidor (puerto 8000 por defecto)
php artisan serve

# Iniciar en un puerto específico
php artisan serve --port=8080

# Iniciar accesible desde la red
php artisan serve --host=0.0.0.0
```

**Acceso:** http://localhost:8000/pos.html

---

## 🔐 Credenciales de Acceso

### Usuarios del Sistema

| Email | Contraseña | Rol | Permisos |
|-------|-----------|-----|----------|
| admin@pos.com | password | Administrador | Todos los permisos |
| manager@pos.com | password | Gerente | Gestión de inventario y reportes |
| cashier@pos.com | password | Cajero | Ventas y caja |
| warehouse@pos.com | password | Bodega | Solo inventario |

**Nota:** Todos los usuarios usan la contraseña `password` en entorno de desarrollo.

---

## 💾 Base de Datos

### Acceso a MySQL

```bash
# Conectar a MySQL
mysql -u root -p

# Seleccionar la base de datos
use pos_fel;

# Ver tablas
show tables;

# Salir de MySQL
exit;
```

### Comandos de Migración

```bash
# Ejecutar migraciones pendientes
php artisan migrate

# Revertir última migración
php artisan migrate:rollback

# Revertir todas las migraciones
php artisan migrate:reset

# Borrar todo y recrear la base de datos
php artisan migrate:fresh

# Recrear con datos de prueba
php artisan migrate:fresh --seed
```

### Seeders (Datos de Prueba)

```bash
# Ejecutar todos los seeders
php artisan db:seed

# Ejecutar un seeder específico
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=ProductSeeder
php artisan db:seed --class=StockSeeder
```

---

## 🔧 Comandos Artisan Útiles

### Caché

```bash
# Limpiar caché de la aplicación
php artisan cache:clear

# Limpiar caché de configuración
php artisan config:clear

# Limpiar caché de rutas
php artisan route:clear

# Limpiar caché de vistas
php artisan view:clear

# Limpiar todo
php artisan optimize:clear
```

### Rutas

```bash
# Ver todas las rutas de la aplicación
php artisan route:list

# Filtrar rutas por nombre
php artisan route:list --name=products

# Ver solo rutas de API
php artisan route:list --path=api
```

### Información del Sistema

```bash
# Ver configuración actual
php artisan about

# Ver variables de entorno
php artisan env

# Ejecutar comandos en tinker (consola interactiva)
php artisan tinker
```

---

## 📝 Logs y Debugging

### Ver Logs en Tiempo Real

```bash
# Monitorear el log principal
tail -f storage/logs/laravel.log

# Ver las últimas 50 líneas
tail -n 50 storage/logs/laravel.log

# Limpiar logs (solo en desarrollo)
> storage/logs/laravel.log
```

### Debugging

```bash
# Ver errores de SQL en la consola
# Agregar en .env:
DB_CONNECTION=mysql
LOG_QUERY=true
LOG_LEVEL=debug
```

---

## 📦 Composer (Dependencias PHP)

```bash
# Instalar dependencias
composer install

# Actualizar dependencias
composer update

# Agregar un paquete
composer require nombre/paquete

# Remover un paquete
composer remove nombre/paquete

# Regenerar autoload
composer dump-autoload
```

---

## 📦 NPM (Dependencias JavaScript)

```bash
# Instalar dependencias
npm install

# Compilar assets para desarrollo
npm run dev

# Compilar assets para producción
npm run build

# Modo watch (recompila automáticamente)
npm run watch
```

---

## 🔄 Git - Control de Versiones

### Comandos Básicos

```bash
# Ver estado actual
git status

# Ver cambios realizados
git diff

# Añadir archivos al staging
git add .
git add archivo.php

# Hacer commit
git commit -m "Descripción del cambio"

# Ver historial de commits
git log
git log --oneline

# Subir cambios al repositorio remoto
git push origin main
```

### Workflow Completo

```bash
# 1. Ver qué archivos cambiaron
git status

# 2. Añadir todos los cambios
git add -A

# 3. Hacer commit con mensaje descriptivo
git commit -m "feat: Agregar módulo de inventario"

# 4. Subir a GitHub
git push origin main
```

### Convenciones de Commits

- `feat:` Nueva característica
- `fix:` Corrección de bug
- `docs:` Solo documentación
- `style:` Cambios de formato
- `refactor:` Refactorización de código
- `test:` Agregar tests
- `chore:` Tareas de mantenimiento

---

## 🗂️ Estructura del Proyecto

```
pos-fel/
├── app/
│   ├── Http/Controllers/    # Controladores
│   ├── Models/              # Modelos Eloquent
│   ├── Services/            # Lógica de negocio
│   └── ...
├── database/
│   ├── migrations/          # Migraciones
│   └── seeders/            # Datos de prueba
├── public/
│   ├── pos.html            # Frontend SPA
│   ├── js/pos.js           # Lógica del frontend
│   └── css/pos.css         # Estilos
├── routes/
│   ├── api.php             # Rutas API
│   └── web.php             # Rutas web
├── storage/logs/           # Archivos de log
├── .env                    # Configuración del entorno
└── README.md               # Documentación principal
```

---

## 🛠️ Crear Nuevos Elementos

### Crear Controlador

```bash
# Controlador básico
php artisan make:controller NombreController

# Controlador con métodos CRUD
php artisan make:controller NombreController --resource

# Controlador API
php artisan make:controller Api/NombreController --api
```

### Crear Modelo

```bash
# Modelo simple
php artisan make:model Nombre

# Modelo con migración
php artisan make:model Nombre -m

# Modelo completo (migración, factory, seeder)
php artisan make:model Nombre -mfs
```

### Crear Migración

```bash
# Crear tabla
php artisan make:migration create_nombre_table

# Modificar tabla
php artisan make:migration add_column_to_table
```

### Crear Seeder

```bash
php artisan make:seeder NombreSeeder
```

### Crear Servicio (Manualmente)

```bash
# Crear directorio si no existe
mkdir -p app/Services

# Crear archivo
touch app/Services/NombreService.php
```

---

## 🧪 Testing

```bash
# Ejecutar todos los tests
php artisan test

# Ejecutar tests con cobertura
php artisan test --coverage

# Ejecutar un test específico
php artisan test --filter NombreDelTest

# Crear nuevo test
php artisan make:test NombreTest
```

---

## ⚙️ Configuración del Entorno (.env)

### Variables Principales

```env
APP_NAME="POS-FEL"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_fel
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:8000
```

**Importante:** Después de modificar `.env`, ejecutar:
```bash
php artisan config:clear
```

---

## 🚨 Solución de Problemas Comunes

### Error: "500 Internal Server Error"

```bash
# Verificar logs
tail -f storage/logs/laravel.log

# Limpiar caché
php artisan optimize:clear

# Verificar permisos
chmod -R 775 storage bootstrap/cache
```

### Error: "Database Connection Failed"

```bash
# Verificar que MySQL esté corriendo
mysql --version

# Verificar credenciales en .env
cat .env | grep DB_

# Recrear base de datos
mysql -u root -p
CREATE DATABASE pos_fel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;

php artisan migrate:fresh --seed
```

### Error: "Route not found"

```bash
# Limpiar caché de rutas
php artisan route:clear

# Ver todas las rutas disponibles
php artisan route:list

# Regenerar caché
php artisan optimize
```

### Error: "Class not found"

```bash
# Regenerar autoload de Composer
composer dump-autoload
```

---

## 📊 Estado Actual del Sistema

### Tablas en la Base de Datos (18 tablas)

1. users - Usuarios del sistema
2. products - Productos
3. product_presentations - Presentaciones de productos
4. customers - Clientes
5. categories - Categorías de productos
6. suppliers - Proveedores
7. warehouses - Bodegas
8. cash_boxes - Cajas registradoras
9. cash_box_openings - Aperturas de caja
10. cash_box_movements - Movimientos de caja
11. sales - Ventas
12. sale_details - Detalles de ventas
13. stock_batches - Lotes de inventario
14. stock_movements - Movimientos de inventario
15. fel_documents - Documentos electrónicos
16. cache - Caché de Laravel
17. jobs - Cola de trabajos
18. password_reset_tokens - Tokens de reseteo

### Productos de Prueba (5 productos)

1. **CC001** - Coca Cola 355ml - Q 6.00
2. **PP002** - Pepsi 355ml - Q 5.50
3. **DS003** - Doritos Nacho 150g - Q 8.00
4. **CH004** - Churro Chocolate - Q 3.00
5. **GB005** - Galleta María 200g - Q 4.50

**Stock:** 100 unidades por producto

---

## 🔗 Enlaces Útiles

- **Repositorio:** https://github.com/WilmerE/pos-fel.git
- **Documentación Laravel:** https://laravel.com/docs/11.x
- **Documentación Sanctum:** https://laravel.com/docs/11.x/sanctum

---

## ⏱️ Workflow Diario Recomendado

### Al Iniciar el Día

```bash
# 1. Actualizar código del repositorio
git pull origin main

# 2. Instalar dependencias si hay cambios
composer install
npm install

# 3. Ejecutar migraciones pendientes
php artisan migrate

# 4. Limpiar caché
php artisan optimize:clear

# 5. Iniciar servidor
php artisan serve
```

### Durante el Desarrollo

```bash
# Ver logs en tiempo real (en otra terminal)
tail -f storage/logs/laravel.log

# Monitorear cambios en el código
npm run watch
```

### Al Finalizar

```bash
# 1. Guardar cambios
git add -A
git commit -m "Descripción de los cambios"

# 2. Subir al repositorio
git push origin main
```

---

## 📅 Última Actualización

**Fecha:** 7 de febrero de 2026  
**Versión:** Laravel 11  
**PHP:** 8.2+  
**Estado:** Sistema operacional, listo para continuar desarrollo

---

## 💡 Notas Importantes

- Siempre usa `php artisan serve` en lugar de navegar directamente a archivos
- Los cambios en `.env` requieren `php artisan config:clear`
- Revisa los logs cuando algo no funcione: `tail -f storage/logs/laravel.log`
- Antes de hacer push, verifica que todo funcione localmente
- Usa commits descriptivos siguiendo las convenciones
- El sistema está listo para continuar con el módulo de inventario

---

**¡Feliz desarrollo! 🚀**
