<?php
/**
 * WPMovieLibrary Movie Class extension.
 * 
 * Add and manage a Movie Custom Post Type
 *
 * @package   WPMovieLibrary
 * @author    Charlie MERLAND <charlie@caercam.org>
 * @license   GPL-3.0
 * @link      http://www.caercam.org/
 * @copyright 2014 CaerCam.org
 */

if ( ! class_exists( 'WPMOLY_Movies' ) ) :

	class WPMOLY_Movies extends WPMOLY_Module {

		/**
		 * Constructor
		 *
		 * @since    1.0
		 */
		public function __construct() {
			$this->register_hook_callbacks();
		}

		/**
		 * Magic!
		 * 
		 * @since    2.0
		 * 
		 * @param    string    $name Called method name
		 * @param    array     $arguments Called method arguments
		 * 
		 * @return   mixed    Callback function return value
		 */
		public static function __callStatic( $name, $arguments ) {

			if ( false !== strpos( $name, 'get_movies_by_' ) ) {
				$name = str_replace( 'get_movies_by_', '', $name );
				array_unshift( $arguments, $name );
				return call_user_func_array( __CLASS__ . '::get_movies_by_meta', $arguments );
			}
		}

		/**
		 * Register callbacks for actions and filters
		 * 
		 * @since    1.0
		 */
		public function register_hook_callbacks() {

			add_action( 'init', __CLASS__ . '::register_post_type', 10 );

			// Load Movies as well as Posts in the Loop
			add_action( 'pre_get_posts', __CLASS__ . '::show_movies_in_home_page', 10, 1 );

			if ( '1' == wpmoly_o( 'search' ) ) {
				add_filter( 'pre_get_posts', __CLASS__ . '::filter_search_query', 11, 1 );
				add_filter( 'get_search_query', __CLASS__ . '::get_search_query', 11, 1 );
			}

			add_filter( 'pre_get_posts', __CLASS__ . '::filter_archives_query', 11, 1 );

			// Movie content
			add_filter( 'the_content', __CLASS__ . '::movie_content' );
			add_filter( 'get_the_excerpt', __CLASS__ . '::movie_excerpt' );

			// Pass meta through URLs
			add_action( 'pre_get_posts', __CLASS__ . '::movies_query_meta', 10, 1 );
			add_filter( 'query_vars', __CLASS__ . '::movies_query_vars', 10, 1 );

			// TODO: is that useful anyway?
			add_filter( 'wpmoly_get_movies_from_media', __CLASS__ . '::get_movies_from_media', 10, 1 );
			add_filter( 'wpmoly_get_movies_from_status', __CLASS__ . '::get_movies_from_status', 10, 1 );
			//add_filter( 'posts_request', __CLASS__ . '::posts_request', 10, 2 );
		}

		/**
		 * Debug
		 *
		 * @since    2.0
		 */
		public static function posts_request( $request, $wp_query ) {
			var_dump( $request );
			return $request;
		}

		/**
		 * Register a 'movie' custom post type and 'import-draft' post status
		 *
		 * @since    1.0
		 */
		public static function register_post_type() {

			$labels = array(
				'name'               => __( 'Movies', 'wpmovielibrary' ),
				'singular_name'      => __( 'Movie', 'wpmovielibrary' ),
				'add_new'            => __( 'Add New', 'wpmovielibrary' ),
				'add_new_item'       => __( 'Add New Movie', 'wpmovielibrary' ),
				'edit_item'          => __( 'Edit Movie', 'wpmovielibrary' ),
				'new_item'           => __( 'New Movie', 'wpmovielibrary' ),
				'all_items'          => __( 'All Movies', 'wpmovielibrary' ),
				'view_item'          => __( 'View Movie', 'wpmovielibrary' ),
				'search_items'       => __( 'Search Movies', 'wpmovielibrary' ),
				'not_found'          => __( 'No movies found', 'wpmovielibrary' ),
				'not_found_in_trash' => __( 'No movies found in Trash', 'wpmovielibrary' ),
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Movies', 'wpmovielibrary' )
			);

			$slug = wpmoly_o( 'rewrite-movie' );
			$slug = ( '' != $slug ? $slug : 'movies' );

			$args = array(
				'labels'             => $labels,
				'rewrite'            => array(
					'slug'       => $slug
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'has_archive'        => true,
				'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'comments' ),
				'menu_position'      => 5
			);

			$args['taxonomies'] = array();
			if ( wpmoly_o( 'enable-categories' ) )
				$args['taxonomies'][] = 'category';
			if ( wpmoly_o( 'enable-tags' ) )
				$args['taxonomies'][] = 'post_tag';

			register_post_type( 'movie', $args );

			register_post_status( 'import-draft', array(
				'label'                     => _x( 'Imported Draft', 'wpmovielibrary' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
				'label_count'               => _n_noop( 'Imported Draft <span class="count">(%s)</span>', 'Imported Draft <span class="count">(%s)</span>' ),
			) );

			register_post_status( 'import-queued', array(
				'label'                     => _x( 'Queued Movie', 'wpmovielibrary' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
				'label_count'               => _n_noop( 'Queued Movie <span class="count">(%s)</span>', 'Queued Movies <span class="count">(%s)</span>' ),
			) );

		}

		/**
		 * Return various Movie's Post Meta. Possible meta: status, media, rating
		 * and data.
		 *
		 * @since    1.0
		 * 
		 * @param    string    Meta type to return: data, status, media or rating
		 * @param    int       Movie Post ID
		 *
		 * @return   array|string    WPMOLY Movie Meta if available, empty string else.
		 */
		public static function get_movie_meta( $post_id = null, $meta = null ) {

			if ( is_null( $post_id ) )
				$post_id =  get_the_ID();

			if ( ! $post = get_post( $post_id ) || 'movie' != get_post_type( $post_id ) )
				return false;

			if ( is_admin() && 'data' == $meta && wpmoly_has_deprecated_meta( $post_id ) && wpmoly_o( 'legacy-mode' ) )
				WPMOLY_Legacy::update_movie( $post_id );

			if ( 'data' == $meta ) {
				$_meta = WPMOLY_Settings::get_supported_movie_meta();
				$value = array();

				$value['tmdb_id'] = get_post_meta( $post_id, "_wpmoly_movie_tmdb_id", true );
				$value['poster'] = get_post_meta( $post_id, "_wpmoly_movie_poster", true );

				foreach ( array_keys( $_meta ) as $slug )
					$value[ $slug ] = get_post_meta( $post_id, "_wpmoly_movie_{$slug}", true );

				return $value;
			}

			$value = get_post_meta( $post_id, "_wpmoly_movie_{$meta}", true );
			if ( 'rating' == $meta )
				$value = number_format( floatval( $value ), 1 );

			return $value;
		}
 
		/**
		 * Show movies in default home page post list.
		 * 
		 * Add action on pre_get_posts hook to add movie to the list of
		 * queryable post_types.
		 *
		 * @since    1.0
		 * 
		 * @param    int       $query the WP_Query Object object to alter
		 *
		 * @return   WP_Query    Query Object
		 */
		public static function show_movies_in_home_page( $query ) {

			if ( ! is_home() && ! is_search() && ! is_archive() )
				return $query;

			$post_type = array( 'post', 'movie' );
			$post_status = array( 'publish', 'private' );

			if ( 1 == wpmoly_o( 'frontpage' ) && is_home() && $query->is_main_query() ) {

				if ( '' != $query->get( 'post_type' ) )
					$post_type = array_unique( array_merge( $query->get( 'post_type' ), $post_type ) );

				if ( '' != $query->get( 'post_status' ) )
					$post_status = array_unique( array_merge( $query->get( 'post_status' ), $post_status ) );

				$query->set( 'post_type', $post_type );
				$query->set( 'post_status', $post_status );
			}

			return $query;
		}

		/**
		 * Show some info about movies in post view.
		 * 
		 * Add a filter on the_content hook to display infos selected in options
		 * about the movie: note, director, overview, actors…
		 *
		 * @since    1.0
		 * 
		 * @param    string      $content The original post content
		 *
		 * @return   string      The filtered content containing original content plus movie infos if available, the untouched original content else.
		 */
		public static function movie_content( $content ) {

			if ( 'movie' != get_post_type() )
				return $content;

			if ( ! wpmoly_o( 'vintage-content' ) )
				$headbox = WPMOLY_Headbox::get_content( $content );
			else
				$headbox = self::movie_vintage_content( $content );

			return $headbox . $content;
		}

		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                       Vintage Movie Content
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		/**
		 * Show some info about movies in post view.
		 * 
		 * Add a filter on the_content hook to display infos selected in options
		 * about the movie: note, director, overview, actors…
		 *
		 * @since    1.0
		 * 
		 * @param    string      $content The original post content
		 *
		 * @return   string      The filtered content containing original content plus movie infos if available, the untouched original content else.
		 */
		public static function movie_vintage_content( $content ) {

			// Caching
			$name = apply_filters( 'wpmoly_cache_name', 'movie_content_' . get_the_ID() );
			$html = WPMOLY_Cache::output( $name, function() use ( $content ) {

				// Naughty PHP 5.3 fix
				$details  = WPMOLY_Movies::movie_details();
				$metadata = WPMOLY_Movies::movie_metadata();

				$html = $details . $metadata;

				return $html;

			}, $echo = false );

			return $html;
		}

		/**
		 * Replace movies mxcerpt by movies overview if available.
		 *
		 * @since    2.0
		 * 
		 * @param    string      $excerpt The original post excerpt
		 *
		 * @return   string      The filtered excerpt containing the movie's overview if any, original excerpt else.
		 */
		public static function movie_excerpt( $excerpt ) {

			if ( 'movie' != get_post_type() )
				return $excerpt;

			if ( ! wpmoly_o( 'excerpt' ) )
				return $excerpt;

			$overview = wpmoly_get_movie_meta( get_the_ID(), 'overview' );
			if ( '' == $overview )
				return $excerpt;

			$excerpt_length = wpmoly_o( 'excerpt-length' );
			if ( ! $excerpt_length )
				$excerpt_length = apply_filters( 'excerpt_length', 55 );
			$excerpt_more   = apply_filters( 'excerpt_more', ' ' . '[&hellip;]' );
			$overview       = wp_trim_words( $overview, $excerpt_length, $excerpt_more );

			return $overview;
		}

		/**
		 * Generate current movie's details list.
		 *
		 * @since    1.0
		 *
		 * @return   null|string    The current movie's metadata list
		 */
		public static function movie_details() {

			if ( 'nowhere' == wpmoly_o( 'show-details' ) || ( 'posts_only' == wpmoly_o( 'show-details' ) && ! is_singular() ) )
				return null;

			$fields = wpmoly_o( 'sort-details' );
			if ( empty( $fields ) || ! isset( $fields['used'] ) )
				return null;

			$fields = $fields['used'];
			if ( isset( $fields['placebo'] ) )
				unset( $fields['placebo'] );
			$post_id = get_the_ID();

			$items = array();

			foreach ( $fields as $slug => $field ) {
				$detail = call_user_func_array( 'wpmoly_get_movie_meta', array( 'post_id' => $post_id, 'meta' => $slug ) );
				$items[] = apply_filters( "wpmoly_format_movie_{$slug}", $detail );
			}

			$html = WPMovieLibrary::render_template( 'movies/movie-details.php', array( 'items' => $items ), $require = 'always' );

			return $html;
		}

		/**
		 * Generate current movie's metadata list.
		 *
		 * @since    1.0
		 *
		 * @return   null|string    The current movie's metadata list
		 */
		public static function movie_metadata() {

			if ( 'nowhere' == wpmoly_o( 'show-meta' ) || ( 'posts_only' == wpmoly_o( 'show-meta' ) && ! is_singular() ) )
				return null;

			$metadata = wpmoly_get_movie_meta();
			$metadata = wpmoly_filter_undimension_array( $metadata );

			$fields = wpmoly_o( 'sort-meta' );
			$default_fields = WPMOLY_Settings::get_supported_movie_meta();

			if ( '' == $metadata || empty( $fields ) || ! isset( $fields['used'] ) )
				return null;

			$fields = $fields['used'];
			if ( isset( $fields['placebo'] ) )
				unset( $fields['placebo'] );
			$items = array();

			foreach ( $fields as $slug => $field ) {

				$_field = $metadata[ $slug ];

				// Custom filter if available
				if ( has_filter( "wpmoly_format_movie_{$slug}" ) )
					$_field = apply_filters( "wpmoly_format_movie_{$slug}", $_field );

				// Filter empty field
				$_field = apply_filters( "wpmoly_format_movie_field", $_field );

				$fields[ $slug ] = $_field;
				$items[] = array( 'slug' => $slug, 'title' => __( $default_fields[ $slug ]['title'], 'wpmovielibrary' ), 'value' => $_field );
			}

			$html = WPMovieLibrary::render_template( 'movies/movie-metadata.php', array( 'items' => $items ), $require = 'always' );

			return $html;
		}

		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                              Queries
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		/**
		 * Add support for Movie Details to the current WP_Query.
		 * 
		 * If current WP_Query has a WPMOLY meta var set, edit the query to
		 * return the movies matching the wanted detail.
		 *
		 * @since    1.0
		 * 
		 * @param    object      $wp_query Current WP_Query instance
		 */
		public static function movies_query_meta( $wp_query ) {

			if ( is_admin() )
				return false;

			if ( isset( $wp_query->query_vars['meta'] ) ) {
				$meta = 'meta';
				$meta_key = $wp_query->query_vars['meta'];
			}
			else if ( isset( $wp_query->query_vars['detail'] ) ) {
				$meta = 'detail';
				$meta_key = $wp_query->query_vars['detail'];
			}
			else
				return false;

			$l10n_rewrite = WPMOLY_L10n::get_l10n_rewrite();
			$meta_value   = strtolower( $wp_query->query_vars['value'] );
			$meta_key     = apply_filters( 'wpmoly_filter_rewrites',$meta_key );
			$meta_key     = array_search( $meta_key, $l10n_rewrite[ $meta ] );

			// If meta_key does not exist, trigger a 404 error
			if ( ! $meta_key ) {
				$wp_query->set( 'post__in', array( -1 ) );
				return false;
			}

			// Languages and countries meta are special
			if ( 'spoken_languages' == $meta_key && 'meta' == $meta )
				$meta = 'languages';
			else if ( 'production_countries' == $meta_key && 'meta' == $meta )
				$meta = 'countries';

			$value = array_search( $meta_value, $l10n_rewrite[ $meta ] );
			if ( ! $value )
				$value = array_search( remove_accents( rawurldecode( $meta_value ) ), $l10n_rewrite[ $meta ] );

			if ( false != $value )
				$meta_value = $value;

			// Year is just a part of release date but can be useful
			if ( 'year' == $meta_key ) {
				$key   = '_wpmoly_movie_release_date';
				$value = $meta_value;
			}
			else if ( 'rating' == $meta_key ) {
				$key   = '_wpmoly_movie_rating';
				$value = number_format( $meta_value, 1, '.', '');
			}
			else {
				$key   = "_wpmoly_movie_{$meta_key}";
				$value = $meta_value;
			}

			$compare = 'LIKE';
			if ( in_array( $meta_key, array( 'status', 'runtime' ) ) )
				$compare = '=';

			$wp_query->set( 'meta_query', array(
					'relation' => 'OR',
					array(
						'key'     => $key,
						'value'   => $value,
						'compare' => $compare
					),
					array(
						'key'     => $key,
						'value'   => str_replace( '-', ' ', $value ),
						'compare' => $compare
					)
				)
			);
		}

		/**
		 * Add Movie Details slugs to queryable vars
		 * 
		 * @since    1.0
		 * 
		 * @param    array     Current WP_Query instance's queryable vars
		 * 
		 * @return   array     Updated WP_Query instance
		 */
		public static function movies_query_vars( $q_var ) {
			$q_var[] = 'detail';
			$q_var[] = 'meta';
			$q_var[] = 'value';
			$q_var[] = 'letter';
			$q_var[] = 'number';
			$q_var[] = 'columns';
			$q_var[] = '_page';
			return $q_var;
		}

		/**
		 * Filter search query to add support for movies
		 * 
		 * If query is a search, find the IDs of all posts having meta
		 * containing the search as well as posts having it in content
		 * or title. 
		 * 
		 * @since    2.0
		 * 
		 * @param    object    $wp_query WP_Query object
		 * 
		 * @return   object    $wp_query WP_Query object
		 */
		public static function filter_search_query( $wp_query ) {

			if ( is_admin() || ! is_search() || ! isset( $wp_query->query['s'] ) )
				return $wp_query;

			global $wpdb;

			$like = $wp_query->query['s'];
			$like = wpmoly_esc_like( $like );
			$like = '%' . str_replace( ' ', '%', $like ) . '%';
			$query = $wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
				  WHERE meta_key LIKE '%s'
				    AND meta_value LIKE '%s'
				 UNION
				 SELECT DISTINCT ID FROM {$wpdb->posts}
				  WHERE ( post_title LIKE '%s' OR post_content LIKE '%s' )
				    AND post_status='publish'
				    AND post_type IN ('post', 'page', 'movie')",
				'%_wpmoly_%', $like, $like, $like
			);

			$results = $wpdb->get_results( $query );
			if ( ! $wpdb->num_rows )
				return $wp_query;

			$ids = array();
			foreach ( $results as $result )
				$ids[] = $result->post_id;

			unset( $wp_query->query );
			$wp_query->set( 'wpmoly_search', $wp_query->get( 's' ) );
			$wp_query->set( 's', null );
			$wp_query->set( 'post_type', array( 'post', 'page', 'movie' ) );
			$wp_query->set( 'post__in', $ids );

			return $wp_query;
		}

		/**
		 * Replace empty search query by the plugin filtered search query.
		 * 
		 * @since    2.1
		 * 
		 * @param    string    $s Search query
		 * 
		 * @return   string    $s WPMOLY Search query
		 */
		public static function get_search_query( $s ) {

			if ( is_admin() || ! is_search() || '' != $s )
				return $s;

			global $wp_query;

			$search = $wp_query->get( 'wpmoly_search' );
			if( '' != $search );
				$s = $search;

			return $s;
		}

		/**
		 * Add movie post_type to archives WP_Query
		 * 
		 * @since    2.1
		 * 
		 * @param    object    $wp_query WP_Query object
		 * 
		 * @return   object    $wp_query WP_Query object
		 */
		public static function filter_archives_query( $wp_query ) {

			if ( ! empty( $query->query_vars['suppress_filters'] ) )
				return $wp_query;

			if ( ( ! is_category() || ( is_category() && '0' == wpmoly_o( 'enable-categories' ) ) )&& ( ! is_tag() || ( is_tag() && '0' == wpmoly_o( 'enable-tags' ) ) ) )
				return $wp_query;

			$post_types = $wp_query->get( 'post_type' );

			if ( '' == $post_types )
				$post_types = array( 'post', 'movie' );
			else if ( is_array( $post_types ) )
				$post_types = array_merge( $post_types, array( 'post', 'movie' ) );
			else
				$post_types = array_merge( (array) $post_types, array( 'post', 'movie' ) );

			$wp_query->set( 'post_type', $post_types );

			return $wp_query;
		}

		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                            General Methods
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		/**
		 * Retrieve a specific Movie. Alias for get_post().
		 * 
		 * @since    2.1
		 * 
		 * @param    int|WP_Post    $post Optional. Post ID or post object. Defaults to global $post.
		 * @param    string         $output Optional, default is Object. Accepts OBJECT, ARRAY_A, or ARRAY_N. Default OBJECT.
		 * @param    string         $filter Optional. Type of filter to apply. Accepts 'raw', 'edit', 'db', or 'display'. Default 'raw'.
		 * 
		 * @return   WP_Post|null    WP_Post on success or null on failure.
		 */
		public static function get_movie( $post = null, $output = OBJECT, $filter = 'raw' ) {

			return get_post( $post = null, $output = OBJECT, $filter = 'raw' );
		}

		/**
		 * Retrieve a list of specific Movie
		 * 
		 * @since    2.1
		 * 
		 * @param    string       $movie_title Page title
		 * @param    string       $output Optional. Output type. OBJECT, ARRAY_N, or ARRAY_A. Default OBJECT.
		 * 
		 * @return   WP_Post|null WP_Post on success or null on failure
		 */
		public static function get_movie_by_title( $movie_title, $output = OBJECT ) {

			return get_page_by_title( $movie_title, $output = OBJECT, $post_type = 'movie' );
		}

		/**
		 * Retrieve a list of Movies based on media
		 * 
		 * @since    2.1
		 * 
		 * @param    array    $args Arguments to retrieve movies
		 * 
		 * @return   array    Array of Post objects
		 */
		public static function get_movies( $args = null ) {

			$defaults = array(
				'number'      => 5,
				'offset'      => 0,
				'collection'  => '',
				'genre'       => null,
				'actor'       => null,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'include'     => array(),
				'exclude'     => array(),
				'media'       => null,
				'status'      => null,
				'rating'      => null,
				'language'    => null,
				'subtitles'   => null,
				'format'      => null,
				'meta'        => null,
				'meta_value'  => null,
				'post_status' => 'publish'
			);

			$r = wp_parse_args( $args, $defaults );
			$meta_query = array();

			if ( ! empty( $r['numberposts'] ) && empty( $r['posts_per_page'] ) )
				$r['posts_per_page'] = $r['numberposts'];
			if ( ! empty($r['include']) )
				$r['post__in'] = wp_parse_id_list( $r['include'] );
			if ( ! empty( $r['exclude'] ) )
				$r['post__not_in'] = wp_parse_id_list( $r['exclude'] );

			if ( ! is_null( $r['meta_value'] ) ) {

				$meta    = array_keys( WPMOLY_Settings::get_supported_movie_meta() );
				$details = array_keys( WPMOLY_Settings::get_supported_movie_details() );

				foreach ( $details as $detail )
					if ( ! is_null( $r[ $detail ] ) )
						$meta_query[] = array( 'key' => "_wpmoly_movie_$detail", 'value' => $r['meta_value'], 'compare' => 'LIKE' );

				if ( ! is_null( $r['meta'] ) && in_array( $r['meta'], $meta ) )
					$meta_query[] = array( 'key' => "_wpmoly_movie_{$r['meta']}", 'value' => $r['meta_value'], 'compare' => 'LIKE' );
			}

			$r['posts_per_page'] = $r['number'];
			$r['post_type']      = 'movie';
			$r['meta_query']     = $meta_query;

			unset( $r['media'], $r['status'], $r['rating'], $r['language'], $r['subtitle'], $r['format'], $r['meta'], $r['meta_value'], $r['number'] );

			$_query = new WP_Query;
			$movies  = $_query->query( $r );

			return $movies;
		}

		/**
		 * Retrieve a list of Movies based on detail
		 * 
		 * This is an alias for self::get_movies_by_meta()
		 * 
		 * @since    2.1
		 * 
		 * @param    string    $detail Detail to search upon
		 * @param    string    $value Detail value 
		 * 
		 * @return   array     Array of Post objects
		 */
		public static function get_movies_by_detail( $detail, $value ) {

			return self::get_movies_by_meta( $detail, $value );
		}

		/**
		 * Retrieve a list of Movies based on meta
		 * 
		 * @since    2.1
		 * 
		 * @param    string    $meta Meta to search upon
		 * @param    string    $value Meta value 
		 * 
		 * @return   array     Array of Post objects
		 */
		public static function get_movies_by_meta( $meta, $value ) {

			$args = array(
				'meta'       => $meta,
				'meta_value' => $value
			);

			return self::get_movies( $args );
		}

		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                              Movie Grid
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		/**
		 * Generate alphanumerical breadcrumb menu for Grid view
		 * 
		 * @since    2.0
		 * 
		 * @return   string    HTML content
		 */
		public static function get_grid_menu( $args ) {

			global $wpdb;

			$defaults = array(
				'order'    => wpmoly_o( 'movie-archives-movies-order', $default = true ),
				'columns'  => wpmoly_o( 'movie-archives-grid-columns', $default = true ),
				'number'   => wpmoly_o( 'movie-archives-movies-per-page', $default = true ),
				'editable' => wpmoly_o( 'movie-archives-frontend-edit', $default = true )
			);
			$args = wp_parse_args( $args, $defaults );

			// Allow URL params to override Shortcode settings
			if ( ! empty( $_GET ) ) {
				$vars = array(
					'number'  => get_query_var( 'number' ),
					'columns' => get_query_var( 'columns' ),
					'order'   => get_query_var( 'order' )
				);
				$args = wp_parse_args( $vars, $args );
			}
			extract( $args );

			$default = str_split( '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ' );
			$letters = array();
			$letter = get_query_var( 'letter' );
			
			$result = $wpdb->get_results( "SELECT DISTINCT LEFT(post_title, 1) as letter FROM {$wpdb->posts} WHERE post_type='movie' AND post_status='publish' ORDER BY letter" );
			foreach ( $result as $r )
				$letters[] = $r->letter;

			$letter_url  = add_query_arg( compact( 'order', 'columns', 'number' ), get_permalink() );
			$default_url = add_query_arg( compact( 'order', 'columns', 'number', 'letter' ), get_permalink() );

			$attributes = compact( 'letters', 'default', 'letter', 'order', 'number', 'columns', 'letter_url', 'default_url', 'editable' );

			$content = self::render_template( 'movies/grid/menu.php', $attributes );

			return $content;
		}

		/**
		 * Generate Movie Grid
		 * 
		 * If a current letter is passed to the query use it to narrow
		 * the list of movies.
		 * 
		 * @since    2.0
		 * 
		 * @param    array     Shortcode arguments to use as parameters
		 * 
		 * @return   string    HTML content
		 */
		public static function get_the_grid( $args = array() ) {

			global $wpdb;

			$defaults = array(
				'number'   => wpmoly_o( 'movie-archives-movies-per-page', $default = true ),
				'columns'  => wpmoly_o( 'movie-archives-grid-column', $default = true ),
				'title'    => false,
				'genre'    => false,
				'rating'   => false,
				'letter'   => '',
				'order'    => wpmoly_o( 'movie-archives-movies-order', $default = true )
			);
			$args = wp_parse_args( $args, $defaults );

			// Allow URL params to override Shortcode settings
			if ( ! empty( $_GET ) ) {
				$vars = array(
					'number'  => get_query_var( 'number' ),
					'columns' => get_query_var( 'columns' ),
					'letter'  => get_query_var( 'letter' ),
					'order'   => get_query_var( 'order' )
				);
				$args = wp_parse_args( $vars, $args );
			}

			extract( $args, EXTR_SKIP );
			$total  = 0;

			$movies = array();
			$total  = wp_count_posts( 'movie' );
			$total  = $total->publish;

			// Limit the maximum number of terms to get
			$limit = wpmoly_o( 'movie-archives-movies-limit', $default = true );
			if ( -1 == $number )
				$number = $limit;

			$number = min( $number, $limit );
			if ( ! $number )
				$number = wpmoly_o( 'movie-archives-movies-per-page', $default = true );

			// Calculate offset
			$paged = get_query_var( '_page' );
			$offset = 0;
			if ( $paged )
				$offset = max( 0, $number * ( $paged - 1 ) );

			// Don't use LIMIT with weird values
			$limit = '';
			if ( $offset < $number )
				$limit = sprintf( 'LIMIT %d,%d', $offset, $number );

			if ( '' != $letter ) {

				$result = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID
						   FROM {$wpdb->posts}
						  WHERE post_type='movie'
						    AND post_status='publish'
						    AND post_title LIKE '%s'
						  ORDER BY post_title {$order}
						  {$limit}",
						wpmoly_esc_like( $letter ) . '%'
					)
				);
				$total = count( $result );

				if ( ! empty( $result ) )
					foreach ( $result as $r )
						$movies[] = $r->ID;
			}

			$args = array(
				'posts_per_page' => $number,
				'offset'         => $offset,
				'orderby'        => 'post_title',
				'order'          => $order,
				'post_type'      => 'movie',
				'post_status'    => 'publish'
			);

			if ( ! empty( $movies ) )
				$args['post__in'] = $movies;

			$movies = get_posts( $args );

			$args = array(
				'order'   => $order,
				'columns' => $columns,
				'number'  => $number,
				'letter'  => $letter
			);
			$url = add_query_arg( $args, get_permalink() );

			$args = array(
				'type'    => 'list',
				'total'   => ceil( ( $total ) / $number ),
				'current' => max( 1, $paged ),
				'format'  => $url . '&_page=%#%',
			);

			$paginate = WPMOLY_Utils::paginate_links( $args );
			$paginate = '<div id="wpmoly-movies-pagination">' . $paginate . '</div>';

			$attributes = array(
				'movies'  => $movies,
				'columns' => $columns,
				'title'   => $title,
				'genre'   => $genre,
				'rating'  => $rating
			);

			$content  = self::render_template( 'movies/grid/loop.php', $attributes );
			$content  = $content . $paginate;

			return $content;
		}

		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                                 Utils
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		/**
		 * Handle Deactivation/Uninstallation actions.
		 * 
		 * Depending on the Plugin settings, conserve, convert, remove
		 * or delete completly all movies created while using the plugin.
		 * 
		 * @param    string    $action Are we deactivating or uninstalling
		 *                             the plugin?
		 * 
		 * @return   boolean   Did everything go smooth or not?
		 */
		public static function clean_movies( $action ) {

			if ( ! in_array( $action, array( 'deactivate', 'uninstall' ) ) )
				return false;

			$_action = get_option( 'wpmoly_settings' );
			if ( ! $_action || ! isset( $_action[ "wpmoly-{$action}-movies" ] ) )
				return false;

			$action = $_action[ "wpmoly-{$action}-movies" ];
			if ( is_array( $action ) )
				$action = $action[0];

			$contents = new WP_Query(
				array(
					'post_type'      => 'movie',
					'posts_per_page' => -1
				)
			);

			switch ( $action ) {
				case 'convert':
					foreach ( $contents->posts as $post ) {
						set_post_type( $post->ID, 'post' );
						add_post_meta( $post->ID, '_wpmoly_content_type', 'movie', true );
					}
					break;
				case 'remove':
					foreach ( $contents->posts as $post ) {
						wp_delete_post( $post->ID, true );
					}
					break;
				case 'delete':
					foreach ( $contents->posts as $post ) {
						wp_delete_post( $post->ID, true );
						$attachments = get_children( array( 'post_parent' => $post->ID ) );
						foreach ( $attachments as $a ) {
							wp_delete_post( $a->ID, true );
						}
					}
					break;
				default:
					break;
			}
		}

		/**
		 * Prepares sites to use the plugin during single or network-wide activation
		 *
		 * @since    1.0
		 *
		 * @param    bool    $network_wide
		 */
		public function activate( $network_wide ) {

			global $wpdb;

			$contents = $wpdb->get_results(
				"SELECT DISTINCT post_id
				   FROM {$wpdb->postmeta}
				  WHERE meta_key IN ('_wpml_content_type', '_wpmoly_content_type')
				    AND meta_value='movie'"
			);

			foreach ( $contents as $p ) {
				set_post_type( $p->post_id, 'movie' );
				delete_post_meta( $p->post_id, '_wpmoly_content_type', 'movie' );
				delete_post_meta( $p->post_id, '_wpml_content_type', 'movie' );
			}

			self::register_post_type();

		}

		/**
		 * Rolls back activation procedures when de-activating the plugin
		 *
		 * @since    1.0
		 */
		public function deactivate() {

			self::clean_movies( 'deactivate' );
		}

		/**
		 * Set the uninstallation instructions
		 *
		 * @since    1.0
		 */
		public static function uninstall() {

			self::clean_movies( 'uninstall' );
		}

		/**
		 * Initializes variables
		 *
		 * @since    1.0
		 */
		public function init() {}

	}

endif;
