<?php
/**
 * Object Cache Pro - EXACTAMENTE la misma base que Object Cache Pro
 * Sistema de detección automática de Redis/Memcached
 */
class FSC_Object_Cache_Pro {
    
    private static $instance = null;
    private $redis = null;
    private $memcached = null;
    private $cache_type = 'none';
    private $prefix = 'fsc:';
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        $this->detect_and_connect();
    }
    
    /**
     * DETECCIÓN AUTOMÁTICA - EXACTAMENTE como Object Cache Pro
     */
    private function detect_and_connect() {
        // 1. Intentar Redis primero (más rápido)
        if ($this->try_redis()) {
            $this->cache_type = 'redis';
            return;
        }
        
        // 2. Intentar Memcached como fallback
        if ($this->try_memcached()) {
            $this->cache_type = 'memcached';
            return;
        }
        
        // 3. Usar WordPress Object Cache
        $this->cache_type = 'wp_cache';
    }
    
    /**
     * Intentar conectar a Redis - DETECCIÓN AUTOMÁTICA
     */
    private function try_redis() {
        if (!class_exists('Redis') && !extension_loaded('redis')) {
            return false;
        }
        
        // Configuraciones automáticas a probar
        $configs = array(
            // Configuración estándar
            array('host' => '127.0.0.1', 'port' => 6379, 'password' => null),
            array('host' => 'localhost', 'port' => 6379, 'password' => null),
            
            // Cloudways
            array('host' => '127.0.0.1', 'port' => 6380, 'password' => null),
            
            // Docker
            array('host' => 'redis', 'port' => 6379, 'password' => null),
            
            // Variables de entorno
            array(
                'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
                'port' => getenv('REDIS_PORT') ?: 6379,
                'password' => getenv('REDIS_PASSWORD') ?: null
            ),
            
            // Constantes de WordPress
            array(
                'host' => defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1',
                'port' => defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379,
                'password' => defined('WP_REDIS_PASSWORD') ? WP_REDIS_PASSWORD : null
            )
        );
        
        foreach ($configs as $config) {
            try {
                $redis = new Redis();
                
                // Timeout corto para no bloquear
                $connected = @$redis->connect($config['host'], $config['port'], 0.5);
                
                if ($connected) {
                    // Autenticar si hay password
                    if (!empty($config['password'])) {
                        $redis->auth($config['password']);
                    }
                    
                    // Verificar que funciona
                    $redis->ping();
                    
                    // Configurar para máximo rendimiento
                    $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
                    if (defined('Redis::COMPRESSION_LZ4')) {
                        $redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZ4);
                    }
                    
                    $this->redis = $redis;
                    return true;
                }
            } catch (Exception $e) {
                // Continuar con la siguiente configuración
                continue;
            }
        }
        
        return false;
    }
    
    /**
     * Intentar conectar a Memcached - DETECCIÓN AUTOMÁTICA
     */
    private function try_memcached() {
        if (!class_exists('Memcached') && !extension_loaded('memcached')) {
            return false;
        }
        
        // Configuraciones automáticas a probar
        $configs = array(
            // Configuración estándar
            array('host' => '127.0.0.1', 'port' => 11211),
            array('host' => 'localhost', 'port' => 11211),
            
            // Docker
            array('host' => 'memcached', 'port' => 11211),
            
            // Variables de entorno
            array(
                'host' => getenv('MEMCACHED_HOST') ?: '127.0.0.1',
                'port' => getenv('MEMCACHED_PORT') ?: 11211
            ),
            
            // Constantes de WordPress
            array(
                'host' => defined('WP_MEMCACHED_HOST') ? WP_MEMCACHED_HOST : '127.0.0.1',
                'port' => defined('WP_MEMCACHED_PORT') ? WP_MEMCACHED_PORT : 11211
            )
        );
        
        foreach ($configs as $config) {
            try {
                $memcached = new Memcached('fsc_pool');
                
                // Solo añadir servidor si no existe
                if (count($memcached->getServerList()) === 0) {
                    $memcached->addServer($config['host'], $config['port']);
                    
                    // Configurar para máximo rendimiento
                    $memcached->setOptions(array(
                        Memcached::OPT_COMPRESSION => true,
                        Memcached::OPT_SERIALIZER => Memcached::SERIALIZER_PHP,
                        Memcached::OPT_HASH => Memcached::HASH_MURMUR,
                        Memcached::OPT_DISTRIBUTION => Memcached::DISTRIBUTION_CONSISTENT,
                        Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
                        Memcached::OPT_BUFFERING => true,
                        Memcached::OPT_BINARY_PROTOCOL => true,
                        Memcached::OPT_NO_BLOCK => true,
                        Memcached::OPT_TCP_NODELAY => true,
                        Memcached::OPT_CONNECT_TIMEOUT => 500, // 0.5 segundos
                        Memcached::OPT_POLL_TIMEOUT => 500,
                        Memcached::OPT_RECV_TIMEOUT => 500000,
                        Memcached::OPT_SEND_TIMEOUT => 500000
                    ));
                }
                
                // Verificar que funciona
                $version = $memcached->getVersion();
                if ($version !== false) {
                    $this->memcached = $memcached;
                    return true;
                }
            } catch (Exception $e) {
                // Continuar con la siguiente configuración
                continue;
            }
        }
        
        return false;
    }
    
    /**
     * Obtener valor del caché
     */
    public function get($key) {
        $full_key = $this->prefix . $key;
        
        switch ($this->cache_type) {
            case 'redis':
                try {
                    $value = $this->redis->get($full_key);
                    return $value !== false ? $value : null;
                } catch (Exception $e) {
                    return null;
                }
                
            case 'memcached':
                try {
                    $value = $this->memcached->get($full_key);
                    return $this->memcached->getResultCode() === Memcached::RES_SUCCESS ? $value : null;
                } catch (Exception $e) {
                    return null;
                }
                
            case 'wp_cache':
                return wp_cache_get($key, 'fsc');
        }
        
        return null;
    }
    
    /**
     * Establecer valor en caché
     */
    public function set($key, $value, $expiration = 3600) {
        $full_key = $this->prefix . $key;
        
        switch ($this->cache_type) {
            case 'redis':
                try {
                    return $this->redis->setex($full_key, $expiration, $value);
                } catch (Exception $e) {
                    return false;
                }
                
            case 'memcached':
                try {
                    return $this->memcached->set($full_key, $value, $expiration);
                } catch (Exception $e) {
                    return false;
                }
                
            case 'wp_cache':
                return wp_cache_set($key, $value, 'fsc', $expiration);
        }
        
        return false;
    }
    
    /**
     * Eliminar del caché
     */
    public function delete($key) {
        $full_key = $this->prefix . $key;
        
        switch ($this->cache_type) {
            case 'redis':
                try {
                    return $this->redis->del($full_key) > 0;
                } catch (Exception $e) {
                    return false;
                }
                
            case 'memcached':
                try {
                    return $this->memcached->delete($full_key);
                } catch (Exception $e) {
                    return false;
                }
                
            case 'wp_cache':
                return wp_cache_delete($key, 'fsc');
        }
        
        return false;
    }
    
    /**
     * Limpiar todo el caché
     */
    public function flush() {
        switch ($this->cache_type) {
            case 'redis':
                try {
                    $keys = $this->redis->keys($this->prefix . '*');
                    if (!empty($keys)) {
                        return $this->redis->del($keys) > 0;
                    }
                    return true;
                } catch (Exception $e) {
                    return false;
                }
                
            case 'memcached':
                try {
                    return $this->memcached->flush();
                } catch (Exception $e) {
                    return false;
                }
                
            case 'wp_cache':
                return wp_cache_flush();
        }
        
        return false;
    }
    
    /**
     * Obtener información del caché
     */
    public function get_info() {
        $info = array(
            'type' => $this->cache_type,
            'connected' => false,
            'stats' => array()
        );
        
        switch ($this->cache_type) {
            case 'redis':
                try {
                    $info['connected'] = $this->redis->ping() === '+PONG';
                    $info['stats'] = $this->redis->info();
                } catch (Exception $e) {
                    // Silencioso
                }
                break;
                
            case 'memcached':
                try {
                    $info['connected'] = $this->memcached->getVersion() !== false;
                    $info['stats'] = $this->memcached->getStats();
                } catch (Exception $e) {
                    // Silencioso
                }
                break;
                
            case 'wp_cache':
                $info['connected'] = true;
                $info['stats'] = array('type' => 'WordPress Object Cache');
                break;
        }
        
        return $info;
    }
    
    /**
     * INSTALACIÓN AUTOMÁTICA - Como Object Cache Pro
     */
    public static function install() {
        // Crear drop-in automáticamente
        $dropin_path = WP_CONTENT_DIR . '/object-cache.php';
        
        if (!file_exists($dropin_path)) {
            $dropin_content = '<?php
/**
 * Object Cache Drop-in - Fast Static Cache Pro
 * Instalado automáticamente
 */

// Cargar Object Cache Pro
if (!class_exists("FSC_Object_Cache_Pro")) {
    require_once "' . FSC_PLUGIN_PATH . 'includes/object-cache-pro.php";
}

// Usar como Object Cache de WordPress
$GLOBALS["wp_object_cache"] = FSC_Object_Cache_Pro::instance();
';
            
            file_put_contents($dropin_path, $dropin_content);
        }
    }
    
    /**
     * Desinstalar drop-in
     */
    public static function uninstall() {
        $dropin_path = WP_CONTENT_DIR . '/object-cache.php';
        if (file_exists($dropin_path)) {
            unlink($dropin_path);
        }
    }
}