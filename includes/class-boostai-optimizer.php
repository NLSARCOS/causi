<?php
/**
 * BoostAI™ Optimizer - Sistema ML Propietario OPTIMIZADO
 */
class SBP_BoostAI_Optimizer {
    
    private $model_path;
    private $analytics_threshold = 50;
    
    public function __construct() {
        // Solo cargar si está habilitado
        if (!get_option('sbp_boostai_enabled', true)) {
            return;
        }
        
        $this->model_path = SBP_ML_DIR . 'boostai-model.json';
        
        // Solo cargar scripts en frontend
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_boostai_scripts'));
            add_action('wp_footer', array($this, 'inject_boostai_tracker'), 999);
        }
        
        // AJAX handlers
        add_action('wp_ajax_sbp_track_metrics', array($this, 'track_user_metrics'));
        add_action('wp_ajax_nopriv_sbp_track_metrics', array($this, 'track_user_metrics'));
        
        // Análisis programado (solo si hay suficientes datos)
        add_action('sbp_boostai_analysis', array($this, 'run_adaptive_analysis'));
    }
    
    /**
     * Cargar scripts de BoostAI™ - OPTIMIZADO
     */
    public function enqueue_boostai_scripts() {
        // Solo cargar si no es bot
        if ($this->is_bot_request()) {
            return;
        }
        
        // TensorFlow.js (versión ligera) - Solo si es necesario
        wp_enqueue_script(
            'tensorflow-js',
            'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.10.0/dist/tf.min.js',
            array(),
            '4.10.0',
            true
        );
        
        // BoostAI™ Optimizer
        wp_enqueue_script(
            'sbp-boostai-optimizer',
            SBP_PLUGIN_URL . 'assets/ml-optimizer.js',
            array('tensorflow-js'),
            SBP_VERSION,
            true
        );
        
        // Configuración adaptativa
        $adaptive_config = $this->get_adaptive_config();
        
        wp_localize_script('sbp-boostai-optimizer', 'sbpBoostAI', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sbp_boostai_nonce'),
            'model_url' => SBP_PLUGIN_URL . 'ml/boostai-model.json',
            'config' => $adaptive_config,
            'session_id' => $this->get_session_id(),
            'page_url' => get_permalink(),
            'device_type' => wp_is_mobile() ? 'mobile' : 'desktop'
        ));
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
    
    /**
     * Obtener configuración adaptativa CONSERVADORA
     */
    private function get_adaptive_config() {
        // Configuración por defecto CONSERVADORA
        $config = array(
            'scroll_prediction_threshold' => 0.8,
            'preload_distance' => 150,
            'lazy_load_threshold' => 200,
            'critical_css_inline' => false,
            'prefetch_next_page' => false
        );
        
        // Solo consultar DB si hay tablas creadas
        global $wpdb;
        $table = $wpdb->prefix . 'sbp_adaptive_config';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
            $current_url = $_SERVER['REQUEST_URI'];
            $device_type = wp_is_mobile() ? 'mobile' : 'desktop';
            
            $configs = $wpdb->get_results($wpdb->prepare("
                SELECT config_key, config_value 
                FROM $table 
                WHERE (page_pattern IS NULL OR %s LIKE CONCAT('%%', page_pattern, '%%'))
                AND (device_type IS NULL OR device_type = %s)
                ORDER BY page_pattern DESC, device_type DESC
                LIMIT 10
            ", $current_url, $device_type));
            
            foreach ($configs as $row) {
                $config[$row->config_key] = json_decode($row->config_value, true);
            }
        }
        
        return $config;
    }
    
    /**
     * Generar ID de sesión único
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['sbp_session_id'])) {
            $_SESSION['sbp_session_id'] = wp_generate_password(32, false);
        }
        
        return $_SESSION['sbp_session_id'];
    }
    
    /**
     * Rastrear métricas de usuario (AJAX) - OPTIMIZADO
     */
    public function track_user_metrics() {
        check_ajax_referer('sbp_boostai_nonce', 'nonce');
        
        global $wpdb;
        
        $table = $wpdb->prefix . 'sbp_user_metrics';
        
        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            wp_send_json_error('Tabla de métricas no existe');
            return;
        }
        
        $data = array(
            'session_id' => sanitize_text_field($_POST['session_id']),
            'page_url' => esc_url_raw($_POST['page_url']),
            'viewport_width' => intval($_POST['viewport_width']),
            'viewport_height' => intval($_POST['viewport_height']),
            'scroll_depth' => floatval($_POST['scroll_depth']),
            'time_on_page' => intval($_POST['time_on_page']),
            'lcp_time' => isset($_POST['lcp_time']) ? floatval($_POST['lcp_time']) : null,
            'fid_time' => isset($_POST['fid_time']) ? floatval($_POST['fid_time']) : null,
            'cls_score' => isset($_POST['cls_score']) ? floatval($_POST['cls_score']) : null,
            'device_type' => sanitize_text_field($_POST['device_type']),
            'connection_type' => isset($_POST['connection_type']) ? sanitize_text_field($_POST['connection_type']) : null
        );
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            wp_send_json_success('Métricas guardadas');
        } else {
            wp_send_json_error('Error al guardar métricas');
        }
    }
    
    /**
     * Ejecutar análisis adaptativo CONSERVADOR
     */
    public function run_adaptive_analysis() {
        global $wpdb;
        
        $metrics_table = $wpdb->prefix . 'sbp_user_metrics';
        $config_table = $wpdb->prefix . 'sbp_adaptive_config';
        
        // Verificar si las tablas existen
        if ($wpdb->get_var("SHOW TABLES LIKE '$metrics_table'") != $metrics_table) {
            return;
        }
        
        // Verificar si tenemos suficientes datos
        $total_metrics = $wpdb->get_var("SELECT COUNT(*) FROM $metrics_table WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        
        if ($total_metrics < $this->analytics_threshold) {
            return;
        }
        
        // Análisis CONSERVADOR por tipo de dispositivo
        $device_types = array('mobile', 'desktop');
        
        foreach ($device_types as $device_type) {
            $this->analyze_device_metrics_conservatively($device_type);
        }
    }
    
    /**
     * Analizar métricas de forma CONSERVADORA
     */
    private function analyze_device_metrics_conservatively($device_type) {
        global $wpdb;
        
        $metrics_table = $wpdb->prefix . 'sbp_user_metrics';
        $config_table = $wpdb->prefix . 'sbp_adaptive_config';
        
        // Obtener métricas promedio de los últimos 7 días
        $metrics = $wpdb->get_row($wpdb->prepare("
            SELECT 
                AVG(scroll_depth) as avg_scroll_depth,
                AVG(time_on_page) as avg_time_on_page,
                AVG(lcp_time) as avg_lcp,
                AVG(viewport_height) as avg_viewport_height,
                COUNT(*) as total_sessions
            FROM $metrics_table 
            WHERE device_type = %s 
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", $device_type));
        
        if (!$metrics || $metrics->total_sessions < 20) {
            return;
        }
        
        // Calcular configuraciones CONSERVADORAS basadas en datos reales
        $new_configs = array();
        
        // Ajustar umbral de predicción de scroll (CONSERVADOR)
        if ($metrics->avg_scroll_depth > 0.9) {
            $new_configs['scroll_prediction_threshold'] = 0.7;
            $new_configs['preload_distance'] = 200;
        } elseif ($metrics->avg_scroll_depth < 0.2) {
            $new_configs['scroll_prediction_threshold'] = 0.9;
            $new_configs['preload_distance'] = 100;
        }
        
        // Ajustar lazy loading basado en viewport (CONSERVADOR)
        if ($metrics->avg_viewport_height < 500) {
            $new_configs['lazy_load_threshold'] = 150;
        } elseif ($metrics->avg_viewport_height > 800) {
            $new_configs['lazy_load_threshold'] = 300;
        }
        
        // Guardar configuraciones adaptativas SOLO si hay cambios significativos
        foreach ($new_configs as $key => $value) {
            $existing = $wpdb->get_var($wpdb->prepare("
                SELECT config_value FROM $config_table 
                WHERE config_key = %s AND device_type = %s AND page_pattern IS NULL
            ", $key, $device_type));
            
            $existing_value = $existing ? json_decode($existing, true) : null;
            
            // Solo actualizar si hay cambio significativo
            if ($existing_value !== $value) {
                $wpdb->replace($config_table, array(
                    'config_key' => $key,
                    'config_value' => json_encode($value),
                    'device_type' => $device_type,
                    'page_pattern' => null
                ));
            }
        }
    }
    
    /**
     * Inyectar tracker BoostAI™ en el footer - OPTIMIZADO
     */
    public function inject_boostai_tracker() {
        // Solo para visitantes anónimos
        if (is_user_logged_in() || $this->is_bot_request()) {
            return;
        }
        
        echo '<script id="sbp-boostai-tracker">
        // BoostAI™ Tracker - Inicializar cuando el DOM esté listo
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof window.SBPBoostAI !== "undefined") {
                window.SBPBoostAI.init();
            }
        });
        </script>';
    }
}