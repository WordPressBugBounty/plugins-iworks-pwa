<?php
/*
Plugin Name: PWA — easy way to Progressive Web App
Text Domain: iworks-pwa
Plugin URI: https://github.com/iworks/iworks-pwa
Description: Your easy way to Progressive Web Application.
Version: 1.7.7
Author: Marcin Pietrzak
Author URI: http://iworks.pl/
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Copyright 2021-2025 Marcin Pietrzak (marcin@iworks.pl)

this program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * static options
 */
$base     = dirname( __FILE__ );
$includes = $base . '/includes';

/**
 * get plugin settings
 *
 * @since 1.0.1
 */
include_once $base . '/etc/options.php';

/**
 * @since 1.0.6
 */
if ( ! class_exists( 'iworks_options' ) ) {
	include_once $includes . '/iworks/options/options.php';
}

/**
 * load
 */
require_once $includes . '/iworks/pwa/class-iworks-pwa-manifest.php';
require_once $includes . '/iworks/pwa/class-iworks-pwa-frontend.php';
require_once $includes . '/iworks/pwa/class-iworks-pwa-apple.php';
require_once $includes . '/iworks/pwa/class-iworks-pwa-microsoft.php';
/**
 * run
 */
new iWorks_PWA_manifest;
new iWorks_PWA_Frontend;
new iWorks_PWA_Apple;
new iWorks_PWA_Microsoft;
/**
 * admin
 */
if ( is_admin() ) {
	require_once $includes . '/iworks/pwa/class-iworks-pwa-administrator.php';
	new iWorks_PWA_Administrator;
}

/**
 * load options
 *
 * since 2.6.8
 *
 */
global $iworks_pwa_options;
$iworks_pwa_options = null;

function iworks_pwa_get_options() {
	global $iworks_pwa_options;
	if ( is_object( $iworks_pwa_options ) ) {
		return $iworks_pwa_options;
	}
	$iworks_pwa_options = new iworks_options();
	$iworks_pwa_options->set_option_function_name( 'iworks_pwa_options' );
	$iworks_pwa_options->set_option_prefix( 'iworks_pwa_' );
	if ( method_exists( $iworks_pwa_options, 'set_plugin' ) ) {
		$iworks_pwa_options->set_plugin( basename( __FILE__ ) );
	}
	return $iworks_pwa_options;
}

