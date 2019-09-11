<?php

if ( empty( $_ENV ) ) {
	require __DIR__ . '/vendor/autoload.php';
	$dotenv = Dotenv\Dotenv::create( __DIR__ );
	$dotenv->load();
}

define( 'DB_NAME', $_ENV['DB_NAME'] );
define( 'DB_USER', $_ENV['DB_USER'] );
define( 'DB_PASSWORD', $_ENV['DB_PASSWORD'] );
define( 'DB_HOST', $_ENV['DB_HOST'] );
define( 'DB_CHARSET', 'utf8' );

$table_prefix = 'wp_';

define( 'WP_HOME', 'http://' . $_SERVER['HTTP_HOST'] );
define( 'WP_SITEURL', WP_HOME . '/' . $_ENV['WP_CORE_DIR'] );

define( 'WP_CONTENT_DIR', dirname( __FILE__ ) . '/' . $_ENV['WP_CONTENT_DIR'] );
define( 'WP_CONTENT_URL', WP_HOME . '/' . $_ENV['WP_CONTENT_DIR'] );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

require_once( ABSPATH . 'wp-settings.php' );
