<?php
/**
 * Plugin Name: IM8 Exclude Pages
 * Plugin URI: http://wordpress.org/plugins/im8-exclude-pages/
 * Description: Adds a meta box to the Edit Page page where you can set to show or exclude the page from page listings.
 * Version: 2.6
 * Author: intermedi8
 * Author URI: http://intermedi8.de
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: im8-exclude-pages
 * Domain Path: /languages
 */


// Exit on direct access
if (! defined('ABSPATH'))
	exit;


if (! class_exists('IM8ExcludePages')) :


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
	protected $version = '2.6';


	/**
	 * basename() of global $pagenow.
	 *
	 * @type	string
	 */
	protected static $page_base;


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
	 * Plugin repository.
	 *
	 * @type	string
	 */
	protected $repository = 'im8-exclude-pages';


	/**
	 * Plugin status (enabled or disabled).
	 *
	 * @type	boolean
	 */
	protected $is_enabled = true;


	/**
	 * Constructor. Register activation routine.
	 *
	 * @see		get_instance()
	 * @return	void
	 */
	public function __construct() {
		register_activation_hook(__FILE__, array(__CLASS__, 'activation'));
	} // function __construct


	/**
	 * Get plugin instance.
	 *
	 * @hook	plugins_loaded
	 * @return	object IM8ExcludePages
	 */
	public static function get_instance() {
		if (null === self::$instance)
			self::$instance = new self;

		return self::$instance;
	} // function get_instance


	/**
	 * Register uninstall routine.
	 *
	 * @hook	activation
	 * @return	void
	 */
	public static function activation() {
		register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));
	} // function activation


	/**
	 * Check if the plugin has to be initialized.
	 *
	 * @hook	plugins_loaded
	 * @return	boolean
	 */
	public static function init_on_demand() {
		global $pagenow;

		if (empty($pagenow))
			return;

		self::$page_base = basename($pagenow, '.php');

		// Always initialize the plugin
		add_action('wp_loaded', array(self::$instance, 'init'));
	} // function init_on_demand


	/**
	 * Register plugin actions and filters.
	 *
	 * @hook	wp_loaded
	 * @return	void
	 */
	public function init() {
		if (is_admin()) {
			add_action('admin_init', array($this, 'autoupdate'));

			$pages = array(
				'post',
				'post-new',
			);
			if (in_array(self::$page_base, $pages)) {
				add_action('add_meta_boxes', array($this, 'add_meta_box'));
				add_action('save_post', array($this, 'update_option'));
			}

			if ('plugins' === self::$page_base) {
				add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'add_settings_link'));
				add_action('in_plugin_update_message-'.basename(dirname(__FILE__)).'/'.basename(__FILE__), array($this, 'update_message'), 10, 2);
			}

			if ('options' === self::$page_base)
				add_action('admin_init', array($this, 'register_setting'));

			if ('options-writing' === self::$page_base)
				add_action('admin_init', array($this, 'add_settings_section'));
		}

		add_filter('get_pages', array($this, 'exclude_pages'));
	} // function init


	/**
	 * Check for and perform necessary updates.
	 *
	 * @hook	admin_init
	 * @return	void
	 */
	public function autoupdate() {
		$options = $this->get_option();
		$update_successful = true;

		if (version_compare($options['version'], '2.0', '<')) {
			$option_name_before_2_0 = 'im8-exclude-pages';
			$exclude_pages = get_option($option_name_before_2_0);

			$new_options = array();
			$new_options['version'] = '2.0';

			if (false !== $exclude_pages && '' !== $exclude_pages)
				$new_options['exclude_pages'] = $exclude_pages;

			if ($options['exclude_new_pages'])
				$new_options['exclude_new_pages'] = true;

			if (update_option($this->option_name, $new_options)) {
				$options = $new_options;
				$update_successful &= delete_option($option_name_before_2_0);
			}
			unset($new_options);
		}

		if ($update_successful) {
			$options['version'] = $this->version;
			update_option($this->option_name, $options);
		}
	} // function autoupdate


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
	 * Add plugin meta box for pages.
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
	 * Update option.
	 *
	 * @hook	save_post
	 * @param	int $id ID of the saved post.
	 * @return	int Post ID, in case no meta data was saved.
	 */
	public function update_option($id) {
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
	} // function update_option


	/**
	 * Add settings link to the plugin list.
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
	 * Print update message based on current plugin version's readme file.
	 *
	 * @hook	in_plugin_update_message-{$file}
	 * @param	array $plugin_data Plugin metadata.
	 * @param	array $r Metadata about the available plugin update.
	 * @return	void
	 */
	public function update_message($plugin_data, $r) {
		if ($plugin_data['update']) {
			$readme = wp_remote_fopen('http://plugins.svn.wordpress.org/'.$this->repository.'/trunk/readme.txt');
			if (! $readme)
				return;

			$pattern = '/==\s*Changelog\s*==(.*)=\s*'.preg_quote($this->version).'\s*=/s';
			if (
				false === preg_match($pattern, $readme, $matches)
				|| ! isset($matches[1])
			)
				return;

			$changelog = (array) preg_split('/[\r\n]+/', trim($matches[1]));
			if (empty($changelog))
				return;

			$output = '<div style="margin: 8px 0 0 26px;">';
			$output .= '<ul style="margin-left: 14px; line-height: 1.5; list-style: disc outside none;">';

			$item_pattern = '/^\s*\*\s*/';
			foreach ($changelog as $line)
				if (preg_match($item_pattern, $line))
					$output .= '<li>'.preg_replace('/`([^`]*)`/', '<code>$1</code>', htmlspecialchars(preg_replace($item_pattern, '', trim($line)))).'</li>';

			$output .= '</ul>';
			$output .= '</div>';

			echo $output;
		}
	} // function update_message


	/**
	 * Register setting for writing options page.
	 *
	 * @hook	admin_init
	 * @return	void
	 */
	public function register_setting() {
		register_setting('writing', $this->option_name, array($this, 'save_setting'));
	} // function register_setting


	/**
	 * Prepare option values before they are saved.
	 *
	 * @see		register_setting()
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
	 * Register settings section.
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
	 * Print checkbox into writing option page.
	 *
	 * @see		add_settings_section()
	 * @return	void
	 */
	public function show_settings() {
		$this->print_exclude_new_pages_ui();
		$this->unload_textdomain();
	} // function show_settings


	/**
	 * Print settings checkbox.
	 *
	 * @see		show_settings()
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
	 * Exclude pages from get_pages() results.
	 *
	 * @hook	get_pages
	 * @param	array $pages Page IDs.
	 * @return	array Page IDs of not excluded pages only.
	 */
	public function exclude_pages($pages) {
		if (! $this->is_enabled)
			return $pages;

		if (count($excluded = $this->get_excluded_pages()))
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
	 * Get excluded pages.
	 *
	 * @return	array Page IDs of excluded pages.
	 */
	protected function get_excluded_pages() {
		$excluded = $this->get_option('exclude_pages', '');
		if (! is_string($excluded) || '' === $excluded)
			return array();

		return explode(',', $excluded);
	} // function get_excluded_pages


	/**
	 * Load plugin textdomain.
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
	 * Disable the plugin filter.
	 *
	 * @see		disable_im8_exclude_pages()
	 * @return	void
	 */
	public function disable() {
		$this->is_enabled = false;
	} // function disable


	/**
	 * Enable the plugin filter.
	 *
	 * @see		enable_im8_exclude_pages()
	 * @return	void
	 */
	public function enable() {
		$this->is_enabled = true;
	} // function enable


	/**
	 * Delete plugin data on uninstall.
	 *
	 * @hook	uninstall
	 * @return	void
	 */
	public static function uninstall() {
		delete_option(self::get_instance()->option_name);
	} // function uninstall

} // class IM8ExcludePages


add_action('plugins_loaded', array(IM8ExcludePages::get_instance(), 'init_on_demand'));




/**
 * Disable the plugin filter.
 *
 * @return	void
 */
function disable_im8_exclude_pages() {
	IM8ExcludePages::get_instance()->disable();
} // function disable_im8_exclude_pages


/**
 * Enable the plugin filter.
 *
 * @return	void
 */
function enable_im8_exclude_pages() {
	IM8ExcludePages::get_instance()->enable();
} // function enable_im8_exclude_pages


endif; // if (! class_exists('IM8ExcludePages'))