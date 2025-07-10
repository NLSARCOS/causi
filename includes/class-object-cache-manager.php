<?php
/**
 * Gestor de Object Cache (Redis/Memcached) para StaticBoost Pro
 */
class SBP_Object_Cache_Manager {
    
    private $cache_type = null;
    private $redis_client = null;
    private $memcached_client = null;
    private $cache_prefix = 'sbp_';
    private $default_ttl = 3600;
    
    public function __construct() {
        $this->detect_cache_system();
        $this->init_cache_client();
        
        // Hooks para integración con WordPress
        add_action('init', array($this, 'setup_cache_hooks'), 1);
        add_action('wp_cache_flush', array($this, 'flush_all_cache'));
    }
    
    /**
     * Detectar sistema de caché disponible
     */
    private function detect_cache_system() {
        // Prioridad: Redis > Memcached > APCu > Archivo
        if (class_exists('Redis') && extension_loaded('redis')) {
            $this->cache_type = 'redis';
        } elseif (class_exists('Memcached') && extension_loaded('memcached')) {
            $this->cache_type = 'memcached';
        } elseif (extension_loaded('apcu') && function_exists('apcu_store')) {
            $this->cache_type = 'apcu';
        } else {
            $this->cache_type = 'file';
        }
        
        // Guardar tipo detectado
        update_option('sbp_cache_type_detected', $this->cache_type);
    }
    
    /**
     * Inicializar cliente de caché
     */
    private function init_cache_client() {
        try {
            switch ($this->cache_type) {
                case 'redis':
                    $this->init_redis();
                    break;
                case 'memcached':
                    $this->init_memcached();
                    break;
                case 'apcu':
                    // APCu no necesita inicialización
                    break;
                case 'file':
                    // File cache no necesita inicialización
                    break;
            }
        } catch (Exception $e) {
            error_log('SBP Cache Error: ' . $e->getMessage());
            $this->cache_type = 'file'; // Fallback
        }
    }
    
    /**
     * Inicializar Redis
     */
    private function init_redis() {
        $this->redis_client = new Redis();
        
        // Configuración Redis
        $redis_host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1';
        $redis_port = defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379;
        $redis_password = defined('WP_REDIS_PASSWORD') ? WP_REDIS_PASSWORD : null;
        $redis_database = defined('WP_REDIS_DATABASE') ? WP_REDIS_DATABASE : 0;
        
        // Conectar
        $connected = $this->redis_client->connect($redis_host, $redis_port, 1); // 1 segundo timeout
        
        if (!$connected) {
            throw new Exception('No se pudo conectar a Redis');
        }
        
        // Autenticación si es necesaria
        if ($redis_password) {
            $this->redis_client->auth($redis_password);
        }
        
        // Seleccionar base de datos
        $this->redis_client->select($redis_database);
        
        // Configurar serialización
        $this->redis_client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        $this->redis_client->setOption(Redis::OPT_PREFIX, $this->cache_prefix);
    }
    
    /**
     * Inicializar Memcached
     */
    private function init_memcached() {
        $this->memcached_client = new Memcached('sbp_cache');
        
        // Configuración Memcached
        $memcached_host = defined('WP_MEMCACHED_HOST') ? WP_MEMCACHED_HOST : '127.0.0.1';
        $memcached_port = defined('WP_MEMCACHED_PORT') ? WP_MEMCACHED_PORT : 11211;
        
        // Añadir servidor si no existe
        if (empty($this->memcached_client->getServerList())) {
            $this->memcached_client->addServer($memcached_host, $memcached_port);
        }
        
        // Configurar opciones
        $this->memcached_client->setOptions(array(
            Memcached::OPT_COMPRESSION => true,
            Memcached::OPT_SERIALIZER => Memcached::SERIALIZER_PHP,
            Memcached::OPT_PREFIX_KEY => $this->cache_prefix,
            Memcached::OPT_DISTRIBUTION => Memcached::DISTRIBUTION_CONSISTENT,
            Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
            Memcached::OPT_NO_BLOCK => true,
            Memcached::OPT_TCP_NODELAY => true,
            Memcached::OPT_CONNECT_TIMEOUT => 1000, // 1 segundo
            Memcached::OPT_POLL_TIMEOUT => 1000,
            Memcached::OPT_RECV_TIMEOUT => 1000,
            Memcached::OPT_SEND_TIMEOUT => 1000
        ));
        
        // Verificar conexión
        $version = $this->memcached_client->getVersion();
        if (!$version) {
            throw new Exception('No se pudo conectar a Memcached');
        }
    }
    
    /**
     * Configurar hooks de caché
     */
    public function setup_cache_hooks() {
        // Cachear consultas de WordPress
        add_filter('posts_pre_query', array($this, 'cache_posts_query'), 10, 2);
        add_action('save_post', array($this, 'invalidate_post_cache'));
        add_action('delete_post', array($this, 'invalidate_post_cache'));
        
        // Cachear opciones
        add_filter('pre_option', array($this, 'cache_option'), 10, 3);
        add_action('updated_option', array($this, 'invalidate_option_cache'), 10, 3);
        
        // Cachear transients
        add_filter('pre_transient', array($this, 'cache_transient'), 10, 2);
        add_action('set_transient', array($this, 'set_transient_cache'), 10, 3);
        
        // Cachear metadatos
        add_filter('get_post_metadata', array($this, 'cache_post_meta'), 10, 4);
        add_action('updated_post_meta', array($this, 'invalidate_post_meta_cache'), 10, 4);
    }
    
    /**
     * Obtener valor del caché
     */
    public function get($key, $default = false) {
        $cache_key = $this->get_cache_key($key);
        
        switch ($this->cache_type) {
            case 'redis':
                if ($this->redis_client) {
                    $value = $this->redis_client->get($cache_key);
                    return $value !== false ? $value : $default;
                }
                break;
                
            case 'memcached':
                if ($this->memcached_client) {
                    $value = $this->memcached_client->get($cache_key);
                    return $this->memcached_client->getResultCode() === Memcached::RES_SUCCESS ? $value : $default;
                }
                break;
                
            case 'apcu':
                $success = false;
                $value = apcu_fetch($cache_key, $success);
                return $success ? $value : $default;
                
            case 'file':
                return $this->get_file_cache($cache_key, $default);
        }
        
        return $default;
    }
    
    /**
     * Guardar valor en caché
     */
    public function set($key, $value, $ttl = null) {
        $cache_key = $this->get_cache_key($key);
        $ttl = $ttl ?: $this->default_ttl;
        
        switch ($this->cache_type) {
            case 'redis':
                if ($this->redis_client) {
                    return $this->redis_client->setex($cache_key, $ttl, $value);
                }
                break;
                
            case 'memcached':
                if ($this->memcached_client) {
                    return $this->memcached_client->set($cache_key, $value, $ttl);
                }
                break;
                
            case 'apcu':
                return apcu_store($cache_key, $value, $ttl);
                
            case 'file':
                return $this->set_file_cache($cache_key, $value, $ttl);
        }
        
        return false;
    }
    
    /**
     * Eliminar valor del caché
     */
    public function delete($key) {
        $cache_key = $this->get_cache_key($key);
        
        switch ($this->cache_type) {
            case 'redis':
                if ($this->redis_client) {
                    return $this->redis_client->del($cache_key) > 0;
                }
                break;
                
            case 'memcached':
                if ($this->memcached_client) {
                    return $this->memcached_client->delete($cache_key);
                }
                break;
                
            case 'apcu':
                return apcu_delete($cache_key);
                
            case 'file':
                return $this->delete_file_cache($cache_key);
        }
        
        return false;
    }
    
    /**
     * Limpiar todo el caché
     */
    public function flush_all_cache() {
        switch ($this->cache_type) {
            case 'redis':
                if ($this->redis_client) {
                    return $this->redis_client->flushDB();
                }
                break;
                
            case 'memcached':
                if ($this->memcached_client) {
                    return $this->memcached_client->flush();
                }
                break;
                
            case 'apcu':
                return apcu_clear_cache();
                
            case 'file':
                return $this->flush_file_cache();
        }
        
        return false;
    }
    
    /**
     * Cachear consultas de posts
     */
    public function cache_posts_query($posts, $query) {
        if ($query->is_main_query() && !is_admin()) {
            $cache_key = 'posts_query_' . md5(serialize($query->query_vars));
            
            $cached_posts = $this->get($cache_key);
            if ($cached_posts !== false) {
                return $cached_posts;
            }
            
            // Si no hay caché, permitir que la consulta continúe
            // y cachear el resultado en 'the_posts'
            add_filter('the_posts', function($posts) use ($cache_key) {
                $this->set($cache_key, $posts, 1800); // 30 minutos
                return $posts;
            }, 10, 1);
        }
        
        return $posts;
    }
    
    /**
     * Invalidar caché de posts
     */
    public function invalidate_post_cache($post_id) {
        // Limpiar caché relacionado con este post
        $this->delete('post_' . $post_id);
        $this->delete('post_meta_' . $post_id);
        
        // Limpiar consultas de posts (patrón)
        $this->delete_pattern('posts_query_*');
    }
    
    /**
     * Cachear opciones
     */
    public function cache_option($pre_option, $option, $default) {
        // Solo cachear opciones específicas para evitar problemas
        $cacheable_options = array(
            'blogname', 'blogdescription', 'admin_email', 'users_can_register',
            'default_role', 'timezone_string', 'date_format', 'time_format',
            'start_of_week', 'template', 'stylesheet', 'posts_per_page'
        );
        
        if (in_array($option, $cacheable_options)) {
            $cache_key = 'option_' . $option;
            $cached_value = $this->get($cache_key);
            
            if ($cached_value !== false) {
                return $cached_value;
            }
        }
        
        return $pre_option;
    }
    
    /**
     * Invalidar caché de opciones
     */
    public function invalidate_option_cache($option, $old_value, $value) {
        $this->delete('option_' . $option);
    }
    
    /**
     * Cachear transients
     */
    public function cache_transient($pre_transient, $transient) {
        $cache_key = 'transient_' . $transient;
        return $this->get($cache_key);
    }
    
    /**
     * Guardar transient en caché
     */
    public function set_transient_cache($transient, $value, $expiration) {
        $cache_key = 'transient_' . $transient;
        $this->set($cache_key, $value, $expiration);
    }
    
    /**
     * Cachear metadatos de posts
     */
    public function cache_post_meta($metadata, $object_id, $meta_key, $single) {
        if ($meta_key) {
            $cache_key = 'post_meta_' . $object_id . '_' . $meta_key;
            $cached_meta = $this->get($cache_key);
            
            if ($cached_meta !== false) {
                return $single ? array($cached_meta) : $cached_meta;
            }
        }
        
        return $metadata;
    }
    
    /**
     * Invalidar caché de metadatos
     */
    public function invalidate_post_meta_cache($meta_id, $object_id, $meta_key, $meta_value) {
        $this->delete('post_meta_' . $object_id . '_' . $meta_key);
        $this->delete('post_meta_' . $object_id);
    }
    
    /**
     * Funciones auxiliares
     */
    private function get_cache_key($key) {
        return $this->cache_prefix . md5($key);
    }
    
    private function delete_pattern($pattern) {
        switch ($this->cache_type) {
            case 'redis':
                if ($this->redis_client) {
                    $keys = $this->redis_client->keys($this->cache_prefix . str_replace('*', '*', $pattern));
                    if ($keys) {
                        return $this->redis_client->del($keys);
                    }
                }
                break;
                
            case 'memcached':
                // Memcached no soporta patrones, limpiar todo
                if ($this->memcached_client) {
                    return $this->memcached_client->flush();
                }
                break;
                
            case 'apcu':
                // APCu no soporta patrones eficientemente
                return apcu_clear_cache();
                
            case 'file':
                return $this->delete_file_pattern($pattern);
        }
        
        return false;
    }
    
    /**
     * Caché de archivos (fallback)
     */
    private function get_file_cache($key, $default) {
        $cache_file = SBP_CACHE_DIR . 'object/' . $key . '.cache';
        
        if (!file_exists($cache_file)) {
            return $default;
        }
        
        $cache_data = file_get_contents($cache_file);
        $cache_data = unserialize($cache_data);
        
        if (!$cache_data || $cache_data['expires'] < time()) {
            unlink($cache_file);
            return $default;
        }
        
        return $cache_data['value'];
    }
    
    private function set_file_cache($key, $value, $ttl) {
        $cache_dir = SBP_CACHE_DIR . 'object/';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
        $cache_file = $cache_dir . $key . '.cache';
        $cache_data = array(
            'value' => $value,
            'expires' => time() + $ttl
        );
        
        return file_put_contents($cache_file, serialize($cache_data), LOCK_EX) !== false;
    }
    
    private function delete_file_cache($key) {
        $cache_file = SBP_CACHE_DIR . 'object/' . $key . '.cache';
        return file_exists($cache_file) ? unlink($cache_file) : true;
    }
    
    private function flush_file_cache() {
        $cache_dir = SBP_CACHE_DIR . 'object/';
        if (!is_dir($cache_dir)) {
            return true;
        }
        
        $files = glob($cache_dir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    private function delete_file_pattern($pattern) {
        $cache_dir = SBP_CACHE_DIR . 'object/';
        $pattern = str_replace('*', '*', $pattern);
        $files = glob($cache_dir . $pattern . '.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    /**
     * Obtener estadísticas del caché
     */
    public function get_cache_stats() {
        $stats = array(
            'type' => $this->cache_type,
            'status' => 'disconnected',
            'memory_usage' => 0,
            'hit_ratio' => 0,
            'keys_count' => 0
        );
        
        try {
            switch ($this->cache_type) {
                case 'redis':
                    if ($this->redis_client && $this->redis_client->ping()) {
                        $info = $this->redis_client->info();
                        $stats['status'] = 'connected';
                        $stats['memory_usage'] = $info['used_memory_human'] ?? '0B';
                        $stats['keys_count'] = $this->redis_client->dbSize();
                    }
                    break;
                    
                case 'memcached':
                    if ($this->memcached_client) {
                        $server_stats = $this->memcached_client->getStats();
                        if ($server_stats) {
                            $stats['status'] = 'connected';
                            $first_server = array_values($server_stats)[0];
                            $stats['memory_usage'] = round($first_server['bytes'] / 1024 / 1024, 2) . 'MB';
                            $stats['keys_count'] = $first_server['curr_items'];
                            $stats['hit_ratio'] = round(($first_server['get_hits'] / max($first_server['cmd_get'], 1)) * 100, 2);
                        }
                    }
                    break;
                    
                case 'apcu':
                    if (function_exists('apcu_cache_info')) {
                        $info = apcu_cache_info();
                        $stats['status'] = 'connected';
                        $stats['memory_usage'] = round($info['mem_size'] / 1024 / 1024, 2) . 'MB';
                        $stats['keys_count'] = $info['num_entries'];
                        $stats['hit_ratio'] = round(($info['num_hits'] / max($info['num_hits'] + $info['num_misses'], 1)) * 100, 2);
                    }
                    break;
                    
                case 'file':
                    $stats['status'] = 'connected';
                    $cache_dir = SBP_CACHE_DIR . 'object/';
                    if (is_dir($cache_dir)) {
                        $files = glob($cache_dir . '*.cache');
                        $stats['keys_count'] = count($files);
                        $total_size = 0;
                        foreach ($files as $file) {
                            $total_size += filesize($file);
                        }
                        $stats['memory_usage'] = round($total_size / 1024 / 1024, 2) . 'MB';
                    }
                    break;
            }
        } catch (Exception $e) {
            error_log('SBP Cache Stats Error: ' . $e->getMessage());
        }
        
        return $stats;
    }
}