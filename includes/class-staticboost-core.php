<?php
/**
 * Clase principal de StaticBoost Pro - Versión Conservadora
 */
class StaticBoost_Core {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_loaded', array($this, 'start_buffering'));
        add_action('shutdown', array($this, 'end_buffering'));
        
        // Regenerar cuando se actualiza contenido
        add_action('save_post', array($this, 'regenerate_static_files'));
        add_action('comment_post', array($this, 'regenerate_page_static'));
        add_action('wp_set_comment_status', array($this, 'regenerate_page_static'));
        
        // Servir estático antes de WordPress
        add_action('template_redirect', array($this, 'serve_static_if_exists'), 1);
        
        // Información de caché
        add_action('wp_footer', array($this, 'add_cache_info'), 999);
        
        // Filtros para optimización
        add_filter('sbp_should_cache_page', array($this, 'should_cache_current_page'), 10, 2);
        
        // CDN Local - Servir assets optimizados
        add_action('init', array($this, 'setup_local_cdn'));
    }
    
    public function init() {
        load_plugin_textdomain('staticboost-pro', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Configurar CDN Local para servir assets optimizados
     */
    public function setup_local_cdn() {
        // Interceptar requests a assets optimizados
        if (isset($_GET['sbp_asset'])) {
            $this->serve_optimized_asset();
            exit;
        }
        
        // Rewrite rules para assets optimizados
        add_rewrite_rule(
            '^sbp-assets/(.+)$',
            'index.php?sbp_asset=$matches[1]',
            'top'
        );
        
        add_rewrite_tag('%sbp_asset%', '([^&]+)');
    }
    
    /**
     * Servir asset optimizado con headers de CDN
     */
    private function serve_optimized_asset() {
        $asset_path = sanitize_text_field($_GET['sbp_asset']);
        $asset_file = SBP_CACHE_DIR . 'assets/' . $asset_path;
        
        if (!file_exists($asset_file) || strpos($asset_path, '..') !== false) {
            status_header(404);
            exit;
        }
        
        $mime_type = $this->get_mime_type($asset_file);
        $etag = md5_file($asset_file);
        $last_modified = filemtime($asset_file);
        
        // Headers de CDN optimizados
        header('Content-Type: ' . $mime_type);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        header('ETag: "' . $etag . '"');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
        header('Vary: Accept-Encoding');
        header('X-StaticBoost: CDN-LOCAL');
        
        // Verificar If-None-Match
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && 
            trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
            status_header(304);
            exit;
        }
        
        // Verificar If-Modified-Since
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && 
            strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $last_modified) {
            status_header(304);
            exit;
        }
        
        // Servir comprimido si está disponible
        $compressed_file = $asset_file . '.gz';
        if (file_exists($compressed_file) && $this->client_accepts_gzip()) {
            header('Content-Encoding: gzip');
            header('Content-Length: ' . filesize($compressed_file));
            readfile($compressed_file);
        } else {
            header('Content-Length: ' . filesize($asset_file));
            readfile($asset_file);
        }
        
        exit;
    }
    
    /**
     * Obtener MIME type del archivo
     */
    private function get_mime_type($file) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        $mime_types = array(
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        );
        
        return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
    }
    
    /**
     * Servir archivo estático si existe
     */
    public function serve_static_if_exists() {
        if (!$this->should_serve_static()) {
            return;
        }
        
        $static_file = $this->get_static_file_path();
        
        if ($this->is_static_file_valid($static_file)) {
            $this->serve_static_file($static_file);
            exit;
        }
    }
    
    /**
     * Verificar si debe servir archivo estático
     */
    private function should_serve_static() {
        // No servir si está deshabilitado
        if (!get_option('sbp_enabled', true)) {
            return false;
        }
        
        // No servir para usuarios logueados
        if (is_user_logged_in()) {
            return false;
        }
        
        // No servir en admin
        if (is_admin()) {
            return false;
        }
        
        // Solo GET requests
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return false;
        }
        
        // No servir si hay parámetros GET
        if (!empty($_GET)) {
            return false;
        }
        
        // Verificar exclusiones
        if ($this->is_page_excluded()) {
            return false;
        }
        
        // Verificar compatibilidad WooCommerce
        if (class_exists('WooCommerce')) {
            if (is_cart() || is_checkout() || is_account_page() || is_product()) {
                return false;
            }
            
            // Verificar cookies de WooCommerce
            if (isset($_COOKIE['woocommerce_cart_hash']) || 
                isset($_COOKIE['woocommerce_items_in_cart'])) {
                return false;
            }
        }
        
        return true;
    }
    
    private function serve_static_file($static_file) {
        // Headers optimizados para máximo rendimiento
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Static-Cache: HIT');
        header('X-Cache-Status: STATIC');
        header('X-StaticBoost: ACTIVE');
        header('Cache-Control: public, max-age=3600');
        header('Vary: Accept-Encoding');
        
        // Servir versión comprimida si el cliente la acepta
        $compressed_file = $static_file . '.gz';
        
        if (file_exists($compressed_file) && $this->client_accepts_gzip()) {
            header('Content-Encoding: gzip');
            header('Content-Length: ' . filesize($compressed_file));
            readfile($compressed_file);
        } else {
            header('Content-Length: ' . filesize($static_file));
            readfile($static_file);
        }
        
        exit;
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
     * Verificar si debe generar archivo estático
     */
    private function should_generate_static() {
        // Aplicar filtro personalizable
        $should_cache = apply_filters('sbp_should_cache_page', true, $_SERVER['REQUEST_URI']);
        
        if (!$should_cache) {
            return false;
        }
        
        return $this->should_serve_static();
    }
    
    /**
     * Filtro para verificar si la página actual debe ser cacheada
     */
    public function should_cache_current_page($should_cache, $url) {
        // No cachear páginas especiales
        if (is_404() || is_feed() || is_robots() || is_trackback()) {
            return false;
        }
        
        // No cachear búsquedas
        if (is_search()) {
            return false;
        }
        
        // No cachear páginas con formularios
        if (is_page() && $this->page_has_forms()) {
            return false;
        }
        
        return $should_cache;
    }
    
    /**
     * Verificar si la página tiene formularios
     */
    private function page_has_forms() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Buscar formularios comunes en el contenido
        $form_patterns = array(
            '<form',
            'contact-form',
            'wpcf7-form',
            'gform_wrapper',
            'ninja-forms'
        );
        
        foreach ($form_patterns as $pattern) {
            if (strpos($post->post_content, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generar archivo estático - CONSERVADOR (NO MODIFICA APARIENCIA)
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
            // Crear versión comprimida
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
        // 1. SOLO optimizaciones que NO afecten la apariencia
        
        // Añadir meta tags de rendimiento (invisible al usuario)
        $performance_meta = '
        <meta name="generator" content="StaticBoost Pro">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        ';
        
        $html = str_replace('</head>', $performance_meta . '</head>', $html);
        
        // 2. Optimizar URLs de assets para CDN local (mantiene funcionalidad)
        $html = $this->optimize_asset_urls_for_local_cdn($html);
        
        // 3. Añadir lazy loading SOLO a imágenes de contenido (no logos/iconos)
        $html = $this->add_safe_lazy_loading($html);
        
        // 4. Optimizar espacios en blanco (invisible al usuario)
        $html = $this->optimize_whitespace_safely($html);
        
        return $html;
    }
    
    /**
     * Optimizar URLs de assets para CDN local
     */
    private function optimize_asset_urls_for_local_cdn($html) {
        $site_url = get_site_url();
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl'];
        
        // Solo optimizar imágenes de uploads (no del tema)
        $html = preg_replace_callback(
            '/src=["\'](' . preg_quote($upload_url, '/') . '[^"\']+\.(jpg|jpeg|png|gif|webp))["\']/',
            function($matches) {
                $original_url = $matches[1];
                $optimized_url = $this->get_optimized_asset_url($original_url);
                return 'src="' . $optimized_url . '"';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Obtener URL optimizada para asset
     */
    private function get_optimized_asset_url($original_url) {
        // Si existe versión WebP optimizada, usarla
        $path_info = pathinfo($original_url);
        $filename = $path_info['filename'];
        $extension = $path_info['extension'];
        
        // Verificar si existe versión WebP
        $webp_file = SBP_ASSETS_DIR . 'images/' . $filename . '.webp';
        if (file_exists($webp_file)) {
            return site_url('sbp-assets/images/' . $filename . '.webp');
        }
        
        return $original_url; // Mantener original si no hay optimizada
    }
    
    /**
     * Lazy loading SEGURO - Solo para imágenes de contenido
     */
    private function add_safe_lazy_loading($html) {
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
        
        // NO tocar estas imágenes críticas:
        $critical_patterns = array(
            'logo', 'icon', 'header', 'nav', 'menu', 'brand',
            'avatar', 'profile', 'admin', 'wp-content/themes'
        );
        
        foreach ($critical_patterns as $pattern) {
            if (stripos($full_tag, $pattern) !== false || 
                stripos($src, $pattern) !== false) {
                return $matches[0]; // Devolver sin modificar
            }
        }
        
        // Verificar si es imagen pequeña (probablemente icono)
        if (preg_match('/width=["\']?(\d+)["\']?/i', $full_tag, $width_match)) {
            if (isset($width_match[1]) && $width_match[1] < 100) {
                return $matches[0]; // No tocar imágenes pequeñas
            }
        }
        
        // Solo aplicar lazy loading a imágenes de contenido grandes
        if (strpos($after_src, 'loading=') === false) {
            $after_src .= ' loading="lazy"';
        }
        
        if (strpos($after_src, 'decoding=') === false) {
            $after_src .= ' decoding="async"';
        }
        
        return '<img' . $before_src . 'src="' . $src . '"' . $after_src . '>';
    }
    
    /**
     * Optimizar espacios en blanco de forma segura
     */
    private function optimize_whitespace_safely($html) {
        // Solo eliminar espacios excesivos entre tags
        $html = preg_replace('/>\s+</', '><', $html);
        
        // Eliminar espacios al inicio y final de líneas
        $html = preg_replace('/^\s+/m', '', $html);
        $html = preg_replace('/\s+$/m', '', $html);
        
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
        $request_uri = $_SERVER['REQUEST_URI'];
        $request_uri = rtrim($request_uri, '/');
        
        if (empty($request_uri)) {
            $request_uri = '/index';
        }
        
        return SBP_CACHE_DIR . ltrim($request_uri, '/') . '/index.html';
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
        
        // Verificar user agents excluidos
        $excluded_user_agents = get_option('sbp_excluded_user_agents', array('bot', 'crawler', 'spider'));
        if (is_string($excluded_user_agents)) {
            $excluded_user_agents = explode("\n", $excluded_user_agents);
        }
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        foreach ($excluded_user_agents as $excluded_ua) {
            $excluded_ua = trim($excluded_ua);
            if (!empty($excluded_ua) && stripos($user_agent, $excluded_ua) !== false) {
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
        
        // Limpiar páginas relacionadas si es un post
        if ($post_id) {
            $post = get_post($post_id);
            if ($post && $post->post_type === 'post') {
                // Limpiar archivo de categorías
                $categories = get_the_category($post_id);
                foreach ($categories as $category) {
                    $this->clear_static_file_by_url(get_category_link($category->term_id));
                }
                
                // Limpiar archivo de tags
                $tags = get_the_tags($post_id);
                if ($tags) {
                    foreach ($tags as $tag) {
                        $this->clear_static_file_by_url(get_tag_link($tag->term_id));
                    }
                }
            }
        }
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
        if (!get_option('sbp_show_cache_info', true) || is_admin() || is_user_logged_in()) {
            return;
        }
        
        $static_file = $this->get_static_file_path();
        $is_static = file_exists($static_file);
        
        echo "\n<!-- StaticBoost Pro: " . ($is_static ? 'STATIC' : 'GENERATED') . " -->";
        echo "\n<!-- Generated: " . date('Y-m-d H:i:s') . " -->";
        echo "\n<!-- BoostAI: " . (get_option('sbp_boostai_enabled', true) ? 'ENABLED' : 'DISABLED') . " -->\n";
    }
}