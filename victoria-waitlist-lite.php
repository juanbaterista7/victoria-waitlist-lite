<?php
/**
 * Plugin Name: Victoria Waitlist Lite
 * Plugin URI: https://victorianexus.laravel.cloud
 * Description: Lista de espera para productos WooCommerce con sincronización a VictoriaNexus CRM.
 * Version: 1.0.0
 * Author: Lina Marin Trademark
 * Author URI: https://linamarintrademark.co
 * License: GPL v2 or later
 * Text Domain: victoria-waitlist-lite
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

defined('ABSPATH') || exit;

// Plugin constants
define('VWL_VERSION', '1.0.0');
define('VWL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VWL_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
final class Victoria_Waitlist_Lite {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Check WooCommerce dependency
        add_action('admin_init', [$this, 'check_woocommerce']);

        // Initialize plugin
        add_action('init', [$this, 'register_post_type']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);

        // WooCommerce hooks
        add_filter('woocommerce_is_purchasable', [$this, 'make_not_purchasable'], 999, 2);
        add_filter('woocommerce_variation_is_purchasable', [$this, 'variation_not_purchasable'], 999, 2);
        add_filter('woocommerce_available_variations', [$this, 'remove_variations'], 10, 2);

        // Catalog button
        add_action('woocommerce_after_shop_loop_item', [$this, 'catalog_button'], 15);

        // Modal and scripts
        add_action('wp_footer', [$this, 'render_modal']);

        // AJAX handlers
        add_action('wp_ajax_vwl_submit', [$this, 'handle_submission']);
        add_action('wp_ajax_nopriv_vwl_submit', [$this, 'handle_submission']);
    }

    /**
     * Check if WooCommerce is active
     */
    public function check_woocommerce() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p><strong>Victoria Waitlist Lite</strong> requiere WooCommerce para funcionar.</p></div>';
            });
        }
    }

    /**
     * Get plugin settings
     */
    public static function get_settings() {
        return get_option('vwl_settings', [
            'api_url' => '',
            'api_key' => '',
            'api_secret' => '',
            'category_slug' => 'waitlist',
            'button_text' => '¡LO QUIERO!',
            'badge_text' => 'Lista de espera',
        ]);
    }

    /**
     * Check if product is waitlist
     */
    public function is_waitlist_product($product) {
        if (is_numeric($product)) {
            $product_id = $product;
        } else {
            $product_id = $product->get_id();
            if ($product->is_type('variation')) {
                $product_id = $product->get_parent_id();
            }
        }

        $settings = self::get_settings();
        $category_slug = $settings['category_slug'] ?: 'waitlist';

        return has_term($category_slug, 'product_cat', $product_id);
    }

    /**
     * Register custom post type for entries
     */
    public function register_post_type() {
        if (post_type_exists('vwl_entry')) return;

        register_post_type('vwl_entry', [
            'labels' => [
                'name' => 'Entradas Waitlist',
                'singular_name' => 'Entrada Waitlist',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'woocommerce',
            'capability_type' => 'post',
            'supports' => ['title'],
        ]);
    }

    /**
     * Make waitlist products not purchasable
     */
    public function make_not_purchasable($purchasable, $product) {
        if ($this->is_waitlist_product($product)) {
            return false;
        }
        return $purchasable;
    }

    /**
     * Make variations not purchasable
     */
    public function variation_not_purchasable($purchasable, $variation) {
        if ($this->is_waitlist_product($variation)) {
            return false;
        }
        return $purchasable;
    }

    /**
     * Remove variation data to prevent swatches from rendering
     */
    public function remove_variations($variations, $product) {
        if (is_admin()) return $variations;

        if ($this->is_waitlist_product($product)) {
            return [];
        }
        return $variations;
    }

    /**
     * Render catalog button
     */
    public function catalog_button() {
        global $product;
        if (!$product || !$this->is_waitlist_product($product)) {
            return;
        }

        $settings = self::get_settings();
        $button_text = $settings['button_text'] ?: '¡LO QUIERO!';

        $product_data = esc_attr(json_encode([
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
            'image' => wp_get_attachment_url($product->get_image_id()),
        ]));

        ?>
        <div class="vwl-catalog-btn-wrapper" style="padding: 10px 10px 15px; text-align: center;">
            <a href="#"
               class="button vwl-open-modal"
               data-product='<?php echo $product_data; ?>'
               style="
                   display: block;
                   background: #333;
                   color: #fff;
                   text-align: center;
                   padding: 12px 20px;
                   font-size: 13px;
                   font-weight: 600;
                   text-transform: uppercase;
                   letter-spacing: 0.5px;
                   border-radius: 0;
                   width: 100%;
                   box-sizing: border-box;
               ">
                <?php echo esc_html($button_text); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Render global modal in footer
     */
    public function render_modal() {
        $settings = self::get_settings();
        ?>
        <!-- Victoria Waitlist Lite Modal -->
        <div id="vwl-modal" style="
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 999999;
            justify-content: center;
            align-items: center;
        ">
            <div style="
                background: #fff;
                padding: 30px;
                border-radius: 8px;
                max-width: 420px;
                width: 90%;
                position: relative;
                max-height: 90vh;
                overflow-y: auto;
            ">
                <button type="button" class="vwl-close-modal" style="
                    position: absolute;
                    top: 10px;
                    right: 15px;
                    background: none;
                    border: none;
                    font-size: 28px;
                    cursor: pointer;
                    color: #666;
                    line-height: 1;
                ">&times;</button>

                <h3 style="margin: 0 0 10px 0; font-size: 20px;">Lista de Espera</h3>
                <p style="margin: 0 0 20px 0; color: #666; font-size: 14px;">
                    Déjanos tus datos y te avisaremos cuando este producto esté disponible.
                </p>

                <div id="vwl-product-info" style="
                    background: #f5f5f5;
                    padding: 12px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                    text-align: center;
                ">
                    <strong id="vwl-product-name" style="display: block; font-size: 15px;"></strong>
                    <small id="vwl-product-sku" style="color: #999;"></small>
                </div>

                <form id="vwl-form">
                    <input type="hidden" name="action" value="vwl_submit">
                    <input type="hidden" name="product_id" id="vwl-product-id">
                    <input type="hidden" name="product_name" id="vwl-input-name">
                    <input type="hidden" name="product_sku" id="vwl-input-sku">
                    <input type="hidden" name="product_price" id="vwl-input-price">
                    <input type="hidden" name="product_image" id="vwl-input-image">
                    <?php wp_nonce_field('vwl_submit', 'vwl_nonce'); ?>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Nombre *</label>
                        <input type="text" name="customer_name" required placeholder="Tu nombre completo" style="
                            width: 100%;
                            padding: 12px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            font-size: 14px;
                            box-sizing: border-box;
                        ">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Email *</label>
                        <input type="email" name="customer_email" required placeholder="tu@email.com" style="
                            width: 100%;
                            padding: 12px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            font-size: 14px;
                            box-sizing: border-box;
                        ">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Teléfono *</label>
                        <input type="tel" name="customer_phone" required placeholder="300 123 4567" style="
                            width: 100%;
                            padding: 12px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            font-size: 14px;
                            box-sizing: border-box;
                        ">
                    </div>

                    <button type="submit" style="
                        width: 100%;
                        padding: 14px;
                        background: #333;
                        color: #fff;
                        border: none;
                        border-radius: 4px;
                        font-size: 15px;
                        font-weight: 600;
                        cursor: pointer;
                    ">
                        UNIRME A LA LISTA
                    </button>

                    <div id="vwl-response" style="margin-top: 15px; text-align: center;"></div>
                </form>
            </div>
        </div>

        <script>
        (function() {
            var modal = document.getElementById('vwl-modal');
            if (!modal) return;

            var form = document.getElementById('vwl-form');
            var response = document.getElementById('vwl-response');

            // Open modal
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.vwl-open-modal');
                if (btn) {
                    e.preventDefault();
                    var data = JSON.parse(btn.getAttribute('data-product'));
                    openModal(data);
                }

                // Close modal
                if (e.target.classList.contains('vwl-close-modal') || e.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Close with ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.style.display === 'flex') {
                    modal.style.display = 'none';
                }
            });

            function openModal(data) {
                // Reset PRIMERO, antes de setear valores
                form.reset();
                response.innerHTML = '';

                // Luego setear los valores
                document.getElementById('vwl-product-id').value = data.id;
                document.getElementById('vwl-input-name').value = data.name;
                document.getElementById('vwl-input-sku').value = data.sku || '';
                document.getElementById('vwl-input-price').value = data.price || '';
                document.getElementById('vwl-input-image').value = data.image || '';
                document.getElementById('vwl-product-name').textContent = data.name;
                document.getElementById('vwl-product-sku').textContent = data.sku ? 'SKU: ' + data.sku : '';

                modal.style.display = 'flex';
            }

            // Form submit
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    var submitBtn = form.querySelector('button[type="submit"]');
                    var originalText = submitBtn.textContent;
                    submitBtn.textContent = 'Enviando...';
                    submitBtn.disabled = true;

                    var formData = new FormData(form);

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.success) {
                            response.innerHTML = '<span style="color: #28a745; font-weight: 500;">✓ ' + data.data.message + '</span>';
                            form.reset();
                            setTimeout(function() {
                                modal.style.display = 'none';
                            }, 2500);
                        } else {
                            response.innerHTML = '<span style="color: #dc3545;">' + (data.data.message || 'Error al enviar') + '</span>';
                        }
                    })
                    .catch(function() {
                        response.innerHTML = '<span style="color: #dc3545;">Error de conexión</span>';
                    })
                    .finally(function() {
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    });
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * Handle AJAX form submission
     */
    public function handle_submission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['vwl_nonce'] ?? '', 'vwl_submit')) {
            wp_send_json_error(['message' => 'Sesión expirada. Recarga la página.']);
        }

        // Sanitize data
        $product_id = absint($_POST['product_id'] ?? 0);
        $product_name = sanitize_text_field($_POST['product_name'] ?? '');
        $product_sku = sanitize_text_field($_POST['product_sku'] ?? '');
        $product_price = sanitize_text_field($_POST['product_price'] ?? '');
        $product_image = esc_url_raw($_POST['product_image'] ?? '');
        $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
        $customer_email = sanitize_email($_POST['customer_email'] ?? '');
        $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');

        // Validate required fields
        if (!$product_id || !$customer_name || !$customer_email || !$customer_phone) {
            wp_send_json_error(['message' => 'Todos los campos son requeridos.']);
        }

        if (!is_email($customer_email)) {
            wp_send_json_error(['message' => 'Email inválido.']);
        }

        // Check for duplicates
        $existing = get_posts([
            'post_type' => 'vwl_entry',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_product_id', 'value' => $product_id],
                ['key' => '_customer_email', 'value' => $customer_email],
            ],
            'posts_per_page' => 1,
        ]);

        if (!empty($existing)) {
            wp_send_json_error(['message' => 'Ya estás inscrito en la lista de espera para este producto.']);
        }

        // Create entry
        $post_id = wp_insert_post([
            'post_type' => 'vwl_entry',
            'post_title' => sprintf('%s - %s', $customer_name, $product_name),
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => 'Error al guardar. Intenta de nuevo.']);
        }

        // Save meta
        update_post_meta($post_id, '_product_id', $product_id);
        update_post_meta($post_id, '_product_name', $product_name);
        update_post_meta($post_id, '_product_sku', $product_sku);
        update_post_meta($post_id, '_product_price', $product_price);
        update_post_meta($post_id, '_product_image', $product_image);
        update_post_meta($post_id, '_customer_name', $customer_name);
        update_post_meta($post_id, '_customer_email', $customer_email);
        update_post_meta($post_id, '_customer_phone', $customer_phone);
        update_post_meta($post_id, '_sync_status', 'pending');
        update_post_meta($post_id, '_created_at', current_time('mysql'));

        // Sync to VictoriaNexus
        $this->sync_to_api($post_id, [
            'product_id' => $product_id,
            'product_name' => $product_name,
            'product_sku' => $product_sku,
            'product_price' => $product_price,
            'product_image' => $product_image,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
        ]);

        wp_send_json_success(['message' => '¡Listo! Te avisaremos cuando esté disponible.']);
    }

    /**
     * Sync entry to VictoriaNexus API
     */
    private function sync_to_api($post_id, $data) {
        $settings = self::get_settings();

        $api_url = rtrim($settings['api_url'] ?? '', '/');
        $api_key = $settings['api_key'] ?? '';
        $api_secret = $settings['api_secret'] ?? '';

        // Skip if not configured
        if (empty($api_url) || empty($api_key) || empty($api_secret)) {
            return;
        }

        // Prepare payload
        $payload = [
            'source' => 'woocommerce_waitlist',
            'product' => [
                'woo_id' => $data['product_id'],
                'name' => $data['product_name'],
                'sku' => $data['product_sku'] ?? '',
                'price' => $data['product_price'] ?? 0,
                'image_url' => $data['product_image'] ?? '',
            ],
            'customer' => [
                'name' => $data['customer_name'],
                'email' => $data['customer_email'],
                'phone' => $data['customer_phone'] ?? '',
            ],
            'notes' => 'Inscrito desde WooCommerce (Victoria Waitlist Lite)',
        ];

        // Generate HMAC signature
        $path = '/api_v1/waitlist';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', 'POST' . $path . $timestamp, $api_secret);

        // Send request
        $response = wp_remote_post($api_url . $path, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-API-Key' => $api_key,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
            ],
            'body' => wp_json_encode($payload),
        ]);

        // Update sync status
        if (is_wp_error($response)) {
            update_post_meta($post_id, '_sync_status', 'error');
            update_post_meta($post_id, '_sync_error', $response->get_error_message());
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($code >= 200 && $code < 300) {
                update_post_meta($post_id, '_sync_status', 'synced');
                update_post_meta($post_id, '_synced_at', current_time('mysql'));
                if (isset($body['data']['wishlist_id'])) {
                    update_post_meta($post_id, '_victorianexus_id', $body['data']['wishlist_id']);
                }
            } else {
                update_post_meta($post_id, '_sync_status', 'error');
                update_post_meta($post_id, '_sync_error', $body['error']['message'] ?? 'Error ' . $code);
            }
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Victoria Waitlist',
            'Victoria Waitlist',
            'manage_woocommerce',
            'victoria-waitlist-lite',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('vwl_settings_group', 'vwl_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        return [
            'api_url' => esc_url_raw($input['api_url'] ?? ''),
            'api_key' => sanitize_text_field($input['api_key'] ?? ''),
            'api_secret' => sanitize_text_field($input['api_secret'] ?? ''),
            'category_slug' => sanitize_title($input['category_slug'] ?? 'waitlist'),
            'button_text' => sanitize_text_field($input['button_text'] ?? '¡LO QUIERO!'),
            'badge_text' => sanitize_text_field($input['badge_text'] ?? 'Lista de espera'),
        ];
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $settings = self::get_settings();
        ?>
        <div class="wrap">
            <h1>Victoria Waitlist Lite</h1>

            <form method="post" action="options.php">
                <?php settings_fields('vwl_settings_group'); ?>

                <h2>Configuración de API (VictoriaNexus)</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">API URL</th>
                        <td>
                            <input type="url" name="vwl_settings[api_url]" value="<?php echo esc_attr($settings['api_url']); ?>" class="regular-text" placeholder="https://victorianexus.laravel.cloud">
                            <p class="description">URL base de la API de VictoriaNexus</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="text" name="vwl_settings[api_key]" value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Secret</th>
                        <td>
                            <input type="password" name="vwl_settings[api_secret]" value="<?php echo esc_attr($settings['api_secret']); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>

                <h2>Configuración de WooCommerce</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Slug de Categoría</th>
                        <td>
                            <input type="text" name="vwl_settings[category_slug]" value="<?php echo esc_attr($settings['category_slug']); ?>" class="regular-text">
                            <p class="description">Slug de la categoría de productos en lista de espera (ej: waitlist, lista-de-espera)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Texto del Botón</th>
                        <td>
                            <input type="text" name="vwl_settings[button_text]" value="<?php echo esc_attr($settings['button_text']); ?>" class="regular-text">
                            <p class="description">Texto que aparece en el botón del catálogo</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Texto del Badge</th>
                        <td>
                            <input type="text" name="vwl_settings[badge_text]" value="<?php echo esc_attr($settings['badge_text']); ?>" class="regular-text">
                            <p class="description">Texto del badge en la imagen del producto</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Guardar Configuración'); ?>
            </form>

            <hr>
            <h2>Estado de Entradas</h2>
            <?php
            $entries = get_posts([
                'post_type' => 'vwl_entry',
                'posts_per_page' => 10,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);

            if ($entries) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>Producto</th><th>Cliente</th><th>Email</th><th>Fecha</th><th>Sync</th></tr></thead>';
                echo '<tbody>';
                foreach ($entries as $entry) {
                    $product_name = get_post_meta($entry->ID, '_product_name', true);
                    $customer_name = get_post_meta($entry->ID, '_customer_name', true);
                    $customer_email = get_post_meta($entry->ID, '_customer_email', true);
                    $sync_status = get_post_meta($entry->ID, '_sync_status', true);
                    $sync_class = $sync_status === 'synced' ? 'color:green;' : ($sync_status === 'error' ? 'color:red;' : 'color:orange;');

                    echo '<tr>';
                    echo '<td>' . esc_html($product_name) . '</td>';
                    echo '<td>' . esc_html($customer_name) . '</td>';
                    echo '<td>' . esc_html($customer_email) . '</td>';
                    echo '<td>' . get_the_date('Y-m-d H:i', $entry) . '</td>';
                    echo '<td style="' . $sync_class . '">' . esc_html($sync_status ?: 'pending') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>No hay entradas aún.</p>';
            }
            ?>
        </div>
        <?php
    }
}

// Initialize plugin
function vwl_init() {
    return Victoria_Waitlist_Lite::instance();
}
add_action('plugins_loaded', 'vwl_init');
