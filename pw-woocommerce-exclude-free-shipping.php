<?php
/**
Plugin Name: PW WooCommerce Exclude Free Shipping
Plugin URI: https://wordpress.org/plugins/pw-woocommerce-exclude-free-shipping
Description: Specify products that cause Free Shipping to not be available when they are in the cart.
Version: 1.4
Author: Pimwick, LLC
Author URI: https://pimwick.com
*/

/*
Copyright (C) 2017 Pimwick, LLC

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

// Verify this isn't called directly.
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

if ( ! class_exists( 'PW_Exclude_Free_Shipping' ) ) :

final class PW_Exclude_Free_Shipping {

    private $meta_name = '_pw_exclude_free_shipping';

    function __construct() {

        // Verify that WooCommerce is installed and active.
        if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_required_message' ) );
            return;
        }

        if ( is_admin() ) {
            add_action( 'woocommerce_product_options_shipping', array( $this, 'woocommerce_product_options_shipping' ) );
            add_action( 'woocommerce_process_product_meta', array( $this, 'woocommerce_process_product_meta' ) );
            add_filter( 'pwbe_common_joins', array( $this, 'pwbe_common_joins' ) );
            add_filter( 'pwbe_common_fields', array( $this, 'pwbe_common_fields' ) );
            add_filter( 'pwbe_common_where', array( $this, 'pwbe_common_where' ) );
            add_filter( 'pwbe_product_columns', array( $this, 'pwbe_product_columns' ) );
            add_filter( 'pwbe_filter_types', array( $this, 'pwbe_filter_types' ) );
        }

        add_filter( 'woocommerce_shipping_free_shipping_is_available', array( $this, 'woocommerce_shipping_free_shipping_is_available' ), 20 );
    }

    function woocommerce_product_options_shipping() {
        woocommerce_wp_checkbox( array(
            'id'            => $this->meta_name,
            'label'         => __( 'Exclude Free Shipping', 'pimwick' ),
            'description'   => __( 'If this product is in the cart, "Free Shipping" is not an option.', 'pimwick' )
        ) );
    }

    function woocommerce_process_product_meta( $post_id ) {
        if ( isset( $_POST[ $this->meta_name ] ) && !empty( $_POST[ $this->meta_name ] ) ) {
            update_post_meta( $post_id, $this->meta_name, esc_attr( $_POST[ $this->meta_name ] ) );
        } else {
            delete_post_meta( $post_id, $this->meta_name );
        }
    }

    function woocommerce_shipping_free_shipping_is_available( $is_available ) {
        global $woocommerce;

        $cart_items = $woocommerce->cart->get_cart();

        foreach ( $cart_items as $key => $item ) {
            if( 'yes' === get_post_meta( $item['product_id'], $this->meta_name, 'true' ) ) {
                return false;
            }
        }

        return $is_available;
    }

    function pwbe_common_joins( $sql ) {
        global $wpdb;

        $sql .= "
            LEFT JOIN
                {$wpdb->postmeta} AS exclude_free_shipping ON (exclude_free_shipping.post_id = parent.ID AND exclude_free_shipping.meta_key = '_pw_exclude_free_shipping')
        ";

        return $sql;
    }

    function pwbe_common_fields( $sql ) {
        global $wpdb;

        $sql .= ",
            COALESCE(NULLIF(exclude_free_shipping.meta_value, ''), 'no') AS _pw_exclude_free_shipping
        ";

        return $sql;
    }

    function pwbe_common_where( $sql ) {
        global $wpdb;

        if ( isset( $_POST['pwbe_flash_store_products_only'] ) && $_POST['pwbe_flash_store_products_only'] == 'true' ) {
            $sql .= "
                AND COALESCE(flash_store.meta_value, '') != ''
            ";
        }

        return $sql;
    }

    function pwbe_product_columns( $product_columns ) {

        $new_column = array(
            'name' => 'Exclude Free Shipping',
            'type' => 'checkbox',
            'table' => 'meta',
            'field' => '_pw_exclude_free_shipping',
            'readonly' => 'false',
            'visibility' => 'parent',
            'sortable' => 'true',
            'views' => array( 'all', 'standard' )
        );

        $insert_index = $this->index_of( 'field', 'product_shipping_class', $product_columns);
        if ( $insert_index <= 0 ) {
            $insert_index = $this->index_of( 'field', '_visibility', $product_columns);
        }
        $existing_index = $insert_index + 1;

        array_splice( $product_columns, $existing_index, 0, array( $new_column ) );

        return $product_columns;
    }

    function pwbe_filter_types( $filter_types ) {
        global $wpdb;

        $filter_types['exclude_free_shipping'] = array( 'name' => 'Exclude Free Shipping', 'type' => 'boolean' );

        ksort( $filter_types );

        return $filter_types;
    }

    function index_of( $key, $value, $array ) {
        foreach ( $array as $k => $v ) {
            if ( $v[ $key ] === $value ) {
                return $k;
            }
        }

        return null;
    }
}

new PW_Exclude_Free_Shipping();

endif;

?>