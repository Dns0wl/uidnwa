<?php
namespace HW_Onsale_Accelerator;

defined( 'ABSPATH' ) || exit;

/**
 * Cache helper supporting object cache or transients with key indexing for targeted invalidation.
 */
class Cache {
        const GROUP = 'hw_onsale_acc';
        const INDEX_OPTION = 'hw_onsale_acc_keys';

        /**
         * Default TTL (10 minutes).
         *
         * @var int
         */
        private $ttl;

        /**
         * Constructor.
         *
         * @param int $ttl Cache lifetime in seconds.
         */
        public function __construct( $ttl = 600 ) {
                $this->ttl = (int) $ttl > 0 ? (int) $ttl : 600;
        }

        /**
         * Retrieve a cached value.
         *
         * @param string $key Cache key.
         * @return mixed False when missing.
         */
        public function get( $key ) {
                $key = $this->normalize_key( $key );

                if ( wp_using_ext_object_cache() ) {
                        return wp_cache_get( $key, self::GROUP );
                }

                return get_transient( $key );
        }

        /**
         * Store a value in cache.
         *
         * @param string $key Cache key.
         * @param mixed  $value Value to store.
         * @param int    $ttl Optional TTL.
         * @return void
         */
        public function set( $key, $value, $ttl = null ) {
                $key = $this->normalize_key( $key );
                $ttl = null !== $ttl ? (int) $ttl : $this->ttl;

                if ( wp_using_ext_object_cache() ) {
                        wp_cache_set( $key, $value, self::GROUP, $ttl );
                } else {
                        set_transient( $key, $value, $ttl );
                }

                $this->remember_key( $key );
        }

        /**
         * Delete a cached value.
         *
         * @param string $key Cache key.
         * @return void
         */
        public function delete( $key ) {
                $key = $this->normalize_key( $key );

                if ( wp_using_ext_object_cache() ) {
                        wp_cache_delete( $key, self::GROUP );
                } else {
                        delete_transient( $key );
                }
        }

        /**
         * Clear all cached items created by the accelerator.
         *
         * @return void
         */
        public function flush_group() {
                $keys = get_option( self::INDEX_OPTION, array() );

                if ( ! empty( $keys ) && is_array( $keys ) ) {
                        foreach ( $keys as $key ) {
                                $this->delete( $key );
                        }
                }

                update_option( self::INDEX_OPTION, array(), false );
        }

        /**
         * Build a namespaced cache key from provided parameters.
         *
         * @param string $prefix Prefix identifier.
         * @param array  $params Parameters to hash.
         * @return string
         */
        public function build_key( $prefix, array $params = array() ) {
                $params = $this->prepare_params_for_hash( $params );
                $hash   = md5( wp_json_encode( $params ) );

                return sprintf( '%s_%s_%s', self::GROUP, $prefix, $hash );
        }

        /**
         * Get default TTL.
         *
         * @return int
         */
        public function get_default_ttl() {
                return $this->ttl;
        }

        /**
         * Normalize key by stripping unsupported characters.
         *
         * @param string $key Raw key.
         * @return string
         */
        private function normalize_key( $key ) {
                $key = preg_replace( '/[^a-zA-Z0-9_\-]/', '_', (string) $key );

                if ( strlen( $key ) > 172 ) {
                        $key = substr( $key, 0, 172 );
                }

                return $key;
        }

        /**
         * Store generated key for later invalidation.
         *
         * @param string $key Cache key.
         * @return void
         */
        private function remember_key( $key ) {
                $keys = get_option( self::INDEX_OPTION, array() );

                if ( ! is_array( $keys ) ) {
                        $keys = array();
                }

                if ( ! in_array( $key, $keys, true ) ) {
                        $keys[] = $key;
                        update_option( self::INDEX_OPTION, $keys, false );
                }
        }

        /**
         * Normalize params for deterministic hashing.
         *
         * @param array $params Params.
         * @return array
         */
        private function prepare_params_for_hash( array $params ) {
                foreach ( $params as $key => $value ) {
                        if ( is_array( $value ) ) {
                                $params[ $key ] = $this->prepare_params_for_hash( $value );
                        }
                }

                ksort( $params );

                return $params;
        }
}
