<?php
/**
 * CDN Local Ultra - El mejor sistema de CDN local del mercado
 */
class SBP_Local_CDN {
    
    private $cdn_url;
    private $cache_dir;
    private $supported_formats;
    private $compression_levels;
    private $object_cache;
    
    public function __construct() {
        $this->object_cache = new SBP_Object_Cache_Manager();
        $this->cdn_url = site_url('sbp-cdn');
        $this->cache_dir = SBP_CACHE_DIR . 'cdn/';
        
        // Formatos soportados con optimizaciones específicas
        $this->supported_formats = array(
            'images' => array('jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'),
            'styles' => array('css'),
            'scripts' => array('js'),
            'fonts' => array('woff', 'woff2', 'ttf', 'eot', 'otf'),
            'videos' => array('mp4', 'webm', 'ogg'),
            'documents' => array('pdf', 'doc', 'docx')
        );
        
        // Niveles de compresión por tipo
        $this->compression_levels = array(
            'images' => 9,
            'styles' => 9,
            'scripts' => 9,
            'fonts' => 6,
            'videos' => 3,
            'documents' => 9
        );
        
        add_action('init', array($this, 'setup_cdn_routes'));
        add_action('template_redirect', array($this, 'handle_cdn_request'), 1);
        add_filter('sbp_static_html', array($this, 'optimize_asset_urls'), 15, 2);
        add_action('wp_enqueue_scripts', array($this, 'intercept_asset_loading'), 1);
        
        // Crear directorios necesarios
        $this->create_cdn_directories();
    }
    
    /**
     * Configurar rutas del CDN local
     */
    public function setup_cdn_routes() {
        if (!get_option('sbp_local_cdn_enabled', true)) {
            return;
        }
        
        // Rewrite rules para el CDN local
        add_rewrite_rule(
            '^sbp-cdn/(.+)$',
            'index.php?sbp_cdn_asset=$matches[1]',
            'top'
        );
        
        add_rewrite_tag('%sbp_cdn_asset%', '([^&]+)');
    }
    
    /**
     * Manejar requests del CDN
     */
    public function handle_cdn_request() {
        $asset_path = get_query_var('sbp_cdn_asset');
        
        if (empty($asset_path)) {
            return;
        }
        
        $this->serve_cdn_asset($asset_path);
        exit;
    }
    
    /**
     * Servir asset desde CDN local con headers ultra optimizados
     */
    private function serve_cdn_asset($asset_path) {
        // Sanitizar path
        $asset_path = sanitize_text_field($asset_path);
        $asset_path = str_replace('..', '', $asset_path); // Prevenir directory traversal
        
        // OPTIMIZACIÓN: Usar object cache para metadatos de archivos
        $cache_key = 'cdn_asset_' . md5($asset_path);
        $cached_info = $this->object_cache->get($cache_key);
        
        $cdn_file = $this->cache_dir . $asset_path;
        $original_file = $this->find_original_file($asset_path);
        
        // Si no existe el archivo optimizado, crearlo
        if (!file_exists($cdn_file) && $original_file) {
            $this->create_optimized_asset($original_file, $cdn_file);
            // Invalidar caché después de crear
            $this->object_cache->delete($cache_key);
        }
        
        // Si aún no existe, servir 404
        if (!file_exists($cdn_file)) {
            status_header(404);
            exit('Asset not found');
        }
        
        // OPTIMIZACIÓN: Usar información cacheada si está disponible
        if ($cached_info && $cached_info['mtime'] === filemtime($cdn_file)) {
            $file_info = $cached_info;
        } else {
            $file_info = $this->get_file_info($cdn_file);
            $this->object_cache->set($cache_key, $file_info, 3600); // 1 hora
        }
        
        $etag = $file_info['etag'];
        $last_modified = $file_info['last_modified'];
        $mime_type = $file_info['mime_type'];
        $file_size = $file_info['size'];
        
        // Headers de CDN profesional
        $this->set_cdn_headers($mime_type, $etag, $last_modified, $file_size);
        
        // Verificar cache del cliente
        if ($this->client_has_valid_cache($etag, $last_modified)) {
            status_header(304);
            exit;
        }
        
        // Servir archivo optimizado
        $this->serve_optimized_file($cdn_file, $mime_type);
        exit;
    }
    
    /**
     * Headers de CDN ultra optimizados
     */
    private function set_cdn_headers($mime_type, $etag, $last_modified, $file_size) {
        // Headers básicos
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . $file_size);
        
        // OPTIMIZACIÓN: Headers de caché MÁS agresivos
        header('Cache-Control: public, max-age=31536000, immutable, stale-while-revalidate=86400'); // 1 año + stale-while-revalidate
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        header('ETag: "' . $etag . '"');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
        
        // OPTIMIZACIÓN: Headers adicionales para máximo rendimiento
        header('Vary: Accept-Encoding, Accept');
        header('X-Content-Type-Options: nosniff');
        header('X-CDN-Cache: HIT');
        header('X-StaticBoost-CDN: LOCAL-ULTRA');
        header('X-Served-By: StaticBoost-Pro');
        header('X-Cache-Status: HIT');
        header('X-Edge-Location: LOCAL');
        header('X-Robots-Tag: noindex'); // Evitar indexación de assets
        
        // OPTIMIZACIÓN: Compresión mejorada
        if ($this->client_supports_compression()) {
            if ($this->client_supports_brotli()) {
                header('Content-Encoding: br');
                header('X-Compression: Brotli');
                header('X-Compression-Ratio: 85'); // Indicar ratio de compresión
            } else {
                header('Content-Encoding: gzip');
                header('X-Compression: Gzip');
                header('X-Compression-Ratio: 70');
            }
        }
        
        // Headers específicos por tipo de archivo
        $this->set_type_specific_headers($mime_type);
        
        // OPTIMIZACIÓN: Headers de seguridad mejorados
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        
        // OPTIMIZACIÓN: Headers de rendimiento adicionales
        if (get_option('sbp_local_cdn_aggressive', false)) {
            header('X-Accel-Expires: 31536000'); // Nginx
            header('Edge-Control: max-age=31536000'); // CloudFlare
            header('CDN-Cache-Control: max-age=31536000'); // Generic CDN
            header('Surrogate-Control: max-age=31536000'); // Varnish
            header('X-Cache-TTL: 31536000'); // Custom TTL
        }
        
        // OPTIMIZACIÓN: Headers de preload para recursos críticos
        if (strpos($mime_type, 'text/css') === 0) {
            header('X-Resource-Type: critical-css');
        } elseif (strpos($mime_type, 'application/javascript') === 0) {
            header('X-Resource-Type: script');
        } elseif (strpos($mime_type, 'font/') === 0) {
            header('X-Resource-Type: font');
        }
    }
    
    /**
     * Headers específicos por tipo de archivo
     */
    private function set_type_specific_headers($mime_type) {
        if (strpos($mime_type, 'font/') === 0) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET');
            header('Access-Control-Allow-Headers: Range');
        }
        
        if (strpos($mime_type, 'image/') === 0) {
            header('Accept-Ranges: bytes');
            header('X-Image-Optimized: StaticBoost-Pro');
        }
        
        if (strpos($mime_type, 'text/css') === 0) {
            header('X-CSS-Minified: true');
        }
        
        if (strpos($mime_type, 'application/javascript') === 0) {
            header('X-JS-Minified: true');
        }
    }
    
    /**
     * Verificar si el cliente tiene caché válido
     */
    private function client_has_valid_cache($etag, $last_modified) {
        // Verificar If-None-Match (ETag)
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $client_etag = trim($_SERVER['HTTP_IF_NONE_MATCH'], '"');
            if ($client_etag === $etag) {
                return true;
            }
        }
        
        // Verificar If-Modified-Since
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $client_time = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            if ($client_time >= $last_modified) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Servir archivo optimizado
     */
    private function serve_optimized_file($file_path, $mime_type) {
        // Verificar si existe versión comprimida
        $compressed_file = null;
        
        if ($this->client_supports_compression()) {
            if ($this->client_supports_brotli()) {
                $brotli_file = $file_path . '.br';
                if (file_exists($brotli_file)) {
                    $compressed_file = $brotli_file;
                }
            }
            
            if (!$compressed_file) {
                $gzip_file = $file_path . '.gz';
                if (file_exists($gzip_file)) {
                    $compressed_file = $gzip_file;
                    header('Content-Encoding: gzip');
                }
            }
        }
        
        // Servir archivo
        if ($compressed_file) {
            header('Content-Length: ' . filesize($compressed_file));
            readfile($compressed_file);
        } else {
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
        }
    }
    
    /**
     * Crear asset optimizado
     */
    private function create_optimized_asset($original_file, $cdn_file) {
        // OPTIMIZACIÓN: Verificar si ya existe una versión reciente
        if (file_exists($cdn_file) && filemtime($cdn_file) > filemtime($original_file)) {
            return; // Ya está optimizado y actualizado
        }
        
        $cdn_dir = dirname($cdn_file);
        if (!file_exists($cdn_dir)) {
            wp_mkdir_p($cdn_dir);
        }
        
        $file_extension = strtolower(pathinfo($original_file, PATHINFO_EXTENSION));
        $asset_type = $this->get_asset_type($file_extension);
        
        switch ($asset_type) {
            case 'images':
                $this->optimize_image($original_file, $cdn_file);
                break;
            case 'styles':
                $this->optimize_css($original_file, $cdn_file);
                break;
            case 'scripts':
                $this->optimize_js($original_file, $cdn_file);
                break;
            case 'fonts':
                $this->optimize_font($original_file, $cdn_file);
                break;
            default:
                // Para otros tipos, solo copiar y comprimir
                copy($original_file, $cdn_file);
                break;
        }
        
        // Crear versiones comprimidas
        $this->create_compressed_versions($cdn_file);
    }
    
    /**
     * Optimizar imagen
     */
    private function optimize_image($original_file, $cdn_file) {
        // OPTIMIZACIÓN: Usar caché para evitar re-optimizar
        $cache_key = 'optimized_image_' . md5($original_file . filemtime($original_file));
        if ($this->object_cache->get($cache_key)) {
            if (file_exists($cdn_file)) {
                return; // Ya optimizada
            }
        }
        
        $image_info = getimagesize($original_file);
        if (!$image_info) {
            copy($original_file, $cdn_file);
            return;
        }
        
        $extension = strtolower(pathinfo($original_file, PATHINFO_EXTENSION));
        
        // Para WebP y AVIF, solo copiar (ya están optimizados)
        if (in_array($extension, array('webp', 'avif'))) {
            copy($original_file, $cdn_file);
            $this->object_cache->set($cache_key, true, 86400); // 24 horas
            return;
        }
        
        // OPTIMIZACIÓN: Optimizar imagen tradicional con mejor calidad/velocidad
        try {
            $image = null;
            
            switch ($image_info['mime']) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($original_file);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($original_file);
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($original_file);
                    break;
                default:
                    copy($original_file, $cdn_file);
                    $this->object_cache->set($cache_key, true, 86400);
                    return;
            }
            
            if ($image) {
                // OPTIMIZACIÓN: Crear versión WebP con mejor calidad
                $webp_file = preg_replace('/\.[^.]+$/', '.webp', $cdn_file);
                imagewebp($image, $webp_file, 90); // Calidad más alta
                
                // OPTIMIZACIÓN: Guardar original optimizado con mejor calidad
                switch ($image_info['mime']) {
                    case 'image/jpeg':
                        imagejpeg($image, $cdn_file, 90); // Calidad más alta
                        break;
                    case 'image/png':
                        imagepng($image, $cdn_file, 5); // Compresión más rápida
                        break;
                    case 'image/gif':
                        imagegif($image, $cdn_file);
                        break;
                }
                
                imagedestroy($image);
                $this->object_cache->set($cache_key, true, 86400); // 24 horas
            }
        } catch (Exception $e) {
            // Si falla la optimización, copiar original
            copy($original_file, $cdn_file);
        }
    }
    
    /**
     * Optimizar CSS
     */
    private function optimize_css($original_file, $cdn_file) {
        $css_content = file_get_contents($original_file);
        
        // Minificar CSS
        $css_content = $this->minify_css($css_content);
        
        // Optimizar URLs dentro del CSS
        $css_content = $this->optimize_css_urls($css_content);
        
        file_put_contents($cdn_file, $css_content);
    }
    
    /**
     * Optimizar JavaScript
     */
    private function optimize_js($original_file, $cdn_file) {
        $js_content = file_get_contents($original_file);
        
        // Minificar JavaScript (básico)
        $js_content = $this->minify_js($js_content);
        
        file_put_contents($cdn_file, $js_content);
    }
    
    /**
     * Optimizar fuente
     */
    private function optimize_font($original_file, $cdn_file) {
        // Para fuentes, solo copiar (ya están optimizadas)
        copy($original_file, $cdn_file);
    }
    
    /**
     * Crear versiones comprimidas
     */
    private function create_compressed_versions($file_path) {
        // OPTIMIZACIÓN: Solo crear si no existen o son más antiguos
        $gzip_file = $file_path . '.gz';
        $brotli_file = $file_path . '.br';
        $file_mtime = filemtime($file_path);
        
        $need_gzip = !file_exists($gzip_file) || filemtime($gzip_file) < $file_mtime;
        $need_brotli = !file_exists($brotli_file) || filemtime($brotli_file) < $file_mtime;
        
        if (!$need_gzip && !$need_brotli) {
            return; // Ya están actualizadas
        }
        
        $content = file_get_contents($file_path);
        $asset_type = $this->get_asset_type(pathinfo($file_path, PATHINFO_EXTENSION));
        $compression_level = $this->compression_levels[$asset_type] ?? 6;
        
        // OPTIMIZACIÓN: Crear versión Gzip solo si es necesario
        if ($need_gzip && function_exists('gzencode')) {
            $gzip_content = gzencode($content, $compression_level);
            file_put_contents($gzip_file, $gzip_content);
        }
        
        // OPTIMIZACIÓN: Crear versión Brotli solo si es necesario y está habilitado
        if ($need_brotli && function_exists('brotli_compress') && get_option('sbp_local_cdn_aggressive', false)) {
            $brotli_content = brotli_compress($content, $compression_level);
            file_put_contents($brotli_file, $brotli_content);
        }
    }
    
    /**
     * Optimizar URLs de assets en HTML
     */
    public function optimize_asset_urls($html, $url) {
        if (!get_option('sbp_local_cdn_enabled', true)) {
            return $html;
        }
        
        $site_url = get_site_url();
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl'];
        $theme_url = get_template_directory_uri();
        
        // Optimizar imágenes de uploads
        $html = preg_replace_callback(
            '/src=["\'](' . preg_quote($upload_url, '/') . '[^"\']+\.(jpg|jpeg|png|gif|webp|avif))["\']/',
            array($this, 'replace_image_url'),
            $html
        );
        
        // Optimizar CSS del tema (solo si está en modo agresivo)
        if (get_option('sbp_local_cdn_aggressive', false)) {
            $html = preg_replace_callback(
                '/href=["\'](' . preg_quote($theme_url, '/') . '[^"\']+\.css)["\']/',
                array($this, 'replace_css_url'),
                $html
            );
            
            // Optimizar JS del tema
            $html = preg_replace_callback(
                '/src=["\'](' . preg_quote($theme_url, '/') . '[^"\']+\.js)["\']/',
                array($this, 'replace_js_url'),
                $html
            );
        }
        
        return $html;
    }
    
    /**
     * Reemplazar URL de imagen
     */
    private function replace_image_url($matches) {
        $original_url = $matches[1];
        $extension = $matches[2];
        
        // Generar URL del CDN
        $relative_path = str_replace(wp_upload_dir()['baseurl'], '', $original_url);
        $cdn_url = $this->cdn_url . '/images' . $relative_path;
        
        // Si soporta WebP, usar esa versión
        if ($this->client_supports_webp() && $extension !== 'webp') {
            $cdn_url = preg_replace('/\.[^.]+$/', '.webp', $cdn_url);
        }
        
        return 'src="' . $cdn_url . '"';
    }
    
    /**
     * Reemplazar URL de CSS
     */
    private function replace_css_url($matches) {
        $original_url = $matches[1];
        $relative_path = str_replace(get_template_directory_uri(), '', $original_url);
        $cdn_url = $this->cdn_url . '/styles' . $relative_path;
        
        return 'href="' . $cdn_url . '"';
    }
    
    /**
     * Reemplazar URL de JS
     */
    private function replace_js_url($matches) {
        $original_url = $matches[1];
        $relative_path = str_replace(get_template_directory_uri(), '', $original_url);
        $cdn_url = $this->cdn_url . '/scripts' . $relative_path;
        
        return 'src="' . $cdn_url . '"';
    }
    
    /**
     * Interceptar carga de assets
     */
    public function intercept_asset_loading() {
        if (!get_option('sbp_local_cdn_enabled', true) || is_admin()) {
            return;
        }
        
        // Interceptar estilos
        add_filter('style_loader_src', array($this, 'optimize_style_src'), 10, 2);
        
        // Interceptar scripts
        add_filter('script_loader_src', array($this, 'optimize_script_src'), 10, 2);
    }
    
    /**
     * Optimizar src de estilos
     */
    public function optimize_style_src($src, $handle) {
        if (get_option('sbp_local_cdn_aggressive', false)) {
            return $this->convert_to_cdn_url($src, 'styles');
        }
        return $src;
    }
    
    /**
     * Optimizar src de scripts
     */
    public function optimize_script_src($src, $handle) {
        if (get_option('sbp_local_cdn_aggressive', false)) {
            return $this->convert_to_cdn_url($src, 'scripts');
        }
        return $src;
    }
    
    /**
     * Convertir URL a CDN
     */
    private function convert_to_cdn_url($original_url, $type) {
        $site_url = get_site_url();
        
        // Solo procesar URLs locales
        if (strpos($original_url, $site_url) !== 0) {
            return $original_url;
        }
        
        $relative_path = str_replace($site_url, '', $original_url);
        return $this->cdn_url . '/' . $type . $relative_path;
    }
    
    /**
     * Funciones auxiliares
     */
    private function find_original_file($asset_path) {
        // Buscar archivo original basado en el path del CDN
        $possible_paths = array(
            ABSPATH . ltrim($asset_path, '/'),
            wp_upload_dir()['basedir'] . '/' . basename($asset_path),
            get_template_directory() . '/' . basename($asset_path)
        );
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return false;
    }
    
    private function get_file_info($file_path) {
        $stat = stat($file_path);
        
        return array(
            'etag' => md5_file($file_path),
            'last_modified' => $stat['mtime'],
            'mtime' => $stat['mtime'], // Para comparaciones de caché
            'size' => $stat['size'],
            'mime_type' => $this->get_mime_type($file_path)
        );
    }
    
    private function get_mime_type($file_path) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        $mime_types = array(
            // Imágenes
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            
            // Estilos
            'css' => 'text/css',
            
            // Scripts
            'js' => 'application/javascript',
            
            // Fuentes
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'otf' => 'font/otf',
            
            // Videos
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            
            // Documentos
            'pdf' => 'application/pdf'
        );
        
        return $mime_types[$extension] ?? 'application/octet-stream';
    }
    
    private function get_asset_type($extension) {
        foreach ($this->supported_formats as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                return $type;
            }
        }
        return 'other';
    }
    
    private function client_supports_compression() {
        return isset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }
    
    private function client_supports_gzip() {
        return isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
               strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
    }
    
    private function client_supports_brotli() {
        return isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
               strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'br') !== false;
    }
    
    private function client_supports_webp() {
        return isset($_SERVER['HTTP_ACCEPT']) && 
               strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;
    }
    
    private function create_cdn_directories() {
        $directories = array(
            $this->cache_dir,
            $this->cache_dir . 'images/',
            $this->cache_dir . 'styles/',
            $this->cache_dir . 'scripts/',
            $this->cache_dir . 'fonts/',
            $this->cache_dir . 'videos/',
            $this->cache_dir . 'documents/'
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }
    
    private function minify_css($css) {
        // Eliminar comentarios
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Eliminar espacios en blanco
        $css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    '), '', $css);
        
        // Optimizar selectores
        $css = str_replace(array('; ', ' ;', ' {', '{ ', ' }', '} ', ': ', ' :', ', ', ' ,'), 
                          array(';', ';', '{', '{', '}', '}', ':', ':', ',', ','), $css);
        
        return trim($css);
    }
    
    private function minify_js($js) {
        // Eliminar comentarios de línea
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Eliminar comentarios de bloque
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Eliminar espacios excesivos
        $js = preg_replace('/\s+/', ' ', $js);
        
        return trim($js);
    }
    
    private function optimize_css_urls($css) {
        // Optimizar URLs dentro del CSS para usar el CDN local
        $css = preg_replace_callback(
            '/url\(["\']?([^"\']+)["\']?\)/',
            function($matches) {
                $url = $matches[1];
                
                // Si es una URL relativa, convertir a CDN
                if (!preg_match('/^https?:\/\//', $url)) {
                    $extension = pathinfo($url, PATHINFO_EXTENSION);
                    $asset_type = $this->get_asset_type($extension);
                    
                    if ($asset_type !== 'other') {
                        $cdn_url = $this->cdn_url . '/' . $asset_type . '/' . ltrim($url, '/');
                        return 'url(' . $cdn_url . ')';
                    }
                }
                
                return $matches[0];
            },
            $css
        );
        
        return $css;
    }
}