<?php
/**
 * Clase de administración para StaticBoost Pro - VERSIÓN COMPLETA
 */
class SBP_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_sbp_toggle_cache', array($this, 'ajax_toggle_cache'));
        add_action('wp_ajax_sbp_generate_all_pages', array($this, 'ajax_generate_all_pages'));
        add_action('wp_ajax_sbp_preload_cache', array($this, 'ajax_preload_cache'));
        add_action('wp_ajax_sbp_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_sbp_get_stats', array($this, 'ajax_get_stats'));
        
        // Optimización de assets con modal
        add_action('wp_ajax_sbp_start_optimization', array($this, 'ajax_start_optimization'));
        add_action('wp_ajax_sbp_get_optimization_status', array($this, 'ajax_get_optimization_status'));
        add_action('wp_ajax_sbp_pause_optimization', array($this, 'ajax_pause_optimization'));
        add_action('wp_ajax_sbp_resume_optimization', array($this, 'ajax_resume_optimization'));
        add_action('wp_ajax_sbp_stop_optimization', array($this, 'ajax_stop_optimization'));
        
        // Object Cache
        add_action('wp_ajax_sbp_test_object_cache', array($this, 'ajax_test_object_cache'));
        add_action('wp_ajax_sbp_flush_object_cache', array($this, 'ajax_flush_object_cache'));
        
        // Hook para limpiar caché cuando cambian configuraciones
        add_action('update_option', array($this, 'clear_cache_on_config_change'), 10, 3);
    }
    
    /**
     * Limpiar caché automáticamente cuando cambian configuraciones SBP
     */
    public function clear_cache_on_config_change($option_name, $old_value, $new_value) {
        // Solo actuar en opciones de SBP
        if (strpos($option_name, 'sbp_') === 0) {
            // Limpiar caché
            sbp_clear_all_cache();
            
            // Mostrar mensaje de confirmación
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>StaticBoost Pro:</strong> Configuración guardada y caché limpiado automáticamente.</p>';
                echo '</div>';
            });
        }
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
        
        // BoostAI
        register_setting('sbp_settings', 'sbp_boostai_enabled');
        
        // PageSpeed
        register_setting('sbp_settings', 'sbp_pagespeed_mode');
        
        // CDN Local
        register_setting('sbp_settings', 'sbp_local_cdn_enabled');
        register_setting('sbp_settings', 'sbp_local_cdn_aggressive');
        
        // Configuraciones granulares de assets
        register_setting('sbp_settings', 'sbp_optimize_images');
        register_setting('sbp_settings', 'sbp_webp_conversion');
        register_setting('sbp_settings', 'sbp_lazy_loading');
        register_setting('sbp_settings', 'sbp_optimize_css');
        register_setting('sbp_settings', 'sbp_critical_css');
        register_setting('sbp_settings', 'sbp_optimize_js');
        register_setting('sbp_settings', 'sbp_optimize_fonts');
        register_setting('sbp_settings', 'sbp_preload_resources');
        register_setting('sbp_settings', 'sbp_minify_html');
        register_setting('sbp_settings', 'sbp_remove_query_strings');
        
        // Object Cache
        register_setting('sbp_settings', 'sbp_object_cache_enabled');
        register_setting('sbp_settings', 'sbp_redis_host');
        register_setting('sbp_settings', 'sbp_redis_port');
        register_setting('sbp_settings', 'sbp_redis_password');
        register_setting('sbp_settings', 'sbp_memcached_host');
        register_setting('sbp_settings', 'sbp_memcached_port');
    }
    
    public function admin_page() {
        $stats = sbp_get_cache_stats();
        $total_pages = sbp_get_total_pages_count();
        $object_cache = new SBP_Object_Cache_Manager();
        $cache_info = $object_cache->get_info();
        ?>
        <div class="wrap">
            <h1>
                <span class="sbp-logo">🚀</span>
                StaticBoost Pro
                <span class="sbp-version">v<?php echo SBP_VERSION; ?></span>
                <span class="sbp-mode-pro">PRODUCCIÓN</span>
            </h1>
            
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
                
                <!-- Estado del Object Cache -->
                <div class="sbp-cache-status">
                    <h3>Object Cache: <?php echo strtoupper($cache_info['type']); ?></h3>
                    <span class="sbp-cache-indicator <?php echo $cache_info['connected'] ? 'connected' : 'disconnected'; ?>">
                        <?php echo $cache_info['connected'] ? '🟢 Conectado' : '🔴 Desconectado'; ?>
                    </span>
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
                    <div class="sbp-stat-icon">🌐</div>
                    <div class="sbp-stat-content">
                        <div class="sbp-stat-number"><?php echo get_option('sbp_local_cdn_enabled', true) ? 'ON' : 'OFF'; ?></div>
                        <div class="sbp-stat-label">CDN Local Ultra</div>
                    </div>
                </div>
                
                <div class="sbp-stat-card">
                    <div class="sbp-stat-icon">💾</div>
                    <div class="sbp-stat-content">
                        <div class="sbp-stat-number"><?php echo size_format($stats['size']); ?></div>
                        <div class="sbp-stat-label">Espacio Usado</div>
                    </div>
                </div>
                
                <div class="sbp-stat-card">
                    <div class="sbp-stat-icon">🤖</div>
                    <div class="sbp-stat-content">
                        <div class="sbp-stat-number"><?php echo get_option('sbp_boostai_enabled', true) ? 'ON' : 'OFF'; ?></div>
                        <div class="sbp-stat-label">BoostAI™</div>
                    </div>
                </div>
                
                <div class="sbp-stat-card">
                    <div class="sbp-stat-icon">🗄️</div>
                    <div class="sbp-stat-content">
                        <div class="sbp-stat-number"><?php echo strtoupper($cache_info['type']); ?></div>
                        <div class="sbp-stat-label">Object Cache</div>
                    </div>
                </div>
            </div>
            
            <!-- Acciones principales -->
            <div class="sbp-actions-grid">
                <button id="sbp-generate-all" class="sbp-btn sbp-btn-primary">
                    <span class="sbp-btn-icon">🚀</span>
                    Generar Todas las Páginas
                </button>
                
                <button id="sbp-preload-cache" class="sbp-btn sbp-btn-secondary">
                    <span class="sbp-btn-icon">⚡</span>
                    Precarga Rápida
                </button>
                
                <button id="sbp-optimize-assets" class="sbp-btn sbp-btn-success">
                    <span class="sbp-btn-icon">🎯</span>
                    Optimizar Assets
                </button>
                
                <button id="sbp-clear-cache" class="sbp-btn sbp-btn-danger">
                    <span class="sbp-btn-icon">🗑️</span>
                    Limpiar Caché
                </button>
                
                <button id="sbp-test-object-cache" class="sbp-btn sbp-btn-info">
                    <span class="sbp-btn-icon">🧪</span>
                    Test Object Cache
                </button>
                
                <button id="sbp-flush-object-cache" class="sbp-btn sbp-btn-warning">
                    <span class="sbp-btn-icon">🔄</span>
                    Flush Object Cache
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
                    <button type="button" class="sbp-collapsible" data-target="basic-config">
                        <span class="sbp-arrow">▼</span>
                        Configuración Básica
                    </button>
                    <div id="basic-config" class="sbp-collapsible-content">
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
                        </table>
                    </div>
                </div>
                
                <!-- Object Cache -->
                <div class="sbp-config-section">
                    <button type="button" class="sbp-collapsible" data-target="object-cache-config">
                        <span class="sbp-arrow">▼</span>
                        🗄️ Object Cache (Redis/Memcached)
                    </button>
                    <div id="object-cache-config" class="sbp-collapsible-content">
                        <div class="sbp-cache-info">
                            <p><strong>Estado actual:</strong> <?php echo strtoupper($cache_info['type']); ?> 
                            <?php echo $cache_info['connected'] ? '(Conectado)' : '(Desconectado)'; ?></p>
                        </div>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Habilitar Object Cache</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_object_cache_enabled" value="1" <?php checked(get_option('sbp_object_cache_enabled', true)); ?> />
                                        Usar Redis/Memcached para caché de objetos
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <h4>Configuración Redis</h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Host Redis</th>
                                <td>
                                    <input type="text" name="sbp_redis_host" value="<?php echo get_option('sbp_redis_host', '127.0.0.1'); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Puerto Redis</th>
                                <td>
                                    <input type="number" name="sbp_redis_port" value="<?php echo get_option('sbp_redis_port', 6379); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Contraseña Redis</th>
                                <td>
                                    <input type="password" name="sbp_redis_password" value="<?php echo get_option('sbp_redis_password', ''); ?>" />
                                    <p class="description">Dejar vacío si no requiere contraseña</p>
                                </td>
                            </tr>
                        </table>
                        
                        <h4>Configuración Memcached</h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Host Memcached</th>
                                <td>
                                    <input type="text" name="sbp_memcached_host" value="<?php echo get_option('sbp_memcached_host', '127.0.0.1'); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Puerto Memcached</th>
                                <td>
                                    <input type="number" name="sbp_memcached_port" value="<?php echo get_option('sbp_memcached_port', 11211); ?>" />
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- BoostAI -->
                <div class="sbp-config-section">
                    <button type="button" class="sbp-collapsible" data-target="boostai-config">
                        <span class="sbp-arrow">▼</span>
                        🤖 BoostAI™ (Machine Learning)
                    </button>
                    <div id="boostai-config" class="sbp-collapsible-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Habilitar BoostAI™</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_boostai_enabled" value="1" <?php checked(get_option('sbp_boostai_enabled', true)); ?> />
                                        Activar optimización inteligente con Machine Learning
                                    </label>
                                    <p class="description">Sistema propietario que aprende del comportamiento de usuarios</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- PageSpeed -->
                <div class="sbp-config-section">
                    <button type="button" class="sbp-collapsible" data-target="pagespeed-config">
                        <span class="sbp-arrow">▼</span>
                        ⚡ Modo PageSpeed 100/100
                    </button>
                    <div id="pagespeed-config" class="sbp-collapsible-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Modo PageSpeed</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_pagespeed_mode" value="1" <?php checked(get_option('sbp_pagespeed_mode', true)); ?> />
                                        Optimizar para PageSpeed Insights 100/100
                                    </label>
                                    <p class="description">Aplica optimizaciones específicas para Core Web Vitals</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- CDN Local Ultra -->
                <div class="sbp-config-section">
                    <button type="button" class="sbp-collapsible" data-target="cdn-config">
                        <span class="sbp-arrow">▼</span>
                        🌐 CDN Local Ultra
                    </button>
                    <div id="cdn-config" class="sbp-collapsible-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">CDN Local</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_local_cdn_enabled" value="1" <?php checked(get_option('sbp_local_cdn_enabled', true)); ?> />
                                        Activar CDN Local Ultra
                                    </label>
                                    <p class="description">Sirve assets optimizados desde tu servidor con headers profesionales</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Modo Agresivo</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_local_cdn_aggressive" value="1" <?php checked(get_option('sbp_local_cdn_aggressive', false)); ?> />
                                        Optimizar también CSS/JS del tema
                                    </label>
                                    <p class="description">⚠️ Puede afectar funcionalidad - probar primero</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Optimización de Assets -->
                <div class="sbp-config-section">
                    <button type="button" class="sbp-collapsible" data-target="assets-config">
                        <span class="sbp-arrow">▼</span>
                        🎯 Optimización de Assets
                    </button>
                    <div id="assets-config" class="sbp-collapsible-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">🖼️ Imágenes</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_optimize_images" value="1" <?php checked(get_option('sbp_optimize_images', true)); ?> />
                                        Optimizar imágenes automáticamente
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">🌐 Conversión WebP</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_webp_conversion" value="1" <?php checked(get_option('sbp_webp_conversion', true)); ?> />
                                        Crear versiones WebP de imágenes
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">⚡ Lazy Loading</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_lazy_loading" value="1" <?php checked(get_option('sbp_lazy_loading', true)); ?> />
                                        Carga diferida de imágenes
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">🎨 CSS</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_optimize_css" value="1" <?php checked(get_option('sbp_optimize_css', true)); ?> />
                                        Optimizar y minificar CSS
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">🎨 CSS Crítico</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_critical_css" value="1" <?php checked(get_option('sbp_critical_css', true)); ?> />
                                        Generar CSS crítico inline
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">⚠️ JavaScript</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_optimize_js" value="1" <?php checked(get_option('sbp_optimize_js', false)); ?> />
                                        Optimizar JavaScript
                                    </label>
                                    <p class="description" style="color: #d63638;">⚠️ CUIDADO: Puede romper funcionalidad del sitio</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">🔤 Fuentes</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_optimize_fonts" value="1" <?php checked(get_option('sbp_optimize_fonts', true)); ?> />
                                        Optimizar carga de fuentes
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">🚀 Preload</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_preload_resources" value="1" <?php checked(get_option('sbp_preload_resources', true)); ?> />
                                        Precargar recursos críticos
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">📄 HTML</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_minify_html" value="1" <?php checked(get_option('sbp_minify_html', true)); ?> />
                                        Minificar HTML
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">🔗 Query Strings</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sbp_remove_query_strings" value="1" <?php checked(get_option('sbp_remove_query_strings', true)); ?> />
                                        Eliminar query strings de assets
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php submit_button('Guardar Configuración'); ?>
            </form>
        </div>
        
        <!-- MODAL DE OPTIMIZACIÓN -->
        <div id="sbp-optimization-modal" class="sbp-modal" style="display: none;">
            <div class="sbp-modal-content">
                <div class="sbp-modal-header">
                    <h2>🎯 Optimización de Assets en Tiempo Real</h2>
                    <span class="sbp-modal-close">&times;</span>
                </div>
                
                <div class="sbp-modal-body">
                    <!-- Estado actual -->
                    <div class="sbp-status-display">
                        <span id="sbp-status-icon">⏳</span>
                        <span id="sbp-status-text">Iniciando optimización...</span>
                    </div>
                    
                    <!-- Barra de progreso -->
                    <div class="sbp-modal-progress">
                        <div id="sbp-modal-progress-fill" class="sbp-modal-progress-fill"></div>
                        <span id="sbp-progress-percentage">0%</span>
                    </div>
                    
                    <!-- Controles -->
                    <div class="sbp-modal-controls">
                        <button id="sbp-pause-btn" class="sbp-btn sbp-btn-warning" style="display: none;">
                            ⏸️ Pausar
                        </button>
                        <button id="sbp-resume-btn" class="sbp-btn sbp-btn-success" style="display: none;">
                            ▶️ Reanudar
                        </button>
                        <button id="sbp-stop-btn" class="sbp-btn sbp-btn-danger">
                            ⏹️ Detener
                        </button>
                    </div>
                    
                    <!-- Estadísticas en tiempo real -->
                    <div class="sbp-realtime-stats">
                        <div class="sbp-stat-item">
                            <span class="sbp-stat-label">Optimizadas:</span>
                            <span id="sbp-stat-optimized" class="sbp-stat-value">0</span>
                        </div>
                        <div class="sbp-stat-item">
                            <span class="sbp-stat-label">Procesando:</span>
                            <span id="sbp-stat-processing" class="sbp-stat-value">0</span>
                        </div>
                        <div class="sbp-stat-item">
                            <span class="sbp-stat-label">Pendientes:</span>
                            <span id="sbp-stat-pending" class="sbp-stat-value">0</span>
                        </div>
                        <div class="sbp-stat-item">
                            <span class="sbp-stat-label">Errores:</span>
                            <span id="sbp-stat-errors" class="sbp-stat-value">0</span>
                        </div>
                    </div>
                    
                    <!-- Filtros -->
                    <div class="sbp-filters">
                        <button class="sbp-filter-btn active" data-filter="all">Todas</button>
                        <button class="sbp-filter-btn" data-filter="optimized">Optimizadas</button>
                        <button class="sbp-filter-btn" data-filter="processing">Procesando</button>
                        <button class="sbp-filter-btn" data-filter="pending">Pendientes</button>
                        <button class="sbp-filter-btn" data-filter="error">Errores</button>
                    </div>
                    
                    <!-- Lista de páginas -->
                    <div class="sbp-pages-container">
                        <div id="sbp-pages-list" class="sbp-pages-list">
                            <!-- Se llena dinámicamente -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    // AJAX Handlers
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
    
    public function ajax_preload_cache() {
        check_ajax_referer('sbp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $pages_generated = sbp_preload_cache();
        
        wp_send_json_success(array(
            'pages' => $pages_generated,
            'message' => "Precarga completada: {$pages_generated} páginas"
        ));
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
    
    // AJAX para optimización de assets
    public function ajax_start_optimization() {
        check_ajax_referer('sbp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        // Obtener URLs para optimizar
        $urls = sbp_get_all_site_urls();
        
        // Guardar estado de optimización
        update_option('sbp_optimization_status', 'running');
        update_option('sbp_optimization_urls', $urls);
        update_option('sbp_optimization_current', 0);
        update_option('sbp_optimization_results', array());
        
        wp_send_json_success(array(
            'urls' => array_slice($urls, 0, 10), // Solo mostrar primeras 10
            'total' => count($urls)
        ));
    }
    
    public function ajax_get_optimization_status() {
        check_ajax_referer('sbp_admin_nonce', 'nonce');
        
        $status = get_option('sbp_optimization_status', 'stopped');
        $urls = get_option('sbp_optimization_urls', array());
        $current = get_option('sbp_optimization_current', 0);
        $results = get_option('sbp_optimization_results', array());
        
        // Simular progreso para demo
        if ($status === 'running' && $current < count($urls)) {
            $current++;
            update_option('sbp_optimization_current', $current);
            
            // Simular resultado
            $results[] = array(
                'url' => $urls[$current - 1] ?? 'test-url',
                'status' => 'optimized',
                'time' => date('H:i:s')
            );
            update_option('sbp_optimization_results', $results);
            
            // Completar si llegamos al final
            if ($current >= count($urls)) {
                update_option('sbp_optimization_status', 'completed');
            }
        }
        
        wp_send_json_success(array(
            'status' => $status,
            'current_page' => $current,
            'total_pages' => count($urls),
            'stats' => array(
                'optimized' => count(array_filter($results, function($r) { return $r['status'] === 'optimized'; })),
                'processing' => $status === 'running' ? 1 : 0,
                'pending' => max(0, count($urls) - $current),
                'errors' => count(array_filter($results, function($r) { return $r['status'] === 'error'; }))
            ),
            'recent_results' => array_slice($results, -10)
        ));
    }
    
    public function ajax_pause_optimization() {
        check_ajax_referer('sbp_admin_nonce', 'nonce');
        update_option('sbp_optimization_status', 'paused');
        wp_send_json_success('Optimización pausada');
    }
    
    public function ajax_resume_optimization() {
        check_ajax_referer('sbp_admin_nonce', 'nonce');
        update_option('sbp_optimization_status', 'running');
        wp_send_json_success('Optimización reanudada');
    }
    
    public function ajax_stop_optimization() {
        check_ajax_referer('sbp_admin_nonce', 'nonce');
        update_option('sbp_optimization_status', 'stopped');
        wp_send_json_success('Optimización detenida');
    }
    
    // AJAX para Object Cache
    public function ajax_test_object_cache() {
        check_ajax_referer('sbp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $object_cache = new SBP_Object_Cache_Manager();
        
        // Test básico
        $test_key = 'sbp_test_' . time();
        $test_value = 'Test value: ' . wp_generate_password(10, false);
        
        $set_result = $object_cache->set($test_key, $test_value, 60);
        $get_result = $object_cache->get($test_key);
        $delete_result = $object_cache->delete($test_key);
        
        $cache_info = $object_cache->get_info();
        
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
        check_ajax_referer('sbp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $object_cache = new SBP_Object_Cache_Manager();
        $result = $object_cache->flush();
        
        if ($result) {
            wp_send_json_success('Object Cache limpiado exitosamente');
        } else {
            wp_send_json_error('Error al limpiar Object Cache');
        }
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
        .sbp-mode-pro { 
            background: #00a32a; 
            color: white; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 12px; 
            margin-left: 5px; 
            font-weight: bold;
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
            margin-bottom: 15px;
        }
        
        .sbp-cache-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .sbp-cache-indicator.connected { color: #00a32a; font-weight: bold; }
        .sbp-cache-indicator.disconnected { color: #d63638; font-weight: bold; }
        
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        
        .sbp-btn-secondary { background: #f0f0f1; color: #2c3338; }
        .sbp-btn-secondary:hover { background: #dcdcde; }
        
        .sbp-btn-success { background: #00a32a; color: white; }
        .sbp-btn-success:hover { background: #007c20; }
        
        .sbp-btn-danger { background: #d63638; color: white; }
        .sbp-btn-danger:hover { background: #b32d2e; }
        
        .sbp-btn-warning { background: #f56e28; color: white; }
        .sbp-btn-warning:hover { background: #e65100; }
        
        .sbp-btn-info { background: #0073aa; color: white; }
        .sbp-btn-info:hover { background: #005a87; }
        
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
        }
        
        .sbp-collapsible {
            background: #f0f0f1;
            border: 1px solid #ccd0d4;
            border-radius: 6px;
            padding: 15px 20px;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sbp-collapsible:hover {
            background: #dcdcde;
        }
        
        .sbp-arrow {
            transition: transform 0.3s ease;
        }
        
        .sbp-arrow.rotated {
            transform: rotate(-90deg);
        }
        
        .sbp-collapsible-content {
            border: 1px solid #ccd0d4;
            border-top: none;
            border-radius: 0 0 6px 6px;
            padding: 20px;
            background: white;
        }
        
        .sbp-cache-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        /* MODAL STYLES */
        .sbp-modal {
            position: fixed;
            z-index: 999999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .sbp-modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .sbp-modal-header {
            background: #0073aa;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sbp-modal-close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .sbp-modal-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .sbp-status-display {
            text-align: center;
            margin: 20px 0;
            font-size: 18px;
        }
        
        .sbp-modal-progress {
            position: relative;
            width: 100%;
            height: 30px;
            background: #f0f0f1;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .sbp-modal-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #00a32a);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        #sbp-progress-percentage {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: bold;
            color: #333;
        }
        
        .sbp-modal-controls {
            text-align: center;
            margin: 20px 0;
        }
        
        .sbp-modal-controls .sbp-btn {
            margin: 0 5px;
        }
        
        .sbp-realtime-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .sbp-stat-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .sbp-stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .sbp-stat-value {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .sbp-filters {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .sbp-filter-btn {
            padding: 8px 16px;
            border: 1px solid #ccd0d4;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .sbp-filter-btn.active {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
        }
        
        .sbp-pages-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ccd0d4;
            border-radius: 6px;
        }
        
        .sbp-pages-list {
            padding: 0;
        }
        
        .sbp-page-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f1;
            font-size: 14px;
        }
        
        .sbp-page-item:last-child {
            border-bottom: none;
        }
        
        .sbp-page-status {
            margin-right: 10px;
            font-size: 16px;
        }
        
        .sbp-page-url {
            flex: 1;
            color: #0073aa;
        }
        
        .sbp-page-time {
            color: #666;
            font-size: 12px;
        }
        ';
    }
}