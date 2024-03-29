<?php
/**
 * WordPress Customize Manager classes
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */

/**
 * Customize Manager class.
 *
 * Bootstraps the Customize experience on the server-side.
 *
 * Sets up the theme-switching process if a theme other than the active one is
 * being previewed and customized.
 *
 * Serves as a factory for Customize Controls and Settings, and
 * instantiates default Customize Controls and Settings.
 *
 * @since 3.4.0
 */
final class WP_Customize_Manager {
	/**
	 * An instance of the theme being previewed.
	 *
	 * @since 3.4.0
	 * @var WP_Theme
	 */
	protected $theme;

	/**
	 * The directory name of the previously active theme (within the theme_root).
	 *
	 * @since 3.4.0
	 * @var string
	 */
	protected $original_stylesheet;

	/**
	 * Whether this is a Customizer pageload.
	 *
	 * @since 3.4.0
	 * @var bool
	 */
	protected $previewing = false;

	/**
	 * Methods and properties dealing with managing widgets in the Customizer.
	 *
	 * @since 3.9.0
	 * @var WP_Customize_Widgets
	 */
	public $widgets;

	/**
	 * Methods and properties dealing with managing nav menus in the Customizer.
	 *
	 * @since 4.3.0
	 * @var WP_Customize_Nav_Menus
	 */
	public $nav_menus;

	/**
	 * Methods and properties dealing with selective refresh in the Customizer preview.
	 *
	 * @since 4.5.0
	 * @var WP_Customize_Selective_Refresh
	 */
	public $selective_refresh;

	/**
	 * Registered instances of WP_Customize_Setting.
	 *
	 * @since 3.4.0
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Sorted top-level instances of WP_Customize_Panel and WP_Customize_Section.
	 *
	 * @since 4.0.0
	 * @var array
	 */
	protected $containers = array();

	/**
	 * Registered instances of WP_Customize_Panel.
	 *
	 * @since 4.0.0
	 * @var array
	 */
	protected $panels = array();

	/**
	 * List of core components.
	 *
	 * @since 4.5.0
	 * @var array
	 */
	protected $components = array( 'widgets', 'nav_menus' );

	/**
	 * Registered instances of WP_Customize_Section.
	 *
	 * @since 3.4.0
	 * @var array
	 */
	protected $sections = array();

	/**
	 * Registered instances of WP_Customize_Control.
	 *
	 * @since 3.4.0
	 * @var array
	 */
	protected $controls = array();

	/**
	 * Panel types that may be rendered from JS templates.
	 *
	 * @since 4.3.0
	 * @var array
	 */
	protected $registered_panel_types = array();

	/**
	 * Section types that may be rendered from JS templates.
	 *
	 * @since 4.3.0
	 * @var array
	 */
	protected $registered_section_types = array();

	/**
	 * Control types that may be rendered from JS templates.
	 *
	 * @since 4.1.0
	 * @var array
	 */
	protected $registered_control_types = array();

	/**
	 * Initial URL being previewed.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	protected $preview_url;

	/**
	 * URL to link the user to when closing the Customizer.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	protected $return_url;

	/**
	 * Mapping of 'panel', 'section', 'control' to the ID which should be autofocused.
	 *
	 * @since 4.4.0
	 * @var string[]
	 */
	protected $autofocus = array();

	/**
	 * Messenger channel.
	 *
	 * @since 4.7.0
	 * @var string
	 */
	protected $messenger_channel;

	/**
	 * Whether the autosave revision of the changeset should be loaded.
	 *
	 * @since 4.9.0
	 * @var bool
	 */
	protected $autosaved = false;

	/**
	 * Whether the changeset branching is allowed.
	 *
	 * @since 4.9.0
	 * @var bool
	 */
	protected $branching = true;

	/**
	 * Whether settings should be previewed.
	 *
	 * @since 4.9.0
	 * @var bool
	 */
	protected $settings_previewed = true;

	/**
	 * Whether a starter content changeset was saved.
	 *
	 * @since 4.9.0
	 * @var bool
	 */
	protected $saved_starter_content_changeset = false;

	/**
	 * Unsanitized values for Customize Settings parsed from $_POST['customized'].
	 *
	 * @var array
	 */
	private $_post_values;

	/**
	 * Changeset UUID.
	 *
	 * @since 4.7.0
	 * @var string
	 */
	private $_changeset_uuid;

	/**
	 * Changeset post ID.
	 *
	 * @since 4.7.0
	 * @var int|false
	 */
	private $_changeset_post_id;

	/**
	 * Changeset data loaded from a customize_changeset post.
	 *
	 * @since 4.7.0
	 * @var array|null
	 */
	private $_changeset_data;

	/**
	 * Constructor.
	 *
	 * @since 3.4.0
	 * @since 4.7.0 Added `$args` parameter.
	 *
	 * @param array $args {
	 *     Args.
	 *
	 *     @type null|string|false $changeset_uuid     Changeset UUID, the `post_name` for the customize_changeset post containing the customized state.
	 *                                                 Defaults to `null` resulting in a UUID to be immediately generated. If `false` is provided, then
	 *                                                 then the changeset UUID will be determined during `after_setup_theme`: when the
	 *                                                 `customize_changeset_branching` filter returns false, then the default UUID will be that
	 *                                                 of the most recent `customize_changeset` post that has a status other than 'auto-draft',
	 *                                                 'publish', or 'trash'. Otherwise, if changeset branching is enabled, then a random UUID will be used.
	 *     @type string            $theme              Theme to be previewed (for theme switch). Defaults to customize_theme or theme query params.
	 *     @type string            $messenger_channel  Messenger channel. Defaults to customize_messenger_channel query param.
	 *     @type bool              $settings_previewed If settings should be previewed. Defaults to true.
	 *     @type bool              $branching          If changeset branching is allowed; otherwise, changesets are linear. Defaults to true.
	 *     @type bool              $autosaved          If data from a changeset's autosaved revision should be loaded if it exists. Defaults to false.
	 * }
	 */
	public function __construct( $args = array() ) {

		$args = array_merge(
			array_fill_keys( array( 'changeset_uuid', 'theme', 'messenger_channel', 'settings_previewed', 'autosaved', 'branching' ), null ),
			$args
		);

		// Note that the UUID format will be validated in the setup_theme() method.
		if ( ! isset( $args['changeset_uuid'] ) ) {
			$args['changeset_uuid'] = wp_generate_uuid4();
		}

		// The theme and messenger_channel should be supplied via $args,
		// but they are also looked at in the $_REQUEST global here for back-compat.
		if ( ! isset( $args['theme'] ) ) {
			if ( isset( $_REQUEST['customize_theme'] ) ) {
				$args['theme'] = wp_unslash( $_REQUEST['customize_theme'] );
			} elseif ( isset( $_REQUEST['theme'] ) ) { // Deprecated.
				$args['theme'] = wp_unslash( $_REQUEST['theme'] );
			}
		}
		if ( ! isset( $args['messenger_channel'] ) && isset( $_REQUEST['customize_messenger_channel'] ) ) {
			$args['messenger_channel'] = sanitize_key( wp_unslash( $_REQUEST['customize_messenger_channel'] ) );
		}

		$this->original_stylesheet = get_stylesheet();
		$this->theme               = wp_get_theme( 0 === validate_file( $args['theme'] ) ? $args['theme'] : null );
		$this->messenger_channel   = $args['messenger_channel'];
		$this->_changeset_uuid     = $args['changeset_uuid'];

		foreach ( array( 'settings_previewed', 'autosaved', 'branching' ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$this->$key = (bool) $args[ $key ];
			}
		}

		require_once ABSPATH . WPINC . '/class-wp-customize-setting.php';
		require_once ABSPATH . WPINC . '/class-wp-customize-panel.php';
		require_once ABSPATH . WPINC . '/class-wp-customize-section.php';
		require_once ABSPATH . WPINC . '/class-wp-customize-control.php';

		require_once ABSPATH . WPINC . '/customize/class-wp-customize-color-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-media-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-upload-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-image-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-background-image-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-background-position-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-cropped-image-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-site-icon-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-header-image-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-theme-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-code-editor-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-widget-area-customize-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-widget-form-customize-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-item-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-location-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-name-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-locations-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-auto-add-control.php';

		require_once ABSPATH . WPINC . '/customize/class-wp-customize-nav-menus-panel.php';

		require_once ABSPATH . WPINC . '/customize/class-wp-customize-themes-panel.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-themes-section.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-sidebar-section.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-section.php';

		require_once ABSPATH . WPINC . '/customize/class-wp-customize-custom-css-setting.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-filter-setting.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-header-image-setting.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-background-image-setting.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-item-setting.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-setting.php';

		/**
		 * Filters the core Customizer components to load.
		 *
		 * This allows Core components to be excluded from being instantiated by
		 * filtering them out of the array. Note that this filter generally runs
		 * during the {@see 'plugins_loaded'} action, so it cannot be added
		 * in a theme.
		 *
		 * @since 4.4.0
		 *
		 * @see WP_Customize_Manager::__construct()
		 *
		 * @param string[]             $components Array of core components to load.
		 * @param WP_Customize_Manager $manager    WP_Customize_Manager instance.
		 */
		$components = apply_filters( 'customize_loaded_components', $this->components, $this );

		require_once ABSPATH . WPINC . '/customize/class-wp-customize-selective-refresh.php';
		$this->selective_refresh = new WP_Customize_Selective_Refresh( $this );

		if ( in_array( 'widgets', $components, true ) ) {
			require_once ABSPATH . WPINC . '/class-wp-customize-widgets.php';
			$this->widgets = new WP_Customize_Widgets( $this );
		}

		if ( in_array( 'nav_menus', $components, true ) ) {
			require_once ABSPATH . WPINC . '/class-wp-customize-nav-menus.php';
			$this->nav_menus = new WP_Customize_Nav_Menus( $this );
		}

		add_action( 'setup_theme', array( $this, 'setup_theme' ) );
		add_action( 'wp_loaded', array( $this, 'wp_loaded' ) );

		// Do not spawn cron (especially the alternate cron) while running the Customizer.
		remove_action( 'init', 'wp_cron' );

		// Do not run update checks when rendering the controls.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );

		add_action( 'wp_ajax_customize_save', array( $this, 'save' ) );
		add_action( 'wp_ajax_customize_trash', array( $this, 'handle_changeset_trash_request' ) );
		add_action( 'wp_ajax_customize_refresh_nonces', array( $this, 'refresh_nonces' ) );
		add_action( 'wp_ajax_customize_load_themes', array( $this, 'handle_load_themes_request' ) );
		add_filter( 'heartbeat_settings', array( $this, 'add_customize_screen_to_heartbeat_settings' ) );
		add_filter( 'heartbeat_received', array( $this, 'check_changeset_lock_with_heartbeat' ), 10, 3 );
		add_action( 'wp_ajax_customize_override_changeset_lock', array( $this, 'handle_override_changeset_lock_request' ) );
		add_action( 'wp_ajax_customize_dismiss_autosave_or_lock', array( $this, 'handle_dismiss_autosave_or_lock_request' ) );

		add_action( 'customize_register', array( $this, 'register_controls' ) );
		add_action( 'customize_register', array( $this, 'register_dynamic_settings' ), 11 ); // Allow code to create settings first.
		add_action( 'customize_controls_init', array( $this, 'prepare_controls' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_control_scripts' ) );

		// Render Common, Panel, Section, and Control templates.
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_panel_templates' ), 1 );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_section_templates' ), 1 );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_control_templates' ), 1 );

		// Export header video settings with the partial response.
		add_filter( 'customize_render_partials_response', array( $this, 'export_header_video_settings' ), 10, 3 );

		// Export the settings to JS via the _wpCustomizeSettings variable.
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'customize_pane_settings' ), 1000 );

		// Add theme update notices.
		if ( current_user_can( 'install_themes' ) || current_user_can( 'update_themes' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
			add_action( 'customize_controls_print_footer_scripts', 'wp_print_admin_notice_templates' );
		}
	}

	/**
	 * Return true if it's an Ajax request.
	 *
	 * @since 3.4.0
	 * @since 4.2.0 Added `$action` param.
	 *
	 * @param string|null $action Whether the supplied Ajax action is being run.
	 * @return bool True if it's an Ajax request, false otherwise.
	 */
	public function doing_ajax( $action = null ) {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		if ( ! $action ) {
			return true;
		} else {
			/*
			 * Note: we can't just use doing_action( "wp_ajax_{$action}" ) because we need
			 * to check before admin-ajax.php gets to that point.
			 */
			return isset( $_REQUEST['action'] ) && wp_unslash( $_REQUEST['action'] ) === $action;
		}
	}

	/**
	 * Custom wp_die wrapper. Returns either the standard message for UI
	 * or the Ajax message.
	 *
	 * @since 3.4.0
	 *
	 * @param string|WP_Error $ajax_message Ajax return.
	 * @param string          $message      Optional. UI message.
	 */
	protected function wp_die( $ajax_message, $message = null ) {
		if ( $this->doing_ajax() ) {
			wp_die( $ajax_message );
		}

		if ( ! $message ) {
			$message = __( 'Something went wrong.' );
		}

		if ( $this->messenger_channel ) {
			ob_start();
			wp_enqueue_scripts();
			wp_print_scripts( array( 'customize-base' ) );

			$settings = array(
				'messengerArgs' => array(
					'channel' => $this->messenger_channel,
					'url'     => wp_customize_url(),
				),
				'error'         => $ajax_message,
			);
			?>
			<script>
			( function( api, settings ) {
				var preview = new api.Messenger( settings.messengerArgs );
				preview.send( 'iframe-loading-error', settings.error );
			} )( wp.customize, <?php echo wp_json_encode( $settings ); ?> );
			</script>
			<?php
			$message .= ob_get_clean();
		}

		wp_die( $message );
	}

	/**
	 * Return the Ajax wp_die() handler if it's a customized request.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0
	 *
	 * @return callable Die handler.
	 */
	public function wp_die_handler() {
		_deprecated_function( __METHOD__, '4.7.0' );

		if ( $this->doing_ajax() || isset( $_POST['customized'] ) ) {
			return '_ajax_wp_die_handler';
		}

		return '_default_wp_die_handler';
	}

	/**
	 * Start preview and customize theme.
	 *
	 * Check if customize query variable exist. Init filters to filter the current theme.
	 *
	 * @since 3.4.0
	 *
	 * @global string $pagenow
	 */
	public function setup_theme() {
		global $pagenow;

		// Check permissions for customize.php access since this method is called before customize.php can run any code.
		if ( 'customize.php' === $pagenow && ! current_user_can( 'customize' ) ) {
			if ( ! is_user_logged_in() ) {
				auth_redirect();
			} else {
				wp_die(
					'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
					'<p>' . __( 'Sorry, you are not allowed to customize this site.' ) . '</p>',
					403
				);
			}
			return;
		}

		// If a changeset was provided is invalid.
		if ( isset( $this->_changeset_uuid ) && false !== $this->_changeset_uuid && ! wp_is_uuid( $this->_changeset_uuid ) ) {
			$this->wp_die( -1, __( 'Invalid changeset UUID' ) );
		}

		/*
		 * Clear incoming post data if the user lacks a CSRF token (nonce). Note that the customizer
		 * application will inject the customize_preview_nonce query parameter into all Ajax requests.
		 * For similar behavior elsewhere in WordPress, see rest_cookie_check_errors() which logs out
		 * a user when a valid nonce isn't present.
		 */
		$has_post_data_nonce = (
			check_ajax_referer( 'preview-customize_' . $this->get_stylesheet(), 'nonce', false )
			||
			check_ajax_referer( 'save-customize_' . $this->get_stylesheet(), 'nonce', false )
			||
			check_ajax_referer( 'preview-customize_' . $this->get_stylesheet(), 'customize_preview_nonce', false )
		);
		if ( ! current_user_can( 'customize' ) || ! $has_post_data_nonce ) {
			unset( $_POST['customized'] );
			unset( $_REQUEST['customized'] );
		}

		/*
		 * If unauthenticated then require a valid changeset UUID to load the preview.
		 * In this way, the UUID serves as a secret key. If the messenger channel is present,
		 * then send unauthenticated code to prompt re-auth.
		 */
		if ( ! current_user_can( 'customize' ) && ! $this->changeset_post_id() ) {
			$this->wp_die( $this->messenger_channel ? 0 : -1, __( 'Non-existent changeset UUID.' ) );
		}

		if ( ! headers_sent() ) {
			send_origin_headers();
		}

		// Hide the admin bar if we're embedded in the customizer iframe.
		if ( $this->messenger_channel ) {
			show_admin_bar( false );
		}

		if ( $this->is_theme_active() ) {
			// Once the theme is loaded, we'll validate it.
			add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );
		} else {
			// If the requested theme is not the active theme and the user doesn't have
			// the switch_themes cap, bail.
			if ( ! current_user_can( 'switch_themes' ) ) {
				$this->wp_die( -1, __( 'Sorry, you are not allowed to edit theme options on this site.' ) );
			}

			// If the theme has errors while loading, bail.
			if ( $this->theme()->errors() ) {
				$this->wp_die( -1, $this->theme()->errors()->get_error_message() );
			}

			// If the theme isn't allowed per multisite settings, bail.
			if ( ! $this->theme()->is_allowed() ) {
				$this->wp_die( -1, __( 'The requested theme does not exist.' ) );
			}
		}

		// Make sure changeset UUID is established immediately after the theme is loaded.
		add_action( 'after_setup_theme', array( $this, 'establish_loaded_changeset' ), 5 );

		/*
		 * Import theme starter content for fresh installations when landing in the customizer.
		 * Import starter content at after_setup_theme:100 so that any
		 * add_theme_support( 'starter-content' ) calls will have been made.
		 */
		if ( get_option( 'fresh_site' ) && 'customize.php' === $pagenow ) {
			add_action( 'after_setup_theme', array( $this, 'import_theme_starter_content' ), 100 );
		}

		$this->start_previewing_theme();
	}

	/**
	 * Establish the loaded changeset.
	 *
	 * This method runs right at after_setup_theme and applies the 'customize_changeset_branching' filter to determine
	 * whether concurrent changesets are allowed. Then if the Customizer is not initialized with a `changeset_uuid` param,
	 * this method will determine which UUID should be used. If changeset branching is disabled, then the most saved
	 * changeset will be loaded by default. Otherwise, if there are no existing saved changesets or if changeset branching is
	 * enabled, then a new UUID will be generated.
	 *
	 * @since 4.9.0
	 *
	 * @global string $pagenow
	 */
	public function establish_loaded_changeset() {
		global $pagenow;

		if ( empty( $this->_changeset_uuid ) ) {
			$changeset_uuid = null;

			if ( ! $this->branching() && $this->is_theme_active() ) {
				$unpublished_changeset_posts = $this->get_changeset_posts(
					array(
						'post_status'               => array_diff( get_post_stati(), array( 'auto-draft', 'publish', 'trash', 'inherit', 'private' ) ),
						'exclude_restore_dismissed' => false,
						'author'                    => 'any',
						'posts_per_page'            => 1,
						'order'                     => 'DESC',
						'orderby'                   => 'date',
					)
				);
				$unpublished_changeset_post  = array_shift( $unpublished_changeset_posts );
				if ( ! empty( $unpublished_changeset_post ) && wp_is_uuid( $unpublished_changeset_post->post_name ) ) {
					$changeset_uuid = $unpublished_changeset_post->post_name;
				}
			}

			// If no changeset UUID has been set yet, then generate a new one.
			if ( empty( $changeset_uuid ) ) {
				$changeset_uuid = wp_generate_uuid4();
			}

			$this->_changeset_uuid = $changeset_uuid;
		}

		if ( is_admin() && 'customize.php' === $pagenow ) {
			$this->set_changeset_lock( $this->changeset_post_id() );
		}
	}

	/**
	 * Callback to validate a theme once it is loaded
	 *
	 * @since 3.4.0
	 */
	public function after_setup_theme() {
		$doing_ajax_or_is_customized = ( $this->doing_ajax() || isset( $_POST['customized'] ) );
		if ( ! $doing_ajax_or_is_customized && ! validate_current_theme() ) {
			wp_redirect( 'themes.php?broken=true' );
			exit;
		}
	}

	/**
	 * If the theme to be previewed isn't the active theme, add filter callbacks
	 * to swap it out at runtime.
	 *
	 * @since 3.4.0
	 */
	public function start_previewing_theme() {
		// Bail if we're already previewing.
		if ( $this->is_preview() ) {
			return;
		}

		$this->previewing = true;

		if ( ! $this->is_theme_active() ) {
			add_filter( 'template', array( $this, 'get_template' ) );
			add_filter( 'stylesheet', array( $this, 'get_stylesheet' ) );
			add_filter( 'pre_option_current_theme', array( $this, 'current_theme' ) );

			// @link: https://core.trac.wordpress.org/ticket/20027
			add_filter( 'pre_option_stylesheet', array( $this, 'get_stylesheet' ) );
			add_filter( 'pre_option_template', array( $this, 'get_template' ) );

			// Handle custom theme roots.
			add_filter( 'pre_option_stylesheet_root', array( $this, 'get_stylesheet_root' ) );
			add_filter( 'pre_option_template_root', array( $this, 'get_template_root' ) );
		}

		/**
		 * Fires once the Customizer theme preview has started.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $manager WP_Customize_Manager instance.
		 */
		do_action( 'start_previewing_theme', $this );
	}

	/**
	 * Stop previewing the selected theme.
	 *
	 * Removes filters to change the current theme.
	 *
	 * @since 3.4.0
	 */
	public function stop_previewing_theme() {
		if ( ! $this->is_preview() ) {
			return;
		}

		$this->previewing = false;

		if ( ! $this->is_theme_active() ) {
			remove_filter( 'template', array( $this, 'get_template' ) );
			remove_filter( 'stylesheet', array( $this, 'get_stylesheet' ) );
			remove_filter( 'pre_option_current_theme', array( $this, 'current_theme' ) );

			// @link: https://core.trac.wordpress.org/ticket/20027
			remove_filter( 'pre_option_stylesheet', array( $this, 'get_stylesheet' ) );
			remove_filter( 'pre_option_template', array( $this, 'get_template' ) );

			// Handle custom theme roots.
			remove_filter( 'pre_option_stylesheet_root', array( $this, 'get_stylesheet_root' ) );
			remove_filter( 'pre_option_template_root', array( $this, 'get_template_root' ) );
		}

		/**
		 * Fires once the Customizer theme preview has stopped.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $manager WP_Customize_Manager instance.
		 */
		do_action( 'stop_previewing_theme', $this );
	}

	/**
	 * Gets whether settings are or will be previewed.
	 *
	 * @since 4.9.0
	 *
	 * @see WP_Customize_Setting::preview()
	 *
	 * @return bool
	 */
	public function settings_previewed() {
		return $this->settings_previewed;
	}

	/**
	 * Gets whether data from a changeset's autosaved revision should be loaded if it exists.
	 *
	 * @since 4.9.0
	 *
	 * @see WP_Customize_Manager::changeset_data()
	 *
	 * @return bool Is using autosaved changeset revision.
	 */
	public function autosaved() {
		return $this->autosaved;
	}

	/**
	 * Whether the changeset branching is allowed.
	 *
	 * @since 4.9.0
	 *
	 * @see WP_Customize_Manager::establish_loaded_changeset()
	 *
	 * @return bool Is changeset branching.
	 */
	public function branching() {

		/**
		 * Filters whether or not changeset branching is allowed.
		 *
		 * By default in core, when changeset branching is not allowed, changesets will operate
		 * linearly in that only one saved changeset will exist at a time (with a 'draft' or
		 * 'future' status). This makes the Customizer operate in a way that is similar to going to
		 * "edit" to one existing post: all users will be making changes to the same post, and autosave
		 * revisions will be made for that post.
		 *
		 * By contrast, when changeset branching is allowed, then the model is like users going
		 * to "add new" for a page and each user makes changes independently of each other since
		 * they are all operating on their own separate pages, each getting their own separate
		 * initial auto-drafts and then once initially saved, autosave revisions on top of that
		 * user's specific post.
		 *
		 * Since linear changesets are deemed to be more suitable for the majority of WordPress users,
		 * they are the default. For WordPress sites that have heavy site management in the Customizer
		 * by multiple users then branching changesets should be enabled by means of this filter.
		 *
		 * @since 4.9.0
		 *
		 * @param bool                 $allow_branching Whether branching is allowed. If `false`, the default,
		 *                                              then only one saved changeset exists at a time.
		 * @param WP_Customize_Manager $wp_customize    Manager instance.
		 */
		$this->branching = apply_filters( 'customize_changeset_branching', $this->branching, $this );

		return $this->branching;
	}

	/**
	 * Get the changeset UUID.
	 *
	 * @since 4.7.0
	 *
	 * @see WP_Customize_Manager::establish_loaded_changeset()
	 *
	 * @return string UUID.
	 */
	public function changeset_uuid() {
		if ( empty( $this->_changeset_uuid ) ) {
			$this->establish_loaded_changeset();
		}
		return $this->_changeset_uuid;
	}

	/**
	 * Get the theme being customized.
	 *
	 * @since 3.4.0
	 *
	 * @return WP_Theme
	 */
	public function theme() {
		if ( ! $this->theme ) {
			$this->theme = wp_get_theme();
		}
		return $this->theme;
	}

	/**
	 * Get the registered settings.
	 *
	 * @since 3.4.0
	 *
	 * @return array
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Get the registered controls.
	 *
	 * @since 3.4.0
	 *
	 * @return array
	 */
	public function controls() {
		return $this->controls;
	}

	/**
	 * Get the registered containers.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public function containers() {
		return $this->containers;
	}

	/**
	 * Get the registered sections.
	 *
	 * @since 3.4.0
	 *
	 * @return array
	 */
	public function sections() {
		return $this->sections;
	}

	/**
	 * Get the registered panels.
	 *
	 * @since 4.0.0
	 *
	 * @return array Panels.
	 */
	public function panels() {
		return $this->panels;
	}

	/**
	 * Checks if the current theme is active.
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	public function is_theme_active() {
		return $this->get_stylesheet() === $this->original_stylesheet;
	}

	/**
	 * Register styles/scripts and initialize the preview of each setting
	 *
	 * @since 3.4.0
	 */
	public function wp_loaded() {

		// Unconditionally register core types for panels, sections, and controls
		// in case plugin unhooks all customize_register actions.
		$this->register_panel_type( 'WP_Customize_Panel' );
		$this->register_panel_type( 'WP_Customize_Themes_Panel' );
		$this->register_section_type( 'WP_Customize_Section' );
		$this->register_section_type( 'WP_Customize_Sidebar_Section' );
		$this->register_section_type( 'WP_Customize_Themes_Section' );
		$this->register_control_type( 'WP_Customize_Color_Control' );
		$this->register_control_type( 'WP_Customize_Media_Control' );
		$this->register_control_type( 'WP_Customize_Upload_Control' );
		$this->register_control_type( 'WP_Customize_Image_Control' );
		$this->register_control_type( 'WP_Customize_Background_Image_Control' );
		$this->register_control_type( 'WP_Customize_Background_Position_Control' );
		$this->register_control_type( 'WP_Customize_Cropped_Image_Control' );
		$this->register_control_type( 'WP_Customize_Site_Icon_Control' );
		$this->register_control_type( 'WP_Customize_Theme_Control' );
		$this->register_control_type( 'WP_Customize_Code_Editor_Control' );
		$this->register_control_type( 'WP_Customize_Date_Time_Control' );

		/**
		 * Fires once WordPress has loaded, allowing scripts and styles to be initialized.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $manager WP_Customize_Manager instance.
		 */
		do_action( 'customize_register', $this );

		if ( $this->settings_previewed() ) {
			foreach ( $this->settings as $setting ) {
				$setting->preview();
			}
		}

		if ( $this->is_preview() && ! is_admin() ) {
			$this->customize_preview_init();
		}
	}

	/**
	 * Prevents Ajax requests from following redirects when previewing a theme
	 * by issuing a 200 response instead of a 30x.
	 *
	 * Instead, the JS will sniff out the location header.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0
	 *
	 * @param int $status Status.
	 * @return int
	 */
	public function wp_redirect_status( $status ) {
		_deprecated_function( __FUNCTION__, '4.7.0' );

		if ( $this->is_preview() && ! is_admin() ) {
			return 200;
		}

		return $status;
	}

	/**
	 * Find the changeset post ID for a given changeset UUID.
	 *
	 * @since 4.7.0
	 *
	 * @param string $uuid Changeset UUID.
	 * @return int|null Returns post ID on success and null on failure.
	 */
	public function find_changeset_post_id( $uuid ) {
		$cache_group       = 'customize_changeset_post';
		$changeset_post_id = wp_cache_get( $uuid, $cache_group );
		if ( $changeset_post_id && 'customize_changeset' === get_post_type( $changeset_post_id ) ) {
			return $changeset_post_id;
		}

		$changeset_post_query = new WP_Query(
			array(
				'post_type'              => 'customize_changeset',
				'post_status'            => get_post_stati(),
				'name'                   => $uuid,
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'cache_results'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'lazy_load_term_meta'    => false,
			)
		);
		if ( ! empty( $changeset_post_query->posts ) ) {
			// Note: 'fields'=>'ids' is not being used in order to cache the post object as it will be needed.
			$changeset_post_id = $changeset_post_query->posts[0]->ID;
			wp_cache_set( $uuid, $changeset_post_id, $cache_group );
			return $changeset_post_id;
		}

		return null;
	}

	/**
	 * Get changeset posts.
	 *
	 * @since 4.9.0
	 *
	 * @param array $args {
	 *     Args to pass into `get_posts()` to query changesets.
	 *
	 *     @type int    $posts_per_page             Number of posts to return. Defaults to -1 (all posts).
	 *     @type int    $author                     Post author. Defaults to current user.
	 *     @type string $post_status                Status of changeset. Defaults to 'auto-draft'.
	 *     @type bool   $exclude_restore_dismissed  Whether to exclude changeset auto-drafts that have been dismissed. Defaults to true.
	 * }
	 * @return WP_Post[] Auto-draft changesets.
	 */
	protected function get_changeset_posts( $args = array() ) {
		$default_args = array(
			'exclude_restore_dismissed' => true,
			'posts_per_page'            => -1,
			'post_type'                 => 'customize_changeset',
			'post_status'               => 'auto-draft',
			'order'                     => 'DESC',
			'orderby'                   => 'date',
			'no_found_rows'             => true,
			'cache_results'             => true,
			'update_post_meta_cache'    => false,
			'update_post_term_cache'    => false,
			'lazy_load_term_meta'       => false,
		);
		if ( get_current_user_id() ) {
			$default_args['author'] = get_current_user_id();
		}
		$args = array_merge( $default_args, $args );

		if ( ! empty( $args['exclude_restore_dismissed'] ) ) {
			unset( $args['exclude_restore_dismissed'] );
			$args['meta_query'] = array(
				array(
					'key'     => '_customize_restore_dismissed',
					'compare' => 'NOT EXISTS',
				),
			);
		}

		return get_posts( $args );
	}

	/**
	 * Dismiss all of the current user's auto-drafts (other than the present one).
	 *
	 * @since 4.9.0
	 * @return int The number of auto-drafts that were dismissed.
	 */
	protected function dismiss_user_auto_draft_changesets() {
		$changeset_autodraft_posts = $this->get_changeset_posts(
			array(
				'post_status'               => 'auto-draft',
				'exclude_restore_dismissed' => true,
				'posts_per_page'            => -1,
			)
		);
		$dismissed                 = 0;
		foreach ( $changeset_autodraft_posts as $autosave_autodraft_post ) {
			if ( $autosave_autodraft_post->ID === $this->changeset_post_id() ) {
				continue;
			}
			if ( update_post_meta( $autosave_autodraft_post->ID, '_customize_restore_dismissed', true ) ) {
				$dismissed++;
			}
		}
		return $dismissed;
	}

	/**
	 * Get the changeset post ID for the loaded changeset.
	 *
	 * @since 4.7.0
	 *
	 * @return int|null Post ID on success or null if there is no post yet saved.
	 */
	public function changeset_post_id() {
		if ( ! isset( $this->_changeset_post_id ) ) {
			$post_id = $this->find_changeset_post_id( $this->changeset_uuid() );
			if ( ! $post_id ) {
				$post_id = false;
			}
			$this->_changeset_post_id = $post_id;
		}
		if ( false === $this->_changeset_post_id ) {
			return null;
		}
		return $this->_changeset_post_id;
	}

	/**
	 * Get the data stored in a changeset post.
	 *
	 * @since 4.7.0
	 *
	 * @param int $post_id Changeset post ID.
	 * @return array|WP_Error Changeset data or WP_Error on error.
	 */
	protected function get_changeset_post_data( $post_id ) {
		if ( ! $post_id ) {
			return new WP_Error( 'empty_post_id' );
		}
		$changeset_post = get_post( $post_id );
		if ( ! $changeset_post ) {
			return new WP_Error( 'missing_post' );
		}
		if ( 'revision' === $changeset_post->post_type ) {
			if ( 'customize_changeset' !== get_post_type( $changeset_post->post_parent ) ) {
				return new WP_Error( 'wrong_post_type' );
			}
		} elseif ( 'customize_changeset' !== $changeset_post->post_type ) {
			return new WP_Error( 'wrong_post_type' );
		}
		$changeset_data = json_decode( $changeset_post->post_content, true );
		$last_error     = json_last_error();
		if ( $last_error ) {
			return new WP_Error( 'json_parse_error', '', $last_error );
		}
		if ( ! is_array( $changeset_data ) ) {
			return new WP_Error( 'expected_array' );
		}
		return $changeset_data;
	}

	/**
	 * Get changeset data.
	 *
	 * @since 4.7.0
	 * @since 4.9.0 This will return the changeset's data with a user's autosave revision merged on top, if one exists and $autosaved is true.
	 *
	 * @return array Changeset data.
	 */
	public function changeset_data() {
		if ( isset( $this->_changeset_data ) ) {
			return $this->_changeset_data;
		}
		$changeset_post_id = $this->changeset_post_id();
		if ( ! $changeset_post_id ) {
			$this->_changeset_data = array();
		} else {
			if ( $this->autosaved() && is_user_logged_in() ) {
				$autosave_post = wp_get_post_autosave( $changeset_post_id, get_current_user_id() );
				if ( $autosave_post ) {
					$data = $this->get_changeset_post_data( $autosave_post->ID );
					if ( ! is_wp_error( $data ) ) {
						$this->_changeset_data = $data;
					}
				}
			}

			// Load data from the changeset if it was not loaded from an autosave.
			if ( ! isset( $this->_changeset_data ) ) {
				$data = $this->get_changeset_post_data( $changeset_post_id );
				if ( ! is_wp_error( $data ) ) {
					$this->_changeset_data = $data;
				} else {
					$this->_changeset_data = array();
				}
			}
		}
		return $this->_changeset_data;
	}

	/**
	 * Starter content setting IDs.
	 *
	 * @since 4.7.0
	 * @var array
	 */
	protected $pending_starter_content_settings_ids = array();

	/**
	 * Import theme starter content into the customized state.
	 *
	 * @since 4.7.0
	 *
	 * @param array $starter_content Starter content. Defaults to `get_theme_starter_content()`.
	 */
	public function import_theme_starter_content( $starter_content = array() ) {
		if ( empty( $starter_content ) ) {
			$starter_content = get_theme_starter_content();
		}

		$changeset_data = array();
		if ( $this->changeset_post_id() ) {
			/*
			 * Don't re-import starter content into a changeset saved persistently.
			 * This will need to be revisited in the future once theme switching
			 * is allowed with drafted/scheduled changesets, since switching to
			 * another theme could result in more starter content being applied.
			 * However, when doing an explicit save it is currently possible for
			 * nav menus and nav menu items specifically to lose their starter_content
			 * flags, thus resulting in duplicates being created since they fail
			 * to get re-used. See #40146.
			 */
			if ( 'auto-draft' !== get_post_status( $this->changeset_post_id() ) ) {
				return;
			}

			$changeset_data = $this->get_changeset_post_data( $this->changeset_post_id() );
		}

		$sidebars_widgets = isset( $starter_content['widgets'] ) && ! empty( $this->widgets ) ? $starter_content['widgets'] : array();
		$attachments      = isset( $starter_content['attachments'] ) && ! empty( $this->nav_menus ) ? $starter_content['attachments'] : array();
		$posts            = isset( $starter_content['posts'] ) && ! empty( $this->nav_menus ) ? $starter_content['posts'] : array();
		$options          = isset( $starter_content['options'] ) ? $starter_content['options'] : array();
		$nav_menus        = isset( $starter_content['nav_menus'] ) && ! empty( $this->nav_menus ) ? $starter_content['nav_menus'] : array();
		$theme_mods       = isset( $starter_content['theme_mods'] ) ? $starter_content['theme_mods'] : array();

		// Widgets.
		$max_widget_numbers = array();
		foreach ( $sidebars_widgets as $sidebar_id => $widgets ) {
			$sidebar_widget_ids = array();
			foreach ( $widgets as $widget ) {
				list( $id_base, $instance ) = $widget;

				if ( ! isset( $max_widget_numbers[ $id_base ] ) ) {

					// When $settings is an array-like object, get an intrinsic array for use with array_keys().
					$settings = get_option( "widget_{$id_base}", array() );
					if ( $settings instanceof ArrayObject || $settings instanceof ArrayIterator ) {
						$settings = $settings->getArrayCopy();
					}

					unset( $settings['_multiwidget'] );

					// Find the max widget number for this type.
					$widget_numbers = array_keys( $settings );
					if ( count( $widget_numbers ) > 0 ) {
						$widget_numbers[]               = 1;
						$max_widget_numbers[ $id_base ] = max( ...$widget_numbers );
					} else {
						$max_widget_numbers[ $id_base ] = 1;
					}
				}
				$max_widget_numbers[ $id_base ] += 1;

				$widget_id  = sprintf( '%s-%d', $id_base, $max_widget_numbers[ $id_base ] );
				$setting_id = sprintf( 'widget_%s[%d]', $id_base, $max_widget_numbers[ $id_base ] );

				$setting_value = $this->widgets->sanitize_widget_js_instance( $instance );
				if ( empty( $changeset_data[ $setting_id ] ) || ! empty( $changeset_data[ $setting_id ]['starter_content'] ) ) {
					$this->set_post_value( $setting_id, $setting_value );
					$this->pending_starter_content_settings_ids[] = $setting_id;
				}
				$sidebar_widget_ids[] = $widget_id;
			}

			$setting_id = sprintf( 'sidebars_widgets[%s]', $sidebar_id );
			if ( empty( $changeset_data[ $setting_id ] ) || ! empty( $changeset_data[ $setting_id ]['starter_content'] ) ) {
				$this->set_post_value( $setting_id, $sidebar_widget_ids );
				$this->pending_starter_content_settings_ids[] = $setting_id;
			}
		}

		$starter_content_auto_draft_post_ids = array();
		if ( ! empty( $changeset_data['nav_menus_created_posts']['value'] ) ) {
			$starter_content_auto_draft_post_ids = array_merge( $starter_content_auto_draft_post_ids, $changeset_data['nav_menus_created_posts']['value'] );
		}

		// Make an index of all the posts needed and what their slugs are.
		$needed_posts = array();
		$attachments  = $this->prepare_starter_content_attachments( $attachments );
		foreach ( $attachments as $attachment ) {
			$key                  = 'attachment:' . $attachment['post_name'];
			$needed_posts[ $key ] = true;
		}
		foreach ( array_keys( $posts ) as $post_symbol ) {
			if ( empty( $posts[ $post_symbol ]['post_name'] ) && empty( $posts[ $post_symbol ]['post_title'] ) ) {
				unset( $posts[ $post_symbol ] );
				continue;
			}
			if ( empty( $posts[ $post_symbol ]['post_name'] ) ) {
				$posts[ $post_symbol ]['post_name'] = sanitize_title( $posts[ $post_symbol ]['post_title'] );
			}
			if ( empty( $posts[ $post_symbol ]['post_type'] ) ) {
				$posts[ $post_symbol ]['post_type'] = 'post';
			}
			$needed_posts[ $posts[ $post_symbol ]['post_type'] . ':' . $posts[ $post_symbol ]['post_name'] ] = true;
		}
		$all_post_slugs = array_merge(
			wp_list_pluck( $attachments, 'post_name' ),
			wp_list_pluck( $posts, 'post_name' )
		);

		/*
		 * Obtain all post types referenced in starter content to use in query.
		 * This is needed because 'any' will not account for post types not yet registered.
		 */
		$post_types = array_filter( array_merge( array( 'attachment' ), wp_list_pluck( $posts, 'post_type' ) ) );

		// Re-use auto-draft starter content posts referenced in the current customized state.
		$existing_starter_content_posts = array();
		if ( ! empty( $starter_content_auto_draft_post_ids ) ) {
			$existing_posts_query = new WP_Query(
				array(
					'post__in'       => $starter_content_auto_draft_post_ids,
					'post_status'    => 'auto-draft',
					'post_type'      => $post_types,
					'posts_per_page' => -1,
				)
			);
			foreach ( $existing_posts_query->posts as $existing_post ) {
				$post_name = $existing_post->post_name;
				if ( empty( $post_name ) ) {
					$post_name = get_post_meta( $existing_post->ID, '_customize_draft_post_name', true );
				}
				$existing_starter_content_posts[ $existing_post->post_type . ':' . $post_name ] = $existing_post;
			}
		}

		// Re-use non-auto-draft posts.
		if ( ! empty( $all_post_slugs ) ) {
			$existing_posts_query = new WP_Query(
				array(
					'post_name__in'  => $all_post_slugs,
					'post_status'    => array_diff( get_post_stati(), array( 'auto-draft' ) ),
					'post_type'      => 'any',
					'posts_per_page' => -1,
				)
			);
			foreach ( $existing_posts_query->posts as $existing_post ) {
				$key = $existing_post->post_type . ':' . $existing_post->post_name;
				if ( isset( $needed_posts[ $key ] ) && ! isset( $existing_starter_content_posts[ $key ] ) ) {
					$existing_starter_content_posts[ $key ] = $existing_post;
				}
			}
		}

		// Attachments are technically posts but handled differently.
		if ( ! empty( $attachments ) ) {

			$attachment_ids = array();

			foreach ( $attachments as $symbol => $attachment ) {
				$file_array    = array(
					'name' => $attachment['file_name'],
				);
				$file_path     = $attachment['file_path'];
				$attachment_id = null;
				$attached_file = null;
				if ( isset( $existing_starter_content_posts[ 'attachment:' . $attachment['post_name'] ] ) ) {
					$attachment_post = $existing_starter_content_posts[ 'attachment:' . $attachment['post_name'] ];
					$attachment_id   = $attachment_post->ID;
					$attached_file   = get_attached_file( $attachment_id );
					if ( empty( $attached_file ) || ! file_exists( $attached_file ) ) {
						$attachment_id = null;
						$attached_file = null;
					} elseif ( $this->get_stylesheet() !== get_post_meta( $attachment_post->ID, '_starter_content_theme', true ) ) {

						// Re-generate attachment metadata since it was previously generated for a different theme.
						$metadata = wp_generate_attachment_metadata( $attachment_post->ID, $attached_file );
						wp_update_attachment_metadata( $attachment_id, $metadata );
						update_post_meta( $attachment_id, '_starter_content_theme', $this->get_stylesheet() );
					}
				}

				// Insert the attachment auto-draft because it doesn't yet exist or the attached file is gone.
				if ( ! $attachment_id ) {

					// Copy file to temp location so that original file won't get deleted from theme after sideloading.
					$temp_file_name = wp_tempnam( wp_basename( $file_path ) );
					if ( $temp_file_name && copy( $file_path, $temp_file_name ) ) {
						$file_array['tmp_name'] = $temp_file_name;
					}
					if ( empty( $file_array['tmp_name'] ) ) {
						continue;
					}

					$attachment_post_data = array_merge(
						wp_array_slice_assoc( $attachment, array( 'post_title', 'post_content', 'post_excerpt' ) ),
						array(
							'post_status' => 'auto-draft', // So attachment will be garbage collected in a week if changeset is never published.
						)
					);

					$attachment_id = media_handle_sideload( $file_array, 0, null, $attachment_post_data );
					if ( is_wp_error( $attachment_id ) ) {
						continue;
					}
					update_post_meta( $attachment_id, '_starter_content_theme', $this->get_stylesheet() );
					update_post_meta( $attachment_id, '_customize_draft_post_name', $attachment['post_name'] );
				}

				$attachment_ids[ $symbol ] = $attachment_id;
			}
			$starter_content_auto_draft_post_ids = array_merge( $starter_content_auto_draft_post_ids, array_values( $attachment_ids ) );
		}

		// Posts & pages.
		if ( ! empty( $posts ) ) {
			foreach ( array_keys( $posts ) as $post_symbol ) {
				if ( empty( $posts[ $post_symbol ]['post_type'] ) || empty( $posts[ $post_symbol ]['post_name'] ) ) {
					continue;
				}
				$post_type = $posts[ $post_symbol ]['post_type'];
				if ( ! empty( $posts[ $post_symbol ]['post_name'] ) ) {
					$post_name = $posts[ $post_symbol ]['post_name'];
				} elseif ( ! empty( $posts[ $post_symbol ]['post_title'] ) ) {
					$post_name = sanitize_title( $posts[ $post_symbol ]['post_title'] );
				} else {
					continue;
				}

				// Use existing auto-draft post if one already exists with the same type and name.
				if ( isset( $existing_starter_content_posts[ $post_type . ':' . $post_name ] ) ) {
					$posts[ $post_symbol ]['ID'] = $existing_starter_content_posts[ $post_type . ':' . $post_name ]->ID;
					continue;
				}

				// Translate the featured image symbol.
				if ( ! empty( $posts[ $post_symbol ]['thumbnail'] )
					&& preg_match( '/^{{(?P<symbol>.+)}}$/', $posts[ $post_symbol ]['thumbnail'], $matches )
					&& isset( $attachment_ids[ $matches['symbol'] ] ) ) {
					$posts[ $post_symbol ]['meta_input']['_thumbnail_id'] = $attachment_ids[ $matches['symbol'] ];
				}

				if ( ! empty( $posts[ $post_symbol ]['template'] ) ) {
					$posts[ $post_symbol ]['meta_input']['_wp_page_template'] = $posts[ $post_symbol ]['template'];
				}

				$r = $this->nav_menus->insert_auto_draft_post( $posts[ $post_symbol ] );
				if ( $r instanceof WP_Post ) {
					$posts[ $post_symbol ]['ID'] = $r->ID;
				}
			}

			$starter_content_auto_draft_post_ids = array_merge( $starter_content_auto_draft_post_ids, wp_list_pluck( $posts, 'ID' ) );
		}

		// The nav_menus_created_posts setting is why nav_menus component is dependency for adding posts.
		if ( ! empty( $this->nav_menus ) && ! empty( $starter_content_auto_draft_post_ids ) ) {
			$setting_id = 'nav_menus_created_posts';
			$this->set_post_value( $setting_id, array_unique( array_values( $starter_content_auto_draft_post_ids ) ) );
			$this->pending_starter_content_settings_ids[] = $setting_id;
		}

		// Nav menus.
		$placeholder_id              = -1;
		$reused_nav_menu_setting_ids = array();
		foreach ( $nav_menus as $nav_menu_location => $nav_menu ) {

			$nav_menu_term_id    = null;
			$nav_menu_setting_id = null;
			$matches             = array();

			// Look for an existing placeholder menu with starter content to re-use.
			foreach ( $changeset_data as $setting_id => $setting_params ) {
				$can_reuse = (
					! empty( $setting_params['starter_content'] )
					&&
					! in_array( $setting_id, $reused_nav_menu_setting_ids, true )
					&&
					preg_match( '#^nav_menu\[(?P<nav_menu_id>-?\d+)\]$#', $setting_id, $matches )
				);
				if ( $can_reuse ) {
					$nav_menu_term_id              = (int) $matches['nav_menu_id'];
					$nav_menu_setting_id           = $setting_id;
					$reused_nav_menu_setting_ids[] = $setting_id;
					break;
				}
			}

			if ( ! $nav_menu_term_id ) {
				while ( isset( $changeset_data[ sprintf( 'nav_menu[%d]', $placeholder_id ) ] ) ) {
					$placeholder_id--;
				}
				$nav_menu_term_id    = $placeholder_id;
				$nav_menu_setting_id = sprintf( 'nav_menu[%d]', $placeholder_id );
			}

			$this->set_post_value(
				$nav_menu_setting_id,
				array(
					'name' => isset( $nav_menu['name'] ) ? $nav_menu['name'] : $nav_menu_location,
				)
			);
			$this->pending_starter_content_settings_ids[] = $nav_menu_setting_id;

			// @todo Add support for menu_item_parent.
			$position = 0;
			foreach ( $nav_menu['items'] as $nav_menu_item ) {
				$nav_menu_item_setting_id = sprintf( 'nav_menu_item[%d]', $placeholder_id-- );
				if ( ! isset( $nav_menu_item['position'] ) ) {
					$nav_menu_item['position'] = $position++;
				}
				$nav_menu_item['nav_menu_term_id'] = $nav_menu_term_id;

				if ( isset( $nav_menu_item['object_id'] ) ) {
					if ( 'post_type' === $nav_menu_item['type'] && preg_match( '/^{{(?P<symbol>.+)}}$/', $nav_menu_item['object_id'], $matches ) && isset( $posts[ $matches['symbol'] ] ) ) {
						$nav_menu_item['object_id'] = $posts[ $matches['symbol'] ]['ID'];
						if ( empty( $nav_menu_item['title'] ) ) {
							$original_object        = get_post( $nav_menu_item['object_id'] );
							$nav_menu_item['title'] = $original_object->post_title;
						}
					} else {
						continue;
					}
				} else {
					$nav_menu_item['object_id'] = 0;
				}

				if ( empty( $changeset_data[ $nav_menu_item_setting_id ] ) || ! empty( $changeset_data[ $nav_menu_item_setting_id ]['starter_content'] ) ) {
					$this->set_post_value( $nav_menu_item_setting_id, $nav_menu_item );
					$this->pending_starter_content_settings_ids[] = $nav_menu_item_setting_id;
				}
			}

			$setting_id = sprintf( 'nav_menu_locations[%s]', $nav_menu_location );
			if ( empty( $changeset_data[ $setting_id ] ) || ! empty( $changeset_data[ $setting_id ]['starter_content'] ) ) {
				$this->set_post_value( $setting_id, $nav_menu_term_id );
				$this->pending_starter_content_settings_ids[] = $setting_id;
			}
		}

		// Options.
		foreach ( $options as $name => $value ) {

			// Serialize the value to check for post symbols.
			$value = maybe_serialize( $value );

			if ( is_serialized( $value ) ) {
				if ( preg_match( '/s:\d+:"{{(?P<symbol>.+)}}"/', $value, $matches ) ) {
					if ( isset( $posts[ $matches['symbol'] ] ) ) {
						$symbol_match = $posts[ $matches['symbol'] ]['ID'];
					} elseif ( isset( $attachment_ids[ $matches['symbol'] ] ) ) {
						$symbol_match = $attachment_ids[ $matches['symbol'] ];
					}

					// If we have any symbol matches, update the values.
					if ( isset( $symbol_match ) ) {
						// Replace found string matches with post IDs.
						$value = str_replace( $matches[0], "i:{$symbol_match}", $value );
					} else {
						continue;
					}
				}
			} elseif ( preg_match( '/^{{(?P<symbol>.+)}}$/', $value, $matches ) ) {
				if ( isset( $posts[ $matches['symbol'] ] ) ) {
					$value = $posts[ $matches['symbol'] ]['ID'];
				} elseif ( isset( $attachment_ids[ $matches['symbol'] ] ) ) {
					$value = $attachment_ids[ $matches['symbol'] ];
				} else {
					continue;
				}
			}

			// Unserialize values after checking for post symbols, so they can be properly referenced.
			$value = maybe_unserialize( $value );

			if ( empty( $changeset_data[ $name ] ) || ! empty( $changeset_data[ $name ]['starter_content'] ) ) {
				$this->set_post_value( $name, $value );
				$this->pending_starter_content_settings_ids[] = $name;
			}
		}

		// Theme mods.
		foreach ( $theme_mods as $name => $value ) {

			// Serialize the value to check for post symbols.
			$value = maybe_serialize( $value );

			// Check if value was serialized.
			if ( is_serialized( $value ) ) {
				if ( preg_match( '/s:\d+:"{{(?P<symbol>.+)}}"/', $value, $matches ) ) {
					if ( isset( $posts[ $matches['symbol'] ] ) ) {
						$symbol_match = $posts[ $matches['symbol'] ]['ID'];
					} elseif ( isset( $attachment_ids[ $matches['symbol'] ] ) ) {
						$symbol_match = $attachment_ids[ $matches['symbol'] ];
					}

					// If we have any symbol matches, update the values.
					if ( isset( $symbol_match ) ) {
						// Replace found string matches with post IDs.
						$value = str_replace( $matches[0], "i:{$symbol_match}", $value );
					} else {
						continue;
					}
				}
			} elseif ( preg_match( '/^{{(?P<symbol>.+)}}$/', $value, $matches ) ) {
				if ( isset( $posts[ $matches['symbol'] ] ) ) {
					$value = $posts[ $matches['symbol'] ]['ID'];
				} elseif ( isset( $attachment_ids[ $matches['symbol'] ] ) ) {
					$value = $attachment_ids[ $matches['symbol'] ];
				} else {
					continue;
				}
			}

			// Unserialize values after checking for post symbols, so they can be properly referenced.
			$value = maybe_unserialize( $value );

			// Handle header image as special case since setting has a legacy format.
			if ( 'header_image' === $name ) {
				$name     = 'header_image_data';
				$metadata = wp_get_attachment_metadata( $value );
				if ( empty( $metadata ) ) {
					continue;
				}
				$value = array(
					'attachment_id' => $value,
					'url'           => wp_get_attachment_url( $value ),
					'height'        => $metadata['height'],
					'width'         => $metadata['width'],
				);
			} elseif ( 'background_image' === $name ) {
				$value = wp_get_attachment_url( $value );
			}

			if ( empty( $changeset_data[ $name ] ) || ! empty( $changeset_data[ $name ]['starter_content'] ) ) {
				$this->set_post_value( $name, $value );
				$this->pending_starter_content_settings_ids[] = $name;
			}
		}

		if ( ! empty( $this->pending_starter_content_settings_ids ) ) {
			if ( did_action( 'customize_register' ) ) {
				$this->_save_starter_content_changeset();
			} else {
				add_action( 'customize_register', array( $this, '_save_starter_content_changeset' ), 1000 );
			}
		}
	}

	/**
	 * Prepare starter content attachments.
	 *
	 * Ensure that the attachments are valid and that they have slugs and file name/path.
	 *
	 * @since 4.7.0
	 *
	 * @param array $attachments Attachments.
	 * @return array Prepared attachments.
	 */
	protected function prepare_starter_content_attachments( $attachments ) {
		$prepared_attachments = array();
		if ( empty( $attachments ) ) {
			return $prepared_attachments;
		}

		// Such is The WordPress Way.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		foreach ( $attachments as $symbol => $attachment ) {

			// A file is required and URLs to files are not currently allowed.
			if ( empty( $attachment['file'] ) || preg_match( '#^https?://$#', $attachment['file'] ) ) {
				continue;
			}

			$file_path = null;
			if ( file_exists( $attachment['file'] ) ) {
				$file_path = $attachment['file']; // Could be absolute path to file in plugin.
			} elseif ( is_child_theme() && file_exists( get_stylesheet_directory() . '/' . $attachment['file'] ) ) {
				$file_path = get_stylesheet_directory() . '/' . $attachment['file'];
			} elseif ( file_exists( get_template_directory() . '/' . $attachment['file'] ) ) {
				$file_path = get_template_directory() . '/' . $attachment['file'];
			} else {
				continue;
			}
			$file_name = wp_basename( $attachment['file'] );

			// Skip file types that are not recognized.
			$checked_filetype = wp_check_filetype( $file_name );
			if ( empty( $checked_filetype['type'] ) ) {
				continue;
			}

			// Ensure post_name is set since not automatically derived from post_title for new auto-draft posts.
			if ( empty( $attachment['post_name'] ) ) {
				if ( ! empty( $attachment['post_title'] ) ) {
					$attachment['post_name'] = sanitize_title( $attachment['post_title'] );
				} else {
					$attachment['post_name'] = sanitize_title( preg_replace( '/\.\w+$/', '', $file_name ) );
				}
			}

			$attachment['file_name']         = $file_name;
			$attachment['file_path']         = $file_path;
			$prepared_attachments[ $symbol ] = $attachment;
		}
		return $prepared_attachments;
	}

	/**
	 * Save starter content changeset.
	 *
	 * @since 4.7.0
	 */
	public function _save_starter_content_changeset() {

		if ( empty( $this->pending_starter_content_settings_ids ) ) {
			return;
		}

		$this->save_changeset_post(
			array(
				'data'            => array_fill_keys( $this->pending_starter_content_settings_ids, array( 'starter_content' => true ) ),
				'starter_content' => true,
			)
		);
		$this->saved_starter_content_changeset = true;

		$this->pending_starter_content_settings_ids = array();
	}

	/**
	 * Get dirty pre-sanitized setting values in the current customized state.
	 *
	 * The returned array consists of a merge of three sources:
	 * 1. If the theme is not currently active, then the base array is any stashed
	 *    theme mods that were modified previously but never published.
	 * 2. The values from the current changeset, if it exists.
	 * 3. If the user can customize, the values parsed from the incoming
	 *    `$_POST['customized']` JSON data.
	 * 4. Any programmatically-set post values via `WP_Customize_Manager::set_post_value()`.
	 *
	 * The name "unsanitized_post_values" is a carry-over from when the customized
	 * state was exclusively sourced from `$_POST['customized']`. Nevertheless,
	 * the value returned will come from the current changeset post and from the
	 * incoming post data.
	 *
	 * @since 4.1.1
	 * @since 4.7.0 Added `$args` parameter and merging with changeset values and stashed theme mods.
	 *
	 * @param array $args {
	 *     Args.
	 *
	 *     @type bool $exclude_changeset Whether the changeset values should also be excluded. Defaults to false.
	 *     @type bool $exclude_post_data Whether the post input values should also be excluded. Defaults to false when lacking the customize capability.
	 * }
	 * @return array
	 */
	public function unsanitized_post_values( $args = array() ) {
		$args = array_merge(
			array(
				'exclude_changeset' => false,
				'exclude_post_data' => ! current_user_can( 'customize' ),
			),
			$args
		);

		$values = array();

		// Let default values be from the stashed theme mods if doing a theme switch and if no changeset is present.
		if ( ! $this->is_theme_active() ) {
			$stashed_theme_mods = get_option( 'customize_stashed_theme_mods' );
			$stylesheet         = $this->get_stylesheet();
			if ( isset( $stashed_theme_mods[ $stylesheet ] ) ) {
				$values = array_merge( $values, wp_list_pluck( $stashed_theme_mods[ $stylesheet ], 'value' ) );
			}
		}

		if ( ! $args['exclude_changeset'] ) {
			foreach ( $this->changeset_data() as $setting_id => $setting_params ) {
				if ( ! array_key_exists( 'value', $setting_params ) ) {
					continue;
				}
				if ( isset( $setting_params['type'] ) && 'theme_mod' === $setting_params['type'] ) {

					// Ensure that theme mods values are only used if they were saved under the current theme.
					$namespace_pattern = '/^(?P<stylesheet>.+?)::(?P<setting_id>.+)$/';
					if ( preg_match( $namespace_pattern, $setting_id, $matches ) && $this->get_stylesheet() === $matches['stylesheet'] ) {
						$values[ $matches['setting_id'] ] = $setting_params['value'];
					}
				} else {
					$values[ $setting_id ] = $setting_params['value'];
				}
			}
		}

		if ( ! $args['exclude_post_data'] ) {
			if ( ! isset( $this->_post_values ) ) {
				if ( isset( $_POST['customized'] ) ) {
					$post_values = json_decode( wp_unslash( $_POST['customized'] ), true );
				} else {
					$post_values = array();
				}
				if ( is_array( $post_values ) ) {
					$this->_post_values = $post_values;
				} else {
					$this->_post_values = array();
				}
			}
			$values = array_merge( $values, $this->_post_values );
		}
		return $values;
	}

	/**
	 * Returns the sanitized value for a given setting from the current customized state.
	 *
	 * The name "post_value" is a carry-over from when the customized state was exclusively
	 * sourced from `$_POST['customized']`. Nevertheless, the value returned will come
	 * from the current changeset post and from the incoming post data.
	 *
	 * @since 3.4.0
	 * @since 4.1.1 Introduced the `$default` parameter.
	 * @since 4.6.0 `$default` is now returned early when the setting post value is invalid.
	 *
	 * @see WP_REST_Server::dispatch()
	 * @see WP_REST_Request::sanitize_params()
	 * @see WP_REST_Request::has_valid_params()
	 *
	 * @param WP_Customize_Setting $setting A WP_Customize_Setting derived object.
	 * @param mixed                $default Value returned $setting has no post value (added in 4.2.0)
	 *                                      or the post value is invalid (added in 4.6.0).
	 * @return string|mixed Sanitized value or the $default provided.
	 */
	public function post_value( $setting, $default = null ) {
		$post_values = $this->unsanitized_post_values();
		if ( ! array_key_exists( $setting->id, $post_values ) ) {
			return $default;
		}
		$value = $post_values[ $setting->id ];
		$valid = $setting->validate( $value );
		if ( is_wp_error( $valid ) ) {
			return $default;
		}
		$value = $setting->sanitize( $value );
		if ( is_null( $value ) || is_wp_error( $value ) ) {
			return $default;
		}
		return $value;
	}

	/**
	 * Override a setting's value in the current customized state.
	 *
	 * The name "post_value" is a carry-over from when the customized state was
	 * exclusively sourced from `$_POST['customized']`.
	 *
	 * @since 4.2.0
	 *
	 * @param string $setting_id ID for the WP_Customize_Setting instance.
	 * @param mixed  $value      Post value.
	 */
	public function set_post_value( $setting_id, $value ) {
		$this->unsanitized_post_values(); // Populate _post_values from $_POST['customized'].
		$this->_post_values[ $setting_id ] = $value;

		/**
		 * Announce when a specific setting's unsanitized post value has been set.
		 *
		 * Fires when the WP_Customize_Manager::set_post_value() method is called.
		 *
		 * The dynamic portion of the hook name, `$setting_id`, refers to the setting ID.
		 *
		 * @since 4.4.0
		 *
		 * @param mixed                $value   Unsanitized setting post value.
		 * @param WP_Customize_Manager $manager WP_Customize_Manager instance.
		 */
		do_action( "customize_post_value_set_{$setting_id}", $value, $this );

		/**
		 * Announce when any setting's unsanitized post value has been set.
		 *
		 * Fires when the WP_Customize_Manager::set_post_value() method is called.
		 *
		 * This is useful for `WP_Customize_Setting` instances to watch
		 * in order to update a cached previewed value.
		 *
		 * @since 4.4.0
		 *
		 * @param string               $setting_id Setting ID.
		 * @param mixed                $value      Unsanitized setting post value.
		 * @param WP_Customize_Manager $manager    WP_Customize_Manager instance.
		 */
		do_action( 'customize_post_value_set', $setting_id, $value, $this );
	}

	/**
	 * Print JavaScript settings.
	 *
	 * @since 3.4.0
	 */
	public function customize_preview_init() {

		/*
		 * Now that Customizer previews are loaded into iframes via GET requests
		 * and natural URLs with transaction UUIDs added, we need to ensure that
		 * the responses are never cached by proxies. In practice, this will not
		 * be needed if the user is logged-in anyway. But if anonymous access is
		 * allowed then the auth cookies would not be sent and WordPress would
		 * not send no-cache headers by default.
		 */
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'X-Robots: noindex, nofollow, noarchive' );
		}
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_filter( 'wp_headers', array( $this, 'filter_iframe_security_headers' ) );

		/*
		 * If preview is being served inside the customizer preview iframe, and
		 * if the user doesn't have customize capability, then it is assumed
		 * that the user's session has expired and they need to re-authenticate.
		 */
		if ( $this->messenger_channel && ! current_user_can( 'customize' ) ) {
			$this->wp_die(
				-1,
				sprintf(
					/* translators: %s: customize_messenger_channel */
					__( 'Unauthorized. You may remove the %s param to preview as frontend.' ),
					'<code>customize_messenger_channel<code>'
				)
			);
			return;
		}

		$this->prepare_controls();

		add_filter( 'wp_redirect', array( $this, 'add_state_query_params' ) );

		wp_enqueue_script( 'customize-preview' );
		wp_enqueue_style( 'customize-preview' );
		add_action( 'wp_head', array( $this, 'customize_preview_loading_style' ) );
		add_action( 'wp_head', array( $this, 'remove_frameless_preview_messenger_channel' ) );
		add_action( 'wp_footer', array( $this, 'customize_preview_settings' ), 20 );
		add_filter( 'get_edit_post_link', '__return_empty_string' );

		/**
		 * Fires once the Customizer preview has initialized and JavaScript
		 * settings have been printed.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $manager WP_Customize_Manager instance.
		 */
		do_action( 'customize_preview_init', $this );
	}

	/**
	 * Filters the X-Frame-Options and Content-Security-Policy headers to ensure frontend can load in customizer.
	 *
	 * @since 4.7.0
	 *
	 * @param array $headers Headers.
	 * @return array Headers.
	 */
	public function filter_iframe_security_headers( $headers ) {
		$headers['X-Frame-Options']         = 'SAMEORIGIN';
		$headers['Content-Security-Policy'] = "frame-ancestors 'self'";
		return $headers;
	}

	/**
	 * Add customize state query params to a given URL if preview is allowed.
	 *
	 * @since 4.7.0
	 *
	 * @see wp_redirect()
	 * @see WP_Customize_Manager::get_allowed_url()
	 *
	 * @param string $url URL.
	 * @return string URL.
	 */
	public function add_state_query_params( $url ) {
		$parsed_original_url = wp_parse_url( $url );
		$is_allowed          = false;
		foreach ( $this->get_allowed_urls() as $allowed_url ) {
			$parsed_allowed_url = wp_parse_url( $allowed_url );
			$is_allowed         = (
				$parsed_allowed_url['scheme'] === $parsed_original_url['scheme']
				&&
				$parsed_allowed_url['host'] === $parsed_original_url['host']
				&&
				0 === strpos( $parsed_original_url['path'], $parsed_allowed_url['path'] )
			);
			if ( $is_allowed ) {
				break;
			}
		}

		if ( $is_allowed ) {
			$query_params = array(
				'customize_changeset_uuid' => $this->changeset_uuid(),
			);
			if ( ! $this->is_theme_active() ) {
				$query_params['customize_theme'] = $this->get_stylesheet();
			}
			if ( $this->messenger_channel ) {
				$query_params['customize_messenger_channel'] = $this->messenger_channel;
			}
			$url = add_query_arg( $query_params, $url );
		}

		return $url;
	}

	/**
	 * Prevent sending a 404 status when returning the response for the customize
	 * preview, since it causes the jQuery Ajax to fail. Send 200 instead.
	 *
	 * @since 4.0.0
	 * @deprecated 4.7.0
	 */
	public function customize_preview_override_404_status() {
		_deprecated_function( __METHOD__, '4.7.0' );
	}

	/**
	 * Print base element for preview frame.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0
	 */
	public function customize_preview_base() {
		_deprecated_function( __METHOD__, '4.7.0' );
	}

	/**
	 * Print a workaround to handle HTML5 tags in IE < 9.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0 Customizer no longer supports IE8, so all supported browsers recognize HTML5.
	 */
	public function customize_preview_html5() {
		_deprecated_function( __FUNCTION__, '4.7.0' );
	}

	/**
	 * Print CSS for loading indicators for the Customizer preview.
	 *
	 * @since 4.2.0
	 */
	public function customize_preview_loading_style() {
		?>
		<style>
			body.wp-customizer-unloading {
				opacity: 0.25;
				cursor: progress !important;
				-webkit-transition: opacity 0.5s;
				transition: opacity 0.5s;
			}
			body.wp-customizer-unloading * {
				pointer-events: none !important;
			}
			form.customize-unpreviewable,
			form.customize-unpreviewable input,
			form.customize-unpreviewable select,
			form.customize-unpreviewable button,
			a.customize-unpreviewable,
			area.customize-unpreviewable {
				cursor: not-allowed !important;
			}
		</style>
		<?php
	}

	/**
	 * Remove customize_messenger_channel query parameter from the preview window when it is not in an iframe.
	 *
	 * This ensures that the admin bar will be shown. It also ensures that link navigation will
	 * work as expected since the parent frame is not being sent the URL to navigate to.
	 *
	 * @since 4.7.0
	 */
	public function remove_frameless_preview_messenger_channel() {
		if ( ! $this->messenger_channel ) {
			return;
		}
		?>
		<script>
		( function() {
			var urlParser, oldQueryParams, newQueryParams, i;
			if ( parent !== window ) {
				return;
			}
			urlParser = document.createElement( 'a' );
			urlParser.href = location.href;
			oldQueryParams = urlParser.search.substr( 1 ).split( /&/ );
			newQueryParams = [];
			for ( i = 0; i < oldQueryParams.length; i += 1 ) {
				if ( ! /^customize_messenger_channel=/.test( oldQueryParams[ i ] ) ) {
					newQueryParams.push( oldQueryParams[ i ] );
				}
			}
			urlParser.search = newQueryParams.join( '&' );
			if ( urlParser.search !== location.search ) {
				location.replace( urlParser.href );
			}
		} )();
		</script>
		<?php
	}

	/**
	 * Print JavaScript settings for preview frame.
	 *
	 * @since 3.4.0
	 */
	public function customize_preview_settings() {
		$post_values                 = $this->unsanitized_post_values( array( 'exclude_changeset' => true ) );
		$setting_validities          = $this->validate_setting_values( $post_values );
		$exported_setting_validities = array_map( array( $this, 'prepare_setting_validity_for_js' ), $setting_validities );

		// Note that the REQUEST_URI is not passed into home_url() since this breaks subdirectory installations.
		$self_url           = empty( $_SERVER['REQUEST_URI'] ) ? home_url( '/' ) : esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$state_query_params = array(
			'customize_theme',
			'customize_changeset_uuid',
			'customize_messenger_channel',
		);
		$self_url           = remove_query_arg( $state_query_params, $self_url );

		$allowed_urls  = $this->get_allowed_urls();
		$allowed_hosts = array();
		foreach ( $allowed_urls as $allowed_url ) {
			$parsed = wp_parse_url( $allowed_url );
			if ( empty( $parsed['host'] ) ) {
				continue;
			}
			$host = $parsed['host'];
			if ( ! empty( $parsed['port'] ) ) {
				$host .= ':' . $parsed['port'];
			}
			$allowed_hosts[] = $host;
		}

		$switched_locale = switch_to_locale( get_user_locale() );
		$l10n            = array(
			'shiftClickToEdit'  => __( 'Shift-click to edit this element.' ),
			'linkUnpreviewable' => __( 'This link is not live-previewable.' ),
			'formUnpreviewable' => __( 'This form is not live-previewable.' ),
		);
		if ( $switched_locale ) {
			restore_previous_locale();
		}

		$settings = array(
			'changeset'         => array(
				'uuid'      => $this->changeset_uuid(),
				'autosaved' => $this->autosaved(),
			),
			'timeouts'          => array(
				'selectiveRefresh' => 250,
				'keepAliveSend'    => 1000,
			),
			'theme'             => array(
				'stylesheet' => $this->get_stylesheet(),
				'active'     => $this->is_theme_active(),
			),
			'url'               => array(
				'self'          => $self_url,
				'allowed'       => array_map( 'esc_url_raw', $this->get_allowed_urls() ),
				'allowedHosts'  => array_unique( $allowed_hosts ),
				'isCrossDomain' => $this->is_cross_domain(),
			),
			'channel'           => $this->messenger_channel,
			'activePanels'      => array(),
			'activeSections'    => array(),
			'activeControls'    => array(),
			'settingValidities' => $exported_setting_validities,
			'nonce'             => current_user_can( 'customize' ) ? $this->get_nonces() : array(),
			'l10n'              => $l10n,
			'_dirty'            => array_keys( $post_values ),
		);

		foreach ( $this->panels as $panel_id => $panel ) {
			if ( $panel->check_capabilities() ) {
				$settings['activePanels'][ $panel_id ] = $panel->active();
				foreach ( $panel->sections as $section_id => $section ) {
					if ( $section->check_capabilities() ) {
						$settings['activeSections'][ $section_id ] = $section->active();
					}
				}
			}
		}
		foreach ( $this->sections as $id => $section ) {
			if ( $section->check_capabilities() ) {
				$settings['activeSections'][ $id ] = $section->active();
			}
		}
		foreach ( $this->controls as $id => $control ) {
			if ( $control->check_capabilities() ) {
				$settings['activeControls'][ $id ] = $control->active();
			}
		}

		?>
		<script type="text/javascript">
			var _wpCustomizeSettings = <?php echo wp_json_encode( $settings ); ?>;
			_wpCustomizeSettings.values = {};
			(function( v ) {
				<?php
				/*
				 * Serialize settings separately from the initial _wpCustomizeSettings
				 * serialization in order to avoid a peak memory usage spike.
				 * @todo We may not even need to export the values at all since the pane syncs them anyway.
				 */
				foreach ( $this->settings as $id => $setting ) {
					if ( $setting->check_capabilities() ) {
						printf(
							"v[%s] = %s;\n",
							wp_json_encode( $id ),
							wp_json_encode( $setting->js_value() )
						);
					}
				}
				?>
			})( _wpCustomizeSettings.values );
		</script>
		<?php
	}

	/**
	 * Prints a signature so we can ensure the Customizer was properly executed.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0
	 */
	public function customize_preview_signature() {
		_deprecated_function( __METHOD__, '4.7.0' );
	}

	/**
	 * Removes the signature in case we experience a case where the Customizer was not properly executed.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0
	 *
	 * @param mixed $return Value passed through for {@see 'wp_die_handler'} filter.
	 * @return mixed Value passed through for {@see 'wp_die_handler'} filter.
	 */
	public function remove_preview_signature( $return = null ) {
		_deprecated_function( __METHOD__, '4.7.0' );

		return $return;
	}

	/**
	 * Is it a theme preview?
	 *
	 * @since 3.4.0
	 *
	 * @return bool True if it's a preview, false if not.
	 */
	public function is_preview() {
		return (bool) $this->previewing;
	}

	/**
	 * Retrieve the template name of the previewed theme.
	 *
	 * @since 3.4.0
	 *
	 * @return string Template name.
	 */
	public function get_template() {
		return $this->theme()->get_template();
	}

	/**
	 * Retrieve the stylesheet name of the previewed theme.
	 *
	 * @since 3.4.0
	 *
	 * @return string Stylesheet name.
	 */
	public function get_stylesheet() {
		return $this->theme()->get_stylesheet();
	}

	/**
	 * Retrieve the template root of the previewed theme.
	 *
	 * @since 3.4.0
	 *
	 * @return string Theme root.
	 */
	public function get_template_root() {
		return get_raw_theme_root( $this->get_template(), true );
	}

	/**
	 * Retrieve the stylesheet root of the previewed theme.
	 *
	 * @since 3.4.0
	 *
	 * @return string Theme root.
	 */
	public function get_stylesheet_root() {
		return get_raw_theme_root( $this->get_stylesheet(), true );
	}

	/**
	 * Filters the current theme and return the name of the previewed theme.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $current_theme {@internal Parameter is not used}
	 * @return string Theme name.
	 */
	public function current_theme( $current_theme ) {
		return $this->theme()->display( 'Name' );
	}

	/**
	 * Validates setting values.
	 *
	 * Validation is skipped for unregistered settings or for values that are
	 * already null since they will be skipped anyway. Sanitization is applied
	 * to values that pass validation, and values that become null or `WP_Error`
	 * after sanitizing are marked invalid.
	 *
	 * @since 4.6.0
	 *
	 * @see WP_REST_Request::has_valid_params()
	 * @see WP_Customize_Setting::validate()
	 *
	 * @param array $setting_values Mapping of setting IDs to values to validate and sanitize.
	 * @param array $options {
	 *     Options.
	 *
	 *     @type bool $validate_existence  Whether a setting's existence will be checked.
	 *     @type bool $validate_capability Whether the setting capability will be checked.
	 * }
	 * @return array Mapping of setting IDs to return value of validate method calls, either `true` or `WP_Error`.
	 */
	public function validate_setting_values( $setting_values, $options = array() ) {
		$options = wp_parse_args(
			$options,
			array(
				'validate_capability' => false,
				'validate_existence'  => false,
			)
		);

		$validities = array();
		foreach ( $setting_values as $setting_id => $unsanitized_value ) {
			$setting = $this->get_setting( $setting_id );
			if ( ! $setting ) {
				if ( $options['validate_existence'] ) {
					$validities[ $setting_id ] = new WP_Error( 'unrecognized', __( 'Setting does not exist or is unrecognized.' ) );
				}
				continue;
			}
			if ( $options['validate_capability'] && ! current_user_can( $setting->capability ) ) {
				$validity = new WP_Error( 'unauthorized', __( 'Unauthorized to modify setting due to capability.' ) );
			} else {
				if ( is_null( $unsanitized_value ) ) {
					continue;
				}
				$validity = $setting->validate( $unsanitized_value );
			}
			if ( ! is_wp_error( $validity ) ) {
				/** This filter is documented in wp-includes/class-wp-customize-setting.php */
				$late_validity = apply_filters( "customize_validate_{$setting->id}", new WP_Error(), $unsanitized_value, $setting );
				if ( is_wp_error( $late_validity ) && $late_validity->has_errors() ) {
					$validity = $late_validity;
				}
			}
			if ( ! is_wp_error( $validity ) ) {
				$value = $setting->sanitize( $unsanitized_value );
				if ( is_null( $value ) ) {
					$validity = false;
				} elseif ( is_wp_error( $value ) ) {
					$validity = $value;
				}
			}
			if ( false === $validity ) {
				$validity = new WP_Error( 'invalid_value', __( 'Invalid value.' ) );
			}
			$validities[ $setting_id ] = $validity;
		}
		return $validities;
	}

	/**
	 * Prepares setting validity for exporting to the client (JS).
	 *
	 * Converts `WP_Error` instance into array suitable for passing into the
	 * `wp.customize.Notification` JS model.
	 *
	 * @since 4.6.0
	 *
	 * @param true|WP_Error $validity Setting validity.
	 * @return true|array If `$validity` was a WP_Error, the error codes will be array-mapped
	 *                    to their respective `message` and `data` to pass into the
	 *                    `wp.customize.Notification` JS model.
	 */
	public function prepare_setting_validity_for_js( $validity ) {
		if ( is_wp_error( $validity ) ) {
			$notification = array();
			foreach ( $validity->errors as $error_code => $error_messages ) {
				$notification[ $error_code ] = array(
					'message' => implode( ' ', $error_messages ),
					'data'    => $validity->get_error_data( $error_code ),
				);
			}
			return $notification;
		} else {
			return true;
		}
	}

	/**
	 * Handle customize_save WP Ajax request to save/update a changeset.
	 *
	 * @since 3.4.0
	 * @since 4.7.0 The semantics of this method have changed to update a changeset, optionally to also change the status and other attributes.
	 */
	public function save() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'unauthenticated' );
		}

		if ( ! $this->is_preview() ) {
			wp_send_json_error( 'not_preview' );
		}

		$action = 'save-customize_' . $this->get_stylesheet();
		if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
			wp_send_json_error( 'invalid_nonce' );
		}

		$changeset_post_id = $this->changeset_post_id();
		$is_new_changeset  = empty( $changeset_post_id );
		if ( $is_new_changeset ) {
			if ( ! current_user_can( get_post_type_object( 'customize_changeset' )->cap->create_posts ) ) {
				wp_send_json_error( 'cannot_create_changeset_post' );
			}
		} else {
			if ( ! current_user_can( get_post_type_object( 'customize_changeset' )->cap->edit_post, $changeset_post_id ) ) {
				wp_send_json_error( 'cannot_edit_changeset_post' );
			}
		}

		if ( ! empty( $_POST['customize_changeset_data'] ) ) {
			$input_changeset_data = json_decode( wp_unslash( $_POST['customize_changeset_data'] ), true );
			if ( ! is_array( $input_changeset_data ) ) {
				wp_send_json_error( 'invalid_customize_changeset_data' );
			}
		} else {
			$input_changeset_data = array();
		}

		// Validate title.
		$changeset_title = null;
		if ( isset( $_POST['customize_changeset_title'] ) ) {
			$changeset_title = sanitize_text_field( wp_unslash( $_POST['customize_changeset_title'] ) );
		}

		// Validate changeset status param.
		$is_publish       = null;
		$changeset_status = null;
		if ( isset( $_POST['customize_changeset_status'] ) ) {
			$changeset_status = wp_unslash( $_POST['customize_changeset_status'] );
			if ( ! get_post_status_object( $changeset_status ) || ! in_array( $changeset_status, array( 'draft', 'pending', 'publish', 'future' ), true ) ) {
				wp_send_json_error( 'bad_customize_changeset_status', 400 );
			}
			$is_publish = ( 'publish' === $changeset_status || 'future' === $changeset_status );
			if ( $is_publish && ! current_user_can( get_post_type_object( 'customize_changeset' )->cap->publish_posts ) ) {
				wp_send_json_error( 'changeset_publish_unauthorized', 403 );
			}
		}

		/*
		 * Validate changeset date param. Date is assumed to be in local time for
		 * the WP if in MySQL format (YYYY-MM-DD HH:MM:SS). Otherwise, the date
		 * is parsed with strtotime() so that ISO date format may be supplied
		 * or a string like "+10 minutes".
		 */
		$changeset_date_gmt = null;
		if ( isset( $_POST['customize_changeset_date'] ) ) {
			$changeset_date = wp_unslash( $_POST['customize_changeset_date'] );
			if ( preg_match( '/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d$/', $changeset_date ) ) {
				$mm         = substr( $changeset_date, 5, 2 );
				$jj         = substr( $changeset_date, 8, 2 );
				$aa         = substr( $changeset_date, 0, 4 );
				$valid_date = wp_checkdate( $mm, $jj, $aa, $changeset_date );
				if ( ! $valid_date ) {
					wp_send_json_error( 'bad_customize_changeset_date', 400 );
				}
				$changeset_date_gmt = get_gmt_from_date( $changeset_date );
			} else {
				$timestamp = strtotime( $changeset_date );
				if ( ! $timestamp ) {
					wp_send_json_error( 'bad_customize_changeset_date', 400 );
				}
				$changeset_date_gmt = gmdate( 'Y-m-d H:i:s', $timestamp );
			}
		}

		$lock_user_id = null;
		$autosave     = ! empty( $_POST['customize_changeset_autosave'] );
		if ( ! $is_new_changeset ) {
			$lock_user_id = wp_check_post_lock( $this->changeset_post_id() );
		}

		// Force request to autosave when changeset is locked.
		if ( $lock_user_id && ! $autosave ) {
			$autosave           = true;
			$changeset_status   = null;
			$changeset_date_gmt = null;
		}

		if ( $autosave && ! defined( 'DOING_AUTOSAVE' ) ) { // Back-compat.
			define( 'DOING_AUTOSAVE', true );
		}

		$autosaved = false;
		$r         = $this->save_changeset_post(
			array(
				'status'   => $changeset_status,
				'title'    => $changeset_title,
				'date_gmt' => $changeset_date_gmt,
				'data'     => $input_changeset_data,
				'autosave' => $autosave,
			)
		);
		if ( $autosave && ! is_wp_error( $r ) ) {
			$autosaved = true;
		}

		// If the changeset was locked and an autosave request wasn't itself an error, then now explicitly return with a failure.
		if ( $lock_user_id && ! is_wp_error( $r ) ) {
			$r = new WP_Error(
				'changeset_locked',
				__( 'Changeset is being edited by other user.' ),
				array(
					'lock_user' => $this->get_lock_user_data( $lock_user_id ),
				)
			);
		}

		if ( is_wp_error( $r ) ) {
			$response = array(
				'message' => $r->get_error_message(),
				'code'    => $r->get_error_code(),
			);
			if ( is_array( $r->get_error_data() ) ) {
				$response = array_merge( $response, $r->get_error_data() );
			} else {
				$response['data'] = $r->get_error_data();
			}
		} else {
			$response       = $r;
			$changeset_post = get_post( $this->changeset_post_id() );

			// Dismiss all other auto-draft changeset posts for this user (they serve like autosave revisions), as there should only be one.
			if ( $is_new_changeset ) {
				$this->dismiss_user_auto_draft_changesets();
			}

			// Note that if the changeset status was publish, then it will get set to Trash if revisions are not supported.
			$response['changeset_status'] = $changeset_post->post_status;
			if ( $is_publish && 'trash' === $response['changeset_status'] ) {
				$response['changeset_status'] = 'publish';
			}

			if ( 'publish' !== $response['changeset_status'] ) {
				$this->set_changeset_lock( $changeset_post->ID );
			}

			if ( 'future' === $response['changeset_status'] ) {
				$response['changeset_date'] = $changeset_post->post_date;
			}

			if ( 'publish' === $response['changeset_status'] || 'trash' === $response['changeset_status'] ) {
				$response['next_changeset_uuid'] = wp_generate_uuid4();
			}
		}

		if ( $autosave ) {
			$response['autosaved'] = $autosaved;
		}

		if ( isset( $response['setting_validities'] ) ) {
			$response['setting_validities'] = array_map( array( $this, 'prepare_setting_validity_for_js' ), $response['setting_validities'] );
		}

		/**
		 * Filters response data for a successful customize_save Ajax request.
		 *
		 * This filter does not apply if there was a nonce or authentication failure.
		 *
		 * @since 4.2.0
		 *
		 * @param array                $response Additional information passed back to the 'saved'
		 *                                       event on `wp.customize`.
		 * @param WP_Customize_Manager $manager  WP_Customize_Manager instance.
		 */
		$response = apply_filters( 'customize_save_response', $response, $this );

		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $response );
		} else {
			wp_send_json_success( $response );
		}
	}

	/**
	 * Save the post for the loaded changeset.
	 *
	 * @since 4.7.0
	 *
	 * @param array $args {
	 *     Args for changeset post.
	 *
	 *     @type array  $data            Optional additional changeset data. Values will be merged on top of any existing post values.
	 *     @type string $status          Post status. Optional. If supplied, the save will be transactional and a post revision will be allowed.
	 *     @type string $title           Post title. Optional.
	 *     @type string $date_gmt        Date in GMT. Optional.
	 *     @type int    $user_id         ID for user who is saving the changeset. Optional, defaults to the current user ID.
	 *     @type bool   $starter_content Whether the data is starter content. If false (default), then $starter_content will be cleared for any $data being saved.
	 *     @type bool   $autosave        Whether this is a request to create an autosave revision.
	 * }
	 *
	 * @return array|WP_Error Returns array on success and WP_Error with array data on error.
	 */
	public function save_changeset_post( $args = array() ) {

		$args = array_merge(
			array(
				'status'          => null,
				'title'           => null,
				'data'            => array(),
				'date_gmt'        => null,
				'user_id'         => get_current_user_id(),
				'starter_content' => false,
				'autosave'        => false,
			),
			$args
		);

		$changeset_post_id       = $this->changeset_post_id();
		$existing_changeset_data = array();
		if ( $changeset_post_id ) {
			$existing_status = get_post_status( $changeset_post_id );
			if ( 'publish' === $existing_status || 'trash' === $existing_status ) {
				return new WP_Error(
					'changeset_already_published',
					__( 'The previous set of changes has already been published. Please try saving your current set of changes again.' ),
					array(
						'next_changeset_uuid' => wp_generate_uuid4(),
					)
				);
			}

			$existing_changeset_data = $this->get_changeset_post_data( $changeset_post_id );
			if ( is_wp_error( $existing_changeset_data ) ) {
				return $existing_changeset_data;
			}
		}

		// Fail if attempting to publish but publish hook is missing.
		if ( 'publish' === $args['status'] && false === has_action( 'transition_post_status', '_wp_customize_publish_changeset' ) ) {
			return new WP_Error( 'missing_publish_callback' );
		}

		// Validate date.
		$now = gmdate( 'Y-m-d H:i:59' );
		if ( $args['date_gmt'] ) {
			$is_future_dated = ( mysql2date( 'U', $args['date_gmt'], false ) > mysql2date( 'U', $now, false ) );
			if ( ! $is_future_dated ) {
				return new WP_Error( 'not_future_date', __( 'You must supply a future date to schedule.' ) ); // Only future dates are allowed.
			}

			if ( ! $this->is_theme_active() && ( 'future' === $args['status'] || $is_future_dated ) ) {
				return new WP_Error( 'cannot_schedule_theme_switches' ); // This should be allowed in the future, when theme is a regular setting.
			}
			$will_remain_auto_draft = ( ! $args['status'] && ( ! $changeset_post_id || 'auto-draft' === get_post_status( $changeset_post_id ) ) );
			if ( $will_remain_auto_draft ) {
				return new WP_Error( 'cannot_supply_date_for_auto_draft_changeset' );
			}
		} elseif ( $changeset_post_id && 'future' === $args['status'] ) {

			// Fail if the new status is future but the existing post's date is not in the future.
			$changeset_post = get_post( $changeset_post_id );
			if ( mysql2date( 'U', $changeset_post->post_date_gmt, false ) <= mysql2date( 'U', $now, false ) ) {
				return new WP_Error( 'not_future_date', __( 'You must supply a future date to schedule.' ) );
			}
		}

		if ( ! empty( $is_future_dated ) && 'publish' === $args['status'] ) {
			$args['status'] = 'future';
		}

		// Validate autosave param. See _wp_post_revision_fields() for why these fields are disallowed.
		if ( $args['autosave'] ) {
			if ( $args['date_gmt'] ) {
				return new WP_Error( 'illegal_autosave_with_date_gmt' );
			} elseif ( $args['status'] ) {
				return new WP_Error( 'illegal_autosave_with_status' );
			} elseif ( $args['user_id'] && get_current_user_id() !== $args['user_id'] ) {
				return new WP_Error( 'illegal_autosave_with_non_current_user' );
			}
		}

		// The request was made via wp.customize.previewer.save().
		$update_transactionally = (bool) $args['status'];
		$allow_revision         = (bool) $args['status'];

		// Amend post values with any supplied data.
		foreach ( $args['data'] as $setting_id => $setting_params ) {
			if ( is_array( $setting_params ) && array_key_exists( 'value', $setting_params ) ) {
				$this->set_post_value( $setting_id, $setting_params['value'] ); // Add to post values so that they can be validated and sanitized.
			}
		}

		// Note that in addition to post data, this will include any stashed theme mods.
		$post_values = $this->unsanitized_post_values(
			array(
				'exclude_changeset' => true,
				'exclude_post_data' => false,
			)
		);
		$this->add_dynamic_settings( array_keys( $post_values ) ); // Ensure settings get created even if they lack an input value.

		/*
		 * Get list of IDs for settings that have values different from what is currently
		 * saved in the changeset. By skipping any values that are already the same, the
		 * subset of changed settings can be passed into validate_setting_values to prevent
		 * an underprivileged modifying a single setting for which they have the capability
		 * from being blocked from saving. This also prevents a user from touching of the
		 * previous saved settings and overriding the associated user_id if they made no change.
		 */
		$changed_setting_ids = array();
		foreach ( $post_values as $setting_id => $setting_value ) {
			$setting = $this->get_setting( $setting_id );

			if ( $setting && 'theme_mod' === $setting->type ) {
				$prefixed_setting_id = $this->get_stylesheet() . '::' . $setting->id;
			} else {
				$prefixed_setting_id = $setting_id;
			}

			$is_value_changed = (
				! isset( $existing_changeset_data[ $prefixed_setting_id ] )
				||
				! array_key_exists( 'value', $existing_changeset_data[ $prefixed_setting_id ] )
				||
				$existing_changeset_data[ $prefixed_setting_id ]['value'] !== $setting_value
			);
			if ( $is_value_changed ) {
				$changed_setting_ids[] = $setting_id;
			}
		}

		/**
		 * Fires before save validation happens.
		 *
		 * Plugins can add just-in-time {@see 'customize_validate_{$this->ID}'} filters
		 * at this point to catch any settings registered after `customize_register`.
		 * The dynamic portion of the hook name, `$this->ID` refers to the setting ID.
		 *
		 * @since 4.6.0
		 *
		 * @param WP_Customize_Manager $manager WP_Customize_Manager instance.
		 */
		do_action( 'customize_save_validation_before', $this );

		// Validate settings.
		$validated_values      = array_merge(
			array_fill_keys( array_keys( $args['data'] ), null ), // Make sure existence/capability checks are done on value-less setting updates.
			$post_values
		);
		$setting_validities    = $this->validate_setting_values(
			$validated_values,
			array(
				'validate_capability' => true,
				'validate_existence'  => true,
			)
		);
		$invalid_setting_count = count( array_filter( $setting_validities, 'is_wp_error' ) );

		/*
		 * Short-circuit if there are invalid settings the update is transactional.
		 * A changeset update is transactional when a status is supplied in the request.
		 */
		if ( $update_transactionally && $invalid_setting_count > 0 ) {
			$response = array(
				'setting_validities' => $setting_validities,
				/* translators: %s: Number of invalid settings. */
				'message'            => sprintf( _n( 'Unable to save due to %s invalid setting.', 'Unable to save due to %s invalid settings.', $invalid_setting_count ), number_format_i18n( $invalid_setting_count ) ),
			);
			return new WP_Error( 'transaction_fail', '', $response );
		}

		// Obtain/merge data for changeset.
		$original_changeset_data = $this->get_changeset_post_data( $changeset_post_id );
		$data                    = $original_changeset_data;
		if ( is_wp_error( $data ) ) {
			$data = array();
		}

		// Ensure that all post values are included in the changeset data.
		foreach ( $post_values as $setting_id => $post_value ) {
			if ( ! isset( $args['data'][ $setting_id ] ) ) {
				$args['data'][ $setting_id ] = array();
			}
			if ( ! isset( $args['data'][ $setting_id ]['value'] ) ) {
				$args['data'][ $setting_id ]['value'] = $post_value;
			}
		}

		foreach ( $args['data'] as $setting_id => $setting_params ) {
			$setting = $this->get_setting( $setting_id );
			if ( ! $setting || ! $setting->check_capabilities() ) {
				continue;
			}

			// Skip updating changeset for invalid setting values.
			if ( isset( $setting_validities[ $setting_id ] ) && is_wp_error( $setting_validities[ $setting_id ] ) ) {
				continue;
			}

			$changeset_setting_id = $setting_id;
			if ( 'theme_mod' === $setting->type ) {
				$changeset_setting_id = sprintf( '%s::%s', $this->get_stylesheet(), $setting_id );
			}

			if ( null === $setting_params ) {
				// Remove setting from changeset entirely.
				unset( $data[ $changeset_setting_id ] );
			} else {

				if ( ! isset( $data[ $changeset_setting_id ] ) ) {
					$data[ $changeset_setting_id ] = array();
				}

				// Merge any additional setting params that have been supplied with the existing params.
				$merged_setting_params = array_merge( $data[ $changeset_setting_id ], $setting_params );

				// Skip updating setting params if unchanged (ensuring the user_id is not overwritten).
				if ( $data[ $changeset_setting_id ] === $merged_setting_params ) {
					continue;
				}

				$data[ $changeset_setting_id ] = array_merge(
					$merged_setting_params,
					array(
						'type'              => $setting->type,
						'user_id'           => $args['user_id'],
						'date_modified_gmt' => current_time( 'mysql', true ),
					)
				);

				// Clear starter_content flag in data if changeset is not explicitly being updated for starter content.
				if ( empty( $args['starter_content'] ) ) {
					unset( $data[ $changeset_setting_id ]['starter_content'] );
				}
			}
		}

		$filter_context = array(
			'uuid'          => $this->changeset_uuid(),
			'title'         => $args['title'],
			'status'        => $args['status'],
			'date_gmt'      => $args['date_gmt'],
			'post_id'       => $changeset_post_id,
			'previous_data' => is_wp_error( $original_changeset_data ) ? array() : $original_changeset_data,
			'manager'       => $this,
		);

		/**
		 * Filters the settings' data that will be persisted into the changeset.
		 *
		 * Plugins may amend additional data (such as additional meta for settings) into the changeset with this filter.
		 *
		 * @since 4.7.0
		 *
		 * @param array $data Updated changeset data, mapping setting IDs to arrays containing a $value item and optionally other metadata.
		 * @param array $context {
		 *     Filter context.
		 *
		 *     @type string               $uuid          Changeset UUID.
		 *     @type string               $title         Requested title for the changeset post.
		 *     @type string               $status        Requested status for the changeset post.
		 *     @type string               $date_gmt      Requested date for the changeset post in MySQL format and GMT timezone.
		 *     @type int|false            $post_id       Post ID for the changeset, or false if it doesn't exist yet.
		 *     @type array                $previous_data Previous data contained in the changeset.
		 *     @type WP_Customize_Manager $manager       Manager instance.
		 * }
		 */
		$data = apply_filters( 'customize_changeset_save_data', $data, $filter_context );

		// Switch theme if publishing changes now.
		if ( 'publish' === $args['status'] && ! $this->is_theme_active() ) {
			// Temporarily stop previewing the theme to allow switch_themes() to operate properly.
			$this->stop_previewing_theme();
			switch_theme( $this->get_stylesheet() );
			update_option( 'theme_switched_via_customizer', true );
			$this->start_previewing_theme();
		}

		// Gather the data for wp_insert_post()/wp_update_post().
		$post_array = array(
			// JSON_UNESCAPED_SLASHES is only to improve readability as slashes needn't be escaped in storage.
			'post_content' => wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
		);
		if ( $args['title'] ) {
			$post_array['post_title'] = $args['title'];
		}
		if ( $changeset_post_id ) {
			$post_array['ID'] = $changeset_post_id;
		} else {
			$post_array['post_type']   = 'customize_changeset';
			$post_array['post_name']   = $this->changeset_uuid();
			$post_array['post_status'] = 'auto-draft';
		}
		if ( $args['status'] ) {
			$post_array['post_status'] = $args['status'];
		}

		// Reset post date to now if we are publishing, otherwise pass post_date_gmt and translate for post_date.
		if ( 'publish' === $args['status'] ) {
			$post_array['post_date_gmt'] = '0000-00-00 00:00:00';
			$post_array['post_date']     = '0000-00-00 00:00:00';
		} elseif ( $args['date_gmt'] ) {
			$post_array['post_date_gmt'] = $args['date_gmt'];
			$post_array['post_date']     = get_date_from_gmt( $args['date_gmt'] );
		} elseif ( $changeset_post_id && 'auto-draft' === get_post_status( $changeset_post_id ) ) {
			/*
			 * Keep bumping the date for the auto-draft whenever it is modified;
			 * this extends its life, preserving it from garbage-collection via
			 * wp_delete_auto_drafts().
			 */
			$post_array['post_date']     = current_time( 'mysql' );
			$post_array['post_date_gmt'] = '';
		}

		$this->store_changeset_revision = $allow_revision;
		add_filter( 'wp_save_post_revision_post_has_changed', array( $this, '_filter_revision_post_has_changed' ), 5, 3 );

		/*
		 * Update the changeset post. The publish_customize_changeset action will cause the settings in the
		 * changeset to be saved via WP_Customize_Setting::save(). Updating a post with publish status will
		 * trigger WP_Customize_Manager::publish_changeset_values().
		 */
		add_filter( 'wp_insert_post_data', array( $this, 'preserve_insert_changeset_post_content' ), 5, 3 );
		if ( $changeset_post_id ) {
			if ( $args['autosave'] && 'auto-draft' !== get_post_status( $changeset_post_id ) ) {
				// See _wp_translate_postdata() for why this is required as it will use the edit_post meta capability.
				add_filter( 'map_meta_cap', array( $this, 'grant_edit_post_capability_for_changeset' ), 10, 4 );

				$post_array['post_ID']   = $post_array['ID'];
				$post_array['post_type'] = 'customize_changeset';

				$r = wp_create_post_autosave( wp_slash( $post_array ) );

				remove_filter( 'map_meta_cap', array( $this, 'grant_edit_post_capability_for_changeset' ), 10 );
			} else {
				$post_array['edit_date'] = true; // Prevent date clearing.

				$r = wp_update_post( wp_slash( $post_array ), true );

				// Delete autosave revision for user when the changeset is updated.
				if ( ! empty( $args['user_id'] ) ) {
					$autosave_draft = wp_get_post_autosave( $changeset_post_id, $args['user_id'] );
					if ( $autosave_draft ) {
						wp_delete_post( $autosave_draft->ID, true );
					}
				}
			}
		} else {
			$r = wp_insert_post( wp_slash( $post_array ), true );
			if ( ! is_wp_error( $r ) ) {
				$this->_changeset_post_id = $r; // Update cached post ID for the loaded changeset.
			}
		}
		remove_filter( 'wp_insert_post_data', array( $this, 'preserve_insert_changeset_post_content' ), 5 );

		$this->_changeset_data = null; // Reset so WP_Customize_Manager::changeset_data() will re-populate with updated contents.

		remove_filter( 'wp_save_post_revision_post_has_changed', array( $this, '_filter_revision_post_has_changed' ) );

		$response = array(
			'setting_validities' => $setting_validities,
		);

		if ( is_wp_error( $r ) ) {
			$response['changeset_post_save_failure'] = $r->get_error_code();
			return new WP_Error( 'changeset_post_save_failure', '', $response );
		}

		return $response;
	}

	/**
	 * Preserve the initial JSON post_content passed to save into the post.
	 *
	 * This is needed to prevent KSES and other {@see 'content_save_pre'} filters
	 * from corrupting JSON data.
	 *
	 * Note that WP_Customize_Manager::validate_setting_values() have already
	 * run on the setting values being serialized as JSON into the post content
	 * so it is pre-sanitized.
	 *
	 * Also, the sanitization logic is re-run through the respective
	 * WP_Customize_Setting::sanitize() method when being read out of the
	 * changeset, via WP_Customize_Manager::post_value(), and this sanitized
	 * value will also be sent into WP_Customize_Setting::update() for
	 * persisting to the DB.
	 *
	 * Multiple users can collaborate on a single changeset, where one user may
	 * have the unfiltered_html capability but another may not. A user with
	 * unfiltered_html may add a script tag to some field which needs to be kept
	 * intact even when another user updates the changeset to modify another field
	 * when they do not have unfiltered_html.
	 *
	 * @since 5.4.1
	 *
	 * @param array $data                An array of slashed and processed post data.
	 * @param array $postarr             An array of sanitized (and slashed) but otherwise unmodified post data.
	 * @param array $unsanitized_postarr An array of slashed yet *unsanitized* and unprocessed post data as originally passed to wp_insert_post().
	 * @return array Filtered post data.
	 */
	public function preserve_insert_changeset_post_content( $data, $postarr, $unsanitized_postarr ) {
		if (
			isset( $data['post_type'] ) &&
			isset( $unsanitized_postarr['post_content'] ) &&
			'customize_changeset' === $data['post_type'] ||
			(
				'revision' === $data['post_type'] &&
				! empty( $data['post_parent'] ) &&
				'customize_changeset' === get_post_type( $data['post_parent'] )
			)
		) {
			$data['post_content'] = $unsanitized_postarr['post_content'];
		}
		return $data;
	}

	/**
	 * Trash or delete a changeset post.
	 *
	 * The following re-formulates the logic from `wp_trash_post()` as done in
	 * `wp_publish_post()`. The reason for bypassing `wp_trash_post()` is that it
	 * will mutate the the `post_content` and the `post_name` when they should be
	 * untouched.
	 *
	 * @since 4.9.0
	 *
	 * @see wp_trash_post()
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int|WP_Post $post The changeset post.
	 * @return mixed A WP_Post object for the trashed post or an empty value on failure.
	 */
	public function trash_changeset_post( $post ) {
		global $wpdb;

		$post = get_post( $post );

		if ( ! ( $post instanceof WP_Post ) ) {
			return $post;
		}
		$post_id = $post->ID;

		if ( ! EMPTY_TRASH_DAYS ) {
			return wp_delete_post( $post_id, true );
		}

		if ( 'trash' === get_post_status( $post ) ) {
			return false;
		}

		/** This filter is documented in wp-includes/post.php */
		$check = apply_filters( 'pre_trash_post', null, $post );
		if ( null !== $check ) {
			return $check;
		}

		/** This action is documented in wp-includes/post.php */
		do_action( 'wp_trash_post', $post_id );

		add_post_meta( $post_id, '_wp_trash_meta_status', $post->post_status );
		add_post_meta( $post_id, '_wp_trash_meta_time', time() );

		$old_status = $post->post_status;
		$new_status = 'trash';
		$wpdb->update( $wpdb->posts, array( 'post_status' => $new_status ), array( 'ID' => $post->ID ) );
		clean_post_cache( $post->ID );

		$post->post_status = $new_status;
		wp_transition_post_status( $new_status, $old_status, $post );

		/** This action is documented in wp-includes/post.php */
		do_action( "edit_post_{$post->post_type}", $post->ID, $post );

		/** This action is documented in wp-includes/post.php */
		do_action( 'edit_post', $post->ID, $post );

		/** This action is documented in wp-includes/post.php */
		do_action( "save_post_{$post->post_type}", $post->ID, $post, true );

		/** This action is documented in wp-includes/post.php */
		do_action( 'save_post', $post->ID, $post, true );

		/** This action is documented in wp-includes/post.php */
		do_action( 'wp_insert_post', $post->ID, $post, true );

		wp_after_insert_post( get_post( $post_id ), true, $post );

		wp_trash_post_comments( $post_id );

		/** This action is documented in wp-includes/post.php */
		do_action( 'trashed_post', $post_id );

		return $post;
	}

	/**
	 * Handle request to trash a changeset.
	 *
	 * @since 4.9.0
	 */
	public function handle_changeset_trash_request() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'unauthenticated' );
		}

		if ( ! $this->is_preview() ) {
			wp_send_json_error( 'not_preview' );
		}

		if ( ! check_ajax_referer( 'trash_customize_changeset', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_nonce',
					'message' => __( 'There was an authentication problem. Please reload and try again.' ),
				)
			);
		}

		$changeset_post_id = $this->changeset_post_id();

		if ( ! $changeset_post_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'No changes saved yet, so there is nothing to trash.' ),
					'code'    => 'non_existent_changeset',
				)
			);
			return;
		}

		if ( $changeset_post_id ) {
			if ( ! current_user_can( get_post_type_object( 'customize_changeset' )->cap->delete_post, $changeset_post_id ) ) {
				wp_send_json_error(
					array(
						'code'    => 'changeset_trash_unauthorized',
						'message' => __( 'Unable to trash changes.' ),
					)
				);
			}

			$lock_user = (int) wp_check_post_lock( $changeset_post_id );

			if ( $lock_user && get_current_user_id() !== $lock_user ) {
				wp_send_json_error(
					array(
						'code'     => 'changeset_locked',
						'message'  => __( 'Changeset is being edited by other user.' ),
						'lockUser' => $this->get_lock_user_data( $lock_user ),
					)
				);
			}
		}

		if ( 'trash' === get_post_status( $changeset_post_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Changes have already been trashed.' ),
					'code'    => 'changeset_already_trashed',
				)
			);
			return;
		}

		$r = $this->trash_changeset_post( $chan