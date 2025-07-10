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
require_once SBP_PLUGIN_PATH . 'includes/functions.php';

// Solo cargar clases necesarias según el contexto
function sbp_load_required_classes() {
    // Admin siempre
    if (is_admin()) {
        require_once SBP_PLUGIN_PATH . 'includes/class-admin.php';
        new SBP_Admin();
    }
    
    // DESACTIVADO TEMPORALMENTE: BoostAI
    // if (get_option('sbp_boostai_enabled', true)) {
    //     require_once SBP_PLUGIN_PATH . 'includes/class-boostai-optimizer.php';
    //     if (class_exists('SBP_BoostAI_Optimizer')) {
    //         new SBP_BoostAI_Optimizer();
    //     }
    // }
    
    // DESACTIVADO TEMPORALMENTE: PageSpeed
    // if (get_option('sbp_pagespeed_mode', true)) {
    //     require_once SBP_PLUGIN_PATH . 'includes/class-pagespeed-optimizer.php';
    //     if (class_exists('SBP_PageSpeed_Optimizer')) {
    //         new SBP_PageSpeed_Optimizer();
    //     }
    // }
    
    // DESACTIVADO TEMPORALMENTE: Asset Optimizer
    // if (get_option('sbp_optimize_images', true) || 
    //     get_option('sbp_optimize_css', true) || 
    //     get_option('sbp_optimize_js', false) || 
    //     get_option('sbp_webp_conversion', true)) {
    //     require_once SBP_PLUGIN_PATH . 'includes/class-asset-optimizer.php';
    //     if (class_exists('SBP_Asset_Optimizer')) {
    //         new SBP_Asset_Optimizer();
    //     }
    // }
    
    // DESACTIVADO TEMPORALMENTE: CDN Local
    // if (get_option('sbp_local_cdn_enabled', true)) {
    //     require_once SBP_PLUGIN_PATH . 'includes/class-local-cdn.php';
    //     if (class_exists('SBP_Local_CDN')) {
    //         new SBP_Local_CDN();
    //     }
    // }
    
    // WooCommerce solo si está activo
    if (class_exists('WooCommerce')) {
        require_once SBP_PLUGIN_PATH . 'includes/class-woocommerce-compat.php';
        if (class_exists('SBP_WooCommerce_Compat')) {
            new SBP_WooCommerce_Compat();
        }
    }
}

// Inicializar el plugin de forma optimizada
function sbp_init() {
    // Verificar que las clases existan antes de instanciar
    if (class_exists('StaticBoost_Core')) {
        new StaticBoost_Core();
    }
    
    // Cargar otras clases según necesidad
    sbp_load_required_classes();
}
add_action('plugins_loaded', 'sbp_init', 1);

// Activación del plugin - SIMPLIFICADA
register_activation_hook(__FILE__, 'sbp_activate');
function sbp_activate() {
    // Crear directorios necesarios
    $directories = [
        SBP_CACHE_DIR,
        SBP_ASSETS_DIR,
        SBP_CACHE_DIR . 'css/',
        SBP_CACHE_DIR . 'js/',
        SBP_CACHE_DIR . 'images/'
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
    
    // Configuración por defecto SIMPLIFICADA
    $default_options = array(
        'sbp_enabled' => true,
        'sbp_cache_lifetime' => 3600,
        'sbp_excluded_pages' => array('/cart', '/checkout', '/my-account'),
        'sbp_show_cache_info' => true,
        
        // DESACTIVADO TEMPORALMENTE
        'sbp_boostai_enabled' => false,
        'sbp_pagespeed_mode' => false,
        'sbp_local_cdn_enabled' => false,
        'sbp_optimize_images' => false,
        'sbp_optimize_css' => false,
        'sbp_lazy_loading' => false,
        'sbp_minify_html' => false
    );
    
    foreach ($default_options as $option => $value) {
        add_option($option, $value);
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Desactivación del plugin
register_deactivation_hook(__FILE__, 'sbp_deactivate');
function sbp_deactivate() {
    // Limpiar caché
    sbp_clear_all_cache();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}