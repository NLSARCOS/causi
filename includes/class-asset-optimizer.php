<?php
/**
 * Optimizador de Assets - VERSIÓN ULTRA CONSERVADORA
 * NO MODIFICA LA APARIENCIA DEL SITIO
 */
class SBP_Asset_Optimizer {
    
    private $optimized_assets = array();
    private $protected_assets = array();
    
    public function __construct() {
        add_action('sbp_asset_optimization', array($this, 'optimize_assets_safely'));
        add_filter('sbp_static_html', array($this, 'optimize_html_assets_safely'), 10, 2);
        
        // Assets que NUNCA deben ser modificados
        $this->protected_assets = array(
            // Scripts críticos
            'jquery', 'jquery-core', 'jquery-migrate', 'wp-embed', 'admin-bar',
            
            // Estilos críticos
            'admin-bar', 'dashicons', 'wp-block-library',
            
            // Patrones de archivos críticos
            'logo', 'icon', 'header', 'nav', 'menu', 'brand', 'favicon',
            
            // Directorios del tema (NO TOCAR)
            '/themes/', '/plugins/'
        );
    }
    
    /**
     * Optimizar assets de forma ULTRA SEGURA
     */
    public function optimize_assets_safely() {
        if (!get_option('sbp_asset_optimization', true)) {
            return;
        }
        
        // SOLO optimizar imágenes de uploads (NO del tema)
        $this->optimize_upload_images_only();
        
        // Generar CSS crítico básico (sin tocar archivos existentes)
        $this->generate_safe_critical_css();
        
        // Crear versiones WebP de imágenes (sin reemplazar originales)
        $this->create_webp_versions();
    }
    
    /**
     * Optimizar SOLO imágenes de uploads (no del tema)
     */
    private function optimize_upload_images_only() {
        if (!get_option('sbp_image_optimization', true)) {
            return;
        }
        
        $images_dir = SBP_ASSETS_DIR . 'images/';
        
        if (!file_exists($images_dir)) {
            wp_mkdir_p($images_dir);
        }
        
        // Solo optimizar imágenes de la carpeta uploads
        $upload_dir = wp_upload_dir();
        $images = $this->get_upload_images_safe($upload_dir['basedir']);
        
        // Limitar a 20 imágenes por ejecución para evitar timeouts
        foreach (array_slice($images, 0, 20) as $image_path) {
            $this->create_optimized_version($image_path, $images_dir);
        }
    }
    
    /**
     * Obtener imágenes SOLO de uploads (seguro)
     */
    private function get_upload_images_safe($uploads_path) {
        $images = array();
        
        if (!is_dir($uploads_path)) {
            return $images;
        }
        
        try {
            // Solo buscar en uploads, no en temas
            $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploads_path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                $extension = strtolower($file->getExtension());
                if (in_array($extension, $allowed_extensions)) {
                    // Verificar que no sea un thumbnail o versión ya optimizada
                    $filename = $file->getFilename();
                    if (!preg_match('/-\d+x\d+\./', $filename)) {
                        $images[] = $file->getPathname();
                    }
                }
            }
        } catch (Exception $e) {
            error_log('SBP Error scanning images: ' . $e->getMessage());
        }
        
        return $images;
    }
    
    /**
     * Crear versión optimizada SIN reemplazar original
     */
    private function create_optimized_version($image_path, $output_dir) {
        $image_info = pathinfo($image_path);
        $base_name = $image_info['filename'];
        
        // Crear versión WebP (más compatible que AVIF)
        $webp_file = $output_dir . $base_name . '.webp';
        
        if (!file_exists($webp_file)) {
            $this->convert_to_webp_safe($image_path, $webp_file);
        }
    }
    
    /**
     * Convertir a WebP de forma segura
     */
    private function convert_to_webp_safe($source, $destination) {
        if (!function_exists('imagewebp')) {
            return false;
        }
        
        $image_info = getimagesize($source);
        if (!$image_info) {
            return false;
        }
        
        try {
            $image = null;
            
            switch ($image_info['mime']) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($source);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($source);
                    // Preservar transparencia
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($source);
                    break;
                default:
                    return false;
            }
            
            if ($image) {
                // Calidad alta para mantener apariencia
                $result = imagewebp($image, $destination, 90);
                imagedestroy($image);
                
                // Crear versión comprimida
                if ($result && function_exists('gzencode')) {
                    $webp_content = file_get_contents($destination);
                    file_put_contents($destination . '.gz', gzencode($webp_content, 9));
                }
                
                return $result;
            }
        } catch (Exception $e) {
            error_log('SBP Error converting to WebP: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Generar CSS crítico básico y seguro
     */
    private function generate_safe_critical_css() {
        $critical_css_file = SBP_CACHE_DIR . 'css/critical-safe.css';
        
        if (!file_exists(dirname($critical_css_file))) {
            wp_mkdir_p(dirname($critical_css_file));
        }
        
        // CSS crítico MUY básico que NO afecta apariencia
        $critical_css = '
        /* StaticBoost Pro - Safe Critical CSS */
        
        /* Performance optimizations (invisible to user) */
        * { box-sizing: border-box; }
        
        /* Responsive images (safe) */
        img { max-width: 100%; height: auto; }
        
        /* Font loading optimization */
        @font-face { font-display: swap; }
        
        /* Lazy loading states (only for new images) */
        .sbp-lazy { opacity: 0; transition: opacity 0.3s ease; }
        .sbp-lazy.sbp-loaded { opacity: 1; }
        
        /* Prevent layout shift for common elements */
        .wp-block-image { margin: 0.5em 0; }
        
        /* Loading states */
        .sbp-loading { opacity: 0.8; }
        ';
        
        // Minificar de forma conservadora
        $critical_css = $this->minify_css_safe($critical_css);
        
        file_put_contents($critical_css_file, $critical_css);
        
        // Crear versión comprimida
        if (function_exists('gzencode')) {
            file_put_contents($critical_css_file . '.gz', gzencode($critical_css, 9));
        }
    }
    
    /**
     * Crear versiones WebP sin reemplazar originales
     */
    private function create_webp_versions() {
        // Esta función ya se ejecuta en optimize_upload_images_only()
        // Separada para claridad en el código
    }
    
    /**
     * Optimizar HTML de forma ULTRA SEGURA
     */
    public function optimize_html_assets_safely($html, $url) {
        if (!get_option('sbp_asset_optimization', true)) {
            return $html;
        }
        
        // SOLO optimizaciones que NO cambien apariencia
        
        // 1. Añadir preconnect headers (invisible al usuario)
        $html = $this->add_safe_preconnect_headers($html);
        
        // 2. Optimizar carga de fuentes (invisible al usuario)
        $html = $this->optimize_font_loading_safe($html);
        
        // 3. Añadir lazy loading SOLO a imágenes grandes de contenido
        $html = $this->add_ultra_safe_lazy_loading($html);
        
        return $html;
    }
    
    /**
     * Añadir headers de preconnect seguros
     */
    private function add_safe_preconnect_headers($html) {
        $preconnect_links = '';
        
        // Solo preconnect a dominios comunes y seguros
        $safe_domains = array(
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com'
        );
        
        foreach ($safe_domains as $domain) {
            $preconnect_links .= '<link rel="preconnect" href="' . $domain . '">' . "\n";
        }
        
        // Añadir crossorigin para Google Fonts
        $preconnect_links .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        
        // Insertar en el head
        $html = str_replace('</head>', $preconnect_links . '</head>', $html);
        
        return $html;
    }
    
    /**
     * Optimizar carga de fuentes de forma segura
     */
    private function optimize_font_loading_safe($html) {
        // Añadir font-display: swap a Google Fonts
        $html = preg_replace(
            '/<link([^>]*?)href=["\']([^"\']*fonts\.googleapis\.com[^"\']*)["\']([^>]*?)>/',
            '<link$1href="$2&display=swap"$3>',
            $html
        );
        
        return $html;
    }
    
    /**
     * Lazy loading ULTRA SEGURO - Solo imágenes grandes de contenido
     */
    private function add_ultra_safe_lazy_loading($html) {
        $html = preg_replace_callback(
            '/<img([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i',
            array($this, 'optimize_img_ultra_safe'),
            $html
        );
        
        return $html;
    }
    
    /**
     * Optimizar imagen de forma ULTRA SEGURA
     */
    private function optimize_img_ultra_safe($matches) {
        $before_src = $matches[1];
        $src = $matches[2];
        $after_src = $matches[3];
        $full_tag = $matches[0];
        
        // Lista EXTENSA de patrones a NO tocar
        $protected_patterns = array(
            'logo', 'icon', 'header', 'nav', 'menu', 'brand', 'favicon',
            'admin', 'wp-admin', 'wp-content/themes', 'wp-content/plugins',
            'avatar', 'profile', 'gravatar', 'thumbnail', 'widget',
            'sidebar', 'footer', 'banner', 'slider', 'carousel'
        );
        
        // Verificar si es una imagen protegida
        foreach ($protected_patterns as $pattern) {
            if (stripos($full_tag, $pattern) !== false || 
                stripos($src, $pattern) !== false) {
                return $matches[0]; // NO TOCAR
            }
        }
        
        // NO tocar imágenes pequeñas (probablemente iconos)
        if (preg_match('/width=["\']?(\d+)["\']?/i', $full_tag, $width_match)) {
            if (isset($width_match[1]) && $width_match[1] < 150) {
                return $matches[0]; // NO TOCAR imágenes pequeñas
            }
        }
        
        if (preg_match('/height=["\']?(\d+)["\']?/i', $full_tag, $height_match)) {
            if (isset($height_match[1]) && $height_match[1] < 150) {
                return $matches[0]; // NO TOCAR imágenes pequeñas
            }
        }
        
        // Solo añadir loading="lazy" si no existe ya
        if (strpos($full_tag, 'loading=') === false) {
            $after_src .= ' loading="lazy"';
        }
        
        // Añadir decoding="async" para mejor rendimiento
        if (strpos($full_tag, 'decoding=') === false) {
            $after_src .= ' decoding="async"';
        }
        
        return '<img' . $before_src . 'src="' . $src . '"' . $after_src . '>';
    }
    
    /**
     * Minificación CSS segura
     */
    private function minify_css_safe($css) {
        // Eliminar comentarios
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Eliminar espacios excesivos
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Eliminar espacios alrededor de caracteres especiales
        $css = str_replace(array(' {', '{ ', ' }', '} ', ': ', ' :', '; ', ' ;'), 
                          array('{', '{', '}', '}', ':', ':', ';', ';'), $css);
        
        return trim($css);
    }
}