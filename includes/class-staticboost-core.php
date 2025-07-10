<?php
/**
 * Clase principal de StaticBoost Pro - Versión OPTIMIZADA
 */
class StaticBoost_Core {
    
    private $should_cache = null;
    private $static_file_path = null;
    private $object_cache = null;
    
    public function __construct() {
        // Inicializar object cache
        $this->object_cache = new SBP_Object_Cache_Manager();
        
        // Solo cargar hooks esenciales
        add_action('init', array($this, 'init'), 1);
        
        // Solo inicializar buffering si es necesario
        if ($this->should_initialize_caching()) {
            add_action('wp_loaded', array($this, 'start_buffering'), 1);
            add_action('shutdown', array($this, 'end_buffering'), 999);
            add_action('template_redirect', array($this, 'serve_static_if_exists'), 1);
        }
        
        // Hooks de limpieza (solo si es necesario)
        if (is_admin() || $this->is_content_update()) {
            add_action('save_post', array($this, 'regenerate_static_files'));
            add_action('comment_post', array($this, 'regenerate_page_static'));
            add_action('wp_set_comment_status', array($this, 'regenerate_page_static'));
        }
        
        // Info de caché solo para usuarios logueados
        if (get_option('sbp_show_cache_info', true) && is_user_logged_in()) {
            add_action('wp_footer', array($this, 'add_cache_info'), 999);
        }
    }
    
    /**
     * Verificar si debe inicializar el sistema de caché
     */
    private function should_initialize_caching() {
        // No inicializar en admin
        if (is_admin()) {
            return false;
        }
        
        // No inicializar si está deshabilitado
        if (!get_option('sbp_enabled', true)) {
            return false;
        }
        
        // No inicializar para bots (ahorro de recursos)
        if ($this->is_bot_request()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar si es una actualización de contenido
     */
    private function is_content_update() {
        return isset($_POST['action']) && in_array($_POST['action'], array('editpost', 'inline-save'));
    }
    
    /**
     * Verificar si es un bot
     */
    private function is_bot_request() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $bot_patterns = array('bot', 'crawler', 'spider', 'scraper');
        
        foreach ($bot_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    public function init() {
        // Cargar textdomain solo si es necesario
        if (is_admin()) {
            load_plugin_textdomain('staticboost-pro', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }
    }
    
    /**
     * Servir archivo estático si existe - OPTIMIZADO
     */
    public function serve_static_if_exists() {
        if (!$this->should_serve_static()) {
            return;
        }
        
        $static_file = $this->get_static_file_path();
        
        if ($this->is_static_file_valid($static_file)) {
            $this->serve_static_file_optimized($static_file);
            exit;
        }
    }
    
    /**
     * Verificar si debe servir archivo estático - OPTIMIZADO
     */
    private function should_serve_static() {
        // Cache del resultado para evitar múltiples verificaciones
        if ($this->should_cache !== null) {
            return $this->should_cache;
        }
        
        // OPTIMIZACIÓN: Usar object cache para decisiones
        $cache_key = 'should_serve_' . md5($_SERVER['REQUEST_URI']);
        $cached_decision = $this->object_cache->get($cache_key);
        
        if ($cached_decision !== false) {
            $this->should_cache = $cached_decision;
            return $this->should_cache;
        }
        
        $this->should_cache = false;
        
        // Verificaciones rápidas primero
        if (!get_option('sbp_enabled', true) || 
            is_user_logged_in() || 
            is_admin() || 
            $_SERVER['REQUEST_METHOD'] !== 'GET' || 
            !empty($_GET)) {
            $this->object_cache->set($cache_key, false, 300); // 5 minutos
            return $this->should_cache;
        }
        
        // Verificar exclusiones solo si es necesario
        if ($this->is_page_excluded()) {
            $this->object_cache->set($cache_key, false, 300);
            return $this->should_cache;
        }
        
        // Verificar WooCommerce solo si está activo
        if (class_exists('WooCommerce') && $this->is_woocommerce_page()) {
            $this->object_cache->set($cache_key, false, 300);
            return $this->should_cache;
        }
        
        $this->should_cache = true;
        $this->object_cache->set($cache_key, true, 300); // 5 minutos
        return $this->should_cache;
    }
    
    /**
     * Verificar páginas de WooCommerce - OPTIMIZADO
     */
    private function is_woocommerce_page() {
        // Verificar cookies primero (más rápido)
        if (isset($_COOKIE['woocommerce_cart_hash']) || 
            isset($_COOKIE['woocommerce_items_in_cart'])) {
            return true;
        }
        
        // Verificar funciones de WooCommerce solo si es necesario
        if (function_exists('is_cart') && is_cart()) return true;
        if (function_exists('is_checkout') && is_checkout()) return true;
        if (function_exists('is_account_page') && is_account_page()) return true;
        if (function_exists('is_product') && is_product()) return true;
        
        return false;
    }
    
    /**
     * Servir archivo estático optimizado
     */
    private function serve_static_file_optimized($static_file) {
        // Headers optimizados para máximo rendimiento
        $etag = md5_file($static_file);
        $last_modified = filemtime($static_file);
        
        // Verificar caché del cliente primero
        if ($this->client_has_valid_cache($etag, $last_modified)) {
            status_header(304);
            exit;
        }
        
        // Headers de rendimiento
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Static-Cache: HIT');
        header('X-StaticBoost: ACTIVE');
        header('Cache-Control: public, max-age=3600');
        header('ETag: "' . $etag . '"');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
        
        // Servir versión comprimida si está disponible
        $compressed_file = $static_file . '.gz';
        
        if (file_exists($compressed_file) && $this->client_accepts_gzip()) {
            header('Content-Encoding: gzip');
            header('Content-Length: ' . filesize($compressed_file));
            readfile($compressed_file);
        } else {
            header('Content-Length: ' . filesize($static_file));
            readfile($static_file);
        }
    }
    
    /**
     * Verificar caché del cliente
     */
    private function client_has_valid_cache($etag, $last_modified) {
        // Verificar ETag
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $client_etag = trim($_SERVER['HTTP_IF_NONE_MATCH'], '"');
            if ($client_etag === $etag) {
                return true;
            }
        }
        
        // Verificar If-Modified-Since
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $client_time = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            if ($client_time >= $last_modified) {
                return true;
            }
        }
        
        return false;
    }
    
    public function start_buffering() {
        if (!$this->should_generate_static()) {
            return;
        }
        
        ob_start(array($this, 'generate_static_file'));
    }
    
    public function end_buffering() {
        if (ob_get_level()) {
            ob_end_flush();
        }
    }
    
    /**
     * Verificar si debe generar archivo estático - OPTIMIZADO
     */
    private function should_generate_static() {
        return $this->should_serve_static();
    }
    
    /**
     * Generar archivo estático - OPTIMIZADO Y CONSERVADOR
     */
    public function generate_static_file($buffer) {
        if (!$this->should_generate_static_from_buffer($buffer)) {
            return $buffer;
        }
        
        $static_file = $this->get_static_file_path();
        $static_dir = dirname($static_file);
        
        if (!file_exists($static_dir)) {
            wp_mkdir_p($static_dir);
        }
        
        // OPTIMIZACIÓN CONSERVADORA - NO CAMBIA APARIENCIA
        $optimized_html = $this->optimize_html_conservatively($buffer);
        
        // Aplicar filtro para optimizaciones adicionales
        $optimized_html = apply_filters('sbp_static_html', $optimized_html, $_SERVER['REQUEST_URI']);
        
        // Añadir información de caché si está habilitado
        if (get_option('sbp_show_cache_info', true)) {
            $cache_info = sprintf(
                "\n<!-- StaticBoost Pro: Generado el %s -->",
                date('Y-m-d H:i:s')
            );
            $optimized_html .= $cache_info;
        }
        
        // Guardar archivo estático
        $result = file_put_contents($static_file, $optimized_html, LOCK_EX);
        
        if ($result) {
            // Crear versión comprimida solo si está habilitado
            if (function_exists('gzencode')) {
                file_put_contents($static_file . '.gz', gzencode($optimized_html, 9), LOCK_EX);
            }
        }
        
        return $buffer;
    }
    
    /**
     * OPTIMIZACIÓN CONSERVADORA - NO MODIFICA APARIENCIA VISUAL
     */
    private function optimize_html_conservatively($html) {
        // Solo aplicar optimizaciones habilitadas
        
        // 1. Minificar HTML si está habilitado
        if (get_option('sbp_minify_html', true)) {
            $html = $this->minify_html_safely($html);
        }
        
        // 2. Añadir preconnect headers si está habilitado
        if (get_option('sbp_preload_resources', true)) {
            $html = $this->add_preconnect_headers($html);
        }
        
        // 3. Lazy loading si está habilitado
        if (get_option('sbp_lazy_loading', true)) {
            $html = $this->add_safe_lazy_loading($html);
        }
        
        // 4. Eliminar query strings si está habilitado
        if (get_option('sbp_remove_query_strings', true)) {
            $html = $this->remove_query_strings_from_assets($html);
        }
        
        return $html;
    }
    
    /**
     * Minificar HTML de forma segura
     */
    private function minify_html_safely($html) {
        // Solo eliminar espacios excesivos entre tags
        $html = preg_replace('/>\s+</', '><', $html);
        
        // Eliminar espacios al inicio y final de líneas
        $html = preg_replace('/^\s+/m', '', $html);
        $html = preg_replace('/\s+$/m', '', $html);
        
        return $html;
    }
    
    /**
     * Añadir headers de preconnect
     */
    private function add_preconnect_headers($html) {
        $preconnect_headers = '';
        
        // Solo preconnect a dominios seguros
        $preconnect_headers .= '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        $preconnect_headers .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        
        // Insertar en el head
        $html = str_replace('</head>', $preconnect_headers . '</head>', $html);
        
        return $html;
    }
    
    /**
     * Lazy loading SEGURO - Solo para imágenes de contenido
     */
    private function add_safe_lazy_loading($html) {
        // OPTIMIZACIÓN: Lazy loading menos agresivo para mayor velocidad
        // Solo aplicar a imágenes que NO sean críticas
        $html = preg_replace_callback(
            '/<img([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i',
            array($this, 'optimize_img_tag_safely'),
            $html
        );
        
        return $html;
    }
    
    /**
     * Optimizar tag de imagen SEGURAMENTE
     */
    private function optimize_img_tag_safely($matches) {
        $before_src = $matches[1];
        $src = $matches[2];
        $after_src = $matches[3];
        $full_tag = $matches[0];
        
        // OPTIMIZACIÓN: Lista más específica para mayor velocidad
        $critical_patterns = array(
            'logo', 'icon', 'header', 'nav', 'menu', 'brand',
            'avatar', 'profile', 'admin', 'wp-content/themes',
            'hero', 'banner', 'slider', 'carousel', 'above-fold'
        );
        
        foreach ($critical_patterns as $pattern) {
            if (stripos($full_tag, $pattern) !== false || 
                stripos($src, $pattern) !== false) {
                return $matches[0]; // Devolver sin modificar
            }
        }
        
        // OPTIMIZACIÓN: Umbral más alto para lazy loading
        if (preg_match('/width=["\']?(\d+)["\']?/i', $full_tag, $width_match)) {
            if (isset($width_match[1]) && $width_match[1] < 200) {
                return $matches[0]; // No tocar imágenes pequeñas
            }
        }
        
        // OPTIMIZACIÓN: Verificar posición en la página
        $position_indicators = array('fold', 'top', 'first', 'main', 'primary');
        $is_above_fold = false;
        
        foreach ($position_indicators as $indicator) {
            if (stripos($full_tag, $indicator) !== false) {
                $is_above_fold = true;
                break;
            }
        }
        
        // Solo aplicar lazy loading si NO está above the fold
        if (!$is_above_fold && strpos($after_src, 'loading=') === false) {
            $after_src .= ' loading="lazy"';
        }
        
        // Siempre añadir decoding async para mejor rendimiento
        if (strpos($after_src, 'decoding=') === false) {
            $after_src .= ' decoding="async"';
        }
        
        return '<img' . $before_src . 'src="' . $src . '"' . $after_src . '>';
    }
    
    /**
     * Eliminar query strings de assets
     */
    private function remove_query_strings_from_assets($html) {
        // Eliminar query strings de CSS y JS
        $html = preg_replace('/(<link[^>]*href=["\'][^"\']*\.css)\?[^"\']*(["\'][^>]*>)/', '$1$2', $html);
        $html = preg_replace('/(<script[^>]*src=["\'][^"\']*\.js)\?[^"\']*(["\'][^>]*>)/', '$1$2', $html);
        
        return $html;
    }
    
    /**
     * Verificar si debe generar estático desde buffer
     */
    private function should_generate_static_from_buffer($buffer) {
        // No generar si el buffer está vacío
        if (empty(trim($buffer))) {
            return false;
        }
        
        // No generar si no es HTML válido
        if (strpos($buffer, '<html') === false && strpos($buffer, '<!DOCTYPE') === false) {
            return false;
        }
        
        // No generar si hay errores PHP
        if (strpos($buffer, 'Fatal error') !== false || 
            strpos($buffer, 'Parse error') !== false) {
            return false;
        }
        
        return true;
    }
    
    private function get_static_file_path() {
        if ($this->static_file_path !== null) {
            return $this->static_file_path;
        }
        
        // OPTIMIZACIÓN: Usar object cache para paths
        $cache_key = 'static_path_' . md5($_SERVER['REQUEST_URI']);
        $cached_path = $this->object_cache->get($cache_key);
        
        if ($cached_path !== false) {
            $this->static_file_path = $cached_path;
            return $this->static_file_path;
        }
        
        $request_uri = $_SERVER['REQUEST_URI'];
        $request_uri = rtrim($request_uri, '/');
        
        if (empty($request_uri)) {
            $request_uri = '/index';
        }
        
        $this->static_file_path = SBP_CACHE_DIR . ltrim($request_uri, '/') . '/index.html';
        $this->object_cache->set($cache_key, $this->static_file_path, 1800); // 30 minutos
        return $this->static_file_path;
    }
    
    private function is_static_file_valid($static_file) {
        if (!file_exists($static_file)) {
            return false;
        }
        
        $cache_lifetime = get_option('sbp_cache_lifetime', 3600);
        $file_time = filemtime($static_file);
        
        return (time() - $file_time) < $cache_lifetime;
    }
    
    private function client_accepts_gzip() {
        return isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
               strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
    }
    
    private function is_page_excluded() {
        $excluded_pages = get_option('sbp_excluded_pages', array());
        if (is_string($excluded_pages)) {
            $excluded_pages = explode("\n", $excluded_pages);
        }
        
        $current_url = $_SERVER['REQUEST_URI'];
        
        foreach ($excluded_pages as $excluded_page) {
            $excluded_page = trim($excluded_page);
            if (!empty($excluded_page) && strpos($current_url, $excluded_page) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    public function regenerate_static_files($post_id = null) {
        if ($post_id) {
            $post_url = get_permalink($post_id);
            $this->clear_static_file_by_url($post_url);
        }
        
        // También limpiar página principal
        $this->clear_static_file_by_url(home_url());
    }
    
    public function regenerate_page_static($post_id = null) {
        if ($post_id) {
            $post_url = get_permalink($post_id);
            $this->clear_static_file_by_url($post_url);
        }
    }
    
    private function clear_static_file_by_url($url) {
        $parsed_url = parse_url($url);
        $path = $parsed_url['path'] ?? '/';
        $path = rtrim($path, '/');
        
        if (empty($path)) {
            $path = '/index';
        }
        
        $static_file = SBP_CACHE_DIR . ltrim($path, '/') . '/index.html';
        
        if (file_exists($static_file)) {
            unlink($static_file);
        }
        
        if (file_exists($static_file . '.gz')) {
            unlink($static_file . '.gz');
        }
    }
    
    public function add_cache_info() {
        if (!get_option('sbp_show_cache_info', true) || is_admin()) {
            return;
        }
        
        $static_file = $this->get_static_file_path();
        $is_static = file_exists($static_file);
        
        echo "\n<!-- StaticBoost Pro: " . ($is_static ? 'STATIC' : 'GENERATED') . " -->";
        echo "\n<!-- Generated: " . date('Y-m-d H:i:s') . " -->";
        echo "\n<!-- BoostAI: " . (get_option('sbp_boostai_enabled', true) ? 'ENABLED' : 'DISABLED') . " -->\n";
    }
}