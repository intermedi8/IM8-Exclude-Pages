=== IM8 Exclude Pages ===
Contributors: intermedi8, ipm-frommen
Donate link: http://intermedi8.de
Tags: exclude, pages, cms, hide, disable, navigation, menu
Requires at least: 2.9.2
Tested up to: 3.8.1
Stable tag: trunk
License: MIT
License URI: http://opensource.org/licenses/MIT

Adds a meta box to the Edit Page page where you can set to show or exclude the page from page listings.

== Description ==

**Adds a meta box to the _Edit Page_ page where you can set to show or exclude the page from page listings.**

* Automatic exclusion of child pages
* Individually disable and enable the plugin filter
* Multilanguage: currently English and German (please help us with translations if you want to see additional languages)
* Ad-free (of course, donations are welcome)

If you would like to **contribute** to this plugin, see its <a href="https://github.com/intermedi8/im8-exclude-pages" target="_blank">**GitHub repository**</a>.

== Installation ==

1. Upload the `im8-exclude-pages` folder to the `/wp-content/plugins` directory on your web server.
2. Activate the plugin through the _Plugins_ menu in WordPress.
3. Find the new _Exclude Page_ meta box on the regular _Edit Page_ page

== Frequently Asked Questions ==

= Is there a way to (temporarily) disable the plugin filter? =

Yes, there is. Suppose you want to exclude a page from your menu but show it in your sitemap (both generated with `wp_list_pages`). To do so, just use the `disable_im8_exclude_pages()` function right before building your sitemap. If you would like to re-enable the filter (e.g., if you have a footer menu, where you want to excvlude the page again), you may do so by calling the `enable_im8_exclude_pages()` function.

== Screenshots ==

1. **Meta box on the page editor** - Here you can toggle the exclusion status for each page.
2. **Example of a child page with excluded ancestor** - You get notified that the child page will be automatically excluded too.
3. **Setting for new pages** - Automatically exclude new pages by default.

== Changelog ==

= 2.7 =
* code reformat
* introduced new setting to automatically exclude pages from search results (thanks to _koroikoroi_ for the feature request)

= 2.6.1 =
* compatible up to WordPress 3.8.1
* added some `index.php` files

= 2.6 =
* integrated plugin update message
* corrected some DocBlocks

= 2.5 =
* fixed bug that prevented plugin from being loaded when activated network-wide

= 2.4 =
* added direct access guard
* removed trailing `?>`

= 2.3 =
* compatible up to WordPress 3.8

= 2.2 =
* bugfix in `autoupdate` routine
* wrapped plugin in `if (! class_exists('IM8ExcludePages'))`
* optimized `uninstall` routine

= 2.1 =
* fixed bug in `exclude_pages`/`get_excluded_pages` (credits go to _blizam_ for reporting this)

= 2.0 =
* complete refactoring
* new `disable_im8_exclude_pages` and `enable_im8_exclude_pages` functions to individually disable and enable the plugin filter (e.g., if you want to exclude several pages from your menu, and include them in your sitemap)
* more usage of WordPress core functions
* moved screenshot to `assets` folder
* added banner image

= 1.16 =
* checked for WP 3.5 compatibility

= 1.15 =
* removed deprecated parameter (credits for the hint go to _ijo_)

= 1.0 =
* initial release