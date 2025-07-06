<?php
/**
 * Plugin Name: StaticBoost Pro
 * Plugin URI: https://github.com/yourname/staticboost-pro
 * Description: Convierte tu sitio WordPress en páginas estáticas ultrarrápidas con optimización inteligente usando BoostAI™ (nuestro sistema de Machine Learning propietario) + CDN Local Ultra.
 * Version: 2.1.0
 * Author: Tu Nombre
 * Author URI: https://tusitio.com
 * License: GPL v2 or later
 * Text Domain: staticboost-pro
 * Requires PHP: 7.4
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('SBP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SBP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SBP_CACHE_DIR', WP_CONTENT_DIR . '/cache/staticboost-pro/');
define('SBP_ASSETS_DIR', SBP_CACHE_DIR . 'assets/');
define('SBP_ML_DIR', SBP_PLUGIN_PATH . 'ml/');
define('SBP_VERSION', '2.1.0');

// Incluir archivos necesarios
require_once SBP_PLUGIN_PATH . 'includes/class-staticboost-core.php';
require_once SBP_PLUGIN_PATH . 'includes/class-admin.php';
require_once SBP_PLUGIN_PATH . 'includes/class-woocommerce-compat.php';
require_once SBP_PLUGIN_PATH . 'includes/class-boostai-optimizer.php';
require_once SBP_PLUGIN_PATH . 'includes/class-pagespeed-optimizer.php';
require_once SBP_PLUGIN_PATH . 'includes/class-asset-optimizer.php';
require_once SBP_PLUGIN_PATH . 'includes/class-local-cdn.php';
require_once SBP_PLUGIN_PATH . 'includes/functions.php';

// Inicializar el plugin
function sbp_init() {
    new StaticBoost_Core();
    
    if (is_admin()) {
        new SBP_Admin();
    }
    
    // Compatibilidad WooCommerce
    if (class_exists('WooCommerce')) {
        new SBP_WooCommerce_Compat();
    }
    
    // BoostAI™ Optimizer
    new SBP_BoostAI_Optimizer();
    
    // PageSpeed Optimizer
    new SBP_PageSpeed_Optimizer();
    
    // Asset Optimizer
    new SBP_Asset_Optimizer();
    
    // CDN Local Ultra (se inicializa automáticamente)
}
add_action('plugins_loaded', 'sbp_init');

// Activación del plugin
register_activation_hook(__FILE__, 'sbp_activate');
function sbp_activate() {
    // Crear directorios necesarios
    $directories = [
        SBP_CACHE_DIR,
        SBP_ASSETS_DIR,
        SBP_CACHE_DIR . 'css/',
        SBP_CACHE_DIR . 'js/',
        SBP_CACHE_DIR . 'images/',
        SBP_CACHE_DIR . 'fonts/',
        SBP_CACHE_DIR . 'cdn/',
        SBP_CACHE_DIR . 'cdn/images/',
        SBP_CACHE_DIR . 'cdn/styles/',
        SBP_CACHE_DIR . 'cdn/scripts/',
        SBP_CACHE_DIR . 'cdn/fonts/',
        SBP_CACHE_DIR . 'cdn/videos/',
        SBP_CACHE_DIR . 'cdn/documents/'
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
    
    // Crear tablas para BoostAI analytics
    sbp_create_analytics_tables();
    
    // Crear archivo .htaccess optimizado para PageSpeed
    sbp_create_pagespeed_htaccess();
    
    // Configuración por defecto optimizada para PageSpeed
    add_option('sbp_enabled', true);
    add_option('sbp_cache_lifetime', 3600);
    add_option('sbp_excluded_pages', array('/cart', '/checkout', '/my-account'));
    add_option('sbp_excluded_user_agents', array('bot', 'crawler', 'spider'));
    add_option('sbp_show_cache_info', true);
    add_option('sbp_boostai_enabled', true);
    add_option('sbp_asset_optimization', true);
    add_option('sbp_aggressive_optimization', false);
    add_option('sbp_image_optimization', true);
    add_option('sbp_critical_css', true);
    add_option('sbp_pagespeed_mode', true);
    add_option('sbp_preload_critical_resources', true);
    add_option('sbp_eliminate_render_blocking', true);
    add_option('sbp_optimize_lcp', true);
    add_option('sbp_minimize_main_thread', true);
    add_option('sbp_local_cdn_enabled', true); // NUEVO: CDN Local habilitado por defecto
    add_option('sbp_local_cdn_aggressive', false); // NUEVO: Modo CDN conservador por defecto
    
    // Programar tareas de optimización
    if (!wp_next_scheduled('sbp_boostai_analysis')) {
        wp_schedule_event(time(), 'hourly', 'sbp_boostai_analysis');
    }
    
    if (!wp_next_scheduled('sbp_asset_optimization')) {
        wp_schedule_event(time(), 'daily', 'sbp_asset_optimization');
    }
    
    if (!wp_next_scheduled('sbp_pagespeed_optimization')) {
        wp_schedule_event(time(), 'twicedaily', 'sbp_pagespeed_optimization');
    }
    
    // Flush rewrite rules para CDN local
    flush_rewrite_rules();
}

// Desactivación del plugin
register_deactivation_hook(__FILE__, 'sbp_deactivate');
function sbp_deactivate() {
    // Limpiar caché
    sbp_clear_all_cache();
    
    // Eliminar tareas programadas
    wp_clear_scheduled_hook('sbp_boostai_analysis');
    wp_clear_scheduled_hook('sbp_asset_optimization');
    wp_clear_scheduled_hook('sbp_pagespeed_optimization');
    
    // Eliminar .htaccess
    $htaccess_path = SBP_CACHE_DIR . '.htaccess';
    if (file_exists($htaccess_path)) {
        unlink($htaccess_path);
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Crear tablas para analytics BoostAI
function sbp_create_analytics_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabla para métricas de usuario
    $table_metrics = $wpdb->prefix . 'sbp_user_metrics';
    $sql_metrics = "CREATE TABLE $table_metrics (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        session_id varchar(32) NOT NULL,
        page_url varchar(500) NOT NULL,
        viewport_width int(11) NOT NULL,
        viewport_height int(11) NOT NULL,
        scroll_depth float NOT NULL,
        time_on_page int(11) NOT NULL,
        lcp_time float DEFAULT NULL,
        fid_time float DEFAULT NULL,
        cls_score float DEFAULT NULL,
        device_type varchar(20) NOT NULL,
        connection_type varchar(20) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY page_url (page_url(191)),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    // Tabla para configuraciones adaptativas
    $table_config = $wpdb->prefix . 'sbp_adaptive_config';
    $sql_config = "CREATE TABLE $table_config (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        config_key varchar(100) NOT NULL,
        config_value text NOT NULL,
        page_pattern varchar(200) DEFAULT NULL,
        device_type varchar(20) DEFAULT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY config_key_pattern (config_key, page_pattern, device_type)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_metrics);
    dbDelta($sql_config);
}

// Crear .htaccess optimizado para PageSpeed 100/100 + CDN Local
function sbp_create_pagespeed_htaccess() {
    $htaccess_content = '
# StaticBoost Pro - PageSpeed 100/100 + CDN Local Ultra
<IfModule mod_rewrite.c>
RewriteEngine On

# CDN Local - Servir assets optimizados
RewriteRule ^sbp-cdn/(.+)$ index.php?sbp_cdn_asset=$1 [QSA,L]

# Servir archivos estáticos HTML directamente (MÁXIMA VELOCIDAD)
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{HTTP_COOKIE} !comment_author_
RewriteCond %{HTTP_COOKIE} !wp-postpass_
RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_
RewriteCond %{HTTP_COOKIE} !woocommerce_cart_hash
RewriteCond %{HTTP_COOKIE} !woocommerce_items_in_cart
RewriteCond %{REQUEST_URI} !^/wp-admin/
RewriteCond %{REQUEST_URI} !^/wp-content/
RewriteCond %{REQUEST_URI} !^/wp-includes/
RewriteCond %{REQUEST_URI} !^/cart/
RewriteCond %{REQUEST_URI} !^/checkout/
RewriteCond %{REQUEST_URI} !^/my-account/
RewriteCond %{REQUEST_URI} !^/sbp-cdn/

# Para página principal
RewriteCond %{REQUEST_URI} ^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/staticboost-pro/index/index.html -f
RewriteRule ^$ wp-content/cache/staticboost-pro/index/index.html [L]

# Para otras páginas
RewriteCond %{REQUEST_URI} !^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/staticboost-pro%{REQUEST_URI}/index.html -f
RewriteRule ^(.*)$ wp-content/cache/staticboost-pro/$1/index.html [L]
</IfModule>

# Headers para PageSpeed 100/100 + CDN Local
<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType text/html "access plus 1 hour"
ExpiresByType text/css "access plus 1 year"
ExpiresByType application/javascript "access plus 1 year"
ExpiresByType image/png "access plus 1 year"
ExpiresByType image/jpg "access plus 1 year"
ExpiresByType image/jpeg "access plus 1 year"
ExpiresByType image/gif "access plus 1 year"
ExpiresByType image/webp "access plus 1 year"
ExpiresByType image/avif "access plus 1 year"
ExpiresByType font/woff "access plus 1 year"
ExpiresByType font/woff2 "access plus 1 year"
ExpiresByType image/svg+xml "access plus 1 year"
ExpiresByType video/mp4 "access plus 1 month"
ExpiresByType video/webm "access plus 1 month"
</IfModule>

# Compresión máxima para PageSpeed + CDN
<IfModule mod_deflate.c>
AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json image/svg+xml text/xml application/xml application/rss+xml
SetOutputFilter DEFLATE
SetEnvIfNoCase Request_URI \.(?:gif|jpe?g|png|webp|avif|mp4|webm)$ no-gzip dont-vary
SetEnvIfNoCase Request_URI \.(?:exe|t?gz|zip|bz2|sit|rar)$ no-gzip dont-vary
</IfModule>

# Brotli compression (si está disponible)
<IfModule mod_brotli.c>
AddOutputFilterByType BROTLI_COMPRESS text/html text/css text/javascript application/javascript application/json image/svg+xml
</IfModule>

# Headers de caché optimizados para CDN Local
<IfModule mod_headers.c>
Header set X-Static-Cache "HIT"
Header set X-StaticBoost "PRO-CDN"
Header set Cache-Control "public, max-age=31536000, immutable" "expr=%{REQUEST_URI} =~ m#\.(css|js|png|jpg|jpeg|gif|webp|avif|woff|woff2|svg|mp4|webm)$#"
Header set Cache-Control "public, max-age=3600" "expr=%{REQUEST_URI} =~ m#\.html$#"

# Preload headers críticos para LCP
Header add Link "</wp-content/cache/staticboost-pro/css/critical-pagespeed.css>; rel=preload; as=style"
Header add Link "<https://fonts.googleapis.com>; rel=preconnect"
Header add Link "<https://fonts.gstatic.com>; rel=preconnect; crossorigin"

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# CDN Local headers
Header set X-CDN-Cache "LOCAL-ULTRA" "expr=%{REQUEST_URI} =~ m#^/sbp-cdn/#"
Header set X-Served-By "StaticBoost-Pro-CDN" "expr=%{REQUEST_URI} =~ m#^/sbp-cdn/#"
</IfModule>

# Optimización de fuentes para CDN Local
<IfModule mod_headers.c>
<FilesMatch "\.(woff|woff2|eot|ttf|otf)$">
Header set Cache-Control "public, max-age=31536000, immutable"
Header set Access-Control-Allow-Origin "*"
Header set Access-Control-Allow-Methods "GET"
Header set Access-Control-Allow-Headers "Range"
</FilesMatch>
</IfModule>

# Optimización de imágenes para CDN Local
<IfModule mod_headers.c>
<FilesMatch "\.(jpg|jpeg|png|gif|webp|avif|svg)$">
Header set Accept-Ranges "bytes"
Header set X-Image-Optimized "StaticBoost-Pro"
</FilesMatch>
</IfModule>

# Optimización de videos para CDN Local
<IfModule mod_headers.c>
<FilesMatch "\.(mp4|webm|ogg)$">
Header set Accept-Ranges "bytes"
Header set X-Video-Optimized "StaticBoost-Pro"
</FilesMatch>
</IfModule>
';
    
    file_put_contents(SBP_CACHE_DIR . '.htaccess', $htaccess_content);
    
    // También crear .htaccess en la raíz para redirigir a archivos estáticos
    $root_htaccess = ABSPATH . '.htaccess';
    $existing_content = file_exists($root_htaccess) ? file_get_contents($root_htaccess) : '';
    
    // Solo agregar si no existe ya
    if (strpos($existing_content, '# StaticBoost Pro') === false) {
        $static_rules = '
# StaticBoost Pro - Servir archivos estáticos ANTES que WordPress
<IfModule mod_rewrite.c>
RewriteEngine On

# CDN Local - Servir assets optimizados
RewriteRule ^sbp-cdn/(.+)$ index.php?sbp_cdn_asset=$1 [QSA,L]

# Servir archivos estáticos HTML directamente (MÁXIMA VELOCIDAD)
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{HTTP_COOKIE} !comment_author_
RewriteCond %{HTTP_COOKIE} !wp-postpass_
RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_
RewriteCond %{HTTP_COOKIE} !woocommerce_cart_hash
RewriteCond %{HTTP_COOKIE} !woocommerce_items_in_cart
RewriteCond %{REQUEST_URI} !^/wp-admin/
RewriteCond %{REQUEST_URI} !^/wp-content/
RewriteCond %{REQUEST_URI} !^/wp-includes/
RewriteCond %{REQUEST_URI} !^/cart/
RewriteCond %{REQUEST_URI} !^/checkout/
RewriteCond %{REQUEST_URI} !^/my-account/
RewriteCond %{REQUEST_URI} !^/sbp-cdn/

# Para página principal
RewriteCond %{REQUEST_URI} ^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/staticboost-pro/index/index.html -f
RewriteRule ^$ wp-content/cache/staticboost-pro/index/index.html [L]

# Para otras páginas
RewriteCond %{REQUEST_URI} !^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/staticboost-pro%{REQUEST_URI}/index.html -f
RewriteRule ^(.*)$ wp-content/cache/staticboost-pro/$1/index.html [L]
</IfModule>

# Fin StaticBoost Pro

';
        
        file_put_contents($root_htaccess, $static_rules . $existing_content);
    }
}

// Hooks para análisis BoostAI
add_action('sbp_boostai_analysis', 'sbp_run_boostai_analysis');
add_action('sbp_asset_optimization', 'sbp_run_asset_optimization');
add_action('sbp_pagespeed_optimization', 'sbp_run_pagespeed_optimization');