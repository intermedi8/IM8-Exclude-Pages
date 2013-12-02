<?php
/**
 * Plugin Name: IM8 Exclude Pages
 * Description: Adds a meta box to the Edit Page page where you can set to show or exclude the page from page listings.
 * Plugin URI: http://intermedi8.de
 * Version: 2.0
 * Author: intermedi8
 * Author URI: http://intermedi8.de
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: im8-exclude-pages
 * Domain Path: /languages
 */


if (IM8ExcludePages::has_to_be_loaded())
	add_action('wp_loaded', array(IM8ExcludePages::get_instance(), 'init'));




/**
 * Main (and only) class.
 */
class IM8ExcludePages {

	/**
	 * Plugin instance.
	 *
	 * @type	object
	 */
	protected static $instance = null;


	/**
	 * Plugin version.
	 *
	 * @type	string
	 */
	protected $version = '2.0';


	/**
	 * basename() of global $pagenow.
	 *
	 * @type	string
	 */
	protected static $page_base;


	/**
	 * Plugin base name.
	 *
	 * @type	string
	 */
	protected $plugin_base;


	/**
	 * Plugin textdomain.
	 *
	 * @type	string
	 */
	protected $textdomain = 'im8-exclude-pages';


	/**
	 * Plugin nonce.
	 *
	 * @type	string
	 */
	protected $nonce = 'im8_exclude_pages_nonce';


	/**
	 * Plugin option name.
	 *
	 * @type	string
	 */
	protected $option_name = 'im8_exclude_pages';


	/**
	 * Plugin status (enabled or disabled).
	 *
	 * @type	boolean
	 */
	protected $is_enabled = true;


	/**
	 * Constructs class.
	 *
	 * @hook wp_loaded
	 */
	public function __construct() {
		register_activation_hook(__FILE__, array(__CLASS__, 'activation'));
		register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));
	} // function __construct


	/**
	 * Get plugin instance.
	 *
	 * @hook	wp_loaded
	 * @return	object IM8ExcludePages
	 */
	public static function get_instance() {
		if (null === self::$instance)
			self::$instance = new self;

		return self::$instance;
	} // function get_instance


	/**
	 * Performs post-update actions.
	 *
	 * @hook	activation
	 * @return	void
	 */
	public static function activation() {
		self::get_instance()->autoupgrade();
	} // function activation


	/**
	 * Checks if the plugin has to be loaded.
	 *
	 * @return	boolean
	 */
	public static function has_to_be_loaded() {
		global $pagenow;

		if (empty($pagenow))
			return false;

		self::$page_base = basename($pagenow, '.php');

		// Always load the plugin
		return true;
	} // function has_to_be_loaded


	/**
	 * Registers plugin actions and filters.
	 *
	 * @hook	wp_loaded
	 * @return	void
	 */
	public function init() {
		if (is_admin()) {
			$pages = array(
				'post',
				'post-new',
			);
			if (in_array(self::$page_base, $pages)) {
				add_action('add_meta_boxes', array($this, 'add_meta_box'));
				add_action('save_post', array($this, 'update'));
			}

			if ('plugins' === self::$page_base)
				add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'add_settings_link'));

			if ('options' === self::$page_base)
				add_action('admin_init', array($this, 'register_setting'));

			if ('options-writing' === self::$page_base)
				add_action('admin_init', array($this, 'add_settings_section'));
		}

		add_filter('get_pages', array($this, 'exclude_pages'));
	} // function init


	/**
	 * Wrapper for get_option().
	 *
	 * @param	string $key Option name.
	 * @param	mixed $default Return value for missing key.
	 * @return	mixed|$default Option value.
	 */
	protected function get_option($key = null, $default = false) {
		static $option = null;
		if (null === $option) {
			$option = get_option($this->option_name, false);
			if (false === $option)
				$option = array(
					'version' => 0,
				);
		}

		if (null === $key)
			return $option;

		if (! isset($option[$key]))
			return $default;

		return $option[$key];
	} // function get_option


	/**
	 * Checks for and performs necessary upgrades.
	 *
	 * @return	void
	 */
	protected function autoupgrade() {
		$options = $this->get_option();
		$version = $this->get_option('version', 0);

		if (version_compare($version, '2.0', '<')) {
			$option_name_before_2_0 = 'im8-exclude-pages';
			$exclude_pages = get_option($option_name_before_2_0);
			$new_options = array();
			$new_options['version'] = '2.0';
			if (false !== $exclude_pages && '' !== $exclude_pages)
				$new_options['exclude_pages'] = $exclude_pages;
			if ($options['exclude_new_pages'])
				$new_options['exclude_new_pages'] = true;
			if (update_option($this->option_name, $new_options))
				delete_option($option_name_before_2_0);
		}
	} // function autoupgrade


	/**
	 * Adds plugin meta box for pages.
	 *
	 * @hook	add_meta_boxes
	 * @return	void
	 */
	public function add_meta_box() {
		$this->load_textdomain();
		add_meta_box('im8_exclude_page_box', __("Exclude Page", 'im8-exclude-pages'), array($this, 'print_meta_box'), 'page', 'side', 'low');
	} // function add_meta_box


	/**
	 * Callback function for plugin meta box.
	 *
	 * @see		add_meta_box()
	 * @param	object $post Post object of currently displayed post.
	 * @return	void
	 */
	public function print_meta_box($post) {
		wp_nonce_field(basename(__FILE__), $this->nonce);
		$excluded_pages = $this->get_excluded_pages();
		$excluded_ancestor = false;
		wp_cache_delete($post->ID, 'posts');
		foreach (get_post_ancestors($post->ID) as $page)
			if (in_array($page, $excluded_pages))
				$excluded_ancestor = $page;
		if ($excluded_ancestor) {
			$ancestor = get_post($excluded_ancestor);
			$url = admin_url(sprintf('post.php?post=%s&action=edit', $excluded_ancestor));
			$title = apply_filters('the_title', $ancestor->post_title);
			printf(__('<p><b>NOTICE:</b> This page is automatically excluded as its ancestor page <a href="%1$s">%2$s</a> is currently excluded, too. This setting will have no effect unless you disable the exclusion of the ancestor page.</p>', 'im8-exclude-pages'), $url, $title);
		}

		if ('post-new' === self::$page_base)
			$checked = $this->get_option('exclude_new_pages');
		else
			$checked = in_array($post->ID, $excluded_pages);
		?>
		<p>
			<label class="selectit" for="im8_exclude_page">
				<input type="checkbox" id="im8_exclude_page" name="im8_exclude_page" class="checkbox" <?php checked($checked, true); ?> />
				<?php _e("Exclude page from page listings", 'im8-exclude-pages'); ?>
			</label>
		</p>
		<?php
		$this->unload_textdomain();
	} // function print_meta_box


	/**
	 * Updates option.
	 *
	 * @hook	save_post
	 * @param	int $id ID of the saved post.
	 * @return	int Post ID, in case no meta data was saved.
	 */
	public function update($id) {
		if (
			! isset($_POST[$this->nonce])
			|| ! wp_verify_nonce($_POST[$this->nonce], basename(__FILE__))
		) return $id;

		if ('page' !== $_POST['post_type'])
			return $id;

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return $id;

		if (wp_is_post_revision($id))
			return $id;

		$excluded_pages = $this->get_excluded_pages();
		if (isset($_POST['im8_exclude_page'])) {
			$excluded_pages[] = $id;
			$excluded_pages = array_unique($excluded_pages);
		} else {
			$index = array_search($id, $excluded_pages);
			if (false !== $index)
				unset($excluded_pages[$index]);
		}
		$options = $this->get_option();
		if (empty($excluded_pages))
			unset($options['exclude_pages']);
		else
			$options['exclude_pages'] = implode(',', $excluded_pages);
		update_option($this->option_name, $options);
	} // function update


	/**
	 * Adds a link to the settings to the plugin list.
	 *
	 * @hook	plugin_action_links_{$file}
	 * @param	array $links Already existing links.
	 * @return	array
	 */
	public function add_settings_link($links) {
		$settings_link = array(
			'<a href="'.admin_url('options-writing.php').'">'.__("Settings").'</a>'
		);

		return array_merge($settings_link, $links);
	} // function add_settings_link


	/**
	 * Registers setting for writing options page.
	 *
	 * @hook	admin_init
	 * @return	void
	 */
	public function register_setting() {
		register_setting('writing', $this->option_name, array($this, 'save_setting'));
	} // function register_setting


	/**
	 * Prepares option values before they are saved.
	 *
	 * @param	array $data Original option values.
	 * @return	array Sanitized option values.
	 */
	public function save_setting($data) {
		$sanitized_data = $this->get_option();
		if (isset($data['exclude_new_pages']))
			$sanitized_data['exclude_new_pages'] = true;
		else
			unset($sanitized_data['exclude_new_pages']);

		return $sanitized_data;
	} // function save_setting


	/**
	 * Registers settings section.
	 *
	 * @hook	admin_init
	 * @return	void
	 */
	public function add_settings_section() {
		$this->load_textdomain();
		add_settings_section(
			$this->option_name,
			__("IM8 Exclude Pages", 'im8-exclude-pages'),
			array($this, 'show_settings'),
			'writing'
		);
	} // function add_settings_section


	/**
	 * Prints checkbox into writing option page.
	 *
	 * @see		register_settings()
	 * @return	void
	 */
	public function show_settings() {
		$this->print_exclude_new_pages_ui();
		$this->unload_textdomain();
	} // function show_settings


	/**
	 * Prints settings checkbox.
	 *
	 * @param	string $label_for Checkbox label.
	 * @return	void
	 */
	protected function print_exclude_new_pages_ui() {
		$exclude_new_pages = $this->get_option('exclude_new_pages');
		$setting_name = 'im8_exclude_pages[exclude_new_pages]';
		?>
		<p>
			<label class="selectit" for="<?php echo $setting_name; ?>">
				<input type="checkbox" id="<?php echo $setting_name; ?>" name="<?php echo $setting_name; ?>" class="checkbox" <?php checked($exclude_new_pages, true); ?>/>
				<?php _e("Exclude new pages by default", 'im8-exclude-pages'); ?>
			</label>
		</p>
		<?php
	} // function print_exclude_new_pages_ui


	/**
	 * Excludes pages from get_pages() result.
	 *
	 * @filter	get_pages
	 * @param	array $pages Page IDs.
	 * @return	array Page IDs of not excluded pages only.
	 */
	public function exclude_pages($pages) {
		if (! $this->is_enabled)
			return $pages;

		$excluded = $this->get_excluded_pages();
		foreach ($pages as $key => $page) {
			if (in_array($page->ID, $excluded))
				unset($pages[$key]);
			else {
				wp_cache_delete($page->ID, 'posts');
			 	foreach (get_post_ancestors($page->ID) as $ancestor)
			 		if (in_array($ancestor, $excluded))
			 			unset($pages[$key]);
			}
		}
		return $pages;
	} // function exclude_pages


	/**
	 * Gets excluded pages.
	 *
	 * @return	array Page IDs of excluded pages.
	 */
	protected function get_excluded_pages() {
		$excluded = $this->get_option('exclude_pages', array());

		return explode(',', $excluded);
	} // function get_excluded_pages


	/**
	 * Loads plugin textdomain.
	 *
	 * @return	boolean
	 */
	protected function load_textdomain() {
		return load_plugin_textdomain($this->textdomain, false, plugin_basename(dirname(__FILE__)).'/languages');
	} // function load_textdomain


	/**
	 * Remove translations from memory.
	 *
	 * @return	void
	 */
	protected function unload_textdomain() {
		unset($GLOBALS['l10n'][$this->textdomain]);
	} // function unload_textdomain


	/**
	 * Disables the plugin filter.
	 *
	 * @return	void
	 */
	public function disable() {
		$this->is_enabled = false;
	} // function disable


	/**
	 * Enables the plugin filter.
	 *
	 * @return	void
	 */
	public function enable() {
		$this->is_enabled = true;
	} // function enable


	/**
	 * Deletes plugin data on uninstall.
	 *
	 * @hook	uninstall
	 * @return	void
	 */
	public static function uninstall() {
		delete_option(self::get_instance()->option_name);
	} // function uninstall

}




/**
 * Disables the plugin filter.
 *
 * @return	void
 */
function disable_im8_exclude_pages() {
	IM8ExcludePages::get_instance()->disable();
} // function disable_im8_exclude_pages


/**
 * Enables the plugin filter.
 *
 * @return	void
 */
function enable_im8_exclude_pages() {
	IM8ExcludePages::get_instance()->enable();
} // function enable_im8_exclude_pages
?>