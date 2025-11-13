# Consentimiento Obligatorio en Checkout

[![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)](https://github.com/vamlemat/atechrefurbconsent/releases/tag/v2.0.0)
[![PrestaShop](https://img.shields.io/badge/PrestaShop-1.7.x-green.svg)](https://www.prestashop.com)
[![License](https://img.shields.io/badge/license-MIT-orange.svg)](LICENSE)

Módulo de PrestaShop para añadir un checkbox de consentimiento obligatorio en el proceso de checkout para productos de categorías específicas.

---

## 📋 Descripción

Este módulo añade un checkbox obligatorio en el checkout de PrestaShop cuando el carrito contiene productos de categorías específicas que hayas configurado. El texto del consentimiento es totalmente personalizable desde el backoffice.

**Características principales:**
- ✅ Checkbox obligatorio que bloquea la finalización del pedido
- ✅ Texto del consentimiento 100% personalizable
- ✅ Selección de múltiples categorías afectadas
- ✅ Compatible con clientes registrados e invitados (guest checkout)
- ✅ Validación tanto en frontend (JavaScript) como backend (PHP)
- ✅ Registro automático del consentimiento en cada pedido
- ✅ Sistema robusto con múltiples capas de protección
- ✅ Logs detallados para debugging y auditoría

---

## 🎯 Casos de Uso

- **Productos reacondicionados** - Sin derecho a devolución
- **Políticas especiales** - Términos específicos de devolución/cambio
- **Advertencias de productos** - Productos peligrosos o con restricciones
- **Productos bajo pedido** - Condiciones especiales de entrega
- **Avisos legales** - Aceptación de términos por categoría
- **Categorías especiales** - Cualquier consentimiento necesario antes de comprar

---

## 📥 Instalación

### Opción 1: Desde GitHub Release (Recomendado)

1. Ve a [Releases](https://github.com/vamlemat/atechrefurbconsent/releases)
2. Descarga el archivo `atechrefurbconsent-v2.0.0.zip`
3. En tu backoffice de PrestaShop:
   - Ve a **Módulos → Subir un módulo**
   - Arrastra el ZIP o selecciónalo
   - Haz clic en **Instalar**

### Opción 2: Instalación Manual

1. Clona este repositorio o descarga el ZIP:
```bash
git clone https://github.com/vamlemat/atechrefurbconsent.git
```

2. Sube la carpeta `atechrefurbconsent` a `/modules/` de tu PrestaShop

3. En el backoffice:
   - Ve a **Módulos → Módulos y servicios**
   - Busca "Consentimiento Obligatorio en Checkout"
   - Haz clic en **Instalar**

---

## ⚙️ Configuración

### 1. Configurar Categorías

1. Ve a **Módulos → Consentimiento Obligatorio en Checkout → Configurar**
2. Selecciona las categorías que requieren el consentimiento
3. Puedes seleccionar múltiples categorías usando el árbol de categorías

### 2. Personalizar el Texto

1. En el campo "Texto del checkbox", escribe el mensaje que verá el cliente
2. El texto admite HTML básico para formato:
```html
<strong>ACEPTO</strong> que los productos reacondicionados 
<u>no tienen devolución ni cambio</u>.
```

3. Haz clic en **Guardar**

### 3. ¡Listo!

El checkbox aparecerá automáticamente en el checkout cuando:
- El carrito contenga productos de las categorías seleccionadas
- El cliente esté en el proceso de finalizar la compra

---

## 🔍 Cómo Funciona

```
┌─────────────────────────────────────────┐
│ 1. Cliente añade producto al carrito   │
│    (de categoría configurada)           │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ 2. Aparece checkbox en checkout         │
│    "CONDICIÓN OBLIGATORIA: [tu texto]"  │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ 3. Botón "Confirmar" deshabilitado      │
│    hasta marcar el checkbox             │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ 4. Cliente marca checkbox               │
│    → Se guarda vía AJAX en BD           │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ 5. Cliente confirma pedido              │
│    → Validación en servidor             │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ 6. Pedido creado ✅                     │
│    → Mensaje privado con consentimiento │
└─────────────────────────────────────────┘
```

### Sistema de Seguridad Multicapa

1. **JavaScript** - Deshabilita el botón hasta marcar el checkbox
2. **AJAX** - Guarda el consentimiento en base de datos
3. **PHP Backend** - Valida en servidor antes de crear el pedido
4. **Registro** - Guarda mensaje privado en el pedido para auditoría

Si alguien intenta burlar el JavaScript (inspeccionando elemento), la validación del servidor lo bloqueará.

---

## 📸 Capturas de Pantalla

### Configuración en Backoffice
![Configuración](docs/screenshots/config.png)

### Checkbox en Checkout
![Checkout](docs/screenshots/checkout.png)

### Mensaje en Pedido
![Pedido](docs/screenshots/order.png)

---

## 🔧 Requisitos

- **PrestaShop:** 1.7.0 o superior
- **PHP:** 7.0 o superior
- **MySQL:** 5.6 o superior

### Compatibilidad Verificada

- ✅ PrestaShop 1.7.0 - 1.7.8
- ✅ Guest checkout (compra sin registro)
- ✅ One Page Checkout (OPC)
- ✅ Checkout estándar de PrestaShop
- ✅ Múltiples métodos de pago
- ✅ Temas personalizados
- ✅ Multidioma

---

## 🐛 Solución de Problemas

### El checkbox no aparece

**Causa:** El producto no está en una categoría configurada.

**Solución:**
1. Verifica que el producto esté en una de las categorías seleccionadas en la configuración
2. Limpia la caché: `rm -rf var/cache/*`
3. Verifica los logs en **Configuración avanzada → Logs**

### Error "Debes aceptar la condición"

**Causa:** El consentimiento no se guardó correctamente.

**Solución:**
1. Abre la consola del navegador (F12)
2. Marca el checkbox y busca mensajes `[ARC]`
3. Si ves errores, verifica:
   - Que el cliente esté logueado (o sea invitado con carrito válido)
   - Que la tabla `ps_arc_consent` exista
   - Los logs del servidor

### El botón de confirmar no se habilita

**Causa:** El JavaScript no encuentra el botón de confirmación.

**Solución:**
1. Abre la consola (F12) y busca: `[ARC] Botón encontrado`
2. Si dice "no encontrado", el tema usa un selector diferente
3. Contacta con soporte o abre un issue con detalles de tu tema

---

## 📚 Documentación Técnica

### Estructura de Archivos

```
atechrefurbconsent/
├── atechrefurbconsent.php          # Clase principal del módulo
├── config.xml                       # Metadatos del módulo
├── index.php                        # Archivo de seguridad
├── controllers/
│   ├── front/
│   │   ├── save.php                # Controlador AJAX
│   │   └── index.php
│   └── index.php
└── views/
    ├── js/
    │   ├── checkbox.js             # Lógica frontend
    │   └── index.php
    ├── templates/
    │   └── hook/
    │       ├── checkout_checkbox.tpl  # Template del checkbox
    │       └── index.php
    └── index.php
```

### Hooks Utilizados

- `displayCheckoutSummaryTop` - Muestra el checkbox en el resumen del checkout
- `displayPaymentTop` - Muestra el checkbox en el paso de pago
- `actionFrontControllerSetMedia` - Carga el JavaScript en checkout
- `actionValidateOrder` - Valida el consentimiento antes de crear el pedido
- `actionObjectOrderAddAfter` - Guarda el mensaje privado después de crear el pedido

### Base de Datos

**Tabla:** `ps_arc_consent`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_arc` | INT | ID autoincremental |
| `id_cart` | INT | ID del carrito |
| `accepted` | TINYINT(1) | 1 = aceptado, 0 = no aceptado |
| `date_add` | DATETIME | Fecha de aceptación |

---

## 🤝 Contribuir

¡Las contribuciones son bienvenidas!

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

---

## 📝 Changelog

### v2.0.0 (2025-11-13)

**Primera versión estable**

- ✨ Módulo renombrado a "Consentimiento Obligatorio en Checkout"
- ✅ Sistema completamente funcional y probado
- ✅ Compatible con guest checkout
- 🐛 Corregido error "headers already sent"
- 🐛 Corregido error "Customer not logged"
- 🐛 Resuelto problema de DELETE que borraba consentimientos
- 🐛 Eliminadas race conditions entre hooks
- 📝 Logs detallados añadidos
- 🔒 Protección contra múltiples ejecuciones
- 🚀 Sistema de fallback JavaScript robusto

---

## 🆘 Soporte

- 📖 [Documentación completa](https://github.com/vamlemat/atechrefurbconsent)
- 🐛 [Reportar un bug](https://github.com/vamlemat/atechrefurbconsent/issues)
- 💡 [Solicitar una feature](https://github.com/vamlemat/atechrefurbconsent/issues/new?labels=enhancement)
- 💬 [Discusiones](https://github.com/vamlemat/atechrefurbconsent/discussions)

---

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo [LICENSE](LICENSE) para más detalles.

---

## 👨‍💻 Autor

**Atech**

Desarrollado con ❤️ para la comunidad PrestaShop

---

## ⭐ Si te gusta este proyecto

Si este módulo te resulta útil, considera:
- ⭐ Darle una estrella en GitHub
- 🐛 Reportar bugs que encuentres
- 💡 Sugerir mejoras
- 🤝 Contribuir con código
- 📢 Compartirlo con otros desarrolladores PrestaShop

---

**¡Gracias por usar Consentimiento Obligatorio en Checkout!** 🚀
