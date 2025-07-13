<?php
/**
 * Generador de archivos estáticos ultra optimizado
 */
class FSC_Static_Generator {
    
    private $object_cache;
    
    public function __construct() {
        $this->object_cache = FSC_Object_Cache_Pro::instance();
        
        // Solo cargar si está habilitado
        if (!get_option('fsc_enabled', true)) {
            return;
        }
        
        // Hooks para generar archivos estáticos
        add_action('wp_loaded', array($this, 'start_buffering'), 1);
        add_action('shutdown', array($this, 'end_buffering'), 999);
        
        // Hooks para limpiar caché
        add_action('save_post', array($this, 'clear_post_cache'));
        add_action('comment_post', array($this, 'clear_post_cache'));
        add_action('wp_set_comment_status', array($this, 'clear_post_cache'));
    }
    
    /**
     * Iniciar buffering para capturar HTML
     */
    public function start_buffering() {
        if (!$this->should_generate_static()) {
            return;
        }
        
        ob_start(array($this, 'generate_static_file'));
    }
    
    /**
     * Finalizar buffering
     */
    public function end_buffering() {
        if (ob_get_level()) {
            ob_end_flush();
        }
    }
    
    /**
     * Verificar si debe generar archivo estático
     */
    private function should_generate_static() {
        // No generar en admin
        if (is_admin()) {
            return false;
        }
        
        // No generar para usuarios logueados
        if (is_user_logged_in()) {
            return false;
        }
        
        // Solo GET sin parámetros
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !empty($_GET)) {
            return false;
        }
        
        // Verificar páginas excluidas (usar caché)
        $cache_key = 'excluded_' . md5($_SERVER['REQUEST_URI']);
        $is_excluded = $this->object_cache->get($cache_key);
        
        if ($is_excluded === null) {
            $is_excluded = $this->is_page_excluded();
            $this->object_cache->set($cache_key, $is_excluded, 3600);
        }
        
        return !$is_excluded;
    }
    
    /**
     * Verificar si la página está excluida
     */
    private function is_page_excluded() {
        $excluded_pages = get_option('fsc_excluded_pages', array());
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
        
        // Verificar WooCommerce
        if (class_exists('WooCommerce')) {
            if (isset($_COOKIE['woocommerce_cart_hash']) || 
                isset($_COOKIE['woocommerce_items_in_cart'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * GENERAR ARCHIVO ESTÁTICO ULTRA OPTIMIZADO
     */
    public function generate_static_file($buffer) {
        if (!$this->should_save_buffer($buffer)) {
            return $buffer;
        }
        
        // Optimizar HTML de forma ultra agresiva
        $optimized_html = $this->optimize_html_ultra($buffer);
        
        // Obtener ruta del archivo
        $static_file = $this->get_static_file_path();
        $static_dir = dirname($static_file);
        
        if (!file_exists($static_dir)) {
            wp_mkdir_p($static_dir);
        }
        
        // Guardar archivo estático
        $result = file_put_contents($static_file, $optimized_html, LOCK_EX);
        
        if ($result) {
            // Crear versión comprimida
            if (function_exists('gzencode')) {
                file_put_contents($static_file . '.gz', gzencode($optimized_html, 9), LOCK_EX);
            }
            
            // Cachear metadatos del archivo
            $cache_key = 'file_meta_' . md5($static_file);
            $this->object_cache->set($cache_key, array(
                'size' => strlen($optimized_html),
                'created' => time(),
                'path' => $static_file
            ), 86400);
        }
        
        return $buffer;
    }
    
    /**
     * OPTIMIZACIÓN HTML ULTRA AGRESIVA
     */
    private function optimize_html_ultra($html) {
        // 1. Minificar HTML agresivamente
        $html = $this->minify_html_aggressive($html);
        
        // 2. Optimizar CSS inline
        $html = $this->optimize_inline_css($html);
        
        // 3. Optimizar JavaScript inline
        $html = $this->optimize_inline_js($html);
        
        // 4. Optimizar imágenes
        $html = $this->optimize_images($html);
        
        // 5. Preload recursos críticos
        $html = $this->add_preload_headers($html);
        
        // 6. Añadir información de caché
        $cache_info = sprintf(
            "\n<!-- Fast Static Cache Pro: %s | %s | Object Cache: %s -->",
            date('Y-m-d H:i:s'),
            'ULTRA-OPTIMIZED',
            strtoupper($this->object_cache->get_info()['type'])
        );
        $html .= $cache_info;
        
        return $html;
    }
    
    /**
     * Minificar HTML de forma agresiva
     */
    private function minify_html_aggressive($html) {
        // Eliminar comentarios HTML (excepto IE)
        $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html);
        
        // Eliminar espacios entre tags
        $html = preg_replace('/>\s+</', '><', $html);
        
        // Eliminar espacios al inicio y final de líneas
        $html = preg_replace('/^\s+/m', '', $html);
        $html = preg_replace('/\s+$/m', '', $html);
        
        // Eliminar líneas vacías
        $html = preg_replace('/\n\s*\n/', "\n", $html);
        
        // Eliminar espacios excesivos
        $html = preg_replace('/\s+/', ' ', $html);
        
        return trim($html);
    }
    
    /**
     * Optimizar CSS inline
     */
    private function optimize_inline_css($html) {
        $html = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/is', function($matches) {
            $css = $matches[1];
            
            // Minificar CSS
            $css = preg_replace('/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $css);
            $css = str_replace(array("\r\n", "\r", "\n", "\t", '  '), '', $css);
            $css = str_replace(array('; ', ' ;', ' {', '{ ', ' }', '} ', ': ', ' :'), 
                              array(';', ';', '{', '{', '}', '}', ':', ':'), $css);
            
            return '<style>' . trim($css) . '</style>';
        }, $html);
        
        return $html;
    }
    
    /**
     * Optimizar JavaScript inline
     */
    private function optimize_inline_js($html) {
        $html = preg_replace_callback('/<script[^>]*>(.*?)<\/script>/is', function($matches) {
            $js = $matches[1];
            
            // Minificar JS básico
            $js = preg_replace('/\/\/.*$/m', '', $js);
            $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
            $js = preg_replace('/\s+/', ' ', $js);
            
            return '<script>' . trim($js) . '</script>';
        }, $html);
        
        return $html;
    }
    
    /**
     * Optimizar imágenes
     */
    private function optimize_images($html) {
        // Añadir lazy loading a imágenes
        $html = preg_replace_callback('/<img([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i', function($matches) {
            $before = $matches[1];
            $src = $matches[2];
            $after = $matches[3];
            
            // No tocar imágenes críticas
            $critical_patterns = array('logo', 'icon', 'header', 'hero', 'banner');
            foreach ($critical_patterns as $pattern) {
                if (stripos($matches[0], $pattern) !== false) {
                    return $matches[0];
                }
            }
            
            // Añadir lazy loading
            if (strpos($after, 'loading=') === false) {
                $after .= ' loading="lazy"';
            }
            
            if (strpos($after, 'decoding=') === false) {
                $after .= ' decoding="async"';
            }
            
            return '<img' . $before . 'src="' . $src . '"' . $after . '>';
        }, $html);
        
        return $html;
    }
    
    /**
     * Añadir headers de preload
     */
    private function add_preload_headers($html) {
        $preload_headers = '';
        
        // Preconnect a dominios externos
        $preload_headers .= '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        $preload_headers .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        
        // DNS prefetch
        $preload_headers .= '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        $preload_headers .= '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        
        $html = str_replace('</head>', $preload_headers . '</head>', $html);
        
        return $html;
    }
    
    /**
     * Verificar si debe guardar el buffer
     */
    private function should_save_buffer($buffer) {
        // Buffer vacío
        if (empty(trim($buffer))) {
            return false;
        }
        
        // No es HTML válido
        if (strpos($buffer, '<html') === false && strpos($buffer, '<!DOCTYPE') === false) {
            return false;
        }
        
        // Hay errores PHP
        if (strpos($buffer, 'Fatal error') !== false || 
            strpos($buffer, 'Parse error') !== false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtener ruta del archivo estático
     */
    private function get_static_file_path() {
        $request_uri = $_SERVER['REQUEST_URI'];
        $request_uri = rtrim($request_uri, '/');
        
        if (empty($request_uri)) {
            $request_uri = '/index';
        }
        
        return FSC_CACHE_DIR . ltrim($request_uri, '/') . '/index.html';
    }
    
    /**
     * Limpiar caché de un post
     */
    public function clear_post_cache($post_id = null) {
        if ($post_id) {
            $post_url = get_permalink($post_id);
            $this->clear_static_file_by_url($post_url);
        }
        
        // También limpiar página principal
        $this->clear_static_file_by_url(home_url());
    }
    
    /**
     * Limpiar archivo estático por URL
     */
    private function clear_static_file_by_url($url) {
        $parsed_url = parse_url($url);
        $path = $parsed_url['path'] ?? '/';
        $path = rtrim($path, '/');
        
        if (empty($path)) {
            $path = '/index';
        }
        
        $static_file = FSC_CACHE_DIR . ltrim($path, '/') . '/index.html';
        
        if (file_exists($static_file)) {
            unlink($static_file);
        }
        
        if (file_exists($static_file . '.gz')) {
            unlink($static_file . '.gz');
        }
        
        // Limpiar caché de metadatos
        $cache_key = 'file_meta_' . md5($static_file);
        $this->object_cache->delete($cache_key);
        
        $cache_key = 'excluded_' . md5($path);
        $this->object_cache->delete($cache_key);
    }
}