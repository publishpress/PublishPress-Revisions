<?php
namespace PublishPress\Revisions;

class Utils {
	public static function isRESTurl() {
		static $arr_url;
	
		if (!isset($arr_url)) {
			$arr_url = parse_url(get_option('siteurl'));
		}
	
		if ($arr_url) {
			$path = isset($arr_url['path']) ? $arr_url['path'] : '';
	
			if (0 === strpos(esc_url_raw($_SERVER['REQUEST_URI']), $path . '/wp-json/oembed/')) {
				return false;	
			}
	
			if (0 === strpos(esc_url_raw($_SERVER['REQUEST_URI']), $path . '/wp-json/')) {
				return true;
			}
		}
	
		return false;
	}

		/**
     * Returns true if is a beta or stable version of WP 5.
     *
     * @return bool
     */
    public static function isWp5()
    {
        global $wp_version;

        return version_compare($wp_version, '5.0', '>=') || substr($wp_version, 0, 2) === '5.';
    }
	
	/**
	 * Based on Edit Flow's \Block_Editor_Compatible::should_apply_compat method.
	 *
	 * @return bool
	 */
	public static function isBlockEditorActive() {
		global $wp_version;

		// Check if PP Custom Post Statuses lower than v2.4 is installed. It disables Gutenberg.
		if ( defined('PPS_VERSION') && version_compare(PPS_VERSION, '2.4-beta', '<') ) {
			return false;
		}

		if (class_exists('Classic_Editor')) {
			if (isset($_REQUEST['classic-editor__forget']) && (isset($_REQUEST['classic']) || isset($_REQUEST['classic-editor']))) {
				return false;
			} elseif (isset($_REQUEST['classic-editor__forget']) && !isset($_REQUEST['classic']) && !isset($_REQUEST['classic-editor'])) {
				return true;
			} elseif (get_option('classic-editor-allow-users') === 'allow') {
				if ($post_id = rvy_detect_post_id()) {
					$which = get_post_meta( $post_id, 'classic-editor-remember', true );

					if ('block-editor' == $which) {
						return true;
					} elseif ('classic-editor' == $which) {
						return false;
					}
				}
			}
		}

		$pluginsState = array(
			'classic-editor' => class_exists( 'Classic_Editor' ), // is_plugin_active('classic-editor/classic-editor.php'),
			'gutenberg'      => function_exists( 'the_gutenberg_project' ), //is_plugin_active('gutenberg/gutenberg.php'),
			'gutenberg-ramp' => class_exists('Gutenberg_Ramp'),
		);

		if ( ! $postType = rvy_detect_post_type() ) {
			$postType = 'page';
		}
		
		if ( $post_type_obj = get_post_type_object( $postType ) ) {
			if ( empty( $post_type_obj->show_in_rest ) ) {
				return false;
			}
		}

		$conditions = array();

		/**
		 * 5.0:
		 *
		 * Classic editor either disabled or enabled (either via an option or with GET argument).
		 * It's a hairy conditional :(
		 */

		if (version_compare($wp_version, '5.9-beta', '>=')) {
            remove_action('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type', 10, 2);
            remove_filter('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type', 10, 2);
        }

		// phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected, WordPress.Security.NonceVerification.NoNonceVerification
		$conditions[] = (self::isWp5() || $pluginsState['gutenberg'])
						&& ! $pluginsState['classic-editor']
						&& ! $pluginsState['gutenberg-ramp']
						&& apply_filters('use_block_editor_for_post_type', true, $postType, PHP_INT_MAX);

		$conditions[] = self::isWp5()
                        && $pluginsState['classic-editor']
                        && (get_option('classic-editor-replace') === 'block'
                            && ! isset($_GET['classic-editor__forget']));

        $conditions[] = self::isWp5()
                        && $pluginsState['classic-editor']
                        && (get_option('classic-editor-replace') === 'classic'
                            && isset($_GET['classic-editor__forget']));

		$conditions[] = $pluginsState['gutenberg-ramp'] 
						&& apply_filters('use_block_editor_for_post', true, get_post(rvy_detect_post_id()), PHP_INT_MAX);

		if (defined('PP_CAPABILITIES_RESTORE_NAV_TYPE_BLOCK_EDITOR_DISABLE') && version_compare($wp_version, '5.9-beta', '>=')) {
			add_filter('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type', 10, 2 );
		}

		// Returns true if at least one condition is true.
		return count(
				   array_filter($conditions,
					   function ($c) {
						   return (bool)$c;
					   }
				   )
			   ) > 0;
	}

	    /**
	 * Adds slashes only to strings.
	 *
	 * @param mixed $value Value to slash only if string.
	 *
	 * @return string|mixed
	 */
	public static function addslashes_to_strings_only( $value ) {
		return \is_string( $value ) ? \addslashes( $value ) : $value;
	}

	/**
	 * Replaces faulty core wp_slash().
	 *
	 * Until WP 5.5 wp_slash() recursively added slashes not just to strings in array/objects, leading to errors.
	 *
	 * @param mixed $value What to add slashes to.
	 *
	 * @return mixed
	 */
	public static function recursively_slash_strings( $value ) {
		return \map_deep( $value, [ self::class, 'addslashes_to_strings_only' ] );
	}
	
	public static function get_post_autosave($post_id, $user_id) {
		global $wpdb;
	
		//$autosave_name = $post_id . '-autosave-v1';

		$autosave_query = "
			SELECT *
			FROM $wpdb->posts
			WHERE post_parent = %d
			AND post_type = 'revision'
			AND post_status = 'inherit'
			AND post_name LIKE '%" . intval($post_id) . "-autosave%'
			AND post_author = %d
			ORDER BY post_date DESC
			LIMIT 1";
	
		$autosave = $wpdb->get_row(
			$wpdb->prepare(
				$autosave_query,
				$post_id,
				$user_id
			)
		);
	
		if ( ! $autosave ) {
			return false;
		}
	
		return get_post($autosave);
	}
}
