<?php
/**
 * Funciones auxiliares para StaticBoost Pro - VERSIÓN CONSERVADORA
 */

/**
 * Limpiar todos los archivos estáticos
 */
function sbp_clear_all_cache() {
    if (!is_dir(SBP_CACHE_DIR)) {
        return true;
    }
    
    return sbp_delete_directory_contents(SBP_CACHE_DIR);
}

/**
 * Eliminar contenido de directorio recursivamente
 */
function sbp_delete_directory_contents($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..', '.htaccess'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            sbp_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return true;
}

/**
 * Eliminar directorio recursivamente
 */
function sbp_delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            sbp_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

/**
 * GENERAR ARCHIVOS ESTÁTICOS - VERSIÓN MEJORADA Y CONSERVADORA
 */
function sbp_generate_all_static_pages() {
    // Aumentar límites para procesamiento masivo
    set_time_limit(0);
    ini_set('memory_limit', '1024M');
    
    $results = array(
        'total' => 0,
        'success' => 0,
        'errors' => 0,
        'urls' => array()
    );
    
    $urls = sbp_get_all_site_urls();
    $results['total'] = count($urls);
    
    // Procesar en lotes más pequeños para evitar timeouts
    $batch_size = 25; // Reducido para mayor estabilidad
    $batches = array_chunk($urls, $batch_size);
    
    foreach ($batches as $batch_index => $batch) {
        foreach ($batch as $url) {
            $success = sbp_generate_static_file_for_url($url);
            
            if ($success) {
                $results['success']++;
            } else {
                $results['errors']++;
            }
            
            $results['urls'][] = array(
                'url' => $url,
                'success' => $success
            );
            
            // Pausa entre requests para no sobrecargar
            usleep(100000); // 0.1 segundos
        }
        
        // Pausa más larga entre lotes
        if ($batch_index < count($batches) - 1) {
            sleep(2); // 2 segundos entre lotes
        }
    }
    
    return $results;
}

/**
 * Obtener todas las URLs del sitio - VERSIÓN CONSERVADORA
 */
function sbp_get_all_site_urls() {
    global $wpdb;
    
    $urls = array();
    
    // 1. PÁGINA PRINCIPAL
    $urls[] = home_url();
    
    // 2. PÁGINAS PUBLICADAS (limitadas para evitar timeouts)
    $pages = $wpdb->get_results("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'page' 
        AND post_status = 'publish'
        ORDER BY menu_order, post_date DESC
        LIMIT 100
    ");
    
    foreach ($pages as $page) {
        $permalink = get_permalink($page->ID);
        if ($permalink && !sbp_is_url_excluded($permalink)) {
            $urls[] = $permalink;
        }
    }
    
    // 3. POSTS RECIENTES (limitados)
    $posts = $wpdb->get_results("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'post' 
        AND post_status = 'publish'
        ORDER BY post_date DESC
        LIMIT 100
    ");
    
    foreach ($posts as $post) {
        $permalink = get_permalink($post->ID);
        if ($permalink && !sbp_is_url_excluded($permalink)) {
            $urls[] = $permalink;
        }
    }
    
    // 4. CUSTOM POST TYPES (excluyendo WooCommerce)
    $post_types = get_post_types(array(
        'public' => true,
        '_builtin' => false
    ));
    
    foreach ($post_types as $post_type) {
        // Excluir productos de WooCommerce
        if ($post_type === 'product' && class_exists('WooCommerce')) {
            continue;
        }
        
        $custom_posts = $wpdb->get_results($wpdb->prepare("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = %s 
            AND post_status = 'publish'
            ORDER BY post_date DESC
            LIMIT 50
        ", $post_type));
        
        foreach ($custom_posts as $custom_post) {
            $permalink = get_permalink($custom_post->ID);
            if ($permalink && !sbp_is_url_excluded($permalink)) {
                $urls[] = $permalink;
            }
        }
    }
    
    // 5. CATEGORÍAS Y TAGS (solo si no es WooCommerce)
    if (!class_exists('WooCommerce')) {
        // Categorías principales
        $categories = get_categories(array(
            'hide_empty' => true,
            'number' => 20
        ));
        
        foreach ($categories as $category) {
            $category_link = get_category_link($category->term_id);
            if ($category_link && !sbp_is_url_excluded($category_link)) {
                $urls[] = $category_link;
            }
        }
        
        // Tags principales
        $tags = get_tags(array(
            'hide_empty' => true,
            'number' => 20
        ));
        
        foreach ($tags as $tag) {
            $tag_link = get_tag_link($tag->term_id);
            if ($tag_link && !sbp_is_url_excluded($tag_link)) {
                $urls[] = $tag_link;
            }
        }
    }
    
    // Eliminar duplicados y URLs vacías
    $urls = array_unique(array_filter($urls));
    
    return $urls;
}

/**
 * Verificar si una URL debe ser excluida
 */
function sbp_is_url_excluded($url) {
    $excluded_pages = get_option('sbp_excluded_pages', array());
    if (is_string($excluded_pages)) {
        $excluded_pages = explode("\n", $excluded_pages);
    }
    
    $parsed_url = parse_url($url);
    $path = $parsed_url['path'] ?? '';
    
    foreach ($excluded_pages as $excluded_page) {
        $excluded_page = trim($excluded_page);
        if (!empty($excluded_page) && strpos($path, $excluded_page) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * GENERAR archivo estático para una URL específica - CONSERVADOR
 */
function sbp_generate_static_file_for_url($url) {
    // Headers para simular visitante anónimo
    $headers = array(
        'User-Agent' => 'StaticBoost-Pro/2.0 (Generator)',
        'Cache-Control' => 'no-cache',
        'Pragma' => 'no-cache',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.5',
        'Accept-Encoding' => 'gzip, deflate'
    );
    
    // Configurar request
    $args = array(
        'timeout' => 60, // Reducido para evitar timeouts
        'headers' => $headers,
        'cookies' => array(),
        'sslverify' => false,
        'user-agent' => 'StaticBoost-Pro/2.0 (Generator)',
        'redirection' => 5,
        'blocking' => true
    );
    
    $response = wp_remote_get($url, $args);
    
    if (is_wp_error($response)) {
        error_log('SBP Error generando ' . $url . ': ' . $response->get_error_message());
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        error_log('SBP Error HTTP ' . $response_code . ' para ' . $url);
        return false;
    }
    
    $html = wp_remote_retrieve_body($response);
    if (empty($html)) {
        error_log('SBP HTML vacío para ' . $url);
        return false;
    }
    
    // Verificar que sea HTML válido
    if (strpos($html, '<html') === false && strpos($html, '<!DOCTYPE') === false) {
        error_log('SBP HTML inválido para ' . $url);
        return false;
    }
    
    // Optimizar HTML de forma CONSERVADORA
    $html = apply_filters('sbp_static_html', $html, $url);
    
    // Guardar archivo estático
    $static_file_path = sbp_get_static_file_path_from_url($url);
    $static_dir = dirname($static_file_path);
    
    if (!file_exists($static_dir)) {
        wp_mkdir_p($static_dir);
    }
    
    $result = file_put_contents($static_file_path, $html, LOCK_EX);
    
    if ($result) {
        // Crear versión comprimida
        if (function_exists('gzencode')) {
            file_put_contents($static_file_path . '.gz', gzencode($html, 9), LOCK_EX);
        }
        
        return true;
    }
    
    return false;
}

/**
 * Obtener ruta del archivo estático desde URL
 */
function sbp_get_static_file_path_from_url($url) {
    $parsed_url = parse_url($url);
    $path = $parsed_url['path'] ?? '/';
    $path = rtrim($path, '/');
    
    if (empty($path) || $path === '/') {
        $path = '/index';
    }
    
    return SBP_CACHE_DIR . ltrim($path, '/') . '/index.html';
}

/**
 * PRECARGAR caché de páginas principales - CONSERVADOR
 */
function sbp_preload_cache() {
    $urls = array();
    
    // Página principal
    $urls[] = home_url();
    
    // Páginas principales (limitado para precarga rápida)
    $pages = get_pages(array(
        'post_status' => 'publish',
        'number' => 20, // Reducido
        'sort_column' => 'menu_order'
    ));
    
    foreach ($pages as $page) {
        $permalink = get_permalink($page->ID);
        if ($permalink && !sbp_is_url_excluded($permalink)) {
            $urls[] = $permalink;
        }
    }
    
    // Posts recientes
    $posts = get_posts(array(
        'post_status' => 'publish',
        'numberposts' => 20, // Reducido
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    foreach ($posts as $post) {
        $permalink = get_permalink($post->ID);
        if ($permalink && !sbp_is_url_excluded($permalink)) {
            $urls[] = $permalink;
        }
    }
    
    // Generar archivos estáticos
    $success_count = 0;
    foreach ($urls as $url) {
        if (sbp_generate_static_file_for_url($url)) {
            $success_count++;
        }
        
        // Pausa entre requests
        usleep(50000); // 0.05 segundos
    }
    
    return $success_count;
}

/**
 * Obtener estadísticas de archivos estáticos
 */
function sbp_get_cache_stats() {
    $stats = array(
        'files' => 0,
        'size' => 0,
        'last_generated' => null
    );
    
    if (!is_dir(SBP_CACHE_DIR)) {
        return $stats;
    }
    
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(SBP_CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        $latest_time = 0;
        
        foreach ($iterator as $file) {
            if ($file->getFilename() === 'index.html') {
                $stats['files']++;
                $stats['size'] += $file->getSize();
                
                $mtime = $file->getMTime();
                if ($mtime > $latest_time) {
                    $latest_time = $mtime;
                }
            }
        }
        
        if ($latest_time > 0) {
            $stats['last_generated'] = date('Y-m-d H:i:s', $latest_time);
        }
    } catch (Exception $e) {
        error_log('SBP Error getting stats: ' . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Limpiar archivo estático de una página específica
 */
function sbp_clear_static_file_by_url($url) {
    $static_file_path = sbp_get_static_file_path_from_url($url);
    
    if (file_exists($static_file_path)) {
        unlink($static_file_path);
        
        // También eliminar versión comprimida
        if (file_exists($static_file_path . '.gz')) {
            unlink($static_file_path . '.gz');
        }
        
        return true;
    }
    
    return false;
}

/**
 * Obtener conteo total de páginas en el sitio
 */
function sbp_get_total_pages_count() {
    global $wpdb;
    
    // Contar páginas
    $pages_count = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->posts} 
        WHERE post_type = 'page' AND post_status = 'publish'
    ");
    
    // Contar posts
    $posts_count = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->posts} 
        WHERE post_type = 'post' AND post_status = 'publish'
    ");
    
    // Contar custom post types (excluyendo productos de WooCommerce)
    $custom_post_types = get_post_types(array(
        'public' => true,
        '_builtin' => false
    ));
    
    $custom_posts_count = 0;
    foreach ($custom_post_types as $post_type) {
        if ($post_type !== 'product') { // Excluir productos WooCommerce
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type = %s AND post_status = 'publish'
            ", $post_type));
            $custom_posts_count += $count;
        }
    }
    
    // Contar categorías y tags (solo si no es WooCommerce)
    $categories_count = 0;
    $tags_count = 0;
    
    if (!class_exists('WooCommerce')) {
        $categories_count = wp_count_terms('category');
        $tags_count = wp_count_terms('post_tag');
    }
    
    return array(
        'pages' => intval($pages_count),
        'posts' => intval($posts_count),
        'custom_posts' => intval($custom_posts_count),
        'categories' => intval($categories_count),
        'tags' => intval($tags_count),
        'total' => intval($pages_count) + intval($posts_count) + intval($custom_posts_count) + intval($categories_count) + intval($tags_count) + 1
    );
}

/**
 * Ejecutar análisis BoostAI
 */
function sbp_run_boostai_analysis() {
    if (class_exists('SBP_BoostAI_Optimizer')) {
        $boostai_optimizer = new SBP_BoostAI_Optimizer();
        $boostai_optimizer->run_adaptive_analysis();
    }
}

/**
 * Ejecutar optimización de assets CONSERVADORA
 */
function sbp_run_asset_optimization() {
    if (class_exists('SBP_Asset_Optimizer')) {
        $asset_optimizer = new SBP_Asset_Optimizer();
        $asset_optimizer->optimize_assets_safely();
    }
}

/**
 * Ejecutar optimización de PageSpeed CONSERVADORA
 */
function sbp_run_pagespeed_optimization() {
    if (class_exists('SBP_PageSpeed_Optimizer')) {
        $pagespeed_optimizer = new SBP_PageSpeed_Optimizer();
        $pagespeed_optimizer->optimize_for_pagespeed_safely();
    }
}

/**
 * Regenerar con nueva configuración
 */
add_action('sbp_regenerate_with_new_config', 'sbp_regenerate_with_new_config');
function sbp_regenerate_with_new_config() {
    // Limpiar caché existente
    sbp_clear_all_cache();
    
    // Regenerar páginas principales con nueva configuración
    sbp_preload_cache();
}