<?php
/**
 * Class Felix_Arntz\WP_OOP_Plugin_Lib\Admin_Pages\Admin_Menu
 *
 * @since n.e.x.t
 * @package wp-oop-plugin-lib
 */

namespace Felix_Arntz\WP_OOP_Plugin_Lib\Admin_Pages;

use Felix_Arntz\WP_OOP_Plugin_Lib\Admin_Pages\Contracts\Admin_Page;

/**
 * Class representing a WordPress admin menu which admin pages can be added to.
 *
 * It may be an existing admin menu or a new custom admin menu.
 *
 * @since n.e.x.t
 */
class Admin_Menu {

	/**
	 * Admin menu slug.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	private $menu_slug;

	/**
	 * Admin menu arguments.
	 *
	 * Only relevant if this is a new admin menu.
	 *
	 * @since n.e.x.t
	 * @var array<string, mixed>
	 */
	private $menu_args;

	/**
	 * Whether the menu has been added (or was already present).
	 *
	 * @since n.e.x.t
	 * @var bool
	 */
	private $menu_added;

	/**
	 * Constructor.
	 *
	 * @since n.e.x.t
	 *
	 * @param string               $menu_slug Admin menu slug. Must be either one of the default admin menu files (e.g.
	 *                                        'options-general.php'), or a custom menu slug, or an empty string to
	 *                                        register admin pages that should not appear in any menu.
	 * @param array<string, mixed> $menu_args {
	 *     Optional. If this is not an already present admin menu, these arguments should be provided.
	 *
	 *     @type string    $menu_title Admin menu title.
	 *     @type string    $icon_url   Icon URL to the icon to be used for this admin menu. Can also be a
	 *                                 base64-encoded SVG using a data URI, or the name of a Dashicons helper class.
	 *     @type int|float $position   Position in the overall menu order this menu should appear.
	 * }
	 */
	public function __construct( string $menu_slug, array $menu_args = array() ) {
		$this->menu_slug = $menu_slug;
		$this->menu_args = wp_parse_args(
			$menu_args,
			array(
				'menu_title' => '',
				'icon_url'   => '',
				'position'   => null,
			)
		);
	}

	/**
	 * Adds an admin page to the admin menu.
	 *
	 * @since n.e.x.t
	 *
	 * @param Admin_Page $page Admin page to add.
	 * @return string Page hook suffix generated by WordPress on success, or empty string on failure.
	 */
	public function add_page( Admin_Page $page ): string {
		// If this is the first attempt to add a page, determine whether the menu needs to be added.
		if ( null === $this->menu_added ) {
			$this->menu_added = $this->is_menu_already_present();
		}

		// If the menu does not yet exist, add it by adding the page, using the menu arguments where applicable.
		if ( ! $this->menu_added ) {
			add_menu_page(
				$this->get_full_page_title( $page ),
				$this->menu_args['menu_title'],
				$page->get_capability(),
				$page->get_slug(),
				'', /* @phpstan-ignore-line While this does seem wrong, it's literally the default value in WP core. */
				$this->menu_args['icon_url'],
				$this->menu_args['position']
			);

			/*
			 * Ensure the slug of the menu is in fact the slug of its first page.
			 * This is necessary because admin menus in WordPress do not technically have their own slugs.
			 */
			$this->menu_slug  = $page->get_slug();
			$this->menu_added = true;
		}

		$hook_suffix = (string) add_submenu_page(
			$this->menu_slug,
			$this->get_full_page_title( $page ),
			$page->get_title(),
			$page->get_capability(),
			$page->get_slug(),
			array( $page, 'render' )
		);
		if ( $hook_suffix ) {
			add_action( "load-{$hook_suffix}", array( $page, 'load' ) );
		}
		return $hook_suffix;
	}

	/**
	 * Returns the slug of the admin menu.
	 *
	 * @since n.e.x.t
	 *
	 * @return string Admin menu slug.
	 */
	public function get_slug(): string {
		return $this->menu_slug;
	}

	/**
	 * Checks whether the menu is already present.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool True if the menu was already added, false otherwise.
	 */
	private function is_menu_already_present(): bool {
		global $admin_page_hooks;

		// Is this a core menu?
		if ( str_ends_with( $this->menu_slug, '.php' ) ) {
			return true;
		}

		// Is this a custom menu that is already present (e.g. from this or another plugin)?
		if ( isset( $admin_page_hooks[ $this->menu_slug ] ) ) {
			return true;
		}

		// Is this not an actual menu but rather a container for admin pages not linked from any menu?
		if ( ! $this->menu_slug ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the full page title to use as part of the HTML document title for the page.
	 *
	 * @since n.e.x.t
	 *
	 * @param Admin_Page $page Admin page to get the full page title for.
	 * @return string Full page title.
	 */
	private function get_full_page_title( Admin_Page $page ): string {
		/*
		 * If there is a title set for this menu that is not already part of the page title,
		 * prefix the page title with it.
		 */
		if ( $this->menu_args['menu_title'] && ! str_contains( $page->get_title(), $this->menu_args['menu_title'] ) ) {
			return $this->menu_args['menu_title'] . $page->get_title();
		}

		return $page->get_title();
	}
}
