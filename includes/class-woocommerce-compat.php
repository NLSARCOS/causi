<?php
/**
 * Compatibilidad con WooCommerce
 */
class SBP_WooCommerce_Compat {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_filter('sbp_should_cache_page', array($this, 'exclude_woo_pages'), 10, 2);
        add_action('woocommerce_cart_updated', array($this, 'clear_cart_related_cache'));
        add_action('woocommerce_checkout_order_processed', array($this, 'clear_checkout_cache'));
        add_action('woocommerce_product_set_stock', array($this, 'clear_product_cache'));
        add_action('woocommerce_variation_set_stock', array($this, 'clear_product_cache'));
    }
    
    public function init() {
        // Asegurar que las páginas de WooCommerce no se cacheen
        add_filter('sbp_excluded_pages', array($this, 'add_woo_excluded_pages'));
    }
    
    /**
     * Añadir páginas de WooCommerce a la lista de exclusión
     */
    public function add_woo_excluded_pages($excluded_pages) {
        $woo_pages = array(
            '/cart',
            '/checkout',
            '/my-account',
            '/shop',
            '?add-to-cart=',
            '?remove_item=',
            '?wc-ajax=',
            '/product-category',
            '/product-tag'
        );
        
        return array_merge($excluded_pages, $woo_pages);
    }
    
    /**
     * Verificar si una página debe ser cacheada (excluyendo WooCommerce)
     */
    public function exclude_woo_pages($should_cache, $url) {
        // No cachear si es una página de WooCommerce dinámica
        if (is_cart() || is_checkout() || is_account_page()) {
            return false;
        }
        
        // No cachear productos individuales (pueden tener stock dinámico)
        if (is_product()) {
            return false;
        }
        
        // No cachear si hay parámetros de WooCommerce
        if (isset($_GET['add-to-cart']) || 
            isset($_GET['remove_item']) || 
            isset($_GET['wc-ajax'])) {
            return false;
        }
        
        // Permitir cachear páginas estáticas de WooCommerce
        if (is_shop() && empty($_GET)) {
            return true;
        }
        
        if ((is_product_category() || is_product_tag()) && empty($_GET)) {
            return true;
        }
        
        return $should_cache;
    }
    
    /**
     * Limpiar caché relacionado con el carrito
     */
    public function clear_cart_related_cache() {
        // Limpiar páginas que pueden mostrar información del carrito
        $pages_to_clear = array(
            home_url(),
            wc_get_cart_url(),
            wc_get_checkout_url()
        );
        
        foreach ($pages_to_clear as $url) {
            sbp_clear_static_file_by_url($url);
        }
    }
    
    /**
     * Limpiar caché del checkout
     */
    public function clear_checkout_cache() {
        sbp_clear_static_file_by_url(wc_get_checkout_url());
        sbp_clear_static_file_by_url(wc_get_cart_url());
    }
    
    /**
     * Limpiar caché cuando cambia el stock de un producto
     */
    public function clear_product_cache($product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $product_url = get_permalink($product_id);
            sbp_clear_static_file_by_url($product_url);
            
            // También limpiar páginas de categoría del producto
            $categories = get_the_terms($product_id, 'product_cat');
            if ($categories) {
                foreach ($categories as $category) {
                    $category_url = get_term_link($category);
                    sbp_clear_static_file_by_url($category_url);
                }
            }
            
            // Limpiar página de tienda
            sbp_clear_static_file_by_url(wc_get_page_permalink('shop'));
        }
    }
}