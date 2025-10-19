<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPPulse_Plugin_Updater {
    private $file;
    private $slug;
    private $version;
    private $endpoint;

    public function __construct( $file, $slug, $version, $endpoint ) {
        $this->file     = $file;
        $this->slug     = $slug;            // must be plugin folder slug
        $this->version  = $version;
        $this->endpoint = untrailingslashit( $endpoint );

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );
    }

    // Strict update check — only set response for this plugin file
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $plugin_basename = plugin_basename( $this->file ); // e.g. my-plugin/my-plugin.php

        // Call server manifest endpoint with slug + current version
        $url = add_query_arg( [
            'slug'    => $this->slug,
            'version' => $this->version,
        ], $this->endpoint . '/update' );

        $res = wp_remote_get( $url, [ 'timeout' => 12 ] );
        if ( is_wp_error( $res ) ) return $transient;

        $body = json_decode( wp_remote_retrieve_body( $res ) );
        if ( empty( $body ) || empty( $body->version ) ) {
            // server returned empty => no update for this slug
            return $transient;
        }

        // Extra guard: ensure server returned same slug (avoid misconfigured server)
        if ( isset( $body->slug ) && $body->slug !== $this->slug ) {
            return $transient;
        }

        // If remote version > local version then register update ONLY for this plugin basename
        if ( version_compare( $this->version, (string) $body->version, '<' ) ) {
            $obj = new stdClass();
            $obj->slug        = $this->slug;
            $obj->plugin      = $plugin_basename;
            $obj->new_version = (string) $body->version;
            $obj->package     = $body->download_url ?? '';
            $obj->url         = $body->homepage ?? '';
            $obj->tested      = $body->tested ?? '';
            $obj->requires    = $body->requires ?? '';

            // IMPORTANT: set only this plugin's response — do not touch other entries
            $transient->response[ $plugin_basename ] = $obj;
        }

        return $transient;
    }

    // Show "View details" popup (plugin info)
    public function plugins_api( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;

        // only respond when wp requests info for this slug
        if ( empty( $args->slug ) || $args->slug !== $this->slug ) {
            return $result;
        }

        $url = add_query_arg( [ 'slug' => $this->slug ], $this->endpoint . '/update' );
        $res = wp_remote_get( $url, [ 'timeout' => 12 ] );
        if ( is_wp_error( $res ) ) return $result;

        $body = json_decode( wp_remote_retrieve_body( $res ) );
        if ( empty( $body ) || empty( $body->version ) ) return $result;

        // build plugin info object expected by WP
        $info = new stdClass();
        $info->name          = $body->name ?? '';
        $info->slug          = $this->slug;
        $info->version       = $body->version;
        $info->author        = $body->author ?? '';
        $info->requires      = $body->requires ?? '';
        $info->tested        = $body->tested ?? '';
        $info->download_link = $body->download_url ?? '';
        $info->sections      = (array) ( $body->sections ?? [] );

        return $info;
    }
}
