<?php

/**
 * Plugin Name: LiteSpeed Cache CloudFlare Single Purge
 * Description: Adds Cloudflare purge functionality to LiteSpeed Cache (LSCWP) when a post or page is updated or when using the "Clear this page - LS Cache".
 * Tags: litespeed cache, cloudflare, purge, litespeed, cdn
 * Author: Creative Hut
 * Author URI: https://www.creativehut.com.br/
 * Version: 1.1
 * Requires at least: 5.5
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Requires Plugins: litespeed-cache
 * License: GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */
/*
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/
if (! defined('ABSPATH')) exit;
if(!class_exists('LSCWP_CFSP')){
    class LSCWP_CFSP {

        private $purge_enabled;
        private $api_key;
        private $api_email;
        private $zone_id;
        private $endpoint;
        private $auth_headers;
        private $debug_enabled;

        public function __construct() {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            if(!is_plugin_active('litespeed-cache/litespeed-cache.php')) return;
            // Get Cloudflare settings from LSCWP options
            $this->purge_enabled = get_option('litespeed.conf.cdn-cloudflare_clear');
            $this->api_key = get_option('litespeed.conf.cdn-cloudflare_key');
            $this->api_email = get_option('litespeed.conf.cdn-cloudflare_email');
            $this->zone_id = get_option('litespeed.conf.cdn-cloudflare_zone');
            $this->debug_enabled = get_option('litespeed.conf.debug') >= 1;

            if (!$this->purge_enabled || !$this->zone_id || !$this->api_key) return;

            // Detect API key type and set authentication headers
            if (strlen($this->api_key) === 40) {
                $this->auth_headers = [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key,
                ];
            } else {
                $this->auth_headers = [
                    'Content-Type' => 'application/json',
                    'X-Auth-Email' => $this->api_email,
                    'X-Auth-Key'   => $this->api_key,
                ];
            }

            // Define Cloudflare API endpoint
            $this->endpoint = "https://api.cloudflare.com/client/v4/zones/{$this->zone_id}/purge_cache";

            // Define LSCWP hooks
            add_action('litespeed_purged_post', [$this, 'LSCWP_CFSP_purge_post'], 10); // Hook for purge after save post
            add_action('litespeed_purged_front', [$this, 'LSCWP_CFSP_purge_single_url'], 10, 1); // Hook for purge on admin bar click "Clear this page - LS Cache"
            add_action('litespeed_purge_url', [$this, 'LSCWP_CFSP_purge_single_url'], 10, 1); // Hook for purge by LSCWP URL hook
        }
        /**
         * LSCWP_CF_SP Logging function
         */
        private function LSCWP_CFSP_Cache_Log($message, $level = 'info') {
            // Check if debug is enabled
            if (!$this->debug_enabled) return;
            // Determine log type
            $prefix = '[LSCWP_CFSP]';
            $log_level = strtoupper($level);
            // If Debug2 class is available (Litespeed >= 5.7)
            if (class_exists('\LiteSpeed\Debug2')) {            
                \LiteSpeed\Debug2::debug("$prefix [$log_level] $message");            
            } else {
                // Fallback for WordPress default log
                error_log("$prefix [$log_level] $message");
            }
        }
        /**
         * Purge specific URL from Cloudflare cache
         */
        public function LSCWP_CFSP_purge_cloudflare_urls($urls) {
            if (!is_array($urls)) $urls = [$urls];
            $urls = array_unique(array_filter($urls));
            if (empty($urls)) return;

            $url_chunks = array_chunk($urls, 30);

            foreach ($url_chunks as $chunk) {
                $response = wp_remote_post($this->endpoint, [
                    'headers' => $this->auth_headers,
                    'body' => json_encode(['files' => $chunk]),
                    'timeout' => 30
                ]);

                if (is_wp_error($response)) {
                    $this->LSCWP_CFSP_Cache_Log(sprintf('❌ Cloudflare purge for %s failed: %s', $urls[0], $response->get_error_message()), 'error');
                    continue;
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!isset($body['success']) || !$body['success']) {
                    $msg = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'unknown error';
                    $this->LSCWP_CFSP_Cache_Log(sprintf('❌ Cloudflare purge for %s failed: %s', $urls[0], $msg), 'error');
                    continue;
                }

                $this->LSCWP_CFSP_Cache_Log('✅ Cloudflare successfully purged these URLs: ' . implode(', ', $chunk), 'notice');
            }
        }
        /**
         * Purge specific post from Cloudflare cache 
         */
        public function LSCWP_CFSP_purge_post($post_id, $type='') {
            if(!$post_id) return;
            $this->LSCWP_CFSP_Cache_Log(sprintf('☁ Cloudflare purge iniciated for Post ID: %s', $post_id));
            $post = get_post($post_id);
            $urls[] = get_permalink($post_id);
            // Post type archive URL
            $post_type_archive_link = get_post_type_archive_link($post->post_type);
            if($post_type_archive_link) $urls[] = $post_type_archive_link;
            // Post terms archive URLs
            $post_taxonomies = get_object_taxonomies($post->post_type);
            foreach($post_taxonomies as $taxonomy) {
                $terms = wp_get_post_terms($post_id, $taxonomy);
                foreach($terms as $term) {
                    $urls[] = get_term_link($term);
                }
            }   
            // Post author archive URL    
            $urls[] = get_author_posts_url($post->post_author);
            if (!empty($urls)) {
                $this->LSCWP_CFSP_purge_cloudflare_urls($urls);
            }
        }
        /**
         * Purge a single URL from Cloudflare cache
         */
        public function LSCWP_CFSP_purge_single_url($url) {
            if (empty($url)) return;
            $this->LSCWP_CFSP_Cache_Log(sprintf('☁ Cloudflare purge iniciated for Single URL: %s', $url));
            $post_id = url_to_postid($url);
            if($post_id) {
                $this->LSCWP_CFSP_purge_post($post_id);
                return;
            }
            $this->LSCWP_CFSP_purge_cloudflare_urls([$url]);        
        }
    }
    add_action('litespeed_init', function() {
        new LSCWP_CFSP();
    });
}