# Importador de Inventario para POS-FEL

Sistema de importación masiva de productos desde archivos JSON con vista previa y validación.

## 🚀 Características

- ✅ **Vista Previa**: Revisa qué se importará antes de confirmar
- ✅ **Validación**: Detecta errores y advertencias antes de importar
- ✅ **Creación Automática**: Crea categorías y proveedores si no existen
- ✅ **Actualización Inteligente**: Actualiza productos existentes por código de barras
- ✅ **Importación Completa**: Productos, presentaciones y stock en un solo archivo

## 📋 Formato del JSON

```json
[
  {
    "barcode": "7501234567890",
    "name": "Coca Cola 600ml",
    "description": "Bebida gaseosa sabor cola",
    "category_name": "Bebidas",
    "brand": "Coca-Cola",
    "location": "Anaquel B-2",
    "supplier_name": "Bebidas Nacionales",
    "presentations": [
      {
        "name": "Unidad",
        "factor": 1,
        "purchase_price": 4.50,
        "sale_price": 6.50
      },
      {
        "name": "Sixpack",
        "factor": 6,
        "purchase_price": 25.00,
        "sale_price": 35.00
      }
    ],
    "stock": [
      {
        "presentation_name": "Unidad",
        "quantity": 120,
        "location": "Anaquel B-2",
        "expiration_date": "2026-12-31"
      }
    ]
  }
]
```

## 📝 Campos del Producto

### Obligatorios
- **barcode**: Código de barras único del producto
- **name**: Nombre del producto

### Opcionales
- **description**: Descripción del producto
- **category_name**: Nombre de la categoría (se crea si no existe)
- **brand**: Marca del producto
- **location**: Ubicación física en el almacén
- **supplier_name**: Nombre del proveedor (se crea si no existe)

## 📦 Presentaciones

Cada producto debe tener al menos una presentación:

```json
{
  "name": "Unidad",           // Obligatorio: Nombre de la presentación
  "factor": 1,                // Obligatorio: Factor de conversión (≥1)
  "purchase_price": 4.50,     // Obligatorio: Precio de compra
  "sale_price": 6.50          // Obligatorio: Precio de venta
}
```

## 📊 Stock

El stock es opcional, pero si se incluye:

```json
{
  "presentation_name": "Unidad",  // Opcional: Nombre de la presentación (default: "Unidad")
  "quantity": 120,                // Obligatorio: Cantidad en stock
  "location": "Anaquel B-2",      // Opcional: Ubicación del lote
  "expiration_date": "2026-12-31" // Opcional: Fecha de vencimiento (formato: YYYY-MM-DD)
}
```

## 🔄 Flujo de Importación

1. **Seleccionar Archivo**
   - Click en "Importar JSON" en el módulo de Productos
   - Selecciona tu archivo .json

2. **Vista Previa**
   - El sistema valida el archivo
   - Muestra estadísticas:
     - Total de productos
     - Productos nuevos vs existentes
     - Presentaciones y lotes
     - Categorías y proveedores
   - Muestra advertencias y errores (si los hay)

3. **Confirmar Importación**
   - Revisa la información
   - Click en "Confirmar Importación"
   - El sistema importa todos los datos en una transacción

## ⚠️ Validaciones

### Errores que bloquean la importación:
- ❌ Código de barras vacío
- ❌ Nombre de producto vacío
- ❌ Presentación sin nombre
- ❌ Factor menor a 1
- ❌ Precios negativos

### Advertencias (no bloquean):
- ⚠️ Producto ya existe (se actualizará)
- ⚠️ Categoría no existe (se creará automáticamente)
- ⚠️ Proveedor no existe (se creará automáticamente)
- ⚠️ Producto sin presentaciones

## 🎯 Comportamiento con Duplicados

### Productos Existentes (mismo barcode):
- Se actualizan nombre, descripción, marca, ubicación
- Se mantiene el ID del producto
- Las presentaciones existentes se actualizan por nombre
- Las presentaciones nuevas se agregan

### Categorías/Proveedores Existentes:
- Se buscan por nombre (case-insensitive)
- Si existen, se reutilizan
- Si no existen, se crean automáticamente

## 📁 Ejemplo Completo

Ver archivo: `storage/app/import-example.json`

Este archivo contiene 2 productos de ejemplo con todas las propiedades configuradas.

## 🔌 Endpoints API

### POST /api/inventory/import/preview
Genera vista previa del archivo
- **Body**: `multipart/form-data` con campo `file`
- **Response**: Estadísticas y validaciones

### POST /api/inventory/import/preview-json
Preview desde JSON directo (sin archivo)
- **Body**: `{ "json": "..." }`
- **Response**: Estadísticas y validaciones

### POST /api/inventory/import/commit
Confirma la importación
- **Body**: `{ "import_id": "..." }`
- **Response**: Resumen de importación

## 🔐 Permisos

Se requiere el permiso `manage_products` para usar el importador.

## 💡 Tips

1. **Prueba Primero**: Importa 2-3 productos primero para verificar el formato
2. **Backups**: Haz backup de tu base de datos antes de importaciones grandes
3. **Categorías/Proveedores**: Si tienes muchos productos, crea las categorías/proveedores primero
4. **Códigos de Barras**: Usa códigos únicos, son la clave para evitar duplicados
5. **Validación**: Revisa siempre las advertencias antes de confirmar

## 🐛 Troubleshooting

**El archivo no se procesa:**
- Verifica que sea un JSON válido
- Máximo 10MB de tamaño
- Extensión debe ser .json

**Errores de formato:**
- Usa un validador JSON online
- Revisa comillas y comas
- Asegúrate que los números no tengan comillas

**Productos no aparecen:**
- Verifica que `active` no esté en `false`
- Revisa el módulo de productos
- Refresca el catálogo

## 📞 Soporte

Para reportar errores o sugerencias, contacta al equipo de desarrollo.
