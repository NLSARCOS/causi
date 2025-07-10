<?php
/**
 * Gestor de Object Cache AUTOMÁTICO (Redis/Memcached) para StaticBoost Pro
 * Detección automática sin configuración manual
 */
class SBP_Object_Cache_Manager {
    
    private $cache_type = null;
    private $redis_client = null;
    private $memcached_client = null;
    private $cache_prefix = 'sbp_';
    private $default_ttl = 3600;
    private $auto_detected = false;
    
    public function __construct() {
        $this->auto_detect_and_connect();
        $this->init_cache_client();
        
        // Hooks para integración con WordPress
        add_action('init', array($this, 'setup_cache_hooks'), 1);
        add_action('wp_cache_flush', array($this, 'flush_all_cache'));
    }
    
    /**
     * DETECCIÓN AUTOMÁTICA - Como Object Cache Pro
     */
    private function auto_detect_and_connect() {
        // 1. REDIS - Detección automática de configuraciones comunes
        if ($this->try_redis_auto_detection()) {
            $this->cache_type = 'redis';
            $this->auto_detected = true;
            return;
        }
        
        // 2. MEMCACHED - Detección automática
        if ($this->try_memcached_auto_detection()) {
            $this->cache_type = 'memcached';
            $this->auto_detected = true;
            return;
        }
        
        // 3. APCu - Si está disponible
        if (extension_loaded('apcu') && function_exists('apcu_store')) {
            $this->cache_type = 'apcu';
            $this->auto_detected = true;
            return;
        }
        
        // 4. Fallback a File Cache
        $this->cache_type = 'file';
        
        // Guardar tipo detectado
        update_option('sbp_cache_type_detected', $this->cache_type);
        update_option('sbp_cache_auto_detected', $this->auto_detected);
    }
    
    /**
     * Detección automática de Redis
     */
    private function try_redis_auto_detection() {
        if (!class_exists('Redis') || !extension_loaded('redis')) {
            return false;
        }
        
        // Configuraciones comunes a probar
        $redis_configs = array(
            // Configuración por defecto
            array('host' => '127.0.0.1', 'port' => 6379, 'password' => null, 'database' => 0),
            array('host' => 'localhost', 'port' => 6379, 'password' => null, 'database' => 0),
            
            // Configuraciones de hosting populares
            array('host' => '127.0.0.1', 'port' => 6380, 'password' => null, 'database' => 0), // Cloudways
            array('host' => 'redis', 'port' => 6379, 'password' => null, 'database' => 0), // Docker
            array('host' => 'localhost', 'port' => 6380, 'password' => null, 'database' => 0),
            
            // Si hay variables de entorno
            array(
                'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
                'port' => getenv('REDIS_PORT') ?: 6379,
                'password' => getenv('REDIS_PASSWORD') ?: null,
                'database' => getenv('REDIS_DATABASE') ?: 0
            ),
            
            // Si hay constantes definidas
            array(
                'host' => defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1',
                'port' => defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379,
                'password' => defined('WP_REDIS_PASSWORD') ? WP_REDIS_PASSWORD : null,
                'database' => defined('WP_REDIS_DATABASE') ? WP_REDIS_DATABASE : 0
            )
        );
        
        foreach ($redis_configs as $config) {
            if ($this->test_redis_connection($config)) {
                // Guardar configuración que funcionó
                update_option('sbp_redis_config', $config);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Probar conexión Redis
     */
    private function test_redis_connection($config) {
        try {
            $redis = new Redis();
            
            // Timeout corto para no bloquear
            $connected = $redis->connect($config['host'], $config['port'], 0.5);
            
            if (!$connected) {
                return false;
            }
            
            // Autenticación si es necesaria
            if ($config['password']) {
                if (!$redis->auth($config['password'])) {
                    $redis->close();
                    return false;
                }
            }
            
            // Seleccionar base de datos
            if (!$redis->select($config['database'])) {
                $redis->close();
                return false;
            }
            
            // Probar operación básica
            $test_key = 'sbp_test_' . time();
            $redis->setex($test_key, 1, 'test');
            $result = $redis->get($test_key);
            $redis->del($test_key);
            
            $redis->close();
            
            return $result === 'test';
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Detección automática de Memcached
     */
    private function try_memcached_auto_detection() {
        if (!class_exists('Memcached') || !extension_loaded('memcached')) {
            return false;
        }
        
        // Configuraciones comunes a probar
        $memcached_configs = array(
            array('host' => '127.0.0.1', 'port' => 11211),
            array('host' => 'localhost', 'port' => 11211),
            array('host' => 'memcached', 'port' => 11211), // Docker
            array('host' => '127.0.0.1', 'port' => 11212), // Configuración alternativa
            
            // Variables de entorno
            array(
                'host' => getenv('MEMCACHED_HOST') ?: '127.0.0.1',
                'port' => getenv('MEMCACHED_PORT') ?: 11211
            ),
            
            // Constantes
            array(
                'host' => defined('WP_MEMCACHED_HOST') ? WP_MEMCACHED_HOST : '127.0.0.1',
                'port' => defined('WP_MEMCACHED_PORT') ? WP_MEMCACHED_PORT : 11211
            )
        );
        
        foreach ($memcached_configs as $config) {
            if ($this->test_memcached_connection($config)) {
                // Guardar configuración que funcionó
                update_option('sbp_memcached_config', $config);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Probar conexión Memcached
     */
    private function test_memcached_connection($config) {
        try {
            $memcached = new Memcached('sbp_test');
            
            // Configurar timeouts cortos
            $memcached->setOptions(array(
                Memcached::OPT_CONNECT_TIMEOUT => 500, // 0.5 segundos
                Memcached::OPT_POLL_TIMEOUT => 500,
                Memcached::OPT_RECV_TIMEOUT => 500,
                Memcached::OPT_SEND_TIMEOUT => 500
            ));
            
            // Añadir servidor
            $memcached->addServer($config['host'], $config['port']);
            
            // Probar operación básica
            $test_key = 'sbp_test_' . time();
            $memcached->set($test_key, 'test', 1);
            $result = $memcached->get($test_key);
            $memcached->delete($test_key);
            
            return $result === 'test';
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Inicializar cliente de caché
     */
    private function init_cache_client() {
        try {
            switch ($this->cache_type) {
                case 'redis':
                    $this->init_redis_client();
                    break;
                case 'memcached':
                    $this->init_memcached_client();
                    break;
                case 'apcu':
                    // APCu no necesita inicialización
                    break;
                case 'file':
                    $this->init_file_cache_dir();
                    break;
            }
        } catch (Exception $e) {
            error_log('SBP Cache Init Error: ' . $e->getMessage());
            $this->cache_type = 'file'; // Fallback
            $this->init_file_cache_dir();
        }
    }
    
    /**
     * Inicializar cliente Redis
     */
    private function init_redis_client() {
        $config = get_option('sbp_redis_config');
        if (!$config) {
            throw new Exception('No Redis config found');
        }
        
        $this->redis_client = new Redis();
        
        // Conectar con timeout
        $connected = $this->redis_client->connect($config['host'], $config['port'], 1);
        
        if (!$connected) {
            throw new Exception('Redis connection failed');
        }
        
        // Autenticación
        if ($config['password']) {
            $this->redis_client->auth($config['password']);
        }
        
        // Base de datos
        $this->redis_client->select($config['database']);
        
        // Configurar serialización
        $this->redis_client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        $this->redis_client->setOption(Redis::OPT_PREFIX, $this->cache_prefix);
        
        // Configurar compresión si está disponible
        if (defined('Redis::OPT_COMPRESSION')) {
            $this->redis_client->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZ4);
        }
    }
    
    /**
     * Inicializar cliente Memcached
     */
    private function init_memcached_client() {
        $config = get_option('sbp_memcached_config');
        if (!$config) {
            throw new Exception('No Memcached config found');
        }
        
        $this->memcached_client = new Memcached('sbp_cache');
        
        // Añadir servidor si no existe
        if (empty($this->memcached_client->getServerList())) {
            $this->memcached_client->addServer($config['host'], $config['port']);
        }
        
        // Configurar opciones optimizadas
        $this->memcached_client->setOptions(array(
            Memcached::OPT_COMPRESSION => true,
            Memcached::OPT_SERIALIZER => Memcached::SERIALIZER_PHP,
            Memcached::OPT_PREFIX_KEY => $this->cache_prefix,
            Memcached::OPT_DISTRIBUTION => Memcached::DISTRIBUTION_CONSISTENT,
            Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
            Memcached::OPT_NO_BLOCK => true,
            Memcached::OPT_TCP_NODELAY => true,
            Memcached::OPT_CONNECT_TIMEOUT => 1000,
            Memcached::OPT_POLL_TIMEOUT => 1000,
            Memcached::OPT_RECV_TIMEOUT => 1000,
            Memcached::OPT_SEND_TIMEOUT => 1000,
            Memcached::OPT_RETRY_TIMEOUT => 1
        ));
        
        // Verificar conexión
        $version = $this->memcached_client->getVersion();
        if (!$version) {
            throw new Exception('Memcached connection failed');
        }
    }
    
    /**
     * Inicializar directorio de file cache
     */
    private function init_file_cache_dir() {
        $cache_dir = SBP_CACHE_DIR . 'object/';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
    }
    
    /**
     * Configurar hooks de caché
     */
    public function setup_cache_hooks() {
        // Solo si el caché está funcionando
        if ($this->is_cache_available()) {
            // Cachear consultas críticas
            add_filter('posts_pre_query', array($this, 'cache_posts_query'), 10, 2);
            add_action('save_post', array($this, 'invalidate_post_cache'));
            add_action('delete_post', array($this, 'invalidate_post_cache'));
            
            // Cachear opciones frecuentes
            add_filter('pre_option', array($this, 'cache_option'), 10, 3);
            add_action('updated_option', array($this, 'invalidate_option_cache'), 10, 3);
            
            // Cachear transients
            add_filter('pre_transient', array($this, 'cache_transient'), 10, 2);
            add_action('set_transient', array($this, 'set_transient_cache'), 10, 3);
        }
    }
    
    /**
     * Verificar si el caché está disponible
     */
    private function is_cache_available() {
        switch ($this->cache_type) {
            case 'redis':
                return $this->redis_client && $this->redis_client->ping();
            case 'memcached':
                return $this->memcached_client && !empty($this->memcached_client->getVersion());
            case 'apcu':
                return function_exists('apcu_store');
            case 'file':
                return is_writable(SBP_CACHE_DIR);
        }
        return false;
    }
    
    /**
     * Obtener valor del caché
     */
    public function get($key, $default = false) {
        if (!$this->is_cache_available()) {
            return $default;
        }
        
        $cache_key = $this->get_cache_key($key);
        
        try {
            switch ($this->cache_type) {
                case 'redis':
                    $value = $this->redis_client->get($cache_key);
                    return $value !== false ? $value : $default;
                    
                case 'memcached':
                    $value = $this->memcached_client->get($cache_key);
                    return $this->memcached_client->getResultCode() === Memcached::RES_SUCCESS ? $value : $default;
                    
                case 'apcu':
                    $success = false;
                    $value = apcu_fetch($cache_key, $success);
                    return $success ? $value : $default;
                    
                case 'file':
                    return $this->get_file_cache($cache_key, $default);
            }
        } catch (Exception $e) {
            error_log('SBP Cache Get Error: ' . $e->getMessage());
        }
        
        return $default;
    }
    
    /**
     * Guardar valor en caché
     */
    public function set($key, $value, $ttl = null) {
        if (!$this->is_cache_available()) {
            return false;
        }
        
        $cache_key = $this->get_cache_key($key);
        $ttl = $ttl ?: $this->default_ttl;
        
        try {
            switch ($this->cache_type) {
                case 'redis':
                    return $this->redis_client->setex($cache_key, $ttl, $value);
                    
                case 'memcached':
                    return $this->memcached_client->set($cache_key, $value, $ttl);
                    
                case 'apcu':
                    return apcu_store($cache_key, $value, $ttl);
                    
                case 'file':
                    return $this->set_file_cache($cache_key, $value, $ttl);
            }
        } catch (Exception $e) {
            error_log('SBP Cache Set Error: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Eliminar valor del caché
     */
    public function delete($key) {
        if (!$this->is_cache_available()) {
            return false;
        }
        
        $cache_key = $this->get_cache_key($key);
        
        try {
            switch ($this->cache_type) {
                case 'redis':
                    return $this->redis_client->del($cache_key) > 0;
                    
                case 'memcached':
                    return $this->memcached_client->delete($cache_key);
                    
                case 'apcu':
                    return apcu_delete($cache_key);
                    
                case 'file':
                    return $this->delete_file_cache($cache_key);
            }
        } catch (Exception $e) {
            error_log('SBP Cache Delete Error: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Limpiar todo el caché
     */
    public function flush_all_cache() {
        try {
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
        } catch (Exception $e) {
            error_log('SBP Cache Flush Error: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Cachear consultas de posts (solo las más importantes)
     */
    public function cache_posts_query($posts, $query) {
        if ($query->is_main_query() && !is_admin() && !$query->is_search()) {
            $cache_key = 'posts_query_' . md5(serialize(array(
                'post_type' => $query->get('post_type'),
                'posts_per_page' => $query->get('posts_per_page'),
                'paged' => $query->get('paged'),
                'meta_query' => $query->get('meta_query'),
                'tax_query' => $query->get('tax_query')
            )));
            
            $cached_posts = $this->get($cache_key);
            if ($cached_posts !== false) {
                return $cached_posts;
            }
            
            // Cachear resultado después de la consulta
            add_filter('the_posts', function($posts) use ($cache_key) {
                if (!empty($posts)) {
                    $this->set($cache_key, $posts, 900); // 15 minutos
                }
                return $posts;
            }, 10, 1);
        }
        
        return $posts;
    }
    
    /**
     * Invalidar caché de posts
     */
    public function invalidate_post_cache($post_id) {
        $this->delete('post_' . $post_id);
        $this->delete_pattern('posts_query_*');
    }
    
    /**
     * Cachear opciones críticas
     */
    public function cache_option($pre_option, $option, $default) {
        // Solo opciones que se consultan frecuentemente
        $critical_options = array(
            'blogname', 'blogdescription', 'template', 'stylesheet',
            'posts_per_page', 'date_format', 'time_format'
        );
        
        if (in_array($option, $critical_options)) {
            $cache_key = 'option_' . $option;
            $cached_value = $this->get($cache_key);
            
            if ($cached_value !== false) {
                return $cached_value;
            }
            
            // Cachear después de obtener el valor
            add_filter('option_' . $option, function($value) use ($cache_key) {
                $this->set($cache_key, $value, 3600); // 1 hora
                return $value;
            }, 10, 1);
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
     * Funciones auxiliares
     */
    private function get_cache_key($key) {
        return md5($key); // Sin prefijo para Redis/Memcached ya configurados
    }
    
    private function delete_pattern($pattern) {
        try {
            switch ($this->cache_type) {
                case 'redis':
                    if ($this->redis_client) {
                        $keys = $this->redis_client->keys(str_replace('*', '*', $pattern));
                        if ($keys) {
                            return $this->redis_client->del($keys);
                        }
                    }
                    break;
                    
                case 'memcached':
                    // Memcached no soporta patrones
                    return $this->memcached_client->flush();
                    
                case 'apcu':
                    return apcu_clear_cache();
                    
                case 'file':
                    return $this->delete_file_pattern($pattern);
            }
        } catch (Exception $e) {
            error_log('SBP Cache Pattern Delete Error: ' . $e->getMessage());
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
        $cache_data = @unserialize($cache_data);
        
        if (!$cache_data || $cache_data['expires'] < time()) {
            @unlink($cache_file);
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
        return file_exists($cache_file) ? @unlink($cache_file) : true;
    }
    
    private function flush_file_cache() {
        $cache_dir = SBP_CACHE_DIR . 'object/';
        if (!is_dir($cache_dir)) {
            return true;
        }
        
        $files = glob($cache_dir . '*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        
        return true;
    }
    
    private function delete_file_pattern($pattern) {
        $cache_dir = SBP_CACHE_DIR . 'object/';
        $pattern = str_replace('*', '*', $pattern);
        $files = glob($cache_dir . $pattern . '.cache');
        
        foreach ($files as $file) {
            @unlink($file);
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
            'memory_usage' => '0B',
            'hit_ratio' => 0,
            'keys_count' => 0,
            'auto_detected' => $this->auto_detected
        );
        
        try {
            switch ($this->cache_type) {
                case 'redis':
                    if ($this->redis_client && $this->redis_client->ping()) {
                        $info = $this->redis_client->info();
                        $stats['status'] = 'connected';
                        $stats['memory_usage'] = $info['used_memory_human'] ?? '0B';
                        $stats['keys_count'] = $this->redis_client->dbSize();
                        
                        // Calcular hit ratio si está disponible
                        if (isset($info['keyspace_hits']) && isset($info['keyspace_misses'])) {
                            $total = $info['keyspace_hits'] + $info['keyspace_misses'];
                            $stats['hit_ratio'] = $total > 0 ? round(($info['keyspace_hits'] / $total) * 100, 2) : 0;
                        }
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
                        $info = @apcu_cache_info();
                        if ($info) {
                            $stats['status'] = 'connected';
                            $stats['memory_usage'] = round($info['mem_size'] / 1024 / 1024, 2) . 'MB';
                            $stats['keys_count'] = $info['num_entries'];
                            $stats['hit_ratio'] = round(($info['num_hits'] / max($info['num_hits'] + $info['num_misses'], 1)) * 100, 2);
                        }
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
    
    /**
     * Obtener información de configuración para el admin
     */
    public function get_config_info() {
        $info = array(
            'type' => $this->cache_type,
            'auto_detected' => $this->auto_detected,
            'config' => null
        );
        
        switch ($this->cache_type) {
            case 'redis':
                $info['config'] = get_option('sbp_redis_config');
                break;
            case 'memcached':
                $info['config'] = get_option('sbp_memcached_config');
                break;
        }
        
        return $info;
    }
}