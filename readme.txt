=== Victoria Waitlist Lite ===
Contributors: linamarintrademark
Tags: woocommerce, waitlist, stock, crm, victorianexus
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lista de espera para productos WooCommerce con sincronización a VictoriaNexus CRM.

== Description ==

Victoria Waitlist Lite permite mostrar un botón "Lista de Espera" en productos de WooCommerce que pertenezcan a una categoría específica. Los clientes pueden inscribirse y los datos se sincronizan automáticamente con VictoriaNexus CRM.

**Características:**

* Botón personalizable en el catálogo de productos
* Modal elegante para capturar datos del cliente
* Detección de duplicados
* Sincronización automática con VictoriaNexus API
* Panel de administración para ver entradas
* Multi-tenant ready (cada tienda puede conectar a diferente empresa)

== Installation ==

1. Sube la carpeta `victoria-waitlist-lite` al directorio `/wp-content/plugins/`
2. Activa el plugin desde el menú 'Plugins' de WordPress
3. Ve a WooCommerce > Victoria Waitlist para configurar
4. Ingresa las credenciales de API de VictoriaNexus
5. Configura el slug de la categoría de productos en lista de espera

== Configuration ==

1. **API URL**: URL base de VictoriaNexus (ej: https://victorianexus.laravel.cloud)
2. **API Key**: Clave de API proporcionada por VictoriaNexus
3. **API Secret**: Secreto de API para autenticación HMAC
4. **Slug de Categoría**: Slug de la categoría WooCommerce (default: waitlist)
5. **Texto del Botón**: Texto que aparece en el botón (default: ¡LO QUIERO!)

== Frequently Asked Questions ==

= ¿Cómo obtengo las credenciales de API? =

Contacta al administrador de VictoriaNexus para obtener tus credenciales API únicas.

= ¿Puedo usar el plugin sin VictoriaNexus? =

Sí, el plugin guarda las entradas localmente en WordPress. La sincronización con VictoriaNexus es opcional.

= ¿Funciona con productos variables? =

Sí, los productos variables en la categoría configurada mostrarán el botón de lista de espera en lugar de los selectores de variación.

== Changelog ==

= 1.0.0 =
* Versión inicial
* Botón en catálogo con modal
* Sincronización con VictoriaNexus API
* Panel de administración
