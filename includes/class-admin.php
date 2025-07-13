<?php
/**
 * Panel de administración ultra optimizado
 */
class FSC_Admin {
    
    private $object_cache;
    
    public function __construct() {
        $this->object_cache = FSC_Object_Cache_Pro::instance();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_fsc_generate_all_pages', array($this, 'ajax_generate_all_pages'));
        add_action('wp_ajax_fsc_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_fsc_test_object_cache', array($this, 'ajax_test_object_cache'));
        add_action('wp_ajax_fsc_flush_object_cache', array($this, 'ajax_flush_object_cache'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Fast Static Cache Pro',
            'Fast Static Cache Pro',
            'manage_options',
            'fast-static-cache',
            array($this, 'admin_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_fast-static-cache') {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            // Generar todas las páginas
            $("#fsc-generate-all").on("click", function() {
                var button = $(this);
                var originalText = button.text();
                
                if (!confirm("¿Generar TODAS las páginas estáticas? Esto puede tomar varios minutos.")) {
                    return;
                }
                
                button.text("Generando...").prop("disabled", true);
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "fsc_generate_all_pages",
                        nonce: "' . wp_create_nonce('fsc_admin_nonce') . '"
                    },
                    timeout: 600000,
                    success: function(response) {
                        if (response.success) {
                            alert("✅ Generación exitosa: " + response.data.success + "/" + response.data.total + " páginas");
                            location.reload();
                        } else {
                            alert("❌ Error: " + response.data);
                        }
                    },
                    error: function() {
                        alert("❌ Error de conexión");
                    },
                    complete: function() {
                        button.text(originalText).prop("disabled", false);
                    }
                });
            });
            
            // Limpiar caché
            $("#fsc-clear-cache").on("click", function() {
                var button = $(this);
                var originalText = button.text();
                
                if (!confirm("¿Limpiar todos los archivos estáticos?")) {
                    return;
                }
                
                button.text("Limpiando...").prop("disabled", true);
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "fsc_clear_cache",
                        nonce: "' . wp_create_nonce('fsc_admin_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            alert("✅ Caché limpiado exitosamente");
                            location.reload();
                        } else {
                            alert("❌ Error: " + response.data);
                        }
                    },
                    error: function() {
                        alert("❌ Error de conexión");
                    },
                    complete: function() {
                        button.text(originalText).prop("disabled", false);
                    }
                });
            });
            
            // Test Object Cache
            $("#fsc-test-cache").on("click", function() {
                var button = $(this);
                var originalText = button.text();
                
                button.text("Probando...").prop("disabled", true);
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "fsc_test_object_cache",
                        nonce: "' . wp_create_nonce('fsc_admin_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            alert("✅ Test exitoso\\n" +
                                  "Tipo: " + data.cache_type.toUpperCase() + "\\n" +
                                  "Conectado: " + (data.connected ? "Sí" : "No") + "\\n" +
                                  "Set: " + (data.set_success ? "OK" : "FAIL") + "\\n" +
                                  "Get: " + (data.get_success ? "OK" : "FAIL") + "\\n" +
                                  "Delete: " + (data.delete_success ? "OK" : "FAIL"));
                        } else {
                            alert("❌ Error: " + response.data);
                        }
                    },
                    error: function() {
                        alert("❌ Error de conexión");
                    },
                    complete: function() {
                        button.text(originalText).prop("disabled", false);
                    }
                });
            });
            
            // Flush Object Cache
            $("#fsc-flush-cache").on("click", function() {
                var button = $(this);
                var originalText = button.text();
                
                if (!confirm("¿Limpiar Object Cache?")) {
                    return;
                }
                
                button.text("Limpiando...").prop("disabled", true);
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "fsc_flush_object_cache",
                        nonce: "' . wp_create_nonce('fsc_admin_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            alert("✅ Object Cache limpiado exitosamente");
                        } else {
                            alert("❌ Error: " + response.data);
                        }
                    },
                    error: function() {
                        alert("❌ Error de conexión");
                    },
                    complete: function() {
                        button.text(originalText).prop("disabled", false);
                    }
                });
            });
        });
        ');
        
        wp_add_inline_style('wp-admin', $this->get_admin_css());
    }
    
    public function register_settings() {
        register_setting('fsc_settings', 'fsc_enabled');
        register_setting('fsc_settings', 'fsc_cache_lifetime');
        register_setting('fsc_settings', 'fsc_excluded_pages');
    }
    
    public function admin_page() {
        $stats = $this->get_cache_stats();
        $cache_info = $this->object_cache->get_info();
        ?>
        <div class="wrap">
            <h1>
                <span class="fsc-logo">⚡</span>
                Fast Static Cache Pro
                <span class="fsc-version">v<?php echo FSC_VERSION; ?></span>
                <span class="fsc-mode">ULTRA OPTIMIZADO</span>
            </h1>
            
            <!-- Estado del sistema -->
            <div class="fsc-status-card">
                <h2>Estado del Sistema</h2>
                
                <div class="fsc-status-grid">
                    <div class="fsc-status-item">
                        <span class="fsc-status-label">Caché Estático:</span>
                        <span class="fsc-status-value <?php echo get_option('fsc_enabled', true) ? 'active' : 'inactive'; ?>">
                            <?php echo get_option('fsc_enabled', true) ? '🟢 ACTIVO' : '🔴 INACTIVO'; ?>
                        </span>
                    </div>
                    
                    <div class="fsc-status-item">
                        <span class="fsc-status-label">Object Cache:</span>
                        <span class="fsc-status-value <?php echo $cache_info['connected'] ? 'active' : 'inactive'; ?>">
                            <?php echo strtoupper($cache_info['type']); ?> 
                            <?php echo $cache_info['connected'] ? '🟢' : '🔴'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas -->
            <div class="fsc-stats-grid">
                <div class="fsc-stat-card">
                    <div class="fsc-stat-icon">📄</div>
                    <div class="fsc-stat-content">
                        <div class="fsc-stat-number"><?php echo $stats['files']; ?></div>
                        <div class="fsc-stat-label">Páginas Estáticas</div>
                    </div>
                </div>
                
                <div class="fsc-stat-card">
                    <div class="fsc-stat-icon">💾</div>
                    <div class="fsc-stat-content">
                        <div class="fsc-stat-number"><?php echo size_format($stats['size']); ?></div>
                        <div class="fsc-stat-label">Espacio Usado</div>
                    </div>
                </div>
                
                <div class="fsc-stat-card">
                    <div class="fsc-stat-icon">🗄️</div>
                    <div class="fsc-stat-content">
                        <div class="fsc-stat-number"><?php echo strtoupper($cache_info['type']); ?></div>
                        <div class="fsc-stat-label">Object Cache</div>
                    </div>
                </div>
                
                <div class="fsc-stat-card">
                    <div class="fsc-stat-icon">⚡</div>
                    <div class="fsc-stat-content">
                        <div class="fsc-stat-number">ULTRA</div>
                        <div class="fsc-stat-label">Velocidad</div>
                    </div>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="fsc-actions-grid">
                <button id="fsc-generate-all" class="fsc-btn fsc-btn-primary">
                    🚀 Generar Todas las Páginas
                </button>
                
                <button id="fsc-clear-cache" class="fsc-btn fsc-btn-danger">
                    🗑️ Limpiar Caché Estático
                </button>
                
                <button id="fsc-test-cache" class="fsc-btn fsc-btn-info">
                    🧪 Test Object Cache
                </button>
                
                <button id="fsc-flush-cache" class="fsc-btn fsc-btn-warning">
                    🔄 Flush Object Cache
                </button>
            </div>
            
            <!-- Configuración -->
            <form method="post" action="options.php">
                <?php settings_fields('fsc_settings'); ?>
                
                <div class="fsc-config-section">
                    <h3>Configuración</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Habilitar Caché</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="fsc_enabled" value="1" <?php checked(get_option('fsc_enabled', true)); ?> />
                                    Activar caché estático ultra optimizado
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Tiempo de Vida</th>
                            <td>
                                <input type="number" name="fsc_cache_lifetime" value="<?php echo get_option('fsc_cache_lifetime', 3600); ?>" min="300" max="86400" />
                                <p class="description">Segundos antes de regenerar (300-86400)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Páginas Excluidas</th>
                            <td>
                                <textarea name="fsc_excluded_pages" rows="5" cols="50"><?php echo esc_textarea(implode("\n", (array)get_option('fsc_excluded_pages', array('/cart', '/checkout', '/my-account')))); ?></textarea>
                                <p class="description">Una URL por línea</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button('Guardar Configuración'); ?>
            </form>
            
            <!-- Información técnica -->
            <div class="fsc-info-section">
                <h3>Información Técnica</h3>
                <ul>
                    <li><strong>Modo:</strong> Ultra Optimizado</li>
                    <li><strong>Servir archivos:</strong> Directamente sin cargar WordPress</li>
                    <li><strong>Object Cache:</strong> <?php echo strtoupper($cache_info['type']); ?> (<?php echo $cache_info['connected'] ? 'Conectado' : 'Desconectado'; ?>)</li>
                    <li><strong>Compresión:</strong> Gzip automático</li>
                    <li><strong>Headers:</strong> Ultra optimizados para velocidad</li>
                    <li><strong>Minificación:</strong> HTML, CSS, JS inline</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    // AJAX Handlers
    public function ajax_generate_all_pages() {
        check_ajax_referer('fsc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $results = $this->generate_all_static_pages();
        wp_send_json_success($results);
    }
    
    public function ajax_clear_cache() {
        check_ajax_referer('fsc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $result = fsc_delete_directory_contents(FSC_CACHE_DIR);
        
        if ($result) {
            wp_send_json_success('Caché limpiado exitosamente');
        } else {
            wp_send_json_error('Error al limpiar caché');
        }
    }
    
    public function ajax_test_object_cache() {
        check_ajax_referer('fsc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $test_key = 'fsc_test_' . time();
        $test_value = 'Test value: ' . wp_generate_password(10, false);
        
        $set_result = $this->object_cache->set($test_key, $test_value, 60);
        $get_result = $this->object_cache->get($test_key);
        $delete_result = $this->object_cache->delete($test_key);
        
        $cache_info = $this->object_cache->get_info();
        
        wp_send_json_success(array(
            'cache_type' => $cache_info['type'],
            'connected' => $cache_info['connected'],
            'set_success' => $set_result,
            'get_success' => $get_result === $test_value,
            'delete_success' => $delete_result,
            'test_value' => $test_value,
            'retrieved_value' => $get_result
        ));
    }
    
    public function ajax_flush_object_cache() {
        check_ajax_referer('fsc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $result = $this->object_cache->flush();
        
        if ($result) {
            wp_send_json_success('Object Cache limpiado exitosamente');
        } else {
            wp_send_json_error('Error al limpiar Object Cache');
        }
    }
    
    /**
     * Generar todas las páginas estáticas
     */
    private function generate_all_static_pages() {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        
        $results = array(
            'total' => 0,
            'success' => 0,
            'errors' => 0
        );
        
        $urls = $this->get_all_site_urls();
        $results['total'] = count($urls);
        
        foreach ($urls as $url) {
            $success = $this->generate_static_file_for_url($url);
            
            if ($success) {
                $results['success']++;
            } else {
                $results['errors']++;
            }
            
            usleep(100000); // 0.1 segundos entre requests
        }
        
        return $results;
    }
    
    /**
     * Obtener todas las URLs del sitio
     */
    private function get_all_site_urls() {
        global $wpdb;
        
        $urls = array();
        
        // Página principal
        $urls[] = home_url();
        
        // Páginas
        $pages = $wpdb->get_results("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'page' 
            AND post_status = 'publish'
            ORDER BY menu_order, post_date DESC
            LIMIT 50
        ");
        
        foreach ($pages as $page) {
            $permalink = get_permalink($page->ID);
            if ($permalink) {
                $urls[] = $permalink;
            }
        }
        
        // Posts
        $posts = $wpdb->get_results("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'post' 
            AND post_status = 'publish'
            ORDER BY post_date DESC
            LIMIT 50
        ");
        
        foreach ($posts as $post) {
            $permalink = get_permalink($post->ID);
            if ($permalink) {
                $urls[] = $permalink;
            }
        }
        
        return array_unique($urls);
    }
    
    /**
     * Generar archivo estático para una URL
     */
    private function generate_static_file_for_url($url) {
        $args = array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'Fast-Static-Cache-Pro/3.0'
            ),
            'cookies' => array(),
            'sslverify' => false
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }
        
        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return false;
        }
        
        // Guardar archivo estático
        $parsed_url = parse_url($url);
        $path = $parsed_url['path'] ?? '/';
        $path = rtrim($path, '/');
        
        if (empty($path)) {
            $path = '/index';
        }
        
        $static_file = FSC_CACHE_DIR . ltrim($path, '/') . '/index.html';
        $static_dir = dirname($static_file);
        
        if (!file_exists($static_dir)) {
            wp_mkdir_p($static_dir);
        }
        
        $result = file_put_contents($static_file, $html, LOCK_EX);
        
        if ($result && function_exists('gzencode')) {
            file_put_contents($static_file . '.gz', gzencode($html, 9), LOCK_EX);
        }
        
        return $result !== false;
    }
    
    /**
     * Obtener estadísticas del caché
     */
    private function get_cache_stats() {
        $stats = array(
            'files' => 0,
            'size' => 0
        );
        
        if (!is_dir(FSC_CACHE_DIR)) {
            return $stats;
        }
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(FSC_CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->getFilename() === 'index.html') {
                    $stats['files']++;
                    $stats['size'] += $file->getSize();
                }
            }
        } catch (Exception $e) {
            // Silencioso
        }
        
        return $stats;
    }
    
    private function get_admin_css() {
        return '
        .fsc-logo { font-size: 24px; }
        .fsc-version { 
            background: #0073aa; 
            color: white; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 12px; 
            margin-left: 10px; 
        }
        .fsc-mode { 
            background: #d63638; 
            color: white; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 12px; 
            margin-left: 5px; 
            font-weight: bold;
        }
        
        .fsc-status-card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .fsc-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .fsc-status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .fsc-status-label {
            font-weight: 500;
        }
        
        .fsc-status-value.active {
            color: #00a32a;
            font-weight: bold;
        }
        
        .fsc-status-value.inactive {
            color: #d63638;
            font-weight: bold;
        }
        
        .fsc-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .fsc-stat-card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .fsc-stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .fsc-stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .fsc-stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .fsc-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .fsc-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .fsc-btn-primary { background: #0073aa; color: white; }
        .fsc-btn-primary:hover { background: #005a87; }
        
        .fsc-btn-danger { background: #d63638; color: white; }
        .fsc-btn-danger:hover { background: #b32d2e; }
        
        .fsc-btn-info { background: #0073aa; color: white; }
        .fsc-btn-info:hover { background: #005a87; }
        
        .fsc-btn-warning { background: #f56e28; color: white; }
        .fsc-btn-warning:hover { background: #e65100; }
        
        .fsc-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .fsc-config-section {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .fsc-info-section {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .fsc-info-section ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .fsc-info-section li {
            margin-bottom: 5px;
        }
        ';
    }
}