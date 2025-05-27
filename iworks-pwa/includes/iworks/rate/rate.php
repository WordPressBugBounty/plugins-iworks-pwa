<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
/**
 * iWorks_Rate - Dashboard Notification module.
 *
 * @version 2.3.0
 * @author  iworks (Marcin Pietrzak)
 *
 */
if ( ! class_exists( 'iworks_rate' ) ) {
	class iworks_rate {

		/**
		 * This class version.
		 *
		 * @since 1.0.1
		 * @var   string
		 */
		private $version = '2.3.0';

		/**
		 * $wpdb->options field name.
		 *
		 * @since 1.0.0
		 * @var   string
		 */
		protected $option_name = 'iworks_rates';

		/**
		 * List of all registered plugins.
		 *
		 * @since 1.0.0
		 * @var   array
		 */
		protected $plugins = array();

		/**
		 * Module options that are stored in database.
		 * Timestamps are stored here.
		 *
		 * Note that this option is stored in site-meta for multisite installs.
		 *
		 * @since 1.0.0
		 * @var   array
		 */
		protected $stored = array();

		/**
		 * The Plugin ID
		 *
		 * @since 2.1.4
		 * @var   string
		 */
		private $plugin_id;

		/**
		 * Initializes and returns the singleton instance.
		 *
		 * @since  1.0.0
		 */
		public static function instance() {
			static $Inst = null;
			if ( null === $Inst ) {
				$Inst = new iworks_rate();
			}
			return $Inst;
		}

		/**
		 * Set up the iworks_rate module. Private singleton constructor.
		 *
		 * @since  1.0.0
		 */
		private function __construct() {
			/**
			 * settings
			 */
			$this->stored = wp_parse_args(
				get_site_option( $this->option_name, false ),
				array()
			);
			/**
			 * actions
			 */
			add_action( 'load-index.php', array( $this, 'load' ) );
			add_action( 'iworks-register-plugin', array( $this, 'register' ), 5, 3 );
			add_action( 'wp_ajax_iworks_rate_button', array( $this, 'ajax_button' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			/**
			 * own hooks
			 */
			add_filter( 'iworks_rate_assistance', array( $this, 'filter_get_assistance_widget' ), 10, 2 );
			add_filter( 'iworks_rate_love', array( $this, 'filter_get_love_widget' ), 10, 2 );
			/**
			 * advertising
			 *
			 * @since 2.1.0
			 */
			add_filter( 'iworks_rate_advertising_og', array( $this, 'filter_get_advertising_og' ) );
		}

		/**
		 * Inicialize admin area
		 *
		 * @since 2.0.2
		 */
		public function admin_init() {
			foreach ( $this->plugins as $plugin_file => $plugin ) {
				add_filter( 'plugin_action_links_' . $plugin_file, array( $this, 'add_donate_link' ), 10, 4 );
			}
		}

		/**
		 * Add donate link to plugin_row_meta.
		 *
		 * @since 2.0.2
		 *
		 * @param array  $actions An array of the plugin's metadata, including the version, author, author URI, and plugin URI.
		 */
		public function add_donate_link( $actions, $plugin_file, $plugin_data, $context ) {
			$slug = 'iworks';
			if (
				isset( $this->plugins[ $plugin_file ] )
				&& isset( $this->plugins[ $plugin_file ]['slug'] )
			) {
				$slug = $this->plugins[ $plugin_file ]['slug'];
			}
			$settings_page_url = apply_filters( 'iworks_rate_settings_page_url_' . $slug, null );
			if ( ! empty( $settings_page_url ) ) {
				$actions['settings'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $settings_page_url ),
					esc_html__( 'Settings', 'iworks-pwa' )
				);
			}
			$actions['donate'] = sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'utm_source' => $slug,
							'utm_medium' => 'plugin-links',
						),
						'https://ko-fi.com/iworks'
					)
				),
				esc_html__( 'Provide us a coffee', 'iworks-pwa' )
			);
			return $actions;
		}

		/**
		 * Load required hooks and scripts for the admin notice.
		 *
		 * @since 2.0.2
		 */
		public function load() {
			/**
			 * regular message
			 */
			$plugin_id = $this->choose_plugin();
			if ( ! empty( $plugin_id ) ) {
				$this->plugin_id = $plugin_id;
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
				add_action( 'admin_notices', array( $this, 'show' ) );
				return;
			}
			/**
			 * anniversary message
			 */
			$plugin_id = $this->choose_anniversary_plugin();
			if ( ! empty( $plugin_id ) ) {
				$this->plugin_id = $plugin_id;
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
				add_action( 'admin_notices', array( $this, 'show_anniversary' ) );
				return;
			}
		}

		/**
		 * Save persistent module-data to the WP database.
		 *
		 * @since  1.0.0
		 */
		protected function store_data() {
			update_site_option( $this->option_name, $this->stored );
		}

		/**
		 * Action handler for 'iworks-register-plugin'
		 * Register an active plugin.
		 *
		 * @since  1.0.0
		 * @param  string $plugin_id WordPress plugin-ID (see: plugin_basename).
		 * @param  string $title Plugin name for display.
		 * @param  string $slug the plugin slug on wp.org
		 */
		public function register( $plugin_id, $title, $slug ) {
			// Ignore incorrectly registered plugins to avoid errors later.
			if ( empty( $plugin_id ) || empty( $title ) || empty( $slug ) ) {
				return;
			}
			/**
			 * collect data
			 */
			$data = array(
				'title' => $title,
				'slug'  => $slug,
			);
			/**
			 * add dat to plugins array
			 */
			$this->plugins[ $plugin_id ] = $data;
			/**
			 * check for option update
			 *
			 * @since 2.0.6
			 *
			 */
			$update = false;
			/*
			 * When the plugin is registered the first time we store some infos
			 * in the persistent module-data that help us later to find out
			 * if/which message should be displayed.
			 */
			if ( empty( $this->stored[ $plugin_id ] ) ) {
				$this->stored[ $plugin_id ] = wp_parse_args(
					array(
						'registered' => time(),
						'show_at'    => $this->get_random_future_timestamp( 7, 14 ),
						'rated'      => 0,
						'hide'       => 0,
					),
					$data
				);
				$update                     = true;
			}
			/**
			 * check slug & mark for update if needed
			 *
			 * @since 2.0.6
			 */
			if ( $this->stored[ $plugin_id ]['slug'] !== $slug ) {
				$this->stored[ $plugin_id ]['slug'] = $slug;
				$update                             = true;
			}
			/**
			 * check last anniversary days
			 *
			 * @since 2.3.0
			 */
			if ( ! isset( $this->stored[ $plugin_id ]['last_anniversary_days'] ) ) {
				$this->stored[ $plugin_id ]['last_anniversary_days'] = floor(
					( time() - $this->stored[ $plugin_id ]['registered'] ) / ( 60 * 60 * 24 )
				);
				$update = true;
			}
			/**
			 * check last anniversary
			 *
			 * @since 2.3.0
			 */
			if ( ! isset( $this->stored[ $plugin_id ]['last_anniversary'] ) ) {
				$this->stored[ $plugin_id ]['last_anniversary'] = 0;
				$update = true;
			}
			/**
			 * check title - can be diferent due language
			 *
			 * @since 2.0.6
			 */
			$this->stored[ $plugin_id ]['title'] = $title;
			/**
			 * Finally save the details if it is needed
			 */
			if ( $update ) {
				$this->store_data();
			}
		}

		/**
		 * Ajax handler called when the user chooses the CTA button.
		 *
		 * @since  1.0.0
		 */
		public function ajax_button() {
			/**
			 * Chekc nonce
			 *
			 * @since 2.1.4
			 */
			if ( ! check_ajax_referer( 'iworks-rate' ) ) {
				wp_send_json_error();
			}
			/**
			 * get plugin ID
			 */
			$nonce_value = sanitize_text_field( filter_input( INPUT_POST, '_wpnonce', FILTER_DEFAULT ) );
			if ( ! wp_verify_nonce( $nonce_value, 'iworks-rate' ) ) {
				wp_send_json_error();
			}
			$plugin_id = sanitize_text_field( filter_input( INPUT_POST, 'plugin_id', FILTER_DEFAULT ) );
			if ( empty( $plugin_id ) ) {
				wp_send_json_error();
			}
			/**
			 * sanitize plugin_id
			 *
			 * @since 2.1.3
			 */
			$plugin_id = sanitize_text_field( $plugin_id );
			if ( ! isset( $this->plugins[ $plugin_id ] ) ) {
				wp_send_json_error();
			}
			/**
			 * sanitize button value
			 *
			 * @since 2.1.3
			 */
			$value = '';
			if ( isset( $_POST['button'] ) ) {
				$value = sanitize_text_field( filter_input( INPUT_POST, 'button', FILTER_DEFAULT ) );
			}
			switch ( $value ) {
				case '':
				case 'add-review':
					$this->add_weeks( $plugin_id );
					break;
				case 'hide':
					$this->add_weeks( $plugin_id );
					$this->hide( $plugin_id );
					break;
				case 'donate':
					$this->add_months( $plugin_id );
					break;
				case 'hide-anniversary':
					$this->hide_anniversary( $plugin_id );
					break;
			}
			wp_send_json_success();
		}

		/**
		 * Hide the rate notice for a specific plugin.
		 *
		 * @since 2.0.0
		 * @param string $plugin_id The ID of the plugin to hide the notice for.
		 */
		public function hide( $plugin_id ) {
			if ( ! isset( $this->stored[ $plugin_id ] ) ) {
				return;
			}
			$this->stored[ $plugin_id ]['hide'] = time();
			$this->store_data();
		}

		/**
		 * Hide the anniversary notice for a specific plugin.
		 *
		 * @since 2.3.0
		 * @param string $plugin_id The ID of the plugin to hide the notice for.
		 */
		public function hide_anniversary( $plugin_id ) {
			if ( ! isset( $this->stored[ $plugin_id ] ) ) {
				return;
			}
			++$this->stored[ $plugin_id ]['last_anniversary'];
			$this->store_data();
		}

		/**
		 * Extend the show_at timestamp by a random number of weeks.
		 *
		 * @since 2.0.0
		 * @param string $plugin_id The ID of the plugin to extend the delay for.
		 * @access private
		 */
		private function add_weeks( $plugin_id ) {
			if ( ! isset( $this->stored[ $plugin_id ] ) ) {
				return;
			}
			$this->stored[ $plugin_id ]['show_at'] = $this->get_random_future_timestamp( 0, 7, 4, 6 );
			$this->store_data();
		}

		/**
		 * Extend the show_at timestamp by a random number of months.
		 *
		 * @since 2.0.0
		 * @param string $plugin_id The ID of the plugin to extend the delay for.
		 * @access private
		 */
		private function add_months( $plugin_id ) {
			if ( ! isset( $this->stored[ $plugin_id ] ) ) {
				return;
			}
			$this->stored[ $plugin_id ]['show_at'] = $this->get_random_future_timestamp( 0, 14, 15, 30 );
			$this->store_data();
		}

		/**
		 * Ajax handler called when the user chooses the dismiss button.
		 *
		 * @since  1.0.0
		 */
		public function dismiss() {
			$plugin = $this->get_plugin_from_post();
			if ( is_wp_error( $plugin ) ) {
				wp_send_json_error();
			}
			wp_send_json_success();
		}

		/**
		 * Action handler for 'load-index.php'
		 * Set-up the Dashboard notification.
		 *
		 * @since  1.0.0
		 */
		public function enqueue() {
			wp_enqueue_style(
				__CLASS__,
				plugin_dir_url( __FILE__ ) . 'admin.css',
				array(),
				$this->version
			);
			wp_enqueue_script(
				__CLASS__,
				plugin_dir_url( __FILE__ ) . 'admin.js',
				array(),
				$this->version,
				true
			);
		}

		/**
		 * Action handler for 'admin_notices'
		 * Display the Dashboard notification.
		 *
		 * @since  1.0.0
		 */
		public function show() {
			$this->render_message( $this->plugin_id );
		}

		/**
		 * Action handler for 'admin_notices'
		 * Display the Dashboard notification.
		 *
		 * @since  2.2.0
		 */
		public function show_anniversary() {
			$filename = $this->stored[ $this->plugin_id ]['last_anniversary'] ? 'anniversary' : 'first-year';
			$this->render_message( $this->plugin_id, $filename, 'happy-anniversary' );
		}
		/**
		 * Check to see if there is a pending message to display and returns
		 * the message details if there is.
		 *
		 * Note that this function is only called on the main Dashboard screen
		 * and only when logged in as super-admin.
		 *
		 * @since  1.0.0
		 * @return object|false
		 *         string $plugin WordPress plugin ID?
		 */
		protected function choose_plugin() {
			if ( wp_is_mobile() ) {
				return false;
			}
			/**
			 * list
			 */
			$choosen = array();
			/**
			 * change time by filter
			 */
			$now = intval( apply_filters( 'iworks_rate_set_custom_time', time() ) );
			foreach ( $this->stored as $plugin_id => $item ) {
				if ( ! isset( $this->plugins[ $plugin_id ] ) ) {
					if ( isset( $this->stored[ $plugin_id ] ) ) {
						unset( $this->stored[ $plugin_id ] );
						$this->store_data();
					}
					continue;
				}
				if ( intval( $item['show_at'] ) > $now ) {
					continue;
				}
				if ( YEAR_IN_SECONDS > time() - intval( $item['hide'] ) ) {
					continue;
				}
				$choosen[] = $plugin_id;
			}
			if ( empty( $choosen ) ) {
				return false;
			}
			return $choosen[ array_rand( $choosen ) ];
		}

		/**
		 * Choose a plugin that is eligible for an anniversary rating prompt.
		 *
		 * @since 2.2.0
		 * @access protected
		 * @return string|false Returns the plugin ID if a matching plugin is found, false otherwise.
		 */
		protected function choose_anniversary_plugin() {
			if ( wp_is_mobile() ) {
				return false;
			}
			/**
			 * list
			 */
			$choosen = array();
			/**
			 * change time by filter
			 */
			$now = intval( apply_filters( 'iworks_rate_set_custom_time', time() ) );
			foreach ( $this->stored as $plugin_id => $item ) {
				if ( ! isset( $this->plugins[ $plugin_id ] ) ) {
					if ( isset( $this->stored[ $plugin_id ] ) ) {
						unset( $this->stored[ $plugin_id ] );
						$this->store_data();
					}
					continue;
				}
				if ( ! isset( $item['last_anniversary_days'] ) ) {
					continue;
				}
				if ( ! isset( $item['last_anniversary'] ) ) {
					continue;
				}
				$anniversary = intval( $item['last_anniversary_days'] / 365 );
				if ( $anniversary < $item['last_anniversary'] ) {
					continue;
				}
				$choosen[] = $plugin_id;
			}
			if ( empty( $choosen ) ) {
				return false;
			}
			return $choosen[ array_rand( $choosen ) ];
		}

		/**
		 * Renders the actual Notification message.
		 *
		 * @since  1.0.0
		 */
		protected function render_message( $plugin_id, $filename = 'thanks', $group = '' ) {
			$file   = $this->get_file( $filename, $group );
			$plugin = $this->get_plugin_data_by_plugin_id( $plugin_id );
			load_template( $file, true, $plugin );
		}

		/**
		 * Get the absolute path to a template file.
		 *
		 * @since 2.0.1
		 * @param string $file The base name of the template file.
		 * @param string $group Optional. Subdirectory within the templates directory.
		 * @return string The full path to the template file.
		 * @access private
		 */
		private function get_file( $file, $group = '' ) {
			return sprintf(
				'%s/templates/%s%s%s.php',
				__DIR__,
				$group,
				'' === $group ? '' : '/',
				sanitize_title( $file )
			);
		}

		/**
		 * Get plugin data by its ID.
		 *
		 * @since 2.0.1
		 * @param string $plugin_id The ID of the plugin to get data for.
		 * @return array|WP_Error Plugin data array or WP_Error on failure.
		 * @access private
		 */
		private function get_plugin_data_by_plugin_id( $plugin_id ) {
			if ( ! isset( $this->plugins[ $plugin_id ] ) ) {
				return new WP_Error(
					'no-plugin',
					sprintf(
						/* translators: %s: plugin id */
						esc_html__( 'There is no plugin with id: %s.', 'iworks-pwa' ),
						$plugin_id
					)
				);
			}
			$plugin              = wp_parse_args(
				$this->plugins[ $plugin_id ],
				$this->stored[ $plugin_id ]
			);
			$plugin['plugin_id'] = $plugin_id;
			$plugin['logo']      = apply_filters( 'iworks_rate_notice_logo_style', '', $plugin );
			$plugin['ajax_url']  = admin_url( 'admin-ajax.php' );
			$plugin['classes']   = array(
				'iworks-rate',
				'iworks-rate-' . $plugin['slug'],
				'iworks-rate-notice',
			);
			if ( ! empty( $plugin['logo'] ) ) {
				$plugin['classes'][] = 'has-logo';
			}
			$plugin['url'] = esc_url(
				sprintf(
				/* translators: %s: plugin slug */
					_x( 'https://wordpress.org/plugins/%s', 'plugins home', 'iworks-pwa' ),
					$plugin['slug']
				)
			);
			$plugin['support_url'] = esc_url(
				sprintf(
					/* translators: %s: plugin slug */
					_x( 'https://wordpress.org/support/plugin/%s', 'plugins support home', 'iworks-pwa' ),
					$plugin['slug']
				)
			);
			/**
			 * Add utm parameters to donate link.
			 *
			 *  @since 2.2.0
			 *
			 * @param string $donate_url Donate URL.
			 * @param array $plugin Plugin data.
			 */
			$plugin['donate_url'] = esc_url(
				add_query_arg(
					array(
						'utm_source'   => $plugin['slug'],
						'utm_medium'   => 'WP-Dashboard',
						'utm_campaign' => 'happy-anniversary',
					),
					'https://ko-fi.com/iworks'
				)
			);
			/**
			 * Change plugin data.
			 *
			 * Allows to change generated plugin data.
			 *
			 * @since 2.1.4
			 *
			 * @param array $plugin {
			 *     Plugin data.
			 *
			 *     @type string $plugin_id Plugin ID
			 *     @type string $logo Logo URL
			 *     @type string $ajax_url Admin AJAX URl
			 *     @type array $classes CSS classes
			 *     @type string $url Plugin repository URL
			 *     @type string $support_url Plugin support URL
			 * }
			 */
			return apply_filters( 'iworks_rate_plugin_data', $plugin );
		}

		/**
		 * Get plugin ID by its slug.
		 *
		 * @since 2.0.1
		 * @param string $slug The plugin slug to search for.
		 * @return string|WP_Error The plugin ID or WP_Error if not found.
		 * @access private
		 */
		private function get_plugin_id_by_slug( $slug ) {
			foreach ( $this->stored as $plugin_id => $plugin ) {
				if ( $slug === $plugin['slug'] ) {
					return $plugin_id;
				}
			}
			return new WP_Error();
		}

		/**
		 * Get the URL to install a plugin from the WordPress.org repository.
		 *
		 * @since 2.1.0
		 * @param string $slug The plugin slug to generate install URL for.
		 * @return string The URL to install the plugin.
		 * @access private
		 */
		private function get_install_plugin_url( $slug ) {
			return wp_nonce_url(
				self_admin_url(
					add_query_arg(
						array(
							'action' => 'install-plugin',
							'plugin' => $slug,
						),
						'update.php'
					)
				),
				'install-plugin_' . $slug
			);
		}

		/**
		 * Generate a random future timestamp based on days and weeks.
		 *
		 * @since 2.1.5
		 * @param int $day_min Minimum number of days to add.
		 * @param int $day_max Maximum number of days to add.
		 * @param int $week_min Minimum number of weeks to add.
		 * @param int $week_max Maximum number of weeks to add.
		 * @return int A future timestamp.
		 * @access private
		 */
		private function get_random_future_timestamp( $day_min = 0, $day_max = 0, $week_min = 0, $week_max = 0 ) {
			$time = time();
			/**
			 * DAY_IN_SECONDS
			 */
			$days = 0;
			if ( 0 < $day_max ) {
				$days = $this->rate_rand( $day_min, $day_max );
			}
			$time += $days * DAY_IN_SECONDS;
			/**
			 * WEEK_IN_SECONDS
			 */
			$weeks = 0;
			if ( 0 < $week_max ) {
				$weeks = $this->rate_rand( $week_min, $week_max );
			}
			$time += $weeks * WEEK_IN_SECONDS;
			/**
			 * returns
			 */
			return $time;
		}

		/**
		 * Generate a random number within a range.
		 *
		 * Uses wp_rand() if available, falls back to mt_rand().
		 *
		 * @since 2.1.5
		 * @param int|null $min Lower limit (included).
		 * @param int|null $max Upper limit (included).
		 * @return int A random number from the specified range.
		 * @access private
		 */
		private function rate_rand( $min = null, $max = null ) {
			if ( function_exists( 'wp_rand' ) ) {
				return wp_rand( $min, $max );
			}
			return mt_rand( $min, $max );
		}

		/**
		 * Get advertising for "OG — Better Share on Social Media" plugin.
		 *
		 * @since 2.1.0
		 */
		public function filter_get_advertising_og( $data ) {
			return array(
				'iworks-adverting-og' => array(
					'title'    => __( 'OpenGraph', 'iworks-pwa' ),
					'callback' => array( $this, 'get_advertising_og_content' ),
					'context'  => 'side',
					'priority' => 'low',
				),
			);
		}

		/**
		 * Advertising content for "OG — Better Share on Social Media" plugin.
		 *
		 * @since 2.1.0
		 */
		public function get_advertising_og_content() {
			$args = array(
				'install_plugin_url' => $this->get_install_plugin_url( 'og' ),
				'plugin_name'        => __( 'OG — Better Share on Social Media', 'iworks-pwa' ),
				'plugin_wp_home'     => __( 'https://wordpress.org/plugins/og/', 'iworks-pwa' ),
			);
			$file = $this->get_file( 'og', 'plugins' );
			load_template( $file, true, $args );
		}

		/**
		 * @since 2.0.1
		 */
		public function filter_get_assistance_widget( $content, $slug ) {
			$plugin_id = $this->get_plugin_id_by_slug( $slug );
			if ( is_wp_error( $plugin_id ) ) {
				return $content;
			}
			$this->enqueue();
			$plugin = $this->get_plugin_data_by_plugin_id( $plugin_id );
			if ( is_wp_error( $plugin ) ) {
				return $content;
			}
			$file = $this->get_file( 'support', 'widgets' );
			ob_start();
			load_template( $file, true, $plugin );
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}

		/**
		 * @since 2.0.1
		 */
		public function filter_get_love_widget( $content, $slug ) {
			$plugin_id = $this->get_plugin_id_by_slug( $slug );
			if ( is_wp_error( $plugin_id ) ) {
				return $content;
			}
			$this->enqueue();
			$plugin = $this->get_plugin_data_by_plugin_id( $plugin_id );
			if ( is_wp_error( $plugin ) ) {
				return $content;
			}
			$file = $this->get_file( 'donate', 'widgets' );
			ob_start();
			load_template( $file, true, $plugin );
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}
	}

	// Initialize the module.
	iworks_rate::instance();
}
