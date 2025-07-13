<?php
/**
 * Generador de archivos estáticos CONSERVADOR
 * Mantiene la apariencia exacta del sitio - Compatible con Elementor
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
        
        // Hooks específicos para Elementor
        add_action('elementor/editor/after_save', array($this, 'clear_elementor_cache'));
        add_action('elementor/core/files/clear_cache', array($this, 'clear_elementor_cache'));
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
     * GENERAR ARCHIVO ESTÁTICO CONSERVADOR
     * NO modifica el HTML - Solo lo guarda tal como está
     */
    public function generate_static_file($buffer) {
        if (!$this->should_save_buffer($buffer)) {
            return $buffer;
        }
        
        // MODO CONSERVADOR - Solo optimizaciones mínimas que NO afecten la apariencia
        $optimized_html = $this->optimize_html_conservatively($buffer);
        
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
     * OPTIMIZACIÓN CONSERVADORA - NO MODIFICA LA APARIENCIA
     * Solo optimizaciones que NO rompan Elementor, Divi, etc.
     */
    private function optimize_html_conservatively($html) {
        // Solo aplicar si el modo conservador está habilitado
        if (!get_option('fsc_conservative_mode', true)) {
            return $html; // Devolver HTML sin modificar
        }
        
        // OPTIMIZACIONES MÍNIMAS Y SEGURAS:
        
        // 1. Solo eliminar comentarios HTML que NO sean de IE o Elementor
        $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>|elementor|divi))(?:(?!-->).)*-->/s', '', $html);
        
        // 2. NO tocar espacios entre tags - puede romper CSS
        // 3. NO minificar CSS inline - puede romper Elementor
        // 4. NO tocar JavaScript - puede romper funcionalidad
        // 5. NO modificar imágenes - puede romper lazy loading de temas
        
        // Solo añadir información de caché al final
        $cache_info = sprintf(
            "\n<!-- Fast Static Cache Pro: %s | CONSERVATIVE MODE | Object Cache: %s -->",
            date('Y-m-d H:i:s'),
            strtoupper($this->object_cache->get_info()['type'])
        );
        $html .= $cache_info;
        
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
        
        // Verificar que no sea una respuesta AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return false;
        }
        
        // Verificar que no sea REST API
        if (defined('REST_REQUEST') && REST_REQUEST) {
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
     * Limpiar caché específico de Elementor
     */
    public function clear_elementor_cache($post_id = null) {
        // Limpiar caché del post específico
        if ($post_id) {
            $post_url = get_permalink($post_id);
            $this->clear_static_file_by_url($post_url);
        }
        
        // Limpiar página principal (puede tener widgets globales)
        $this->clear_static_file_by_url(home_url());
        
        // Limpiar todas las páginas si es un template global
        if (get_post_type($post_id) === 'elementor_library') {
            $this->clear_all_cache();
        }
    }
    
    /**
     * Limpiar todo el caché
     */
    public function clear_all_cache() {
        if (is_dir(FSC_CACHE_DIR)) {
            $this->delete_directory_contents(FSC_CACHE_DIR);
        }
        
        // Limpiar object cache también
        $this->object_cache->flush();
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
    
    /**
     * Eliminar contenido de directorio
     */
    private function delete_directory_contents($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..', '.htaccess'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return true;
    }
    
    /**
     * Eliminar directorio
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
}