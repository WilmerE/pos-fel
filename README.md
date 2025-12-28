# Sistema POS-FEL

Sistema de Punto de Venta con integración de Facturación Electrónica en Línea (FEL) para Guatemala, desarrollado con Laravel 11 y JavaScript vanilla.

## 📋 Características

- ✅ **Gestión de Ventas**: Búsqueda en tiempo real de productos, cálculo automático de IVA, múltiples presentaciones
- ✅ **Control de Inventario**: Sistema FIFO, control de lotes, fechas de vencimiento
- ✅ **Gestión de Caja**: Apertura/cierre de caja, registro de ingresos/egresos, cálculo de diferencias
- ✅ **Sistema de Roles**: Admin, Manager, Cashier, Warehouse con permisos granulares
- ✅ **Facturación Electrónica**: Integración FEL (simulada) lista para certificador real
- ✅ **Interfaz Moderna**: SPA con búsqueda tipo Select2, mensajes amigables
- ✅ **Anulación de Ventas**: Con reversión automática de inventario y caja

## 🔧 Requisitos del Sistema

### Windows / Linux / macOS

- **PHP** >= 8.2
- **Composer** >= 2.0
- **MySQL** >= 8.0 o **MariaDB** >= 10.3
- **Node.js** >= 18.x (opcional, para assets)
- **Git**

### Extensiones PHP Requeridas

```ini
php-curl
php-mbstring
php-xml
php-pdo
php-mysql
php-zip
php-bcmath
php-gd
```

## 📦 Instalación

### 1. Clonar el Repositorio

```bash
git clone https://github.com/WilmerE/pos-fel.git
cd pos-fel
```

### 2. Instalar Dependencias

```bash
composer install
```

### 3. Configurar Variables de Entorno

```bash
# Copiar archivo de ejemplo
cp .env.example .env

# Editar .env con tus datos
```

**Configuración de Base de Datos en `.env`:**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_fel
DB_USERNAME=root
DB_PASSWORD=tu_password
```

### 4. Generar Key de la Aplicación

```bash
php artisan key:generate
```

### 5. Crear Base de Datos

**MySQL/MariaDB:**
```sql
CREATE DATABASE pos_fel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Windows (MySQL Workbench o phpMyAdmin):**
- Crear base de datos con nombre `pos_fel`
- Collation: `utf8mb4_unicode_ci`

### 6. Ejecutar Migraciones y Seeders

```bash
# Crear tablas y datos de prueba
php artisan migrate:fresh --seed
```

## 👥 Usuarios de Prueba

El sistema crea automáticamente 4 usuarios con diferentes roles:

| Email | Password | Rol | Permisos |
|-------|----------|-----|----------|
| admin@pos.com | password | Administrador | Todos los permisos |
| manager@pos.com | password | Gerente | Ver reportes, gestionar productos |
| cashier@pos.com | password | Cajero | Ventas, caja |
| warehouse@pos.com | password | Bodeguero | Gestión de inventario |

## 🏪 Datos de Prueba

### Productos Incluidos

El seeder crea 5 productos con 2 presentaciones cada uno:

1. **Coca Cola** (CC001) - Unidad: Q5.00 / Caja (12u): Q55.00
2. **Agua Pura** (AG001) - Unidad: Q5.00 / Caja (12u): Q55.00
3. **Galletas Dulces** (GL001) - Unidad: Q5.00 / Caja (12u): Q55.00
4. **Pan Blanco** (PN001) - Unidad: Q5.00 / Caja (12u): Q55.00
5. **Leche** (LC001) - Unidad: Q5.00 / Caja (12u): Q55.00

**Stock Inicial:** 100 unidades de cada producto

## 🚀 Iniciar la Aplicación

### Método 1: Servidor PHP Integrado (Desarrollo)

```bash
php artisan serve
```

Acceder a: http://localhost:8000/pos.html

### Método 2: XAMPP/WAMP/Laragon (Windows)

1. Mover proyecto a carpeta `htdocs` o `www`
2. Configurar virtual host (opcional)
3. Acceder a: http://localhost/pos-fel/public/pos.html

### Método 3: Apache/Nginx (Linux)

**Apache - Virtual Host:**
```apache
<VirtualHost *:80>
    ServerName pos-fel.local
    DocumentRoot /var/www/pos-fel/public
    
    <Directory /var/www/pos-fel/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name pos-fel.local;
    root /var/www/pos-fel/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

## 📁 Estructura del Proyecto

```
pos-fel/
├── app/
│   ├── Http/Controllers/Api/    # Controladores REST API
│   ├── Models/                  # Modelos Eloquent
│   ├── Services/                # Lógica de negocio
│   │   ├── SaleService.php      # Gestión de ventas
│   │   ├── StockService.php     # Control de inventario
│   │   ├── CashBoxService.php   # Gestión de caja
│   │   ├── FelService.php       # Facturación electrónica
│   │   └── SaleAnnulmentService.php
│   └── Traits/                  # Traits reutilizables
├── database/
│   ├── migrations/              # Migraciones de BD (18 tablas)
│   └── seeders/                 # Datos de prueba
├── public/
│   ├── pos.html                 # SPA principal
│   ├── css/pos.css              # Estilos (702 líneas)
│   └── js/pos.js                # Lógica frontend (881 líneas)
└── routes/
    └── api.php                  # Rutas de API REST
```

## 🔐 Sistema de Permisos

### Roles

- **Admin**: Acceso total al sistema
- **Manager**: Reportes, productos, usuarios
- **Cashier**: Ventas y caja
- **Warehouse**: Inventario y stock

### Permisos Principales

```
- sell: Realizar ventas
- view_sales: Ver ventas
- cancel_sale: Cancelar ventas
- annul_sale: Anular ventas facturadas
- manage_stock: Gestionar inventario
- view_stock: Ver inventario
- open_cash_box: Abrir caja
- close_cash_box: Cerrar caja
- manage_cash_movements: Registrar ingresos/egresos
- view_cash_box: Ver estado de caja
- generate_fiscal_document: Generar facturas FEL
- annul_fiscal_document: Anular facturas FEL
```

## 📝 Flujo de Trabajo

### 1. Abrir Caja

1. Ir al módulo **Caja**
2. Click en **Abrir Caja**
3. Ingresar efectivo inicial (ej: Q500.00)

### 2. Realizar Venta

1. Ir al módulo **Ventas**
2. Click en **Nueva Venta**
3. Ingresar datos del cliente
4. Buscar productos por código o nombre (ej: "CC001" o "Coca")
5. Seleccionar presentación y cantidad
6. Click en **Agregar a la Venta**
7. Repetir para más productos
8. Click en **Confirmar Venta**

### 3. Cerrar Caja

1. Ir al módulo **Caja**
2. Click en **Cerrar Caja**
3. Ingresar efectivo contado final
4. El sistema calcula diferencia automáticamente

## 🛠️ Comandos Útiles

```bash
# Limpiar cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Recrear base de datos
php artisan migrate:fresh --seed

# Ver rutas disponibles
php artisan route:list

# Generar nueva key
php artisan key:generate

# Ver logs
tail -f storage/logs/laravel.log
```

## 🐛 Solución de Problemas

### Error de Conexión a Base de Datos

**Síntoma**: `SQLSTATE[HY000] [2002] Connection refused`

**Solución**:
```bash
# 1. Verificar que MySQL esté corriendo
# Windows (XAMPP): Iniciar MySQL desde panel
# Linux: sudo systemctl start mysql
# macOS: brew services start mysql

# 2. Verificar credenciales en .env
# 3. Crear la base de datos si no existe
```

### Error 500 - Internal Server Error

**Solución**:
```bash
# 1. Verificar permisos de directorios
chmod -R 775 storage bootstrap/cache

# 2. Limpiar cache
php artisan cache:clear
php artisan config:clear

# 3. Revisar logs
cat storage/logs/laravel.log
```

### Error de Token CSRF

**Solución**: Las rutas API usan Sanctum, no CSRF. Verificar que el token de autenticación esté en localStorage.

### Productos no se muestran en búsqueda

**Solución**:
```bash
# Ejecutar seeders nuevamente
php artisan db:seed --class=ProductSeeder
php artisan db:seed --class=StockSeeder
```

## 📊 Base de Datos

### Tablas Principales (18 en total)

- `users` - Usuarios del sistema
- `roles` - Roles de usuario
- `permissions` - Permisos granulares
- `products` - Catálogo de productos
- `product_presentations` - Presentaciones de productos
- `stock_batches` - Lotes de inventario (FIFO)
- `stock_movements` - Movimientos de inventario
- `sales` - Ventas realizadas
- `sale_items` - Items de cada venta
- `cash_boxes` - Cajas diarias
- `cash_movements` - Movimientos de caja
- `fiscal_documents` - Documentos FEL
- `annulments` - Anulaciones de ventas

## 🔄 Git Workflow

```bash
# Ver estado
git status

# Agregar cambios
git add .

# Commit
git commit -m "Descripción de cambios"

# Push a GitHub
git push origin main

# Pull cambios
git pull origin main
```

## 📞 Soporte

Para problemas o dudas sobre el sistema:

- **GitHub Issues**: https://github.com/WilmerE/pos-fel/issues

## 📄 Licencia

Este proyecto es privado y confidencial.

## 🎯 Próximas Características (Roadmap)

- [ ] Integración real con certificador FEL
- [ ] Reportes y dashboard avanzados
- [ ] Gestión de clientes recurrentes
- [ ] Impresión de tickets de venta
- [ ] Múltiples puntos de venta
- [ ] Sincronización en tiempo real
- [ ] App móvil (React Native)
- [ ] Backup automático de base de datos

---

**Desarrollado con ❤️ para el mercado guatemalteco**
