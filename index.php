<?php
/**
 * Front to the WordPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells WordPress to load the theme.
 *
 * @package WordPress
 */

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::create( __DIR__ );
$dotenv->load();

/**
 * Tells WordPress to load the WordPress theme and output it.
 *
 * @var bool
 */
define( 'WP_USE_THEMES', true );

/** Loads the WordPress Environment and Template */
require dirname( __FILE__ ) . '/' . $_ENV['WP_CORE_DIR'] . '/wp-blog-header.php';

add_action( '', '' );
