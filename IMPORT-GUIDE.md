# 📦 Guía de Importación de Inventario

## Formatos Soportados

El sistema de importación acepta **2 formatos de JSON**:

### 1️⃣ Formato Estándar (Array de Productos)

Ideal para importaciones directas desde sistemas externos.

```json
[
  {
    "barcode": "7501234567890",
    "name": "Producto Ejemplo",
    "description": "Descripción del producto",
    "category_name": "Bebidas",
    "brand": "Marca",
    "location": "Anaquel A-1",
    "supplier_name": "Proveedor SA",
    "presentations": [
      {
        "name": "Unidad",
        "factor": 1,
        "purchase_price": 4.50,
        "sale_price": 6.50
      }
    ],
    "stock": [
      {
        "presentation_name": "Unidad",
        "quantity": 100,
        "location": "Almacén",
        "expiration_date": "2026-12-31"
      }
    ]
  }
]
```

**Archivo de ejemplo:** `storage/app/import-example.json`

---

### 2️⃣ Formato por Categoría (Agrupado)

Ideal para importación organizadas por categorías desde hojas de cálculo.

```json
{
  "categoria": "HIGIENE",
  "items": [
    {
      "ubicacion": "A1",
      "producto": "CEPILLO COLGATE 12+2",
      "marca": "COLGATE",
      "compra": { 
        "caja": 35.00, 
        "blister": null, 
        "unidad": 2.50 
      },
      "venta": { 
        "caja": null, 
        "blister": null, 
        "unidad": 5.00 
      },
      "existencia": { 
        "caja": 1, 
        "blister": 0, 
        "unidad": 7 
      },
      "vencimiento": "2026-02-01",
      "proveedor": "Proveedor SA"
    }
  ]
}
```

**Archivo de ejemplo:** `storage/app/import-category-example.json`

#### Características del Formato por Categoría:

- **Presentaciones automáticas:** El sistema crea presentaciones basándose en los precios de `compra` y `venta`
- **Factores predeterminados:**
  - `unidad`: factor 1
  - `blister`: factor 6
  - `caja`: factor 12
- **Stock automático:** Se crea stock por presentación según valores en `existencia`
- **Códigos de barras:** Se generan automáticamente combinando categoría + producto

---

## 📋 Campos del Formato por Categoría

### Obligatorios:
- `producto` (string): Nombre del producto
- `venta.unidad` (number): Precio de venta de la presentación unidad

### Opcionales:
- `ubicacion` (string): Ubicación física del producto
- `marca` (string): Marca del producto
- `compra.unidad` (number): Precio compra unidad **(si no se proporciona, se calcula como 70% del precio de venta)**
- `compra.blister` (number): Precio compra presentación blister
- `compra.caja` (number): Precio compra presentación caja
- `venta.blister` (number): Precio venta presentación blister
- `venta.caja` (number): Precio venta presentación caja
- `existencia.unidad` (number): Stock en unidades
- `existencia.blister` (number): Stock en blisters
- `existencia.caja` (number): Stock en cajas
- `vencimiento` (string): Fecha de vencimiento (formato: YYYY-MM-DD)
- `proveedor` (string): Nombre del proveedor

### 💡 Manejo de Precios de Compra:
Si `compra` es `null` o no tiene valor, el sistema:
- Calcula automáticamente: `precio_compra = precio_venta × 0.70` (margen 30%)
- Esto permite importar productos cuando solo conoces el precio de venta
- Puedes ajustar los precios de compra después desde la interfaz

---

## 🎯 Casos Especiales

### 1. Solo precio de venta disponible
```json
{
  "producto": "HILO DENTAL",
  "compra": { "caja": null, "blister": null, "unidad": null },
  "venta": { "caja": null, "blister": null, "unidad": 15.00 }
}
```
✅ **Resultado:** Se crea con precio_compra = 10.50 (70% de 15.00)

### 2. Existencia sin precio de compra definido
```json
{
  "producto": "HISOPADOS",
  "compra": { "unidad": null },
  "venta": { "unidad": 5.00 },
  "existencia": { "caja": 3, "unidad": 3 }
}
```
✅ **Resultado:** Se crea con precio_compra = 3.50, stock de 3 unidades

### 3. Precio de venta menor que compra
```json
{
  "producto": "CEPILLO ADULTO",
  "compra": { "unidad": 20.00 },
  "venta": { "unidad": 5.00 }
}
```
⚠️ **Resultado:** Se importa sin problemas (puede ser liquidación o error de datos)

### 4. Múltiples presentaciones con precios mixtos
```json
{
  "producto": "Coca Cola",
  "compra": { "caja": 45.00, "unidad": 4.00 },
  "venta": { "caja": 60.00, "blister": 35.00, "unidad": 6.50 }
}
```
✅ **Resultado:** 
- Unidad: compra 4.00, venta 6.50
- Blister: compra 24.50 (70% de 35.00), venta 35.00
- Caja: compra 45.00, venta 60.00

---

## 🚀 Proceso de Importación

### Paso 1: Preparar el JSON

1. Organiza tus productos en uno de los formatos soportados
2. Valida que el JSON sea válido (usa [jsonlint.com](https://jsonlint.com))
3. Guarda el archivo con extensión `.json`

### Paso 2: Vista Previa

1. En el módulo **Productos**, click en **📤 Importar JSON**
2. Selecciona tu archivo JSON
3. El sistema detecta automáticamente el formato
4. Revisa la vista previa:
   - Total de productos
   - Productos nuevos vs existentes
   - Presentaciones y stock
   - Advertencias y errores

### Paso 3: Confirmar Importación

1. Si no hay errores, click en **Confirmar Importación**
2. El sistema procesa:
   - Crea categorías si no existen
   - Crea proveedores si no existen
   - Crea productos nuevos
   - Actualiza productos existentes (por código de barras)
   - Crea presentaciones
   - Registra lotes de stock
3. Todo se ejecuta en una transacción atómica

---

## ✅ Validaciones

El sistema valida:

- ✓ Estructura del JSON
- ✓ Campos requeridos
- ✓ Precios válidos (> 0)
- ✓ Factores válidos (≥ 1)
- ✓ Códigos de barras duplicados
- ✓ Formato de fechas

### Advertencias (no bloquean)
- ⚠️ Productos que ya existen (se actualizarán)
- ⚠️ Categorías/proveedores que no existen (se crearán automáticamente)

### Errores (bloquean importación)
- ❌ JSON inválido
- ❌ Campos requeridos faltantes
- ❌ Precios negativos o inválidos
- ❌ Factores menores a 1

---

## 📊 Ejemplos de Uso

### Importar productos de higiene desde hoja de cálculo

```bash
# 1. Exporta tu hoja a CSV
# 2. Convierte a JSON con el formato por categoría
# 3. Importa el archivo
```

Usa `import-category-example.json` como referencia.

### Importar catálogo completo de proveedor

```bash
# 1. Obtén el archivo JSON del proveedor
# 2. Valida que tenga el formato estándar
# 3. Importa el archivo
```

Usa `import-example.json` como referencia.

---

## 🔧 Endpoints API

### Preview (Formato Estándar)
```
POST /api/inventory/import/preview
Content-Type: multipart/form-data
file: archivo.json
```

### Preview (Formato por Categoría)
```
POST /api/inventory/import/preview-category
Content-Type: multipart/form-data
file: archivo.json
```

### Commit
```
POST /api/inventory/import/commit
Content-Type: application/json
{ "import_id": "import_abc123..." }
```

---

## 💡 Tips

1. **Prueba primero:** Usa la vista previa para validar antes de importar
2. **Backups:** Haz respaldo de tu base de datos antes de importaciones grandes
3. **Lotes pequeños:** Divide importaciones grandes en archivos de ~100 productos
4. **Categorías:** Asegúrate de usar nombres consistentes para categorías
5. **Proveedores:** Usa nombres exactos para evitar duplicados
6. **Fechas:** Usa formato ISO (YYYY-MM-DD) para vencimientos
7. **Precios de compra:** Si no los tienes, no te preocupes - el sistema calcula 70% del precio de venta
8. **Múltiples categorías:** Puedes importar un array de objetos con diferentes categorías en un solo archivo

### Ejemplo de múltiples categorías:
```json
[
  {
    "categoria": "BEBE",
    "items": [...]
  },
  {
    "categoria": "HIGIENE",
    "items": [...]
  }
]
```

---

## 🆘 Solución de Problemas

### "JSON inválido"
- Valida tu JSON en jsonlint.com
- Verifica comillas dobles (no simples)
- Revisa comas al final de elementos

### "No hay presentaciones definidas"
- Asegura que al menos `venta.unidad` tenga un valor válido
- El precio de compra es opcional (se calcula automáticamente)
- Valores `null` o `0` en venta no crean presentaciones

### "Precio de venta menor que precio de compra"
- No es un error, el sistema lo permite
- Puede ser promoción, liquidación o error en los datos
- Revisa los precios en la vista previa antes de confirmar

### "Categoría no encontrada"
- No es error, se crea automáticamente
- Verifica el nombre si quieres usar una existente

### "Productos no aparecen"
- Revisa que no haya errores en el preview
- Verifica que la importación se completó exitosamente
- Actualiza el catálogo (F5)

### "Campo compra es null"
- No es un problema, el sistema calcula: precio_compra = precio_venta × 0.70
- Puedes editar los precios después de la importación

---

## 📞 Soporte

Para más ayuda, revisa los archivos de ejemplo:
- `storage/app/import-example.json` - Formato estándar (8 productos)
- `storage/app/import-category-example.json` - Formato por categoría (9 productos de Higiene)
- `storage/app/import-real-data.json` - Ejemplos con casos especiales (productos Bebé + Tira Cross con precios null)

### Probar la Importación

1. **Formato estándar:** Sube `import-example.json`
2. **Formato categoría básico:** Sube `import-category-example.json`
3. **Casos especiales:** Sube `import-real-data.json` para ver cómo maneja precios null

Todos los archivos están listos para importar directamente desde tu interfaz.
