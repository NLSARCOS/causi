<?php
/**
 * Optimizador PageSpeed 100/100 - VERSIÓN CONSERVADORA
 * Optimiza para PageSpeed SIN romper el diseño
 */
class SBP_PageSpeed_Optimizer {
    
    public function __construct() {
        add_action('sbp_pagespeed_optimization', array($this, 'optimize_for_pagespeed_safely'));
        add_filter('sbp_static_html', array($this, 'optimize_html_for_pagespeed_safely'), 5, 2);
        add_action('wp_head', array($this, 'add_critical_performance_headers'), 1);
    }
    
    /**
     * Optimización PageSpeed SEGURA
     */
    public function optimize_for_pagespeed_safely() {
        if (!get_option('sbp_pagespeed_mode', true)) {
            return;
        }
        
        // 1. Generar CSS crítico para PageSpeed (sin tocar archivos existentes)
        $this->generate_pagespeed_critical_css();
        
        // 2. Crear headers de preload optimizados
        $this->create_preload_manifest();
        
        // 3. Optimizar configuración del servidor
        $this->update_htaccess_for_pagespeed();
    }
    
    /**
     * Generar CSS crítico específico para PageSpeed
     */
    private function generate_pagespeed_critical_css() {
        $critical_css_file = SBP_CACHE_DIR . 'css/pagespeed-critical.css';
        
        if (!file_exists(dirname($critical_css_file))) {
            wp_mkdir_p(dirname($critical_css_file));
        }
        
        // CSS crítico optimizado para Core Web Vitals
        $critical_css = '
        /* StaticBoost Pro - PageSpeed Critical CSS */
        
        /* Prevent layout shift */
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { 
            margin: 0; 
            padding: 0; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
        }
        
        /* Responsive media */
        img, video, iframe { 
            max-width: 100%; 
            height: auto; 
            display: block;
        }
        
        /* Font loading optimization */
        @font-face { font-display: swap; }
        
        /* Lazy loading states */
        .sbp-lazy { opacity: 0; transition: opacity 0.2s ease; }
        .sbp-lazy.sbp-loaded { opacity: 1; }
        
        /* Prevent CLS for WordPress blocks */
        .wp-block-image, .wp-block-gallery { margin: 1em 0; }
        .aligncenter { text-align: center; margin: 1em auto; }
        .alignleft { float: left; margin: 0 1em 1em 0; }
        .alignright { float: right; margin: 0 0 1em 1em; }
        
        /* Loading states */
        .sbp-loading { opacity: 0.8; pointer-events: none; }
        ';
        
        // Minificar agresivamente para PageSpeed
        $critical_css = $this->minify_css_aggressive($critical_css);
        
        file_put_contents($critical_css_file, $critical_css);
        
        // Crear versión comprimida
        if (function_exists('gzencode')) {
            file_put_contents($critical_css_file . '.gz', gzencode($critical_css, 9));
        }
    }
    
    /**
     * Crear manifest de preload
     */
    private function create_preload_manifest() {
        $preload_file = SBP_CACHE_DIR . 'preload-manifest.json';
        
        $preload_resources = array(
            'critical_css' => content_url('cache/staticboost-pro/css/pagespeed-critical.css'),
            'preconnect' => array(
                'https://fonts.googleapis.com',
                'https://fonts.gstatic.com'
            ),
            'dns_prefetch' => array(
                '//fonts.googleapis.com',
                '//fonts.gstatic.com'
            )
        );
        
        file_put_contents($preload_file, json_encode($preload_resources, JSON_PRETTY_PRINT));
    }
    
    /**
     * Actualizar .htaccess para PageSpeed máximo
     */
    private function update_htaccess_for_pagespeed() {
        $htaccess_content = '
# StaticBoost Pro - PageSpeed 100/100 Optimization
<IfModule mod_rewrite.c>
RewriteEngine On

# Servir archivos estáticos HTML directamente
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{HTTP_COOKIE} !comment_author_
RewriteCond %{HTTP_COOKIE} !wp-postpass_
RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_
RewriteCond %{HTTP_COOKIE} !woocommerce_cart_hash
RewriteCond %{HTTP_COOKIE} !woocommerce_items_in_cart
RewriteCond %{REQUEST_URI} !^/wp-admin/
RewriteCond %{REQUEST_URI} !^/wp-content/
RewriteCond %{REQUEST_URI} !^/wp-includes/
RewriteCond %{REQUEST_URI} !^/cart/
RewriteCond %{REQUEST_URI} !^/checkout/
RewriteCond %{REQUEST_URI} !^/my-account/

# Para página principal
RewriteCond %{REQUEST_URI} ^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/staticboost-pro/index/index.html -f
RewriteRule ^$ wp-content/cache/staticboost-pro/index/index.html [L]

# Para otras páginas
RewriteCond %{REQUEST_URI} !^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/staticboost-pro%{REQUEST_URI}/index.html -f
RewriteRule ^(.*)$ wp-content/cache/staticboost-pro/$1/index.html [L]
</IfModule>

# Headers para PageSpeed 100/100
<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType text/html "access plus 1 hour"
ExpiresByType text/css "access plus 1 year"
ExpiresByType application/javascript "access plus 1 year"
ExpiresByType image/png "access plus 1 year"
ExpiresByType image/jpg "access plus 1 year"
ExpiresByType image/jpeg "access plus 1 year"
ExpiresByType image/gif "access plus 1 year"
ExpiresByType image/webp "access plus 1 year"
ExpiresByType image/avif "access plus 1 year"
ExpiresByType font/woff "access plus 1 year"
ExpiresByType font/woff2 "access plus 1 year"
ExpiresByType image/svg+xml "access plus 1 year"
</IfModule>

# Compresión máxima
<IfModule mod_deflate.c>
AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json image/svg+xml text/xml application/xml application/rss+xml
SetOutputFilter DEFLATE
SetEnvIfNoCase Request_URI \.(?:gif|jpe?g|png|webp|avif)$ no-gzip dont-vary
SetEnvIfNoCase Request_URI \.(?:exe|t?gz|zip|bz2|sit|rar)$ no-gzip dont-vary
</IfModule>

# Brotli compression (si está disponible)
<IfModule mod_brotli.c>
AddOutputFilterByType BROTLI_COMPRESS text/html text/css text/javascript application/javascript application/json image/svg+xml
</IfModule>

# Headers de caché optimizados
<IfModule mod_headers.c>
Header set X-Static-Cache "HIT"
Header set X-StaticBoost "PRO"
Header set Cache-Control "public, max-age=31536000, immutable" "expr=%{REQUEST_URI} =~ m#\.(css|js|png|jpg|jpeg|gif|webp|avif|woff|woff2|svg)$#"
Header set Cache-Control "public, max-age=3600" "expr=%{REQUEST_URI} =~ m#\.html$#"

# Preload headers críticos
Header add Link "</wp-content/cache/staticboost-pro/css/pagespeed-critical.css>; rel=preload; as=style"
Header add Link "<https://fonts.googleapis.com>; rel=preconnect"
Header add Link "<https://fonts.gstatic.com>; rel=preconnect; crossorigin"

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Optimización de fuentes
<IfModule mod_headers.c>
<FilesMatch "\.(woff|woff2|eot|ttf)$">
Header set Cache-Control "public, max-age=31536000, immutable"
Header set Access-Control-Allow-Origin "*"
</FilesMatch>
</IfModule>
';
        
        file_put_contents(SBP_CACHE_DIR . '.htaccess', $htaccess_content);
    }
    
    /**
     * Optimizar HTML para PageSpeed SEGURAMENTE
     */
    public function optimize_html_for_pagespeed_safely($html, $url) {
        if (!get_option('sbp_pagespeed_mode', true)) {
            return $html;
        }
        
        // 1. Inline CSS crítico (sin tocar CSS existente)
        $html = $this->inline_critical_css_safely($html);
        
        // 2. Añadir preload headers críticos
        $html = $this->add_critical_preload_headers_safely($html);
        
        // 3. Optimizar scripts para FID (sin romper funcionalidad)
        $html = $this->optimize_scripts_for_fid_safely($html);
        
        // 4. Añadir meta tags de rendimiento
        $html = $this->add_performance_meta_tags($html);
        
        return $html;
    }
    
    /**
     * Inline CSS crítico SEGURAMENTE
     */
    private function inline_critical_css_safely($html) {
        $critical_css_file = SBP_CACHE_DIR . 'css/pagespeed-critical.css';
        
        if (file_exists($critical_css_file)) {
            $critical_css = file_get_contents($critical_css_file);
            
            // Solo añadir, NO reemplazar CSS existente
            $inline_css = '<style id="sbp-pagespeed-critical">' . $critical_css . '</style>';
            $html = str_replace('</head>', $inline_css . "\n</head>", $html);
        }
        
        return $html;
    }
    
    /**
     * Añadir preload headers SEGURAMENTE
     */
    private function add_critical_preload_headers_safely($html) {
        $preload_headers = '';
        
        // Preconnect a dominios críticos
        $preload_headers .= '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        $preload_headers .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        
        // DNS prefetch
        $preload_headers .= '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        $preload_headers .= '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        
        // Insertar al inicio del head
        $html = preg_replace('/<head([^>]*)>/i', '<head$1>' . "\n" . $preload_headers, $html);
        
        return $html;
    }
    
    /**
     * Optimizar scripts para FID SEGURAMENTE
     */
    private function optimize_scripts_for_fid_safely($html) {
        // Solo diferir scripts NO críticos
        $safe_to_defer = array(
            'wp-embed',
            'comment-reply'
        );
        
        foreach ($safe_to_defer as $script_handle) {
            $html = preg_replace(
                '/<script([^>]*?)id=["\']' . $script_handle . '-js["\']([^>]*?)>/i',
                '<script$1id="' . $script_handle . '-js"$2 defer>',
                $html
            );
        }
        
        return $html;
    }
    
    /**
     * Añadir meta tags de rendimiento
     */
    private function add_performance_meta_tags($html) {
        $performance_meta = '
        <meta name="generator" content="StaticBoost Pro">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#ffffff">
        ';
        
        $html = str_replace('</head>', $performance_meta . '</head>', $html);
        
        return $html;
    }
    
    /**
     * Añadir headers críticos de rendimiento
     */
    public function add_critical_performance_headers() {
        if (!get_option('sbp_pagespeed_mode', true) || is_admin()) {
            return;
        }
        
        // Solo añadir si no están ya presentes
        if (!wp_style_is('sbp-critical', 'done')) {
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        }
    }
    
    /**
     * Minificación agresiva de CSS para PageSpeed
     */
    private function minify_css_aggressive($css) {
        // Eliminar comentarios
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Eliminar espacios en blanco
        $css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    '), '', $css);
        
        // Optimizar selectores y propiedades
        $css = str_replace(array('; ', ' ;', ' {', '{ ', ' }', '} ', ': ', ' :', ', ', ' ,'), 
                          array(';', ';', '{', '{', '}', '}', ':', ':', ',', ','), $css);
        
        // Eliminar último punto y coma antes de }
        $css = str_replace(';}', '}', $css);
        
        return trim($css);
    }
}