<?php
/**
 * Gestor de Object Cache con Redis y Memcached
 */
class SBP_Object_Cache_Manager {
    
    private $redis_connection = null;
    private $memcached_connection = null;
    private $cache_type = 'none';
    private $prefix = 'sbp_';
    
    public function __construct() {
        $this->init_cache_connections();
    }
    
    /**
     * Inicializar conexiones de caché
     */
    private function init_cache_connections() {
        // Intentar Redis primero
        if (get_option('sbp_redis_enabled', false) && $this->init_redis()) {
            $this->cache_type = 'redis';
            return;
        }
        
        // Intentar Memcached como fallback
        if (get_option('sbp_memcached_enabled', false) && $this->init_memcached()) {
            $this->cache_type = 'memcached';
            return;
        }
        
        // Usar caché de WordPress como último recurso
        $this->cache_type = 'wp_cache';
    }
    
    /**
     * Inicializar Redis
     */
    private function init_redis() {
        if (!class_exists('Redis') && !extension_loaded('redis')) {
            return false;
        }
        
        try {
            $this->redis_connection = new Redis();
            $host = get_option('sbp_redis_host', '127.0.0.1');
            $port = get_option('sbp_redis_port', 6379);
            $password = get_option('sbp_redis_password', '');
            
            $connected = $this->redis_connection->connect($host, $port, 2); // 2 segundos timeout
            
            if ($connected && !empty($password)) {
                $this->redis_connection->auth($password);
            }
            
            if ($connected) {
                // Configurar Redis para mejor rendimiento
                $this->redis_connection->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
                $this->redis_connection->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZ4);
                return true;
            }
        } catch (Exception $e) {
            error_log('SBP Redis Error: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Inicializar Memcached
     */
    private function init_memcached() {
        if (!class_exists('Memcached') && !extension_loaded('memcached')) {
            return false;
        }
        
        try {
            $this->memcached_connection = new Memcached('sbp_pool');
            
            // Solo añadir servidores si no existen ya
            if (count($this->memcached_connection->getServerList()) === 0) {
                $host = get_option('sbp_memcached_host', '127.0.0.1');
                $port = get_option('sbp_memcached_port', 11211);
                
                $this->memcached_connection->addServer($host, $port);
                
                // Configurar opciones para mejor rendimiento
                $this->memcached_connection->setOptions([
                    Memcached::OPT_COMPRESSION => true,
                    Memcached::OPT_SERIALIZER => Memcached::SERIALIZER_PHP,
                    Memcached::OPT_HASH => Memcached::HASH_MURMUR,
                    Memcached::OPT_DISTRIBUTION => Memcached::DISTRIBUTION_CONSISTENT,
                    Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
                    Memcached::OPT_BUFFERING => true,
                    Memcached::OPT_BINARY_PROTOCOL => true,
                    Memcached::OPT_NO_BLOCK => true,
                    Memcached::OPT_TCP_NODELAY => true,
                    Memcached::OPT_CONNECT_TIMEOUT => 2000, // 2 segundos
                    Memcached::OPT_POLL_TIMEOUT => 2000,
                    Memcached::OPT_RECV_TIMEOUT => 750000, // 0.75 segundos
                    Memcached::OPT_SEND_TIMEOUT => 750000
                ]);
            }
            
            // Verificar conexión
            $version = $this->memcached_connection->getVersion();
            return $version !== false;
            
        } catch (Exception $e) {
            error_log('SBP Memcached Error: ' . $e->getMessage());
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
                if ($this->redis_connection) {
                    try {
                        $value = $this->redis_connection->get($full_key);
                        return $value !== false ? $value : null;
                    } catch (Exception $e) {
                        error_log('SBP Redis Get Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'memcached':
                if ($this->memcached_connection) {
                    try {
                        $value = $this->memcached_connection->get($full_key);
                        return $this->memcached_connection->getResultCode() === Memcached::RES_SUCCESS ? $value : null;
                    } catch (Exception $e) {
                        error_log('SBP Memcached Get Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'wp_cache':
                return wp_cache_get($key, 'sbp');
        }
        
        return null;
    }
    
    /**
     * Establecer valor en el caché
     */
    public function set($key, $value, $expiration = 3600) {
        $full_key = $this->prefix . $key;
        
        switch ($this->cache_type) {
            case 'redis':
                if ($this->redis_connection) {
                    try {
                        return $this->redis_connection->setex($full_key, $expiration, $value);
                    } catch (Exception $e) {
                        error_log('SBP Redis Set Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'memcached':
                if ($this->memcached_connection) {
                    try {
                        return $this->memcached_connection->set($full_key, $value, $expiration);
                    } catch (Exception $e) {
                        error_log('SBP Memcached Set Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'wp_cache':
                return wp_cache_set($key, $value, 'sbp', $expiration);
        }
        
        return false;
    }
    
    /**
     * Eliminar valor del caché
     */
    public function delete($key) {
        $full_key = $this->prefix . $key;
        
        switch ($this->cache_type) {
            case 'redis':
                if ($this->redis_connection) {
                    try {
                        return $this->redis_connection->del($full_key) > 0;
                    } catch (Exception $e) {
                        error_log('SBP Redis Delete Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'memcached':
                if ($this->memcached_connection) {
                    try {
                        return $this->memcached_connection->delete($full_key);
                    } catch (Exception $e) {
                        error_log('SBP Memcached Delete Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'wp_cache':
                return wp_cache_delete($key, 'sbp');
        }
        
        return false;
    }
    
    /**
     * Limpiar todo el caché
     */
    public function flush() {
        switch ($this->cache_type) {
            case 'redis':
                if ($this->redis_connection) {
                    try {
                        // Solo limpiar claves con nuestro prefijo
                        $keys = $this->redis_connection->keys($this->prefix . '*');
                        if (!empty($keys)) {
                            return $this->redis_connection->del($keys) > 0;
                        }
                        return true;
                    } catch (Exception $e) {
                        error_log('SBP Redis Flush Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'memcached':
                if ($this->memcached_connection) {
                    try {
                        // Memcached no tiene flush selectivo, usar versioning
                        $version = $this->get('cache_version') ?: 1;
                        return $this->set('cache_version', $version + 1, 0); // Sin expiración
                    } catch (Exception $e) {
                        error_log('SBP Memcached Flush Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'wp_cache':
                return wp_cache_flush();
        }
        
        return false;
    }
    
    /**
     * Obtener múltiples valores
     */
    public function get_multiple($keys) {
        $results = array();
        
        switch ($this->cache_type) {
            case 'redis':
                if ($this->redis_connection) {
                    try {
                        $full_keys = array_map(function($key) {
                            return $this->prefix . $key;
                        }, $keys);
                        
                        $values = $this->redis_connection->mget($full_keys);
                        
                        foreach ($keys as $index => $key) {
                            $results[$key] = $values[$index] !== false ? $values[$index] : null;
                        }
                    } catch (Exception $e) {
                        error_log('SBP Redis MGet Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'memcached':
                if ($this->memcached_connection) {
                    try {
                        $full_keys = array_map(function($key) {
                            return $this->prefix . $key;
                        }, $keys);
                        
                        $values = $this->memcached_connection->getMulti($full_keys);
                        
                        foreach ($keys as $key) {
                            $full_key = $this->prefix . $key;
                            $results[$key] = isset($values[$full_key]) ? $values[$full_key] : null;
                        }
                    } catch (Exception $e) {
                        error_log('SBP Memcached MGet Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'wp_cache':
                foreach ($keys as $key) {
                    $results[$key] = wp_cache_get($key, 'sbp');
                }
                break;
        }
        
        return $results;
    }
    
    /**
     * Establecer múltiples valores
     */
    public function set_multiple($data, $expiration = 3600) {
        switch ($this->cache_type) {
            case 'redis':
                if ($this->redis_connection) {
                    try {
                        $pipe = $this->redis_connection->multi(Redis::PIPELINE);
                        
                        foreach ($data as $key => $value) {
                            $pipe->setex($this->prefix . $key, $expiration, $value);
                        }
                        
                        $results = $pipe->exec();
                        return !in_array(false, $results, true);
                    } catch (Exception $e) {
                        error_log('SBP Redis MSet Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'memcached':
                if ($this->memcached_connection) {
                    try {
                        $prefixed_data = array();
                        foreach ($data as $key => $value) {
                            $prefixed_data[$this->prefix . $key] = $value;
                        }
                        
                        return $this->memcached_connection->setMulti($prefixed_data, $expiration);
                    } catch (Exception $e) {
                        error_log('SBP Memcached MSet Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'wp_cache':
                $success = true;
                foreach ($data as $key => $value) {
                    if (!wp_cache_set($key, $value, 'sbp', $expiration)) {
                        $success = false;
                    }
                }
                return $success;
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
                if ($this->redis_connection) {
                    try {
                        $info['connected'] = $this->redis_connection->ping() === '+PONG';
                        $info['stats'] = $this->redis_connection->info();
                    } catch (Exception $e) {
                        error_log('SBP Redis Info Error: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'memcached':
                if ($this->memcached_connection) {
                    try {
                        $info['connected'] = $this->memcached_connection->getVersion() !== false;
                        $info['stats'] = $this->memcached_connection->getStats();
                    } catch (Exception $e) {
                        error_log('SBP Memcached Info Error: ' . $e->getMessage());
                    }
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
     * Cerrar conexiones
     */
    public function __destruct() {
        if ($this->redis_connection) {
            try {
                $this->redis_connection->close();
            } catch (Exception $e) {
                // Ignorar errores al cerrar
            }
        }
        
        if ($this->memcached_connection) {
            try {
                $this->memcached_connection->quit();
            } catch (Exception $e) {
                // Ignorar errores al cerrar
            }
        }
    }
}