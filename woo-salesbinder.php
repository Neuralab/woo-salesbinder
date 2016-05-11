<?php
/**
 * Plugin Name: Woo + SalesBinder
 * Plugin URI: https://wordpress.org/plugins/woo-salesbinder/
 * Description: Sync WooCommerce with your SalesBinder data.
 * Author: SalesBinder Development Team
 * Author URI: http://www.salesbinder.com
 * Version: 1.0.5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
require_once plugin_dir_path( __DIR__ ) . 'woocommerce/includes/wc-notice-functions.php';
require_once dirname(dirname(plugin_dir_path( __DIR__ ))) . '/wp-admin/includes/image.php';
require_once 'customs.php';

if ( ! class_exists( 'WC_SalesBinder' ) ) :

class WC_SalesBinder {

    protected static $instance = null;

    /**
     * Initialize the plugin.
     *
     * @since 1.0
     */


    public function __construct() {
        if ( class_exists( 'WooCommerce' ) ) {

           // Create a Section in the woocommerce settings
            add_filter( 'woocommerce_settings_tabs_array', array($this, 'wcsalesbinder_add_section') );
            add_action( 'woocommerce_settings_tabs_wcsalesbinder', array($this, 'settings_tab') );
            add_action( 'woocommerce_update_options_wcsalesbinder', array($this, 'update_settings') );
            //add_action( 'woocommerce_order_status_completed', array($this, 'woo_order_success'));
			      add_action( 'woocommerce_checkout_order_processed', array($this, 'woo_order_success'));
            add_action( 'wp_admin_force_sync', array($this, 'force_sync') );

            add_action( 'wcsalesbinder_cron', array($this, 'cron'));
            add_action( 'wcsalesbinder_partial_cron', array($this, 'partial_cron'));
            add_filter('cron_schedules', array($this, 'cron_schedules'));
        }
    }


    public function force_sync() {

        $this->update_settings();
        do_action('wcsalesbinder_cron');
    }


    public function wcsalesbinder_add_section( $sections ) {

        $sections['wcsalesbinder'] = __( 'Woo + SalesBinder', 'woocommerce' );
        return $sections;
    }


    public function settings_tab() {

        woocommerce_admin_fields( $this->wcsalesbinder_get_settings() );
        $current_sync_page_cmb = (get_option("current_sync_page")) ? get_option("current_sync_page") : 0;
        $total_pages_to_sync = (get_option("total_pages_to_sync")) ? get_option("total_pages_to_sync") : 0;
        $wcsalesbinder_last_synced = (get_option("wcsalesbinder_last_synced")) ? get_option("wcsalesbinder_last_synced") : '';
        //echo '<a id="link_force_sync" class="button button-secondary" style="float: right;" href="#"> Force Sync Inventory Data </a>';
        if ($current_sync_page_cmb > 0 && $total_pages_to_sync > 0) echo '<div id="message" class="updated"><p><strong>Syncing</strong>: Page ' . $current_sync_page_cmb . ' of ' . $total_pages_to_sync . '</p></div>';
        if ($current_sync_page_cmb > 0 && $total_pages_to_sync > 0) {
          echo '<p><strong>Data last synced:</strong> syncing now...</p>';
        }elseif (!empty($wcsalesbinder_last_synced)) {
          echo '<p><strong>Data last synced:</strong> '. human_time_diff( $wcsalesbinder_last_synced ) .' ago</p>';
        }
        echo '<script type="text/javascript"> jQuery(document).ready(function($){ $(document).on("click", "#link_force_sync", function(e){ e.preventDefault(); $("#wcsalesbinder_withtest").val("1"); $("#mainform").submit();}); });</script>';
    }


    public function wcsalesbinder_get_settings(){

        $settings_wcsalesbinder = array();

        $settings_wcsalesbinder[] = array(
            'name' => __( 'Woo + SalesBinder Settings', 'woocommerce' ),
            'type' => 'title',
            'desc' => __( 'The following options are used to integrate your SalesBinder Account data with this WooCommerce website.', 'woocommerce' ),
            'id' => 'wcsalesbinder'
        );

        $settings_wcsalesbinder[] =  array(
            'name'     => __( 'SalesBinder Subdomain', 'woocommerce' ),
            'desc_tip' => __( 'Enter your SalesBinder Subdomain', 'woocommerce' ),
            'id'       => 'wcsalesbinder_subdomain',
            'type'     => 'text',
            'desc'     => __( '.salesbinder.com', 'woocommerce' ),
        );

        $settings_wcsalesbinder[] =  array(
            'name'     => __( 'SalesBinder API Key', 'woocommerce' ),
            'desc_tip' => __( 'Enter your SalesBinder Api Key', 'woocommerce' ),
            'id'       => 'wcsalesbinder_apikey',
            'type'     => 'text',
            'css'      => 'min-width:400px;',
            'desc'     => __( 'By example: c6d822b53968f4e7894568bfasde57d899bb72k', 'woocommerce' ),
        );

        $settings_wcsalesbinder[] =  array(
            'name'     => __( 'Context Account', 'woocommerce' ),
            'desc_tip' => __( 'Choose an Account Context', 'woocommerce' ),
            'id'       => 'wcsalesbinder_context_account',
            'type'     => 'radio',
            'desc'     => __( '', 'woocommerce' ),
            'options'  => array(
                2=> __('Customer', 'woocommerce'),
                8=> __('Prospect', 'woocommerce'),
            ),
        );

        $settings_wcsalesbinder[] =  array(
            'name'     => __( 'Context Document', 'woocommerce' ),
            'desc_tip' => __( 'Choose a Document Context', 'woocommerce' ),
            'id'       => 'wcsalesbinder_context_document',
            'type'     => 'select',
            'desc'     => __( '', 'woocommerce' ),
            'options'  => array(
                4=> __('Estimate', 'woocommerce'),
                5=> __('Invoice', 'woocommerce'),
                11=> __('Purchase Order', 'woocommerce'),

            ),
        );

        $settings_wcsalesbinder[] =  array(
            'name'     => __( 'Full Sync Interval', 'woocommerce' ),
            'desc_tip' => __( 'Choose an interval option from the list. This will be used to complete a full sync of your SalesBinder Account data (slower)', 'woocommerce' ),
            'id'       => 'wcsalesbinder_sync',
            'type'     => 'select',
            'desc'     => __( '', 'woocommerce' ),
            'options'  => array(
                'disabled'=> __('Disabled', 'woocommerce'),
                //'hourly'=> __('Hourly', 'woocommerce'),
                'daily'=> __('Daily', 'woocommerce'),
                'twicedaily'=> __('Twicedaily', 'woocommerce'),
            ),
        );

        $settings_wcsalesbinder[] =  array(
            'name'     => __( 'Incremental Sync Interval', 'woocommerce' ),
            'desc_tip' => __( 'Choose an interval option from this list. This will be used to sync your latest data changes from your SalesBinder Account (faster)', 'woocommerce' ),
            'id'       => 'wcsalesbinder_partial_sync',
            'type'     => 'select',
            'desc'     => __( '', 'woocommerce' ),
            'options'  => array(
                'disabled'=> __('Disabled', 'woocommerce'),
                'onceevery5minutes'=> __('Every 5 minutes', 'woocommerce'),
                'onceevery30minutes'=> __('Every 30 minutes', 'woocommerce'),
            ),
        );

        $settings_wcsalesbinder[] =  array(
            'name'     => __( '', 'woocommerce' ),
            'desc_tip' => __( '', 'woocommerce' ),
            'id'       => 'wcsalesbinder_withtest',
            'type'     => 'text',
            'css'      =>  'display:none;',
            'desc'     => __( '', 'woocommerce' ),
        );


        $settings_wcsalesbinder[] = array(
            'type' => 'sectionend',
            'id' => 'wcsalesbinder_end'
        );

        $settings_wcsalesbinder[] = array(
            'name' => __( 'Note:' ),
            'type' => 'title',
            'desc' => __( '<div style="max-width: 700px;">Pressing the "Save changes" button below will restart the sync process in the background. It may take a few minutes for your initial sync to start showing products in your WooCommerce Products section.</div>', 'woocommerce' ),
            'id' => 'wcsalesbinder_note'
        );

        $settings_wcsalesbinder[] = array(
            'type' => 'sectionend',
            'id' => 'wcsalesbinder_end'
        );

        return apply_filters( 'wc_settings_wcsalesbinder', $settings_wcsalesbinder );
    }


    public function update_settings() {

        woocommerce_update_options( $this->wcsalesbinder_get_settings() );

        wp_clear_scheduled_hook('wcsalesbinder_cron');

        $interval = get_option('wcsalesbinder_sync');
        $partial_interval = get_option('wcsalesbinder_partial_sync');

        if (!empty($interval)) {
            wp_schedule_event(time(), $interval, 'wcsalesbinder_cron');
        }

        // Only updates partial sync interval if a sync has already completed.
        // If this is the first sync, this cron job will be setup after the first sync is completed.
        if ((get_option("wcsalesbinder_last_synced")) && (!empty($partial_interval))) {
            wp_clear_scheduled_hook('wcsalesbinder_partial_cron');
            wp_schedule_event(time(), $partial_interval, 'wcsalesbinder_partial_cron');
        }

        $withtest = get_option('wcsalesbinder_withtest');
        if($withtest == 1){
            update_option('wcsalesbinder_withtest', '0');
            do_action('wcsalesbinder_cron');
        }
    }


    public function cron_schedules($schedules)
    {
        $schedules['onceevery5minutes'] = array(
            'interval' => 60 * 5,
            'display' => 'Once Every 5 Minutes',
            'wcsalesbinder' => true
        );

        $schedules['onceevery30minutes'] = array(
            'interval' => 60 * 30,
            'display' => 'Once Every 30 Minutes',
            'wcsalesbinder' => true
        );

        $schedules['twicehourly'] = array(
            'interval' => 30 * 60,
            'display' => 'Twice Hourly',
            'wcsalesbinder' => true
        );

        return $schedules;
    }


    public function partial_cron()
    {
        $subdomain = get_option( 'wcsalesbinder_subdomain' );
        $api_key = get_option( 'wcsalesbinder_apikey' );

        if (!$api_key || !$subdomain) {
          return;
        }

        $this->sync_categories();
        $this->sync_products('partial');
    }


    public function cron()
    {
        $subdomain = get_option( 'wcsalesbinder_subdomain' );
        $api_key = get_option( 'wcsalesbinder_apikey' );

        if (!$api_key || !$subdomain) {
          return;
        }

        if (!ini_get('safe_mode')) {
          set_time_limit(0);
        }

        wc_print_notice(str_repeat('-', 16). ' SalesBinder sync started ' . str_repeat('-', 16), 'notice');
        wc_print_notice(date('m/d/Y H:i:s'), 'notice');

        $this->sync_categories();
        $this->sync_products();

        wc_print_notice(date('m/d/Y H:i:s'), 'notice');
        wc_print_notice( str_repeat('-', 16). ' SalesBinder sync completed ' . str_repeat('-', 16), 'notice');
    }


    public function sync_categories()
    {
        $page = 1;
        $local_categories = array();
        $server_categories = array();
        $subdomain = get_option( 'wcsalesbinder_subdomain' );
        $api_key = get_option( 'wcsalesbinder_apikey' );

        do {
          $url = 'https://'.$api_key.':x@' . $subdomain . '.salesbinder.com/api/categories.json?page=' . $page;
          $response = wp_remote_get($url, array('timeout' => 60,));

          if (wp_remote_retrieve_response_code($response) != 200 || is_wp_error($response)) {
            wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
            return;
          }

          $response = json_decode(wp_remote_retrieve_body($response), true);

          if (!empty($response['Categories'])) {
            foreach ($response['Categories'] as $category) {
              $server_categories[] = $category['Category']['id'];
              $category_name = sanitize_text_field(str_replace('&', '&amp;', $category['Category']['name']));

              // check if exists
              $term = get_term_by('name', $category_name, 'product_cat');

                if (!empty($term->term_id)) {
                  // exists then update
                  $category_id = $term->term_id;

                  wp_update_term($category_id, 'product_cat', array(
                    'description' => $category['Category']['description']
                  ));

                } else {
                  //doesn't exist, then create
                  $category_id = wp_insert_term($category_name, 'product_cat', array('description'=>$category['Category']['description']));

                  $category_id = (array_key_exists('term_id', $category_id)) ? $category_id['term_id'] : null;
                }

              if (!empty($category_id)) {
                // Check if it has woocommerce_term_meta
                $old_id = get_woocommerce_term_meta($category_id, 'id_category_salesbinder', true);

                if(!$old_id) // doesn't have, then create
                    add_woocommerce_term_meta($category_id, 'id_category_salesbinder', $category['Category']['id'], true);
                    add_woocommerce_term_meta($category_id, 'product_count_product_cat', 0);
              }
            }
          }
        } while(!empty($response['pages']) && ++$page <= $response['pages']);

        // delete categories
        $local_categories = $this->getAllCategories();
        $to_delete = array_diff($local_categories, $server_categories);
        foreach ($to_delete as $delete) {
            $index = array_search($delete, $local_categories);
            delete_woocommerce_term_meta($index, 'id_category_salesbinder', $delete);
            wp_delete_term( $index, 'product_cat' );
        }
    }


    public function sync_products($partial = null)
    {
        ini_set('max_execution_time', 0);
        if (!get_option("current_sync_page"))
        {
          update_option("current_sync_page", 1);
        }

        if (get_option("wcsalesbinder_last_synced")) $wcsalesbinder_last_synced = get_option("wcsalesbinder_last_synced");

        $page = get_option("current_sync_page");
        if ($page === 0) $page = 1;

        $subdomain = get_option( 'wcsalesbinder_subdomain' );
        $api_key = get_option( 'wcsalesbinder_apikey' );
        $server_products = array();
        $local_products = array();

        do {

          if (isset($partial)) {
            $timestamp12 = (!empty($wcsalesbinder_last_synced)) ? ($wcsalesbinder_last_synced - 3600) : (time() - 43200);
            $url = 'https://'.$api_key.':x@' . $subdomain . '.salesbinder.com/api/items.json?page=' . $page . '&page_limit=40&order_field=modified&order_direction=desc&modified_since='.$timestamp12;
          }else{
            $url = 'https://'.$api_key.':x@' . $subdomain . '.salesbinder.com/api/items.json?page=' . $page . '&page_limit=40&order_field=modified&order_direction=desc';
          }
          $response = wp_remote_get($url, array('timeout' => 60));

          if (wp_remote_retrieve_response_code($response) != 200 || is_wp_error($response)) {
            wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
            return;
          }

          $response = json_decode(wp_remote_retrieve_body($response), true);

          if (!empty($response['pages']) && !isset($partial)) update_option("total_pages_to_sync", $response['pages']);

          if (!empty($response['Items'])) {

            foreach ($response['Items'] as $item) {
              if (!$item['Item']['published']) {
                continue;
              }

              $server_products[] = $item['Item']['id'];
              // check if product exists
              $post_id = $this->get_product_by_id_salesbinder($item['Item']['id']);
              if($post_id){
                // Exists!
                $wc_product = new WC_Product_Factory();
                // update
                wc_salesbinder_customs::da_update_post( array(
                  'ID' => $post_id,
                  'post_title' => $item['Item']['name'],
                  'post_content' => $item['Item']['description']
                ) );

                $product = $wc_product->get_product($post_id);
              }else{
                // Doesn't exist => create!
                $product = wc_salesbinder_customs::create_product($item);

                if (!$product) {
                  wc_print_notice('SalesBinder sync failed to add ' . $item['Item']['name'] . ' (' . $item['Item']['id'] . ')', 'error');
                  continue;
                }
              }

              // update prices
			        update_post_meta( $product->post->ID, '_regular_price', $item['Item']['price'] );
              update_post_meta( $product->post->ID, '_price', $item['Item']['price'] );

              // update stock
              update_post_meta( $product->post->ID, '_sku', $item['Item']['sku'] );
              update_post_meta( $product->post->ID, '_stock', $item['Item']['quantity'] );

              $product_old_specs = maybe_unserialize( get_post_meta($product->post->ID, '_product_attributes', true) );

              $specs = array();

              if (!empty($item['ItemDetail'])) {
                $i = 0;
                $product_weight = null;
                foreach ($item['ItemDetail'] as $detail) {

                  if (!empty($detail['CustomField']['publish']) && !empty($detail['value'])) {
                    $specs[$detail['CustomField']['name']] = array(
                        'name'=> $detail['CustomField']['name'],
                        'value'=> $detail['value'],
                        'position'=> $detail['CustomField']['weight'],
                        'is_visible'=> 1,
                        'is_variation'=> 0,
                        'is_taxonomy'=> 0
                    );

                    if (isset($detail['CustomField']['name']) && (strpos(strtolower($detail['CustomField']['name']),'weight') !== false)) {
                      $product_weight = $detail['value'];
                    }

                    $i++;
                  }

                }

                if (!empty($specs)) { // add custom fields
                    update_post_meta($product->post->ID, '_product_attributes', maybe_serialize($specs));
                }

                if (!empty($product_weight)) {
                    update_post_meta($product->post->ID, '_weight', preg_replace('/\D/', '', $product_weight) ); // set weight
                }else{
                    update_post_meta($product->post->ID, '_weight', null);
                }
              }

              if(!empty($product_old_specs) && empty($specs)){ // before has custom fields but actually doesn't have
                  update_post_meta($product->post->ID, '_product_attributes', '');
              }

              // Asign Images to Product
              $filenames = array();
              $existing_filenames = array();


              $galery = get_post_meta($product->post->ID, '_product_image_gallery', true);
              $images = explode(',', $galery);

              if (!empty($images)) {
                foreach ($images as $image) {
                  $filename_only = basename( get_attached_file( $image ) );
                  $existing_filenames[$image] = $filename_only;
                }
              }

              if (!empty($item['Image'])) {

                // ensure image order is correct so primary image is first
                usort($item['Image'], function($a, $b) {
                  return $a['weight'] - $b['weight'];
                });

                foreach ($item['Image'] as $image) {

                  // get url_medium filename
                  $path_parts = pathinfo($image['url_medium']);
                  $image['filename'] = $path_parts['basename'];

                  if (!in_array($image['filename'], $existing_filenames)) {

                    $image_response = wp_remote_get($image['url_medium'], array(
                      'stream' => true,
                      'timeout' => 10
                    ));

                    if (wp_remote_retrieve_response_code($image_response) != 200 || is_wp_error($image_response) || !is_readable($image_response['filename'])) {
                      //wc_print_notice('SalesBinder could not sync image for "'.$item['Item']['name'].'" (image url likely incorrect): ' . $image['url_medium'], 'error');
                      continue;
                    }

                    $this->set_product_gallery($product->post->ID, $image_response['filename'], $item['Item']['name']);

                    unlink($image_response['filename']);
                  }

                  $filenames[] = $image['filename'];
                }
              }

              // Delete images not sent!
              $old = get_post_meta($product->post->ID, '_product_image_gallery', true);
              $id_images = explode(',',$old);
              $temp_images = array();
              foreach ($id_images as $id_image) {
                $temp_images[$id_image] = basename( get_attached_file( $id_image ) );
              }

              if (!empty($existing_filenames)) {
                foreach ($existing_filenames as $id => $existing_filename) {
                  if (!in_array($existing_filename, $filenames)) {
                      $index = array_search($existing_filename, $temp_images);
                      $id = array_search($index, $id_images);
                      unset($id_images[$id]);
                      update_post_meta( $product->post->ID, '_product_image_gallery', implode(',', $id_images) );
                  }
                }
              }

              // asign categories to product
              if (!empty($item['Category']['name'])) {
                $category_name = sanitize_text_field(str_replace('&', '&amp;', $item['Category']['name']));
                $term = get_term_by('name', $category_name, 'product_cat');
                if (!empty($term)) {
                  $category_id = $term->term_id;
                  $check_asign = wp_set_object_terms( $product->post->ID, $category_id, 'product_cat' );

                  if(isset($check_asign)){
                    update_woocommerce_term_meta($category_id, 'product_count_product_cat', ($term->count + 1));
                  }
                }
              }
            }
          }
          $page++;
          update_option("current_sync_page", $page);
        } while(!empty($response['pages']) && $page <= $response['pages']);

        if(!get_option("wcsalesbinder_last_synced"))
        {
          // Setup incremental cron now that the first full sync has completed.
          // This will only run after the first sync successfully completes.
          $partial_interval = get_option('wcsalesbinder_partial_sync');
          if (!empty($partial_interval)) {
              wp_clear_scheduled_hook('wcsalesbinder_partial_cron');
              wp_schedule_event(time(), $partial_interval, 'wcsalesbinder_partial_cron');
          }
        }

        update_option("wcsalesbinder_last_synced", time());
        update_option("current_sync_page", 0); // Set to zero if sync fully completes
        update_option("total_pages_to_sync", 0);

        $page = 0;

        /*
        // TODO: delete products
        $local_products = $this->getAllProducts();
        $to_delete = array_diff($local_products, $server_products);
        foreach ($to_delete as $delete) {
            $index = array_search($delete, $local_products);
            wp_delete_post( $index );
        }

        // Delete all products with author = 0
        wc_salesbinder_customs::delete_post_author_zero();
        */
    }


    private function account($context, $email) {

        $subdomain = get_option( 'wcsalesbinder_subdomain' );
        $api_key = get_option( 'wcsalesbinder_apikey' );

		    $url = 'https://'.$api_key.':x@' . $subdomain . '.salesbinder.com/api/customers.json?email=' . urlencode($email);
        $response = wp_remote_get($url, array(
          'timeout'=>30
        ));

        if (wp_remote_retrieve_response_code($response) != 200 || is_wp_error($response)) {
          wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
          return;
        }

        $response = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($response['Customers'][0]['Customer']['id'])) {
          return $response['Customers'][0]['Customer']['id'];
        }
    }

    public function woo_order_success( $order_id ) {

        $subdomain = get_option( 'wcsalesbinder_subdomain' );
        $api_key = get_option( 'wcsalesbinder_apikey' );

        $order = new WC_Order( $order_id );
        $myuser_id = (int)$order->user_id;
        $user_info = get_userdata($myuser_id);
        $user = $this->getUserData($myuser_id);

        $account_context = get_option('wcsalesbinder_context_account');

    		if (!empty($user)) {
            if (!empty($_POST["billing_first_name"])) {
                $name = sanitize_text_field($_POST["billing_first_name"]);
                if (!empty($_POST["billing_last_name"])) $name = $name . ' ' . sanitize_text_field($_POST["billing_last_name"]);
            }
            if (!empty($_POST["billing_company"])) $name = sanitize_text_field($_POST["billing_company"]); // Use company name in SalesBinder if provided
            $billing_email = (!isset($_POST["billing_email"])) ? "" : sanitize_email($_POST["billing_email"]);
            $office_email = !empty($user->billing_email) ? $user->billing_email : '';
        		$office_phone = !empty($user->billing_phone) ? $user->billing_phone : '';
        		$billing_address_1 = !empty($user->billing_address_1) ? $user->billing_address_1 : '';
        		$billing_address_2 = !empty($user->billing_address_2) ? $user->billing_address_2 : '';
        		$billing_city = !empty($user->billing_city) ? $user->billing_city : '';
        		$billing_region = !empty($user->billing_state) ? $user->billing_state : '';
        		$billing_country = !empty($user->billing_country) ? $user->billing_country : '';
        		$billing_postal_code = !empty($user->billing_postal_code) ? $user->billing_postal_code : '';
        		$shipping_address_1 = !empty($user->shipping_address_1) ? $user->shipping_address_1 : '';
        		$shipping_address_2 = !empty($user->shipping_address_2) ? $user->shipping_address_2 : '';
        		$shipping_city = !empty($user->shipping_city) ? $user->shipping_city : '';
        		$shipping_region = !empty($user->shipping_state) ? $user->shipping_state : '';
        		$shipping_country = !empty($user->shipping_country) ? $user->shipping_country : '';
        		$shipping_postal_code = !empty($user->shipping_postcode) ? $user->shipping_postcode : '';
    		}else{
            if (!empty($_POST["billing_first_name"])) {
                $name = sanitize_text_field($_POST["billing_first_name"]);
                if (!empty($_POST["billing_last_name"])) $name = $name . ' ' . sanitize_text_field($_POST["billing_last_name"]);
            }
            if (!empty($_POST["billing_company"])) $name = sanitize_text_field($_POST["billing_company"]); // Use company name in SalesBinder if provided
            $billing_email = (!isset($_POST["billing_email"])) ? "" : sanitize_email($_POST["billing_email"]);
            $office_email = $billing_email;
            $office_phone = (!isset($_POST["billing_phone"])) ? "" : sanitize_text_field($_POST["billing_phone"]);
            $billing_address_1 = (!isset($_POST["billing_address_1"])) ? "" : sanitize_text_field($_POST["billing_address_1"]);
            $billing_address_2 = (!isset($_POST["billing_address_2"])) ? "" : sanitize_text_field($_POST["billing_address_2"]);
            $billing_city = (!isset($_POST["billing_city"])) ? "" : sanitize_text_field($_POST["billing_city"]);
            $billing_region = (!isset($_POST["billing_state"])) ? "" : sanitize_text_field($_POST["billing_state"]);
            $billing_country = (!isset($_POST["billing_country"])) ? "" : sanitize_text_field($_POST["billing_country"]);
            $billing_postal_code = (!isset($_POST["billing_postcode"])) ? "" : sanitize_text_field($_POST["billing_postcode"]);
            $shipping_address_1 = (!isset($_POST["shipping_address_1"])) ? "" : sanitize_text_field($_POST["shipping_address_1"]);
            $shipping_address_2 = (!isset($_POST["shipping_address_2"])) ? "" : sanitize_text_field($_POST["shipping_address_2"]);
            $shipping_city = (!isset($_POST["shipping_city"])) ? "" : sanitize_text_field($_POST["shipping_city"]);
            $shipping_region = (!isset($_POST["shipping_region"])) ? "" : sanitize_text_field($_POST["shipping_region"]);
            $shipping_country = (!isset($_POST["shipping_country"])) ? "" : sanitize_text_field($_POST["shipping_country"]);
            $shipping_postal_code = (!isset($_POST["shipping_postal_code"])) ? "" : sanitize_text_field($_POST["shipping_postal_code"]);
        }

        $account_id = $this->account($account_context, $billing_email);

        if (empty($account_id)) {
          $account = array(
              'Customer' => array(
                  'context_id' => $account_context ?: 8,
                  'name' => (!empty($name)) ? $name : 'No Name Provided',
                  'office_email' => $office_email,
                  'office_phone' => $office_phone,
                  'billing_address_1' => $billing_address_1,
                  'billing_address_2' => $billing_address_2,
                  'billing_city' => $billing_city,
                  'billing_region' => $billing_region,
                  'billing_country' => $billing_country,
                  'billing_postal_code' => $billing_postal_code,
                  'shipping_address_1' => $shipping_address_1,
                  'shipping_address_2' => $shipping_address_2,
                  'shipping_city' => $shipping_city,
                  'shipping_region' => $shipping_region,
                  'shipping_country' => $shipping_country,
                  'shipping_postal_code' => $shipping_postal_code
              )
          );

          $url = 'https://'.$subdomain . '.salesbinder.com/api/customers.json';
          $response = wp_remote_post($url, array(
      			'headers' => array(
      			  'Authorization' => 'Basic ' . base64_encode($api_key . ':x')
      			),
            'timeout' => 45,
            'body' => json_encode($account),
            'redirection' => 5
          ));

          if (wp_remote_retrieve_response_code($response) != 200 || is_wp_error($response)) {
            wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
            //error_log( 'SalesBinder sync failed to load (code not 200) ' . $url );
            return;
          }

          $account = json_decode($response['body'], true);
          if (empty($account['Customer']['id'])) {
            wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
            //error_log( 'Failed to get new customer ID: ' . $url );
            return;
          }
          $account_id = $account['Customer']['id'];
        }

        $document_context = get_option('wcsalesbinder_context_document');
        $document = array(
          'Document' => array(
            'context_id' => $document_context ?: 4,
            'customer_id' => $account_id,
            'issue_date' => date('Y-m-d', strtotime($order->order_date)),
            'shipping_address' => $order->get_formatted_shipping_address(),
          ),
          'DocumentsItem' => array()
        );

        $items_order = $order->get_items(); // get all items
        foreach ($items_order as $item) {
            $id_product_salesbinder = get_post_meta( $item['product_id'], 'id_product_salesbinder', true); // get id product salesbinder
            if(!empty($id_product_salesbinder)){
                $item_salesbinder = array(
                    'item_id' => $id_product_salesbinder,
                    'quantity' => $item['qty'],
                    'price' => round($item['line_subtotal']/$item['qty'],2),
                    'tax' => $item['line_tax'],
                    'tax2'=> 0,
                );

                $document['DocumentsItem'][] = $item_salesbinder;
            }
        }

        $url = 'https://'.$subdomain . '.salesbinder.com/api/documents.json';
        $response = wp_remote_post($url, array(
    			'headers' => array(
    				'Authorization' => 'Basic ' . base64_encode($api_key . ':x')
    			),
          'timeout' => 45,
          'body' => json_encode($document),
          'redirection' => 5
        ));

        if (wp_remote_retrieve_response_code($response) != 200 || is_wp_error($response)) {
          wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
          return;
        }

        $customer = json_decode($response['body'], true);
        if (empty($customer['Document']['id'])) {
          wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
          return;
        }

        update_post_meta( $order_id, 'id_purchase_salesbinder', $customer['Document']['id'] );
    }

    private function set_product_gallery($post_id, $image_path, $item_name){
      $upload = wp_upload_bits( basename($image_path), null, file_get_contents( $image_path ) );
      $wp_filetype = wp_check_filetype( basename( $upload['file'] ), null );

      $wp_upload_dir = wp_upload_dir();

      $attachment = array(
              //'guid' => $wp_upload_dir['baseurl'] . _wp_relative_upload_path( $upload['file'] ),
              'post_mime_type' => $wp_filetype['type'],
              'post_title' => $item_name ?: 'Product Photo',
              'post_content' => '',
              'post_status' => 'inherit'
      );

      $bng_attach_id = get_post_thumbnail_id($post_id);
      $base_path = explode("/", get_attached_file( $bng_attach_id ));
      $total_base_path = count($base_path);
      unset($base_path[$total_base_path-1]);
      $base_path = implode("/", $base_path);

      $unfiltered_attach = wp_get_attachment_metadata($bng_attach_id, true);

      if($unfiltered_attach)
      {
        $path_array = explode("/", $unfiltered_attach["file"]);
        $partial_path = end($path_array);

        if(file_exists($base_path . "/" . $partial_path))
        {
          unlink($base_path . "/" . $partial_path);
        }

        foreach ($unfiltered_attach["sizes"] as $value) {
            if(file_exists($base_path . "/" . $value["file"]))
            {
              unlink($base_path . "/" . $value["file"]);
            }
        }
      }

      $attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );
      $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );

      wp_update_attachment_metadata( $attach_id, $attach_data );

      $old = get_post_meta( $post_id, '_product_image_gallery', true );

      if(empty($old)){
        update_post_meta( $post_id, '_product_image_gallery', $attach_id );
      }else{
        update_post_meta( $post_id, '_product_image_gallery', $old.','.$attach_id );
      }
      update_post_meta( $post_id, '_thumbnail_id', $attach_id );
    }


    private function get_product_by_id_salesbinder($idSalesBinder)
    {
        $id_post = null;
        global $wpdb;
        $results = $wpdb->get_results( "SELECT post_id as id FROM ".$wpdb->prefix."postmeta WHERE meta_key = 'id_product_salesbinder' AND meta_value='".$idSalesBinder."' ", OBJECT );
        foreach ($results as $result) {
            $id_post = $result->id;
        }

        return $id_post;
    }


    private function getAllCategories() {
        global $wpdb;
        $results = $wpdb->get_results( 'SELECT woocommerce_term_id as term_id, meta_value as value FROM '.$wpdb->prefix.'woocommerce_termmeta WHERE meta_key = "id_category_salesbinder"', OBJECT );

        $return = array();
        foreach ($results as $result) {
            $return[$result->term_id] = $result->value;
        }

        return $return;
    }


    private function getAllProducts() {
        global $wpdb;
        $results = $wpdb->get_results( 'SELECT post_id as id, meta_value as value FROM '.$wpdb->prefix.'postmeta WHERE meta_key = "id_product_salesbinder"', OBJECT );

        $return = array();
        if (!empty($results)) {
          foreach ($results as $result) {
              $return[$result->id] = $result->value;
          }
        }

        return $return;
    }

    private function getUserData($user_id) {

        $userdata = get_user_meta($user_id,'',true);

        $user = new stdClass;
        if (!empty($userdata)) {
          foreach ($userdata as $attr => $valarr) {
              $user->$attr = $valarr[0];
          }
          return $user;
        }
        return false;
    }


    /**
     * Return an instance of this class.
     *
     * @return object A single instance of this class.
     * @since  1.0
     */
    public static function get_instance() {
        // If the single instance hasn't been set, set it now.
        if ( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }


}

add_action( 'init', array( 'WC_SalesBinder', 'get_instance' ), 0 );

endif;
