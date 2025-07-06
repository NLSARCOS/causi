<?php
/**
 * Clase para el panel de administración - StaticBoost Pro
 */
class SBP_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
        
        // AJAX handlers
        add_action('wp_ajax_sbp_toggle_cache', array($this, 'ajax_toggle_cache'));
        add_action('wp_ajax_sbp_generate_all_pages', array($this, 'ajax_generate_all_pages'));
        add_action('wp_ajax_sbp_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_sbp_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_sbp_preload_cache', array($this, 'ajax_preload_cache'));
        add_action('wp_ajax_sbp_optimize_assets', array($this, 'ajax_optimize_assets'));
        
        // NUEVOS AJAX handlers para seguimiento en tiempo real
        add_action('wp_ajax_sbp_start_optimization', array($this, 'ajax_start_optimization'));
        add_action('wp_ajax_sbp_pause_optimization', array($this, 'ajax_pause_optimization'));
        add_action('wp_ajax_sbp_resume_optimization', array($this, 'ajax_resume_optimization'));
        add_action('wp_ajax_sbp_stop_optimization', array($this, 'ajax_stop_optimization'));
        add_action('wp_ajax_sbp_get_optimization_status', array($this, 'ajax_get_optimization_status'));
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
    
    public function admin_init() {
        register_setting('sbp_settings', 'sbp_enabled');
        register_setting('sbp_settings', 'sbp_cache_lifetime');
        register_setting('sbp_settings', 'sbp_excluded_pages');
        register_setting('sbp_settings', 'sbp_excluded_user_agents');
        register_setting('sbp_settings', 'sbp_show_cache_info');
        register_setting('sbp_settings', 'sbp_boostai_enabled');
        register_setting('sbp_settings', 'sbp_asset_optimization');
        register_setting('sbp_settings', 'sbp_aggressive_optimization');
        register_setting('sbp_settings', 'sbp_image_optimization');
        register_setting('sbp_settings', 'sbp_critical_css');
        register_setting('sbp_settings', 'sbp_pagespeed_mode');
        register_setting('sbp_settings', 'sbp_preload_critical_resources');
        register_setting('sbp_settings', 'sbp_eliminate_render_blocking');
        register_setting('sbp_settings', 'sbp_optimize_lcp');
        register_setting('sbp_settings', 'sbp_minimize_main_thread');
        register_setting('sbp_settings', 'sbp_local_cdn_enabled');
        register_setting('sbp_settings', 'sbp_local_cdn_aggressive');
    }
    
    public function admin_page() {
        $stats = sbp_get_cache_stats();
        $total_pages = sbp_get_total_pages_count();
        $enabled = get_option('sbp_enabled', true);
        $boostai_enabled = get_option('sbp_boostai_enabled', true);
        $asset_optimization = get_option('sbp_asset_optimization', true);
        $aggressive_optimization = get_option('sbp_aggressive_optimization', false);
        $pagespeed_mode = get_option('sbp_pagespeed_mode', true);
        $local_cdn_enabled = get_option('sbp_local_cdn_enabled', true);
        $progress_percent = $total_pages['total'] > 0 ? round(($stats['files'] / $total_pages['total']) * 100, 1) : 0;
        ?>
        <div class="wrap">
            <div class="sbp-header">
                <h1>⚡ StaticBoost Pro</h1>
                <p class="sbp-tagline">Páginas estáticas ultrarrápidas con BoostAI™ + CDN Local</p>
            </div>
            
            <!-- Control Principal -->
            <div class="sbp-main-card">
                <div class="sbp-header-controls">
                    <div class="sbp-status">
                        <h2>Sistema de Páginas Estáticas</h2>
                        <p class="sbp-status-text <?php echo $enabled ? 'active' : 'inactive'; ?>">
                            <?php echo $enabled ? '🟢 Activo' : '🔴 Inactivo'; ?>
                        </p>
                        <?php if ($boostai_enabled): ?>
                        <p class="sbp-ai-status">🧠 BoostAI™ Optimizando</p>
                        <?php endif; ?>
                        <?php if ($pagespeed_mode): ?>
                        <p class="sbp-pagespeed-status">🚀 Modo PageSpeed 100/100</p>
                        <?php endif; ?>
                        <?php if ($local_cdn_enabled): ?>
                        <p class="sbp-cdn-status">🌐 CDN Local Activo</p>
                        <?php endif; ?>
                    </div>
                    <div class="sbp-toggle">
                        <label class="sbp-switch">
                            <input type="checkbox" id="sbp-toggle-cache" <?php checked($enabled); ?>>
                            <span class="sbp-slider"></span>
                        </label>
                    </div>
                </div>
                
                <!-- Estadísticas -->
                <div class="sbp-stats">
                    <div class="sbp-stat">
                        <span class="sbp-stat-number"><?php echo $stats['files']; ?></span>
                        <span class="sbp-stat-label">Páginas Estáticas</span>
                    </div>
                    <div class="sbp-stat">
                        <span class="sbp-stat-number"><?php echo $total_pages['total']; ?></span>
                        <span class="sbp-stat-label">Total Páginas</span>
                    </div>
                    <div class="sbp-stat">
                        <span class="sbp-stat-number"><?php echo $progress_percent; ?>%</span>
                        <span class="sbp-stat-label">Completado</span>
                    </div>
                    <div class="sbp-stat">
                        <span class="sbp-stat-number"><?php echo $this->format_bytes($stats['size']); ?></span>
                        <span class="sbp-stat-label">Tamaño Total</span>
                    </div>
                </div>
                
                <!-- Barra de Progreso -->
                <div class="sbp-progress-container">
                    <div class="sbp-progress-bar">
                        <div class="sbp-progress-fill" style="width: <?php echo $progress_percent; ?>%"></div>
                    </div>
                    <span class="sbp-progress-text"><?php echo $stats['files']; ?> de <?php echo $total_pages['total']; ?> páginas convertidas a estáticas</span>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="sbp-actions-card">
                <h3>🎯 Acciones Principales</h3>
                <div class="sbp-actions">
                    <button type="button" id="sbp-generate-all" class="sbp-btn sbp-btn-primary">
                        <span class="sbp-btn-icon">🚀</span>
                        Convertir TODAS las Páginas
                    </button>
                    <button type="button" id="sbp-preload-cache" class="sbp-btn sbp-btn-secondary">
                        <span class="sbp-btn-icon">⚡</span>
                        Conversión Rápida
                    </button>
                    <button type="button" id="sbp-optimize-assets" class="sbp-btn sbp-btn-info">
                        <span class="sbp-btn-icon">🎯</span>
                        Optimizar Assets
                    </button>
                    <button type="button" id="sbp-clear-cache" class="sbp-btn sbp-btn-danger">
                        <span class="sbp-btn-icon">🗑️</span>
                        Limpiar Todo
                    </button>
                </div>
                
                <!-- Progreso de Generación -->
                <div id="sbp-generation-progress" class="sbp-generation-progress" style="display: none;">
                    <div class="sbp-progress-bar">
                        <div class="sbp-progress-fill" style="width: 0%"></div>
                    </div>
                    <p id="sbp-generation-status">Iniciando conversión...</p>
                </div>
            </div>
            
            <!-- Configuración Avanzada -->
            <div class="sbp-config-card">
                <h3 class="sbp-collapsible" data-target="sbp-config-content">
                    ⚙️ Configuración Avanzada <span class="sbp-arrow">▼</span>
                </h3>
                <div id="sbp-config-content" class="sbp-config-content" style="display: none;">
                    <form method="post" action="options.php">
                        <?php settings_fields('sbp_settings'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">🌐 CDN Local Ultra</th>
                                <td>
                                    <input type="checkbox" name="sbp_local_cdn_enabled" value="1" <?php checked(get_option('sbp_local_cdn_enabled', true)); ?> />
                                    <label><strong>Activar CDN Local (RECOMENDADO)</strong></label>
                                    <p class="description">Servir assets optimizados desde el servidor local con headers de CDN profesional</p>
                                    
                                    <br><br>
                                    <input type="checkbox" name="sbp_local_cdn_aggressive" value="1" <?php checked(get_option('sbp_local_cdn_aggressive', false)); ?> />
                                    <label><strong>Modo CDN Agresivo</strong></label>
                                    <p class="description">Optimizaciones avanzadas: Brotli, HTTP/2 Push, Edge Caching</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">🚀 Modo PageSpeed 100/100</th>
                                <td>
                                    <input type="checkbox" name="sbp_pagespeed_mode" value="1" <?php checked(get_option('sbp_pagespeed_mode', true)); ?> />
                                    <label><strong>Activar optimizaciones para PageSpeed Insights</strong></label>
                                    <p class="description">Optimizaciones específicas para obtener 100/100 en PageSpeed: CSS crítico inline, preload headers, eliminación de render-blocking</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">🧠 BoostAI™ Optimizer</th>
                                <td>
                                    <input type="checkbox" name="sbp_boostai_enabled" value="1" <?php checked(get_option('sbp_boostai_enabled', true)); ?> />
                                    <label>Activar optimización inteligente con BoostAI™</label>
                                    <p class="description">Nuestro sistema de Machine Learning propietario que optimiza la carga de contenido basado en el comportamiento del usuario</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Optimización de Assets</th>
                                <td>
                                    <input type="checkbox" name="sbp_asset_optimization" value="1" <?php checked(get_option('sbp_asset_optimization', true)); ?> />
                                    <label>Activar optimización básica de assets (RECOMENDADO)</label>
                                    <p class="description">Optimizaciones seguras: lazy loading inteligente, preload headers, compresión de imágenes</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">⚠️ Optimización Agresiva</th>
                                <td>
                                    <input type="checkbox" name="sbp_aggressive_optimization" value="1" <?php checked(get_option('sbp_aggressive_optimization', false)); ?> />
                                    <label><strong>Activar optimización agresiva (EXPERIMENTAL)</strong></label>
                                    <p class="description" style="color: #d63638;">
                                        <strong>ADVERTENCIA:</strong> Puede romper el diseño del sitio. Solo activar si sabes lo que haces.<br>
                                        Incluye: minificación y combinación de CSS/JS, modificación de assets del tema.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Optimización de Imágenes</th>
                                <td>
                                    <input type="checkbox" name="sbp_image_optimization" value="1" <?php checked(get_option('sbp_image_optimization', true)); ?> />
                                    <label>Generar formatos WebP automáticamente</label>
                                    <p class="description">Convierte imágenes a formato WebP para mejor compresión (solo imágenes de uploads)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">CSS Crítico</th>
                                <td>
                                    <input type="checkbox" name="sbp_critical_css" value="1" <?php checked(get_option('sbp_critical_css', true)); ?> />
                                    <label>Generar CSS crítico para PageSpeed</label>
                                    <p class="description">CSS crítico optimizado para eliminar render-blocking y mejorar LCP</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Tiempo de Vida</th>
                                <td>
                                    <input type="number" name="sbp_cache_lifetime" value="<?php echo get_option('sbp_cache_lifetime', 3600); ?>" min="60" step="60" />
                                    <p class="description">Segundos antes de que expire el archivo estático (3600 = 1 hora)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Páginas Excluidas</th>
                                <td>
                                    <textarea name="sbp_excluded_pages" rows="3" cols="50"><?php echo esc_textarea(implode("\n", (array)get_option('sbp_excluded_pages', array('/cart', '/checkout', '/my-account')))); ?></textarea>
                                    <p class="description">Una URL por línea (ej: /carrito, /checkout). WooCommerce se excluye automáticamente.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">User Agents Excluidos</th>
                                <td>
                                    <textarea name="sbp_excluded_user_agents" rows="2" cols="50"><?php echo esc_textarea(implode("\n", (array)get_option('sbp_excluded_user_agents', array('bot', 'crawler', 'spider')))); ?></textarea>
                                    <p class="description">User agents que no deben recibir páginas estáticas</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Mostrar Info de Debug</th>
                                <td>
                                    <input type="checkbox" name="sbp_show_cache_info" value="1" <?php checked(get_option('sbp_show_cache_info', true)); ?> />
                                    <label>Mostrar información en comentarios HTML</label>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button('💾 Guardar Configuración'); ?>
                    </form>
                </div>
            </div>
            
            <!-- Información del Sistema -->
            <div class="sbp-info-card">
                <h3>📊 Estado del Sistema</h3>
                <div class="sbp-system-info">
                    <div class="sbp-info-item">
                        <strong>WooCommerce:</strong> 
                        <?php echo class_exists('WooCommerce') ? '✅ Detectado (compatibilidad activa)' : '❌ No detectado'; ?>
                    </div>
                    <div class="sbp-info-item">
                        <strong>BoostAI™:</strong> 
                        <?php echo $boostai_enabled ? '✅ Habilitado' : '❌ Deshabilitado'; ?>
                    </div>
                    <div class="sbp-info-item">
                        <strong>CDN Local:</strong> 
                        <?php 
                        if ($local_cdn_enabled) {
                            echo get_option('sbp_local_cdn_aggressive', false) ? '🚀 Modo Agresivo' : '✅ Modo Estándar';
                        } else {
                            echo '❌ Deshabilitado';
                        }
                        ?>
                    </div>
                    <div class="sbp-info-item">
                        <strong>Optimización de Assets:</strong> 
                        <?php 
                        if ($asset_optimization) {
                            echo $aggressive_optimization ? '⚠️ Modo Agresivo' : '✅ Modo Seguro';
                        } else {
                            echo '❌ Deshabilitada';
                        }
                        ?>
                    </div>
                    <div class="sbp-info-item">
                        <strong>PageSpeed Mode:</strong> 
                        <?php echo $pagespeed_mode ? '🚀 Activo (100/100)' : '❌ Deshabilitado'; ?>
                    </div>
                    <div class="sbp-info-item">
                        <strong>Última conversión:</strong> 
                        <?php echo $stats['last_generated'] ? $stats['last_generated'] : 'Nunca'; ?>
                    </div>
                </div>
                
                <?php if ($aggressive_optimization): ?>
                <div class="sbp-warning">
                    <p><strong>⚠️ ADVERTENCIA:</strong> El modo agresivo está activado. Si experimentas problemas visuales, desactívalo.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- MODAL DE SEGUIMIENTO EN TIEMPO REAL -->
        <div id="sbp-optimization-modal" class="sbp-modal" style="display: none;">
            <div class="sbp-modal-content">
                <div class="sbp-modal-header">
                    <h2>🎯 Optimización de Assets en Tiempo Real</h2>
                    <button class="sbp-modal-close">&times;</button>
                </div>
                
                <div class="sbp-modal-body">
                    <!-- Estado General -->
                    <div class="sbp-optimization-status">
                        <div class="sbp-status-indicator">
                            <span id="sbp-status-icon">⏳</span>
                            <span id="sbp-status-text">Preparando optimización...</span>
                        </div>
                        <div class="sbp-status-progress">
                            <div class="sbp-progress-bar">
                                <div id="sbp-modal-progress-fill" class="sbp-progress-fill" style="width: 0%"></div>
                            </div>
                            <span id="sbp-progress-percentage">0%</span>
                        </div>
                    </div>
                    
                    <!-- Controles -->
                    <div class="sbp-optimization-controls">
                        <button id="sbp-pause-btn" class="sbp-btn sbp-btn-warning" style="display: none;">
                            <span class="sbp-btn-icon">⏸️</span>
                            Pausar
                        </button>
                        <button id="sbp-resume-btn" class="sbp-btn sbp-btn-success" style="display: none;">
                            <span class="sbp-btn-icon">▶️</span>
                            Reanudar
                        </button>
                        <button id="sbp-stop-btn" class="sbp-btn sbp-btn-danger" style="display: none;">
                            <span class="sbp-btn-icon">⏹️</span>
                            Detener
                        </button>
                    </div>
                    
                    <!-- Lista de Páginas -->
                    <div class="sbp-pages-container">
                        <h3>📄 Estado de las Páginas</h3>
                        
                        <!-- Filtros -->
                        <div class="sbp-filters">
                            <button class="sbp-filter-btn active" data-filter="all">Todas</button>
                            <button class="sbp-filter-btn" data-filter="optimized">✅ Optimizadas</button>
                            <button class="sbp-filter-btn" data-filter="optimizing">⏳ Optimizando</button>
                            <button class="sbp-filter-btn" data-filter="pending">⏸️ Pendientes</button>
                            <button class="sbp-filter-btn" data-filter="error">❌ Errores</button>
                        </div>
                        
                        <!-- Lista de páginas -->
                        <div id="sbp-pages-list" class="sbp-pages-list">
                            <!-- Se llena dinámicamente -->
                        </div>
                    </div>
                    
                    <!-- Estadísticas en tiempo real -->
                    <div class="sbp-real-time-stats">
                        <div class="sbp-stat-item">
                            <span class="sbp-stat-label">Optimizadas:</span>
                            <span id="sbp-stat-optimized" class="sbp-stat-value">0</span>
                        </div>
                        <div class="sbp-stat-item">
                            <span class="sbp-stat-label">En proceso:</span>
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
                </div>
            </div>
        </div>
        
        <style>
        .sbp-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .sbp-header h1 {
            font-size: 2.5em;
            margin: 0;
            background: linear-gradient(45deg, #0073aa, #00a32a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sbp-tagline {
            font-size: 1.1em;
            color: #666;
            margin: 10px 0 0 0;
        }
        
        .sbp-main-card, .sbp-actions-card, .sbp-config-card, .sbp-info-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .sbp-header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .sbp-status h2 {
            margin: 0 0 8px 0;
            font-size: 22px;
        }
        
        .sbp-status-text {
            margin: 0 0 8px 0;
            font-weight: 600;
            font-size: 16px;
        }
        
        .sbp-status-text.active {
            color: #00a32a;
        }
        
        .sbp-status-text.inactive {
            color: #d63638;
        }
        
        .sbp-ai-status, .sbp-pagespeed-status, .sbp-cdn-status {
            margin: 0;
            font-size: 13px;
            color: #0073aa;
            font-weight: 500;
        }
        
        /* Switch Toggle */
        .sbp-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
        }
        
        .sbp-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .sbp-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 28px;
        }
        
        .sbp-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .sbp-slider {
            background-color: #00a32a;
        }
        
        input:checked + .sbp-slider:before {
            transform: translateX(22px);
        }
        
        /* Estadísticas */
        .sbp-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .sbp-stat {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        
        .sbp-stat-number {
            display: block;
            font-size: 28px;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 8px;
        }
        
        .sbp-stat-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        /* Barra de Progreso */
        .sbp-progress-container {
            margin-bottom: 15px;
        }
        
        .sbp-progress-bar {
            width: 100%;
            height: 10px;
            background: #f0f0f1;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .sbp-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #00a32a);
            transition: width 0.3s ease;
            border-radius: 5px;
        }
        
        .sbp-progress-text {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        /* Botones */
        .sbp-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        
        .sbp-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .sbp-btn-primary {
            background: linear-gradient(135deg, #0073aa, #005a87);
            color: white;
        }
        
        .sbp-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,115,170,0.3);
        }
        
        .sbp-btn-secondary {
            background: linear-gradient(135deg, #f0f0f1, #ddd);
            color: #333;
        }
        
        .sbp-btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        
        .sbp-btn-info {
            background: linear-gradient(135deg, #00a32a, #008a20);
            color: white;
        }
        
        .sbp-btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,163,42,0.3);
        }
        
        .sbp-btn-danger {
            background: linear-gradient(135deg, #d63638, #b32d2e);
            color: white;
        }
        
        .sbp-btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(214,54,56,0.3);
        }
        
        .sbp-btn-warning {
            background: linear-gradient(135deg, #f56e28, #e65100);
            color: white;
        }
        
        .sbp-btn-success {
            background: linear-gradient(135deg, #00a32a, #008a20);
            color: white;
        }
        
        .sbp-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .sbp-btn-icon {
            font-size: 18px;
        }
        
        /* Modal */
        .sbp-modal {
            position: fixed;
            z-index: 999999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
        }
        
        .sbp-modal-content {
            background-color: #fff;
            margin: 2% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: sbpModalSlideIn 0.3s ease-out;
        }
        
        @keyframes sbpModalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .sbp-modal-header {
            background: linear-gradient(135deg, #0073aa, #00a32a);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sbp-modal-header h2 {
            margin: 0;
            font-size: 24px;
        }
        
        .sbp-modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s;
        }
        
        .sbp-modal-close:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .sbp-modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        /* Estado de optimización */
        .sbp-optimization-status {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .sbp-status-indicator {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        #sbp-status-icon {
            font-size: 24px;
        }
        
        #sbp-status-text {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .sbp-status-progress {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .sbp-status-progress .sbp-progress-bar {
            flex: 1;
            height: 12px;
        }
        
        #sbp-progress-percentage {
            font-weight: 600;
            color: #0073aa;
            min-width: 40px;
        }
        
        /* Controles */
        .sbp-optimization-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            justify-content: center;
        }
        
        .sbp-optimization-controls .sbp-btn {
            min-width: 120px;
        }
        
        /* Filtros */
        .sbp-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .sbp-filter-btn {
            padding: 8px 16px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .sbp-filter-btn.active {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
        }
        
        .sbp-filter-btn:hover {
            border-color: #0073aa;
            color: #0073aa;
        }
        
        .sbp-filter-btn.active:hover {
            color: white;
        }
        
        /* Lista de páginas */
        .sbp-pages-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
        }
        
        .sbp-page-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }
        
        .sbp-page-item:last-child {
            border-bottom: none;
        }
        
        .sbp-page-item:hover {
            background: #f8f9fa;
        }
        
        .sbp-page-status {
            font-size: 18px;
            margin-right: 15px;
            min-width: 25px;
        }
        
        .sbp-page-url {
            flex: 1;
            font-size: 14px;
            color: #333;
            word-break: break-all;
        }
        
        .sbp-page-time {
            font-size: 12px;
            color: #666;
            margin-left: 15px;
        }
        
        /* Estadísticas en tiempo real */
        .sbp-real-time-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #eee;
        }
        
        .sbp-stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .sbp-stat-item .sbp-stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .sbp-stat-item .sbp-stat-value {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        /* Progreso de Generación */
        .sbp-generation-progress {
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        /* Configuración Colapsable */
        .sbp-collapsible {
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0 0 20px 0;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .sbp-collapsible:hover {
            color: #0073aa;
        }
        
        .sbp-arrow {
            transition: transform 0.3s;
            font-size: 14px;
        }
        
        .sbp-arrow.rotated {
            transform: rotate(180deg);
        }
        
        .sbp-config-content {
            transition: all 0.3s;
        }
        
        /* Sistema Info */
        .sbp-system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 15px;
        }
        
        .sbp-info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 14px;
            border: 1px solid #dee2e6;
        }
        
        /* Warning */
        .sbp-warning {
            margin-top: 20px;
            padding: 15px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            color: #856404;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sbp-header-controls {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .sbp-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .sbp-actions {
                grid-template-columns: 1fr;
            }
            
            .sbp-system-info {
                grid-template-columns: 1fr;
            }
            
            .sbp-modal-content {
                width: 95%;
                margin: 5% auto;
            }
            
            .sbp-optimization-controls {
                flex-direction: column;
                align-items: center;
            }
            
            .sbp-filters {
                justify-content: center;
            }
        }
        </style>
        <?php
    }
    
    private function format_bytes($bytes, $precision = 1) {
        $units = array('B', 'KB', 'MB', 'GB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'settings_page_staticboost-pro') {
            return;
        }
        
        wp_enqueue_script(
            'sbp-admin',
            SBP_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            SBP_VERSION,
            true
        );
        
        wp_localize_script('sbp-admin', 'sbp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sbp_nonce')
        ));
    }
    
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $stats = sbp_get_cache_stats();
        
        $wp_admin_bar->add_menu(array(
            'id' => 'sbp-menu',
            'title' => '⚡ Estático (' . $stats['files'] . ')',
            'href' => admin_url('options-general.php?page=staticboost-pro')
        ));
    }
    
    // AJAX Handlers existentes...
    public function ajax_toggle_cache() {
        check_ajax_referer('sbp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $current_status = get_option('sbp_enabled', true);
        $new_status = !$current_status;
        
        update_option('sbp_enabled', $new_status);
        
        wp_send_json_success(array(
            'enabled' => $new_status,
            'message' => $new_status ? 'Sistema activado' : 'Sistema desactivado'
        ));
    }
    
    public function ajax_generate_all_pages() {
        check_ajax_referer('sbp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        // Aumentar límites para procesamiento masivo
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        
        try {
            $result = sbp_generate_all_static_pages();
            
            wp_send_json_success(array(
                'message' => 'Conversión completada exitosamente',
                'total' => $result['total'],
                'success' => $result['success'],
                'errors' => $result['errors']
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error durante la conversión: ' . $e->getMessage());
        }
    }
    
    public function ajax_clear_cache() {
        check_ajax_referer('sbp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $result = sbp_clear_all_cache();
        
        if ($result) {
            wp_send_json_success('Archivos estáticos limpiados exitosamente');
        } else {
            wp_send_json_error('Error al limpiar archivos estáticos');
        }
    }
    
    public function ajax_get_stats() {
        check_ajax_referer('sbp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $stats = sbp_get_cache_stats();
        wp_send_json_success($stats);
    }
    
    public function ajax_preload_cache() {
        check_ajax_referer('sbp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            $preloaded = sbp_preload_cache();
            
            wp_send_json_success(array(
                'message' => 'Conversión rápida completada exitosamente',
                'pages' => $preloaded
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error durante la conversión rápida: ' . $e->getMessage());
        }
    }
    
    public function ajax_optimize_assets() {
        check_ajax_referer('sbp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            // Ejecutar optimización de assets
            sbp_run_asset_optimization();
            
            // Ejecutar optimización de PageSpeed
            sbp_run_pagespeed_optimization();
            
            $mode = get_option('sbp_aggressive_optimization', false) ? 'agresivo' : 'seguro';
            $pagespeed = get_option('sbp_pagespeed_mode', true) ? ' + PageSpeed 100/100' : '';
            
            wp_send_json_success(array(
                'message' => "Optimización completada (modo {$mode}{$pagespeed})"
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error durante la optimización: ' . $e->getMessage());
        }
    }
    
    // NUEVOS AJAX handlers para seguimiento en tiempo real
    public function ajax_start_optimization() {
        check_ajax_referer('sbp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        // Inicializar estado de optimización
        update_option('sbp_optimization_status', 'running');
        update_option('sbp_optimization_progress', 0);
        update_option('sbp_optimization_current_page', 0);
        
        // Obtener lista de URLs a optimizar
        $urls = sbp_get_all_site_urls();
        update_option('sbp_optimization_urls', $urls);
        update_option('sbp_optimization_results', array());
        
        wp_send_json_success(array(
            'status' => 'started',
            'total_pages' => count($urls),
            'urls' => array_slice($urls, 0, 10) // Solo primeras 10 para mostrar
        ));
    }
    
    public function ajax_pause_optimization() {
        check_ajax_referer('sbp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        update_option('sbp_optimization_status', 'paused');
        
        wp_send_json_success(array(
            'status' => 'paused',
            'message' => 'Optimización pausada'
        ));
    }
    
    public function ajax_resume_optimization() {
        check_ajax_referer('sbp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        update_option('sbp_optimization_status', 'running');
        
        wp_send_json_success(array(
            'status' => 'running',
            'message' => 'Optimización reanudada'
        ));
    }
    
    public function ajax_stop_optimization() {
        check_ajax_referer('sbp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        update_option('sbp_optimization_status', 'stopped');
        
        wp_send_json_success(array(
            'status' => 'stopped',
            'message' => 'Optimización detenida'
        ));
    }
    
    public function ajax_get_optimization_status() {
        check_ajax_referer('sbp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $status = get_option('sbp_optimization_status', 'idle');
        $progress = get_option('sbp_optimization_progress', 0);
        $current_page = get_option('sbp_optimization_current_page', 0);
        $urls = get_option('sbp_optimization_urls', array());
        $results = get_option('sbp_optimization_results', array());
        
        $stats = array(
            'optimized' => 0,
            'processing' => 0,
            'pending' => 0,
            'errors' => 0
        );
        
        foreach ($results as $result) {
            if ($result['status'] === 'success') {
                $stats['optimized']++;
            } elseif ($result['status'] === 'processing') {
                $stats['processing']++;
            } elseif ($result['status'] === 'error') {
                $stats['errors']++;
            }
        }
        
        $stats['pending'] = count($urls) - $stats['optimized'] - $stats['errors'];
        
        wp_send_json_success(array(
            'status' => $status,
            'progress' => $progress,
            'current_page' => $current_page,
            'total_pages' => count($urls),
            'stats' => $stats,
            'recent_results' => array_slice($results, -10) // Últimos 10 resultados
        ));
    }
}