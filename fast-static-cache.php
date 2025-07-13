<?php
/**
 * Plugin Name: Fast Static Cache Pro
 * Plugin URI: https://github.com/yourname/fast-static-cache
 * Description: Sistema de caché estático CONSERVADOR que mantiene la apariencia exacta del sitio. Compatible con Elementor, Divi, y todos los temas.
 * Version: 3.0.0
 * Author: Tu Nombre
 * License: GPL v2 or later
 * Text Domain: fast-static-cache
 * Requires PHP: 7.4
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('FSC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FSC_CACHE_DIR', WP_CONTENT_DIR . '/cache/fast-static-cache/');
define('FSC_VERSION', '3.0.0');

// SISTEMA CONSERVADOR - SERVIR HTML ESTÁTICO SIN MODIFICACIONES
// Este código se ejecuta ANTES que WordPress para máxima velocidad
if (!defined('WP_CLI') && !is_admin() && $_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    fsc_serve_static_conservatively();
}

/**
 * SERVIR ARCHIVOS ESTÁTICOS DE FORMA CONSERVADORA
 * Mantiene la apariencia exacta del sitio
 */
function fsc_serve_static_conservatively() {
    // Verificar si el usuario está logueado (cookies)
    if (fsc_user_is_logged_in()) {
        return; // Cargar WordPress normalmente
    }
    
    // Verificar si es una página excluida
    if (fsc_is_excluded_page()) {
        return; // Cargar WordPress normalmente
    }
    
    // Obtener ruta del archivo estático
    $static_file = fsc_get_static_file_path();
    
    // Verificar si el archivo existe y es válido
    if (!file_exists($static_file) || !fsc_is_file_valid($static_file)) {
        return; // Cargar WordPress normalmente
    }
    
    // SERVIR ARCHIVO ESTÁTICO SIN MODIFICACIONES
    fsc_serve_static_file_conservatively($static_file);
    exit; // NO CARGAR WORDPRESS
}

/**
 * Verificar si el usuario está logueado (sin cargar WordPress)
 */
function fsc_user_is_logged_in() {
    // Verificar cookies de WordPress
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'wordpress_logged_in_') === 0 ||
            strpos($name, 'wp-postpass_') === 0 ||
            strpos($name, 'comment_author_') === 0 ||
            strpos($name, 'woocommerce_cart_hash') === 0 ||
            strpos($name, 'woocommerce_items_in_cart') === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Verificar si es una página excluida (sin cargar WordPress)
 */
function fsc_is_excluded_page() {
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Páginas siempre excluidas
    $excluded = array(
        '/wp-admin/',
        '/wp-content/',
        '/wp-includes/',
        '/wp-json/',
        '/cart/',
        '/checkout/',
        '/my-account/',
        '/admin/',
        '?',
        '&'
    );
    
    foreach ($excluded as $pattern) {
        if (strpos($request_uri, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Obtener ruta del archivo estático
 */
function fsc_get_static_file_path() {
    $request_uri = $_SERVER['REQUEST_URI'];
    $request_uri = rtrim($request_uri, '/');
    
    if (empty($request_uri)) {
        $request_uri = '/index';
    }
    
    return FSC_CACHE_DIR . ltrim($request_uri, '/') . '/index.html';
}

/**
 * Verificar si el archivo es válido
 */
function fsc_is_file_valid($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    // Verificar edad del archivo (1 hora por defecto)
    $max_age = 3600; // 1 hora
    return (time() - filemtime($file_path)) < $max_age;
}

/**
 * SERVIR ARCHIVO ESTÁTICO DE FORMA CONSERVADORA
 * Sin modificaciones que puedan romper la apariencia
 */
function fsc_serve_static_file_conservatively($file_path) {
    $etag = md5_file($file_path);
    $last_modified = filemtime($file_path);
    $file_size = filesize($file_path);
    
    // Headers conservadores - no agresivos
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Static-Cache: HIT-CONSERVATIVE');
    header('X-Fast-Static: CONSERVATIVE');
    header('Cache-Control: public, max-age=3600');
    header('ETag: "' . $etag . '"');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
    
    // Verificar caché del cliente
    if (fsc_client_has_cache($etag, $last_modified)) {
        http_response_code(304);
        exit;
    }
    
    // Servir archivo comprimido si existe
    $gzip_file = $file_path . '.gz';
    if (file_exists($gzip_file) && fsc_client_accepts_gzip()) {
        header('Content-Encoding: gzip');
        header('Content-Length: ' . filesize($gzip_file));
        readfile($gzip_file);
    } else {
        header('Content-Length: ' . $file_size);
        readfile($file_path);
    }
}

/**
 * Verificar caché del cliente
 */
function fsc_client_has_cache($etag, $last_modified) {
    if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
        $client_etag = trim($_SERVER['HTTP_IF_NONE_MATCH'], '"');
        if ($client_etag === $etag) {
            return true;
        }
    }
    
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $client_time = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        if ($client_time >= $last_modified) {
            return true;
        }
    }
    
    return false;
}

/**
 * Verificar si el cliente acepta gzip
 */
function fsc_client_accepts_gzip() {
    return isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
           strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
}

// CARGAR WORDPRESS SOLO SI ES NECESARIO
// Si llegamos aquí, significa que necesitamos cargar WordPress

// Incluir Object Cache EXACTAMENTE como Object Cache Pro
require_once FSC_PLUGIN_PATH . 'includes/object-cache-pro.php';

// Incluir clases principales
require_once FSC_PLUGIN_PATH . 'includes/class-static-generator.php';
require_once FSC_PLUGIN_PATH . 'includes/class-admin.php';

/**
 * Inicializar el plugin
 */
function fsc_init() {
    // Instalar Object Cache automáticamente
    FSC_Object_Cache_Pro::install();
    
    // Inicializar generador estático
    new FSC_Static_Generator();
    
    // Admin solo en backend
    if (is_admin()) {
        new FSC_Admin();
    }
}
add_action('plugins_loaded', 'fsc_init', 1);

/**
 * Activación del plugin
 */
register_activation_hook(__FILE__, 'fsc_activate');
function fsc_activate() {
    // Crear directorios
    $directories = array(
        FSC_CACHE_DIR,
        FSC_CACHE_DIR . 'assets/',
        FSC_CACHE_DIR . 'css/',
        FSC_CACHE_DIR . 'js/',
        FSC_CACHE_DIR . 'images/'
    );
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
    
    // Instalar Object Cache automáticamente
    FSC_Object_Cache_Pro::install();
    
    // Crear .htaccess conservador
    fsc_create_htaccess();
    
    // Configuración por defecto
    add_option('fsc_enabled', true);
    add_option('fsc_cache_lifetime', 3600);
    add_option('fsc_excluded_pages', array('/cart', '/checkout', '/my-account'));
    add_option('fsc_conservative_mode', true); // MODO CONSERVADOR por defecto
}

/**
 * Crear .htaccess conservador
 */
function fsc_create_htaccess() {
    $htaccess_content = '
# Fast Static Cache Pro - MODO CONSERVADOR
<IfModule mod_rewrite.c>
RewriteEngine On

# SERVIR ARCHIVOS ESTÁTICOS SIN MODIFICACIONES
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_
RewriteCond %{HTTP_COOKIE} !wp-postpass_
RewriteCond %{HTTP_COOKIE} !comment_author_
RewriteCond %{HTTP_COOKIE} !woocommerce_cart_hash
RewriteCond %{REQUEST_URI} !^/wp-admin/
RewriteCond %{REQUEST_URI} !^/wp-content/
RewriteCond %{REQUEST_URI} !^/wp-includes/
RewriteCond %{REQUEST_URI} !^/wp-json/

# Página principal
RewriteCond %{REQUEST_URI} ^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/fast-static-cache/index/index.html -f
RewriteRule ^$ wp-content/cache/fast-static-cache/index/index.html [L]

# Otras páginas
RewriteCond %{REQUEST_URI} !^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/fast-static-cache%{REQUEST_URI}/index.html -f
RewriteRule ^(.*)$ wp-content/cache/fast-static-cache/$1/index.html [L]
</IfModule>

# Headers conservadores
<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType text/html "access plus 1 hour"
ExpiresByType text/css "access plus 1 month"
ExpiresByType application/javascript "access plus 1 month"
ExpiresByType image/png "access plus 1 month"
ExpiresByType image/jpg "access plus 1 month"
ExpiresByType image/jpeg "access plus 1 month"
ExpiresByType image/gif "access plus 1 month"
ExpiresByType image/webp "access plus 1 month"
</IfModule>

<IfModule mod_deflate.c>
AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript
</IfModule>

<IfModule mod_headers.c>
Header set X-Static-Cache "HIT-CONSERVATIVE"
Header set Cache-Control "public, max-age=2592000" "expr=%{REQUEST_URI} =~ m#\.(css|js|png|jpg|jpeg|gif|webp)$#"
Header set Cache-Control "public, max-age=3600" "expr=%{REQUEST_URI} =~ m#\.html$#"
</IfModule>
';
    
    file_put_contents(FSC_CACHE_DIR . '.htaccess', $htaccess_content);
}

/**
 * Desactivación
 */
register_deactivation_hook(__FILE__, 'fsc_deactivate');
function fsc_deactivate() {
    // Limpiar caché
    if (is_dir(FSC_CACHE_DIR)) {
        fsc_delete_directory_contents(FSC_CACHE_DIR);
    }
    
    // Eliminar .htaccess
    $htaccess_path = FSC_CACHE_DIR . '.htaccess';
    if (file_exists($htaccess_path)) {
        unlink($htaccess_path);
    }
}

/**
 * Eliminar contenido de directorio
 */
function fsc_delete_directory_contents($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..', '.htaccess'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            fsc_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return true;
}

/**
 * Eliminar directorio
 */
function fsc_delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            fsc_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}