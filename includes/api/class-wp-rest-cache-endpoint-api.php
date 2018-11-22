<?php

/**
 * API for endpoint caching.
 *
 * @link:       http://www.acato.nl
 * @since       2018.1
 *
 * @package     WP_Rest_Cache
 * @subpackage  WP_Rest_Cache/includes/api
 */

/**
 * API for endpoint caching.
 *
 * Caches complete endpoints and handles the deletion if single items are updated.
 *
 * @package     WP_Rest_Cache
 * @subpackage  WP_Rest_Cache/includes/api
 * @author:     Richard Korthuis - Acato <richardkorthuis@acato.nl>
 *
 * @TODO:       Clear caches if new Posts/Terms are created
 */
class WP_Rest_Cache_Endpoint_Api {

    /**
     * The requested URI.
     *
     * @access   private
     * @var      string $request_uri The requested URI string.
     */
    private $request_uri;

    /**
     * The current cache key.
     *
     * @access  private
     * @var     string $cache_key The current cache key.
     */
    private $cache_key;

    /**
     * The response headers that need to be send with the cached call.
     *
     * $access  private
     * @var     array $response_headers The response headers.
     */
    private $response_headers = array(
        'Content-Type'                  => 'application/json; charset=UTF-8',
        'X-WP-cached-call'              => 'served-cache',
        'X-Robots-Tag'                  => 'noindex',
        'X-Content-Type-Options'        => 'nosniff',
        'Access-Control-Expose-Headers' => 'X-WP-Total, X-WP-TotalPages',
        'Access-Control-Allow-Headers'  => 'Authorization, Content-Type'
    );

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
    }

    /**
     * Get the requested URI and create the cache key.
     *
     * @return  string The request URI.
     */
    public function build_request_uri() {
        $request_uri  = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );
        $uri_parts    = parse_url( $request_uri );
        $request_path = rtrim( $uri_parts['path'], '/' );

        if ( isset( $uri_parts['query'] ) && ! empty( $uri_parts['query'] ) ) {
            parse_str( $uri_parts['query'], $params );
            ksort( $params );
            $request_path .= '?' . http_build_query( $params );
        }

        $this->request_uri = $request_path;
        $this->cache_key   = md5( $this->request_uri );

        return $request_path;
    }

    /**
     * Save the response headers so they can be added to the cache.
     *
     * @param   bool $served Whether the request has already been served. Default false.
     * @param   WP_HTTP_Response $result Result to send to the client.
     * @param   WP_REST_Request $request Request used to generate the response.
     * @param   WP_REST_Server $server Server instance.
     */
    public function save_cache_headers( $served, WP_HTTP_Response $result, WP_REST_Request $request, WP_REST_Server $server ) {
        $headers = $result->get_headers();

        if ( isset( $headers ) && ! empty( $headers ) ) {
            foreach ( $headers as $key => $value ) {
                $this->response_headers[ $key ] = $value;
            }
        }
    }

    /**
     * Cache the response data.
     *
     * @param   array $result Response data to send to the client.
     * @param   WP_REST_Server $server Server instance.
     * @param   WP_REST_Request $request Request used to generate the response.
     *
     * @return  array Response data to send to the client.
     */
    public function save_cache( $result, WP_REST_Server $server, WP_REST_Request $request ) {
        // Only Avoid cache if not 200
        if ( ! empty( $result ) && is_array( $result ) && isset( $result['data']['status'] ) && (int) $result['data']['status'] !== 200 ) {
            return $result;
        }

        // Encode the json result
        $data       = array(
            'data'    => $result,
            'headers' => $this->response_headers
        );
        $last_error = json_last_error();

        // No errors? Lets save!
        if ( $last_error === JSON_ERROR_NONE ) {
            WP_Rest_Cache_Caching::get_instance()->set_cache( $this->cache_key, $data, 'endpoint', $this->request_uri );
        }

        return $result;
    }

    /**
     * Check if caching should be skipped.
     *
     * @return bool True if no caching should be applied, false if caching can be applied.
     */
    public function skip_caching() {
        // Don't run if we are calling to cache the request (see later in the code)
        if ( isset( $_GET['wp-rest-cache'] ) && (string) $_GET['wp-rest-cache'] === '1' ) {
            return true;
        }

        // Only cache GET-requests
        if ( 'GET' !== filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_STRING ) ) {
            return true;
        }

        // Make sure we only apply to allowed api calls
        $rest_prefix = sprintf( '/%s/', get_option( 'wp_rest_cache_rest_prefix', 'wp-json' ) );
        if ( strpos( $this->request_uri, $rest_prefix ) === false ) {
            return true;
        }

        $allowed_endpoints = get_option( 'wp_rest_cache_allowed_endpoints', [] );

        $allowed_endpoint = false;
        foreach ( $allowed_endpoints as $namespace => $endpoints ) {
            foreach ( $endpoints as $endpoint ) {
                if ( strpos( $this->request_uri, $rest_prefix . $namespace . '/' . $endpoint ) !== false ) {
                    $allowed_endpoint = true;
                    break 2;
                }
            }
        }

        if ( ! $allowed_endpoint ) {
            return true;
        }

        // We dont skip
        return false;
    }

    /**
     * Check if the current call is a REST API call, if so check if it has already been cached, otherwise cache it.
     */
    public function get_api_cache() {

        $this->build_request_uri();

        if ( $this->skip_caching() ) {
            return;
        }

        $cache = WP_Rest_Cache_Caching::get_instance()->get_cache( $this->cache_key );

        if ( $cache !== false ) {
            // We want the data to be json
            $data       = wp_json_encode( $cache['data'] );
            $last_error = json_last_error();

            if ( $last_error === JSON_ERROR_NONE ) {
                foreach ( $cache['headers'] as $key => $value ) {
                    $header = sprintf( '%s: %s', $key, $value );
                    header( $header );
                }

                echo $data;
                exit;
            }
        }

        // catch the headers after serving
        add_filter( 'rest_pre_serve_request', [ $this, 'save_cache_headers' ], 9999, 4 );

        // catch the result after serving
        add_filter( 'rest_pre_echo_response', [ $this, 'save_cache' ], 1000, 3 );
    }

    /**
     * Re-save the options if they have changed. We need them as options since we are going to use them early in the
     * WordPress process even before several hooks are fired.
     */
    public function save_options() {
        $original_allowed_endpoints = get_option( 'wp_rest_cache_allowed_endpoints', [] );
        $allowed_endpoints          = apply_filters( 'wp_rest_cache/allowed_endpoints', $original_allowed_endpoints );
        if ( $original_allowed_endpoints != $allowed_endpoints ) {
            update_option( 'wp_rest_cache_allowed_endpoints', $allowed_endpoints );
        }

        $original_rest_prefix = get_option( 'wp_rest_cache_rest_prefix' );
        $rest_prefix          = rest_get_url_prefix();
        if ( $original_rest_prefix != $rest_prefix ) {
            update_option( 'wp_rest_cache_rest_prefix', $rest_prefix );
        }
    }
}