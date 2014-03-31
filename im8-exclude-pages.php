<?php # -*- coding: utf-8 -*-
/**
 * Plugin Name: IM8 Exclude Pages
 * Plugin URI: http://wordpress.org/plugins/im8-exclude-pages/
 * Description: Adds a meta box to the Edit Page page where you can set to show or exclude the page from page listings.
 * Version: 2.7
 * Author: intermedi8, ipm-frommen
 * Author URI: http://intermedi8.de
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: im8-exclude-pages
 * Domain Path: /languages
 */

// Exit on direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'IM8ExcludePages' ) ) :

	/**
	 * Main (and only) class.
	 */
	class IM8ExcludePages {

		/**
		 * Plugin instance.
		 *
		 * @var     object
		 */
		private static $instance = NULL;

		/**
		 * Plugin version.
		 *
		 * @var     string
		 */
		private $version;

		/**
		 * basename() of global $pagenow.
		 *
		 * @var     string
		 */
		private $page_base;

		/**
		 * Plugin textdomain.
		 *
		 * @var     string
		 */
		private $textdomain;

		/**
		 * Plugin textdomain path.
		 *
		 * @var     string
		 */
		private $textdomain_path;

		/**
		 * Plugin nonce.
		 *
		 * @var     string
		 */
		private $nonce = 'im8_exclude_pages_nonce';

		/**
		 * Plugin option name.
		 *
		 * @var     string
		 */
		private $option_name = 'im8_exclude_pages';

		/**
		 * Plugin repository.
		 *
		 * @var     string
		 */
		private $repository = 'im8-exclude-pages';

		/**
		 * Plugin status (enabled or disabled).
		 *
		 * @var     boolean
		 */
		private $is_enabled = TRUE;

		/**
		 * Constructor. Registers activation routine.
		 *
		 * @see        get_instance()
		 */
		public function __construct() {

			$headers = array(
				//'plugin_name' => 'Plugin Name',
				'version'         => 'Version',
				'textdomain'      => 'Text Domain',
				'textdomain_path' => 'Domain Path',
			);
			$file_data = get_file_data( __FILE__, $headers );
			foreach ( $file_data as $key => $value ) {
				$this->$key = $value;
			}

			register_activation_hook( __FILE__, array( __CLASS__, 'activation' ) );
		} // function __construct

		/**
		 * Gets plugin instance.
		 *
		 * @wp-hook     plugins_loaded
		 * @return      object
		 */
		public static function get_instance() {

			if ( self::$instance === NULL ) {
				self::$instance = new self;
			}

			return self::$instance;
		} // function get_instance

		/**
		 * Registers uninstall routine.
		 *
		 * @wp-hook     activation
		 */
		public static function activation() {

			register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );
		} // function activation

		/**
		 * Checks if the plugin has to be initialized.
		 *
		 * @wp-hook     plugins_loaded
		 * @return      boolean
		 */
		public function init_on_demand() {

			global $pagenow;

			if ( empty( $pagenow ) ) {
				return;
			}

			$this->page_base = basename( $pagenow, '.php' );

			// Always initialize the plugin
			add_action( 'wp_loaded', array( $this, 'init' ) );
		} // function init_on_demand

		/**
		 * Registers plugin actions and filters.
		 *
		 * @wp-hook     wp_loaded
		 */
		public function init() {

			if ( is_admin() ) {
				add_action( 'admin_init', array( $this, 'autoupdate' ) );

				$pages = array(
					'post',
					'post-new',
				);
				if ( in_array( $this->page_base, $pages ) ) {
					add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
					add_action( 'save_post', array( $this, 'update_option' ) );
				}

				if ( $this->page_base === 'plugins' ) {
					add_filter(
						'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_settings_link' )
					);
					add_action(
						'in_plugin_update_message-' . basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ),
						array( $this, 'update_message' )
					);
				}

				if ( $this->page_base === 'options' ) {
					add_action( 'admin_init', array( $this, 'register_setting' ) );
				}

				if ( $this->page_base === 'options-writing' ) {
					add_action( 'admin_init', array( $this, 'add_settings_section' ) );
				}
			}

			add_filter( 'get_pages', array( $this, 'exclude_pages' ) );
			add_filter( 'pre_get_posts', array( $this, 'exclude_pages_from_search' ) );
		} // function init

		/**
		 * Checks for and performs necessary updates.
		 *
		 * @wp-hook     admin_init
		 */
		public function autoupdate() {

			$options = $this->get_option();
			$update_successful = TRUE;

			if ( version_compare( $options[ 'version' ], '2.0', '<' ) ) {
				$option_name_before_2_0 = 'im8-exclude-pages';
				$exclude_pages = get_option( $option_name_before_2_0 );

				$new_options = array();
				$new_options[ 'version' ] = '2.0';

				if ( $exclude_pages !== FALSE && $exclude_pages !== '' ) {
					$new_options[ 'exclude_pages' ] = $exclude_pages;
				}

				if (
					isset( $options[ 'exclude_new_pages' ] )
					&& $options[ 'exclude_new_pages' ]
				) {
					$new_options[ 'exclude_new_pages' ] = TRUE;
				}

				if ( update_option( $this->option_name, $new_options ) ) {
					$options = $new_options;
					$update_successful &= delete_option( $option_name_before_2_0 );
				}
				unset( $new_options );
			}

			if ( $update_successful ) {
				$options[ 'version' ] = $this->version;
				update_option( $this->option_name, $options );
			}
		} // function autoupdate

		/**
		 * Wrapper for get_option().
		 *
		 * @param    string $key     Option name.
		 * @param    mixed  $default Return value for missing key.
		 *
		 * @return    mixed|$default Option value.
		 */
		private function get_option( $key = NULL, $default = FALSE ) {

			static $option = NULL;
			if ( $option === NULL ) {
				$option = get_option( $this->option_name, FALSE );
				if ( $option === FALSE ) {
					$option = array(
						'version' => 0,
					);
				}
			}

			if ( $key === NULL ) {
				return $option;
			}

			if ( ! isset( $option[ $key ] ) ) {
				return $default;
			}

			return $option[ $key ];
		} // function get_option

		/**
		 * Adds plugin meta box for pages.
		 *
		 * @wp-hook    add_meta_boxes
		 * @return    void
		 */
		public function add_meta_box() {

			$this->load_textdomain();
			add_meta_box(
				'im8_exclude_page_box', __( "Exclude Page", 'im8-exclude-pages' ), array( $this, 'print_meta_box' ),
				'page', 'side', 'low'
			);
		} // function add_meta_box

		/**
		 * Callback function for plugin meta box.
		 *
		 * @see        add_meta_box()
		 *
		 * @param    object $post Post object of currently displayed post.
		 *
		 * @return    void
		 */
		public function print_meta_box( $post ) {

			wp_nonce_field( basename( __FILE__ ), $this->nonce );
			$excluded_pages = $this->get_excluded_pages();
			$excluded_ancestor = FALSE;
			wp_cache_delete( $post->ID, 'posts' );
			foreach ( get_post_ancestors( $post->ID ) as $page ) {
				if ( in_array( $page, $excluded_pages ) ) {
					$excluded_ancestor = $page;
				}
			}
			if ( $excluded_ancestor ) {
				$ancestor = get_post( $excluded_ancestor );
				$url = admin_url( sprintf( 'post.php?post=%s&action=edit', $excluded_ancestor ) );
				$title = apply_filters( 'the_title', $ancestor->post_title );
				echo '<p>';
				printf(
					_x(
						'<b>NOTICE:</b> This page is automatically excluded as its ancestor page <a href="%s">%s</a> is currently excluded, too. This setting will have no effect unless you disable the exclusion of the ancestor page.',
						'%s = URL of ancestor page, %s = title of ancestor page',
						'im8-exclude-pages'
					), $url, $title
				);
				echo '</p>';
			}

			if ( $this->page_base === 'post-new' ) {
				$checked = $this->get_option( 'exclude_new_pages' );
			} else {
				$checked = in_array( $post->ID, $excluded_pages );
			}
			?>
			<p>
				<label class="selectit" for="im8_exclude_page">
					<input type="checkbox" id="im8_exclude_page" name="im8_exclude_page" class="checkbox" <?php checked(
						$checked, TRUE
					); ?> />
					<?php _e( "Exclude page from page listings", 'im8-exclude-pages' ); ?>
				</label>
			</p>
			<?php
			$this->unload_textdomain();
		} // function print_meta_box

		/**
		 * Updates option.
		 *
		 * @wp-hook    save_post
		 *
		 * @param    int $id ID of the saved post.
		 */
		public function update_option( $id ) {

			if (
				! isset( $_POST[ $this->nonce ] )
				|| ! wp_verify_nonce( $_POST[ $this->nonce ], basename( __FILE__ ) )
			) {
				return;
			}

			if ( $_POST[ 'post_type' ] !== 'page' ) {
				return;
			}

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( wp_is_post_revision( $id ) ) {
				return;
			}

			$excluded_pages = $this->get_excluded_pages();
			if ( isset( $_POST[ 'im8_exclude_page' ] ) ) {
				$excluded_pages[ ] = $id;
				$excluded_pages = array_unique( $excluded_pages );
			} else {
				$index = array_search( $id, $excluded_pages );
				if ( $index !== FALSE ) {
					unset( $excluded_pages[ $index ] );
				}
			}
			$options = $this->get_option();
			if ( empty( $excluded_pages ) ) {
				unset( $options[ 'exclude_pages' ] );
			} else {
				$options[ 'exclude_pages' ] = implode( ',', $excluded_pages );
			}
			update_option( $this->option_name, $options );
		} // function update_option

		/**
		 * Adds settings link to the plugin list.
		 *
		 * @wp-hook    plugin_action_links_{$file}
		 *
		 * @param    array $links Already existing links.
		 *
		 * @return    array
		 */
		public function add_settings_link( $links ) {

			$settings_link = array(
				'<a href="' . admin_url( 'options-writing.php' ) . '">' . __( "Settings" ) . '</a>'
			);

			return array_merge( $links, $settings_link );
		} // function add_settings_link

		/**
		 * Prints update message based on current plugin version's readme file.
		 *
		 * @wp-hook    in_plugin_update_message-{$file}
		 *
		 * @param    array $plugin_data Plugin metadata.
		 *
		 * @return    void
		 */
		public function update_message( $plugin_data ) {

			if ( $plugin_data[ 'update' ] ) {
				$readme = wp_remote_fopen(
					'http://plugins.svn.wordpress.org/' . $this->repository . '/trunk/readme.txt'
				);
				if ( ! $readme ) {
					return;
				}

				$pattern = '/==\s*Changelog\s*==(.*)=\s*' . preg_quote( $this->version ) . '\s*=/s';
				if (
					preg_match( $pattern, $readme, $matches ) === FALSE
					|| ! isset( $matches[ 1 ] )
				) {
					return;
				}

				$changelog = (array) preg_split( '/[\r\n]+/', trim( $matches[ 1 ] ) );
				if ( empty( $changelog ) ) {
					return;
				}

				$output = '<div style="margin: 8px 0 0 26px;">';
				$output .= '<ul style="margin-left: 14px; line-height: 1.5; list-style: disc outside none;">';

				$item_pattern = '/^\s*\*\s*/';
				foreach ( $changelog as $line ) {
					if ( preg_match( $item_pattern, $line ) ) {
						$output .= '<li>' . preg_replace(
								'/`([^`]*)`/', '<code>$1</code>', htmlspecialchars(
									preg_replace( $item_pattern, '', trim( $line ) )
								)
							) . '</li>';
					}
				}

				$output .= '</ul>';
				$output .= '</div>';

				echo $output;
			}
		} // function update_message

		/**
		 * Registers setting for writing options page.
		 *
		 * @wp-hook    admin_init
		 * @return    void
		 */
		public function register_setting() {

			register_setting( 'writing', $this->option_name, array( $this, 'save_setting' ) );
		} // function register_setting

		/**
		 * Prepares option values before they are saved.
		 *
		 * @see        register_setting()
		 *
		 * @param    array $data Original option values.
		 *
		 * @return    array Sanitized option values.
		 */
		public function save_setting( $data ) {

			$sanitized_data = $this->get_option();

			if ( isset( $data[ 'exclude_new_pages' ] ) ) {
				$sanitized_data[ 'exclude_new_pages' ] = TRUE;
			} else {
				unset( $sanitized_data[ 'exclude_new_pages' ] );
			}

			if ( isset( $data[ 'exclude_from_search' ] ) ) {
				$sanitized_data[ 'exclude_from_search' ] = TRUE;
			} else {
				unset( $sanitized_data[ 'exclude_from_search' ] );
			}

			return $sanitized_data;
		} // function save_setting

		/**
		 * Registers settings section.
		 *
		 * @wp-hook    admin_init
		 * @return    void
		 */
		public function add_settings_section() {

			$this->load_textdomain();
			add_settings_section(
				$this->option_name,
				__( "IM8 Exclude Pages", 'im8-exclude-pages' ),
				array( $this, 'show_settings' ),
				'writing'
			);
		} // function add_settings_section

		/**
		 * Prints checkbox into writing option page.
		 *
		 * @see        add_settings_section()
		 * @return    void
		 */
		public function show_settings() {

			$this->print_exclude_pages_ui();
			$this->unload_textdomain();
		} // function show_settings

		/**
		 * Prints settings UI.
		 *
		 * @see        show_settings()
		 *
		 * @return    void
		 */
		private function print_exclude_pages_ui() {

			$exclude_new_pages = $this->get_option( 'exclude_new_pages' );
			$setting_name = $this->option_name . '[exclude_new_pages]';
			?>
			<p>
				<label class="selectit" for="<?php echo $setting_name; ?>">
					<input type="checkbox" id="<?php echo $setting_name; ?>" name="<?php echo $setting_name; ?>" class="checkbox" <?php checked(
						$exclude_new_pages, TRUE
					); ?>/>
					<?php _e( "Exclude new pages by default", 'im8-exclude-pages' ); ?>
				</label>
			</p>
			<?php
			$exclude_from_search = $this->get_option( 'exclude_from_search' );
			$setting_name = $this->option_name . '[exclude_from_search]';
			?>
			<p>
				<label class="selectit" for="<?php echo $setting_name; ?>">
					<input type="checkbox" id="<?php echo $setting_name; ?>" name="<?php echo $setting_name; ?>" class="checkbox" <?php checked(
						$exclude_from_search, TRUE
					); ?>/>
					<?php _e( "Exclude pages from search", 'im8-exclude-pages' ); ?>
				</label>
			</p>
		<?php
		} // function print_exclude_pages_ui

		/**
		 * Excludes pages from get_pages() results.
		 *
		 * @wp-hook    get_pages
		 *
		 * @param    array $pages Page IDs.
		 *
		 * @return    array Page IDs of not excluded pages only.
		 */
		public function exclude_pages( $pages ) {

			if ( ! $this->is_enabled ) {
				return $pages;
			}

			if ( count( $excluded = $this->get_excluded_pages() ) ) {
				foreach ( $pages as $key => $page ) {
					if ( in_array( $page->ID, $excluded ) ) {
						unset( $pages[ $key ] );
					} else {
						wp_cache_delete( $page->ID, 'posts' );
						foreach ( get_post_ancestors( $page->ID ) as $ancestor ) {
							if ( in_array( $ancestor, $excluded ) ) {
								unset( $pages[ $key ] );
							}
						}
					}
				}
			}

			return $pages;
		} // function exclude_pages

		/**
		 * Excludes pages from search results.
		 *
		 * @wp-hook    pre_get_posts
		 *
		 * @param    object $query Query
		 *
		 * @return    object
		 */
		function exclude_pages_from_search( $query ) {

			if (
				$query->is_search
				&& $query->is_main_query()
				&& $this->get_option( 'exclude_from_search' )
			) {
				$exclude_pages = $this->get_excluded_pages( TRUE );

				if ( ! empty( $exclude_pages ) ) {
					$post__not_in = $query->get( 'post__not_in' );
					if ( empty( $post__not_in ) ) {
						$post__not_in = array();
					}
					if ( is_string( $post__not_in ) ) {
						$post__not_in = explode( ', ', $post__not_in );
					}
					$exclude_pages = array_merge( $post__not_in, $exclude_pages );
					$query->set( 'post__not_in', $exclude_pages );
				}
			}

			return $query;
		} // function exclude_pages_from_search

		/**
		 * Gets excluded pages.
		 *
		 * @param       boolean $deep Include all descendants?
		 *
		 * @return    array Page IDs of excluded pages.
		 */
		private function get_excluded_pages( $deep = FALSE ) {

			$pages = $this->get_option( 'exclude_pages', '' );
			if ( ! is_string( $pages ) || $pages === '' ) {
				return array();
			}

			$pages = explode( ',', $pages );

			if ( $deep ) {
				foreach ( $pages as $page ) {
					$args = array(
						'post_parent' => $page,
						'post_type'   => 'page',
					);
					$children = get_children( $args );
					foreach ( $children as $child ) {
						$pages[ ] = $child->ID;
					}
				}
				$pages = array_unique( $pages );
			}

			return $pages;
		} // function get_excluded_pages

		/**
		 * Loads plugin textdomain.
		 *
		 * @return    boolean
		 */
		private function load_textdomain() {

			return load_plugin_textdomain(
				$this->textdomain, FALSE, plugin_basename( dirname( __FILE__ ) ) . $this->textdomain_path
			);
		} // function load_textdomain

		/**
		 * Removes translations from memory.
		 *
		 * @return    void
		 */
		private function unload_textdomain() {

			unset( $GLOBALS[ 'l10n' ][ $this->textdomain ] );
		} // function unload_textdomain

		/**
		 * Disables the plugin filter.
		 *
		 * @see        disable_im8_exclude_pages()
		 * @return    void
		 */
		public function disable() {

			$this->is_enabled = FALSE;
		} // function disable

		/**
		 * Enables the plugin filter.
		 *
		 * @see        enable_im8_exclude_pages()
		 * @return    void
		 */
		public function enable() {

			$this->is_enabled = TRUE;
		} // function enable

		/**
		 * Deletes plugin data on uninstall.
		 *
		 * @wp-hook    uninstall
		 * @return    void
		 */
		public static function uninstall() {

			delete_option( self::get_instance()->option_name );
		} // function uninstall

	} // class IM8ExcludePages

	$IM8ExcludePages = IM8ExcludePages::get_instance();
	add_action( 'plugins_loaded', array( $IM8ExcludePages, 'init_on_demand' ) );

	/**
	 * Disables the plugin filter.
	 *
	 * @return    void
	 */
	function disable_im8_exclude_pages() {

		IM8ExcludePages::get_instance()
		               ->disable();
	} // function disable_im8_exclude_pages

	/**
	 * Enables the plugin filter.
	 *
	 * @return    void
	 */
	function enable_im8_exclude_pages() {

		IM8ExcludePages::get_instance()
		               ->enable();
	} // function enable_im8_exclude_pages

endif; // if (! class_exists('IM8ExcludePages'))