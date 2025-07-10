<?php
/**
 * Clase de administración para StaticBoost Pro - VERSIÓN SIMPLIFICADA
 */
class SBP_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers básicos
        add_action('wp_ajax_sbp_toggle_cache', array($this, 'ajax_toggle_cache'));
        add_action('wp_ajax_sbp_generate_all_pages', array($this, 'ajax_generate_all_pages'));
        add_action('wp_ajax_sbp_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_sbp_get_stats', array($this, 'ajax_get_stats'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'StaticBoost Pro',
            'StaticBoost Pro',
            'manage_options',
            'staticboost-pro',
            array($this, 'admin_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_staticboost-pro') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'sbp-admin',
            SBP_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            SBP_VERSION,
            true
        );
        
        wp_localize_script('sbp-admin', 'sbp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sbp_admin_nonce')
        ));
        
        // CSS inline para el admin
        wp_add_inline_style('wp-admin', $this->get_admin_css());
    }
    
    public function register_settings() {
        // Configuraciones básicas
        register_setting('sbp_settings', 'sbp_enabled');
        register_setting('sbp_settings', 'sbp_cache_lifetime');
        register_setting('sbp_settings', 'sbp_excluded_pages');
        register_setting('sbp_settings', 'sbp_show_cache_info');
        register_setting('sbp_settings', 'sbp_minify_html');
    }
    
    public function admin_page() {
        $stats = sbp_get_cache_stats();
        $total_pages = sbp_get_total_pages_count();
        ?>
        <div class="wrap">
            <h1>
                <span class="sbp-logo">🚀</span>
                StaticBoost Pro
                <span class="sbp-version">v<?php echo SBP_VERSION; ?></span>
                <span class="sbp-mode">MODO BÁSICO</span>
            </h1>
            
            <div class="notice notice-info">
                <p><strong>🔧 Modo de Emergencia Activado:</strong> Solo funciones básicas habilitadas para máximo rendimiento. El sitio debería cargar mucho más rápido ahora.</p>
            </div>
            
            <!-- Estado del sistema -->
            <div class="sbp-status-card">
                <div class="sbp-status-header">
                    <h2>Estado del Sistema</h2>
                    <div class="sbp-toggle-container">
                        <label class="sbp-toggle">
                            <input type="checkbox" id="sbp-toggle-cache" <?php checked(get_option('sbp_enabled', true)); ?>>
                            <span class="sbp-toggle-slider"></span>
                        </label>
                        <span class="sbp-status-text <?php echo get_option('sbp_enabled', true) ? 'active' : 'inactive'; ?>">
                            <?php echo get_option('sbp_enabled', true) ? '🟢 Activo' : '🔴 Inactivo'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas -->
            <div class="sbp-stats-grid">
                <div class="sbp-stat-card">
                    <div class="sbp-stat-icon">📄</div>
                    <div class="sbp-stat-content">
                        <div class="sbp-stat-number"><?php echo $stats['files']; ?></div>
                        <div class="sbp-stat-label">Páginas Estáticas</div>
                    </div>
                </div>
                
                <div class="sbp-stat-card">
                    <div class="sbp-stat-icon">⚡</div>
                    <div class="sbp-stat-content">
                        <div class="sbp-stat-number"><?php echo $total_pages['total']; ?></div>
                        <div class="sbp-stat-label">Total Páginas</div>
                    </div>
                </div>
                
                <div class="sbp-stat-card">
                    <div class="sbp-stat-icon">🔧</div>
                    <div class="sbp-stat-content">
                        <div class="sbp-stat-number">BÁSICO</div>
                        <div class="sbp-stat-label">Modo Actual</div>
                    </div>
                </div>
                
                <div class="sbp-stat-card">
                    <div class="sbp-stat-icon">💾</div>
                    <div class="sbp-stat-content">
                        <div class="sbp-stat-number"><?php echo size_format($stats['size']); ?></div>
                        <div class="sbp-stat-label">Espacio Usado</div>
                    </div>
                </div>
            </div>
            
            <!-- Acciones principales -->
            <div class="sbp-actions-grid">
                <button id="sbp-generate-all" class="sbp-btn sbp-btn-primary">
                    <span class="sbp-btn-icon">🚀</span>
                    Generar Páginas Estáticas
                </button>
                
                <button id="sbp-clear-cache" class="sbp-btn sbp-btn-danger">
                    <span class="sbp-btn-icon">🗑️</span>
                    Limpiar Caché
                </button>
            </div>
            
            <!-- Progreso de generación -->
            <div id="sbp-generation-progress" class="sbp-progress-container" style="display: none;">
                <div class="sbp-progress-header">
                    <h3>Generando Páginas Estáticas</h3>
                </div>
                <div class="sbp-progress-bar">
                    <div class="sbp-progress-fill"></div>
                </div>
                <div id="sbp-generation-status" class="sbp-progress-status">Iniciando...</div>
            </div>
            
            <!-- Configuraciones -->
            <form method="post" action="options.php">
                <?php settings_fields('sbp_settings'); ?>
                
                <!-- Configuración Básica -->
                <div class="sbp-config-section">
                    <h2>⚙️ Configuración Básica</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Tiempo de Vida del Caché</th>
                            <td>
                                <input type="number" name="sbp_cache_lifetime" value="<?php echo get_option('sbp_cache_lifetime', 3600); ?>" min="300" max="86400" />
                                <p class="description">Tiempo en segundos antes de regenerar el caché (300-86400)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Páginas Excluidas</th>
                            <td>
                                <textarea name="sbp_excluded_pages" rows="5" cols="50"><?php echo esc_textarea(implode("\n", (array)get_option('sbp_excluded_pages', array('/cart', '/checkout', '/my-account')))); ?></textarea>
                                <p class="description">Una URL por línea (ej: /cart, /checkout)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Mostrar Info de Caché</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sbp_show_cache_info" value="1" <?php checked(get_option('sbp_show_cache_info', true)); ?> />
                                    Mostrar información de caché en el HTML
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Minificar HTML</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sbp_minify_html" value="1" <?php checked(get_option('sbp_minify_html', false)); ?> />
                                    Minificar HTML (básico)
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button('Guardar Configuración'); ?>
            </form>
            
            <div class="sbp-info-box">
                <h3>🚀 Modo de Emergencia Activado</h3>
                <p>Se han desactivado temporalmente las siguientes funciones para optimizar el rendimiento:</p>
                <ul>
                    <li>❌ BoostAI™ (Machine Learning)</li>
                    <li>❌ CDN Local Ultra</li>
                    <li>❌ Optimización de Assets</li>
                    <li>❌ PageSpeed 100/100</li>
                    <li>❌ Lazy Loading</li>
                </ul>
                <p><strong>✅ Activo:</strong> Solo caché estático básico (muy eficiente)</p>
            </div>
        </div>
        <?php
    }
    
    // AJAX Handlers simplificados
    public function ajax_toggle_cache() {
        check_ajax_referer('sbp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $current_status = get_option('sbp_enabled', true);
        $new_status = !$current_status;
        
        update_option('sbp_enabled', $new_status);
        
        wp_send_json_success(array(
            'enabled' => $new_status,
            'message' => $new_status ? 'StaticBoost Pro activado' : 'StaticBoost Pro desactivado'
        ));
    }
    
    public function ajax_generate_all_pages() {
        check_ajax_referer('sbp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $results = sbp_generate_all_static_pages();
        wp_send_json_success($results);
    }
    
    public function ajax_clear_cache() {
        check_ajax_referer('sbp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $result = sbp_clear_all_cache();
        
        if ($result) {
            wp_send_json_success('Caché limpiado exitosamente');
        } else {
            wp_send_json_error('Error al limpiar caché');
        }
    }
    
    public function ajax_get_stats() {
        check_ajax_referer('sbp_admin_nonce', 'nonce');
        
        $stats = sbp_get_cache_stats();
        wp_send_json_success($stats);
    }
    
    private function get_admin_css() {
        return '
        .sbp-logo { font-size: 24px; }
        .sbp-version { 
            background: #0073aa; 
            color: white; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 12px; 
            margin-left: 10px; 
        }
        .sbp-mode { 
            background: #d63638; 
            color: white; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 12px; 
            margin-left: 5px; 
        }
        
        .sbp-status-card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .sbp-status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sbp-toggle-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sbp-toggle {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .sbp-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .sbp-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .sbp-toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        .sbp-toggle input:checked + .sbp-toggle-slider {
            background-color: #00a32a;
        }
        
        .sbp-toggle input:checked + .sbp-toggle-slider:before {
            transform: translateX(26px);
        }
        
        .sbp-status-text.active { color: #00a32a; font-weight: bold; }
        .sbp-status-text.inactive { color: #d63638; font-weight: bold; }
        
        .sbp-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .sbp-stat-card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .sbp-stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .sbp-stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .sbp-stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .sbp-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .sbp-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .sbp-btn-primary { background: #0073aa; color: white; }
        .sbp-btn-primary:hover { background: #005a87; }
        
        .sbp-btn-danger { background: #d63638; color: white; }
        .sbp-btn-danger:hover { background: #b32d2e; }
        
        .sbp-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .sbp-progress-container {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .sbp-progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f1;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .sbp-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #00a32a);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .sbp-progress-status {
            text-align: center;
            color: #666;
            font-weight: 500;
        }
        
        .sbp-config-section {
            margin: 20px 0;
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
        }
        
        .sbp-info-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .sbp-info-box h3 {
            margin-top: 0;
            color: #856404;
        }
        
        .sbp-info-box ul {
            margin: 10px 0;
        }
        
        .sbp-info-box li {
            margin: 5px 0;
        }
        ';
    }
}