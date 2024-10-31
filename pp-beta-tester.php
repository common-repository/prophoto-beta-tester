<?php
/*
	Plugin Name: ProPhoto Beta Tester
	Plugin URI: http://wordpress.org/extend/plugins/prophoto-beta-tester/
	Description: Facilitates beta-testing for future releases of the ProPhoto theme
	Author: Jared Henderson
	Version: 0.17
	Author URI: http://www.prophotoblogs.com/beta-testing-plugin/
 */


class ProPhotoBetaTester {
	
	
	function init() {
		if ( strpos( get_option( 'template' ), 'prophoto' ) === false ) {
			return;
		}
		
		add_action('admin_menu', array( &$this, 'addMenuItem' ) );
		
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'ProPhotoBetaTester' ) {
			wp_enqueue_style( 'pp-beta-tester.css', plugins_url() . '/pp-beta-tester/pp-beta-tester.css' );
		}
		
		add_action( 'post_pp_loaded', array( &$this, 'importP3Menu' ) );
		add_action( 'post_pp_loaded', array( &$this, 'updateSeoTerms' ) );
		add_action( 'post_pp_loaded', array( &$this, 'fixMenuSplittage' ) );
		add_action( 'post_pp_loaded', array( &$this, 'updateMenuTerms' ) );
		add_action( 'post_pp_loaded', array( &$this, 'fixSlideshowTerms' ) );
		add_action( 'post_pp_loaded', array( &$this, 'deleteSuperHugeSprites' ) );
		add_action( 'post_pp_loaded', array( &$this, 'convertFeaturedGalleriesDropdown' ) );
		add_action( 'post_pp_loaded', array( &$this, 'updateContactLogDbName' ) );
		
		self::fixBadTransient();
	}
	
	
	function updateContactLogDbName() {
		if ( ppOpt::test( 'converted_log_format', 'true' ) ) {
			return;
		}
		
		$oldFormatContactLog = get_option( 'p4theme_contact_log' );
		
		if ( is_array( $oldFormatContactLog ) ) {
		
			$newFormatContactLog = get_option( 'prophoto_theme_contact_log' );
			
			if ( is_array( $newFormatContactLog ) ) {
				$updatedContactLog = array_merge( $newFormatContactLog, $oldFormatContactLog );
			
			} else {
				$updatedContactLog = $oldFormatContactLog;
			}
			
		} else {
			$updatedContactLog = (array) get_option( 'prophoto_theme_contact_log' );
		}
		
		if ( $p3ContactLog = get_option( 'p3theme_contact_log' ) ) {
			foreach ( $p3ContactLog as $entry ) {
				if ( !in_array( $entry, $updatedContactLog ) ) {
					$updatedContactLog[] = $entry;
				}
			}
		}

		if ( false == get_option( 'prophoto_theme_contact_log' ) ) {
			add_option( 'prophoto_theme_contact_log', '', '', 'no'  );
		}
		$success = update_option( 'prophoto_theme_contact_log', $updatedContactLog );
		delete_option( 'p4theme_contact_log' );
		ppOpt::update( 'converted_log_format', 'true' );
	}
	
	
	function convertFeaturedGalleriesDropdown() {
		if ( ppOpt::test( 'featured_galleries_converted', 'true' ) || pp::site()->svn < 528 ) {
			return;
		}
		
		$featuredSlideshows = (array) json_decode( get_option( 'pp_featured_slideshows' ), true );
		
		$handles = array( 'primary_nav_menu', 'secondary_nav_menu', 'widget_menu_1', 'widget_menu_2', 'widget_menu_3' );
		foreach ( $handles as $handle ) {
			$structure = $initialStructure = json_decode( ppOpt::id( $handle . '_structure' ), true );

			foreach ( (array) $structure as $menuID => $maybeChildren ) {
				$menuData = json_decode( ppOpt::menuData( $menuID ), true );
				if ( $menuData['type'] == 'internal' && $menuData['internalType'] == 'featured_slideshows' ) {
					if ( !$featuredSlideshows ) {
						if ( is_array( $maybeChildren ) ) {
							foreach ( $mabyeChildren as $childMenuID ) {
								$structure[$childMenuID] = $childMenuID;
							}
						}
						unset( $structure[$menuID] );
					} else {
						$structure[$menuID] = array();
						$menuData['type'] = 'container';
						unset( $menuData['internalType'] );
						ppOpt::update( $menuID, json_encode( $menuData ) );

						$nextID = ppMenuAdmin::highestID( $structure, 0, $handle ) + 1;
						foreach ( $featuredSlideshows as $slideshowID => $slideshow ) {
							$newMenuID = $handle . '_' . $nextID;
							$structure[$menuID][$newMenuID] = $newMenuID;
							$newMenuData = array(
								'type' => 'internal',
								'internalType' => 'gallery',
								'galleryDisplay' => 'popup_slideshow',
								'galleryID' => $slideshowID,
								'text' => $slideshow['title'],
							);
							ppOpt::update( $newMenuID, json_encode( $newMenuData ) );
							$nextID++;
						}
					}
				}
			}

			if ( $structure != $initialStructure ) {
				ppOpt::update( $handle . '_structure', json_encode( $structure ) );
			}
		}
		delete_option( 'pp_featured_slideshows' );
		ppOpt::update( 'featured_galleries_converted', 'true' );
	}
	
	
	
	function deleteSuperHugeSprites() {
		if ( pp::site()->svn < 550 && !ppOpt::test( 'sprites_fixed', 'true' ) ) {
			if ( pp::site()->svn > 497 ) {
				$hugeSprites = glob( pp::fileInfo()->imagesFolderPath . '/gallery_btn*' );
				foreach ( $hugeSprites as $hugeSprite ) {
					@unlink( $hugeSprite );
				}
				ppOpt::update( 'sprites_fixed', 'true' );
				ppSlideshowGallery::btnsSrcs();
			}
 		}
		if ( pp::site()->svn > 550 ) {
			ppOpt::delete( 'sprites_fixed' );
		}
	}
	
	
	
	function fixBadTransient() {
		if ( $transientExpiration = get_option( '_transient_timeout_pp_delay_next_auto_upgrade_attempt' ) ) {
			if ( $transientExpiration - time() > ( 60*60 * 12 ) ) {
				delete_transient( 'pp_delay_next_auto_upgrade_attempt' );
			}
		}
	}
	

	function fixSlideshowTerms() {
		if ( pp::site()->svn > 425 ) {
			return;
		}
		if ( !ppOpt::exists( 'masthead_slideshow_image_order' ) ) {
			ppOpt::update( 'masthead_slideshow_image_order', ppOpt::id( 'masthead_gallery_image_order' ) );
		}
		if ( !ppOpt::exists( 'masthead_slideshow_loop_images' ) ) {
			ppOpt::update( 'masthead_slideshow_loop_images', ppOpt::id( 'masthead_gallery_loop_images' ) );
		}
		
		if ( pp::site()->svn > 375 && ppOpt::exists( 'masthead_slideshow_loop_images' ) && ppOpt::exists( 'masthead_gallery_image_order' ) ) {
			ppOpt::delete( 'masthead_gallery_image_order' );
			ppOpt::delete( 'masthead_gallery_loop_images' );
		}
	}

	
	function updateMenuTerms() {
		if ( pp::site()->svn < 290 || pp::site()->svn > 320 ) {
			return;
		}
		if ( !ppOpt::test( 'menu_terms_beta_fixed', 'yes_fixed' ) ) {
			$options = ppActiveDesign::options();
			$newTerms = $this->newMenuTerms();
			$updateOptions = array();
			foreach ( $options as $optionKey => $optionVal ) {
				if ( array_key_exists( $optionKey, $newTerms ) ) {
					$updateOptions[$newTerms[$optionKey]] = $optionVal;
				}
			}
			
			if ( isset( $updateOptions['primary_nav_menu_structure'] ) ) {
				$updateOptions['primary_nav_menu_structure'] = str_replace( 
					'main_menu_link_', 
					'primary_nav_menu_item_', 
					$updateOptions['primary_nav_menu_structure'] 
				);
			}
			
			if ( isset( $updateOptions['primary_nav_menu_split_after_id'] ) ) {
				$updateOptions['primary_nav_menu_split_after_id'] = str_replace(
					'main_menu_link_',
					'primary_nav_menu_item_',
					$updateOptions['primary_nav_menu_split_after_id']
				);
			}
			
			ppOpt::updateMultiple( $updateOptions );
			ppOpt::update( 'menu_terms_beta_fixed', 'yes_fixed' );

			$imgs = ppActiveDesign::imgs();
			if ( isset( $imgs['nav_bg'] ) ) {
				ppImg::update( 'primary_nav_menu_bg', $imgs['nav_bg'] );
			}
			
			for ( $i = 1; $i <= 100; $i++ ) { 
				if ( isset( $imgs['main_menu_link_'.$i] ) ) {
					ppImg::update( 'primary_nav_menu_item_' . $i, $imgs['main_menu_link_'.$i] );
				}
				if ( isset( $imgs['main_menu_link_'.$i.'_icon'] ) ) {
					ppImg::update( 'primary_nav_menu_item_' . $i . '_icon', $imgs['main_menu_link_'.$i.'_icon'] );
				}
			}
		}
	}
	
		
	function fixMenuSplittage() {
		
		if ( pp::site()->svn > 295 ) {
			if ( ppOpt::test( 'p3_menu_imported' ) ) {
				ppOpt::delete( 'p3_menu_imported' );
			}
			return;
		}
		
		// .foo { margin:0 } nav #toplevel #main_menu_link_17 { float:right; margin-right:0 }
		if ( !ppOpt::test( 'menu_align_beta_fixed' ) && pp::site()->svn < 285 ) {

			if ( ppOpt::test( 'headerlayout', 'pptclassic' ) ) {
				ppOpt::update( 'nav_align', 'left' );
			}
			
			if ( ppOpt::test( 'nav_align', 'center || right' ) ) {
				return;
			}
			
			$css = ppOpt::id( 'override_css' );
			
			if ( preg_match( '/nav #toplevel #main_menu_([^ ]*) { float:right; margin-right:0 }/', $css, $match ) ) {
				$searchID = 'main_menu_' . $match[1];
				NrDump::it( $match );
				NrDump::it( $css );
				NrDump::it( $searchID );
				NrDump::it( str_replace( $match[0], '', $css ) );
				
				$menuItems = json_decode( ppOpt::id( 'main_menu_structure' ), true );
				$lastID = null;
				foreach ( $menuItems as $ID => $ignore ) {
					if ( $ID == $searchID ) {
						NrDump::it( 'set split_id to ' . $lastID );
						ppOpt::update( 'nav_split_after_id', $lastID );
					}
					$lastID = $ID;
				}
				ppOpt::update( 'override_css', str_replace( $match[0], '', $css ) );
				ppOpt::update( 'menu_align_beta_fixed', 'true' );
				ppOpt::update( 'nav_align', 'split' );
			}
		}
	}
	
	
	function importP3Menu() {
		if ( pp::site()->svn > 290 ) {
			if ( ppOpt::test( 'p3_menu_imported' ) ) {
				ppOpt::delete( 'p3_menu_imported' );
			}
			return;
		}
		
		if ( ppOpt::test( 'p3_menu_imported', 'yes_imported' ) ) {
			return;
		}
		
		$conf = ppUtil::loadConfig( 'options' );
		if ( ppOpt::id( 'main_menu_structure' ) != $conf['main_menu_structure'] && is_array( json_decode( ppOpt::id( 'main_menu_structure' ), true ) ) ) {
			return;
		}
		if ( pp::site()->svn < 264 ) {
			return;
		}
		
		
		if ( is_admin() ) {
			if ( NrUtil::GET( 'page', 'p4-customize' ) && NrUtil::GET( 'pp_tab', 'menu' ) ) {
				// run the import in admin only if on the menu page
			} else {
				return;
			}
		}
		
		$design = array( 'options' => ppActiveDesign::options(), 'images' => ppActiveDesign::imgs() );
		$contactText = ppOpt::id( 'contactform_link_text' ) ? ppOpt::id( 'contactform_link_text' ) : 'Contact';
		
		if ( method_exists( 'ppUtil', 'logVar' ) ) {
			ppUtil::logVar( 'importing menu from beta testing plugin', 'importing menu from beta plugin' );
		}
		$imported = ppImportP3Menu::importMenu( $design, $contactText );
		$imported['options']['p3_menu_imported'] = 'yes_imported';
		ppOpt::updateMultiple( $imported['options'] );
		ppImg::updateMultiple( $imported['images'] );
		
		foreach ( ppImportP3Menu::eliminatedOptions() as $eliminatedOptionID ) {
			ppOpt::delete( $eliminatedOptionID );
		}
		
		$imgs = ppActiveDesign::imgs();
		for ( $i = 1; $i <= 30; $i++ ) { 
			if ( isset( $imgs["nav_customlink{$i}_icon"] ) ) {
				ppImportP3::moveImportImg( $imgs["nav_customlink{$i}_icon"] );
			}
		}
		ppImportP3::moveImportImg( 'rss-icon.png' );
		
	}
	
	
	function updateSeoTerms() {
		if ( pp::site()->svn > 231 ) {
			return;
		}
		
		$seoKeys = array(
			'seo_title_home',
			'seo_title_front_page',
			'seo_title_single',
			'seo_title_page',
			'seo_title_category',
			'seo_title_archive',
			'seo_title_search',
			'seo_title_author',
			'seo_title_tag',
		);
		$seoTerms = array();
		foreach ( $seoKeys as $seoKey ) {
			$seoTerms[$seoKey] = ppOpt::id( $seoKey );
		}
		
		foreach ( $seoTerms as $key => $val ) {
			$seoTerms[$key] = str_replace( 
				array( '%search%',       '%archive_title%', '%category_title%' ),
				array( '%search_query%', '%archive_date%',  '%category_name%' ),
				$val
			);
		}
		ppOpt::updateMultiple( $seoTerms );
	}
	
	
	public function addMenuItem() {
		add_management_page( 
			'ProPhoto Beta Testing', 
			'ProPhoto Beta Testing', 
			'update_plugins', 
			'ProPhotoBetaTester', 
			array( &$this, 'dashboard' )
		);
	}
	
	
	public function dashboard() {
		include( dirname( __FILE__ ) . '/dashboard.php' );
	}
	
	
	protected function wpVersion() {
		$ver = str_pad( intval( str_replace( '.', '', $GLOBALS['wp_version'] ) ), 3, '0' );
		return ( $ver == '000' ) ? 999 : $ver;
	}
	
	
	function ProPhotoBetaTester() {
		if ( function_exists( 'spl_autoload_register' ) && floatval( $GLOBALS['wp_version'] ) >= 3.2 ) {
			$this->init();
		} else {
			add_action( 'admin_notices', create_function( '', 'echo "<div class=\'error\' style=\'padding:4px 9px;\'>
			<em>ProPhoto Beta Testing Plugin</em> and  
			<em>ProPhoto4</em> <b>require WordPress version 3.2 or higher</b>.</div>";' ) );
		}
	}
	
	
	function newMenuTerms() {
		$terms = array(
			'nav_align'                               => 'primary_nav_menu_align',
			'nav_dropdown_bg_color'                   => 'primary_nav_menu_dropdown_bg_color',
			'nav_dropdown_bg_color_bind'              => 'primary_nav_menu_dropdown_bg_color_bind',
			'nav_dropdown_bg_hover_color'             => 'primary_nav_menu_dropdown_bg_hover_color',
			'nav_dropdown_bg_hover_color_bind'        => 'primary_nav_menu_dropdown_bg_hover_color_bind',
			'nav_dropdown_opacity'                    => 'primary_nav_menu_dropdown_opacity',
			'nav_dropdown_link_textsize'              => 'primary_nav_menu_dropdown_link_textsize',
			'nav_dropdown_link_font_color'            => 'primary_nav_menu_dropdown_link_font_color',
			'nav_dropdown_link_font_color_bind'       => 'primary_nav_menu_dropdown_link_font_color_bind',
			'nav_dropdown_link_hover_font_color'      => 'primary_nav_menu_dropdown_link_hover_font_color',
			'nav_dropdown_link_hover_font_color_bind' => 'primary_nav_menu_dropdown_link_hover_font_color_bind',
			'nav_link_spacing_between'                => 'primary_nav_menu_link_spacing_between',
			'nav_edge_padding'                        => 'primary_nav_menu_edge_padding',
			'nav_border_top'                          => 'primary_nav_menu_border_top_onoff',
			'nav_border_bottom'                       => 'primary_nav_menu_border_bottom_onoff',
			'nav_top_border_width'                    => 'primary_nav_menu_top_border_width',
			'nav_top_border_style'                    => 'primary_nav_menu_top_border_style',
			'nav_top_border_color'                    => 'primary_nav_menu_top_border_color',
			'nav_btm_border_width'                    => 'primary_nav_menu_btm_border_width',
			'nav_btm_border_style'                    => 'primary_nav_menu_btm_border_style',
			'nav_btm_border_color'                    => 'primary_nav_menu_btm_border_color',
			'nav_split_after_id'                      => 'primary_nav_menu_split_after_id',
			'nav_link_font_size'                      => 'primary_nav_menu_link_font_size',
			'main_menu_structure'                     => 'primary_nav_menu_structure',
			'nav_bg_img_repeat'                       => 'primary_nav_menu_bg_img_repeat',
			'nav_bg_img_position'                     => 'primary_nav_menu_bg_img_position',
			'nav_bg_img_attachment'                   => 'primary_nav_menu_bg_img_attachment',
			'nav_bg_color'                            => 'primary_nav_menu_bg_color',
			'nav_bg_color_bind'                       => 'primary_nav_menu_bg_color_bind',
			'nav_link_font_size'                      => 'primary_nav_menu_link_font_size',
			'nav_link_font_color'                     => 'primary_nav_menu_link_font_color',
			'nav_link_font_color_bind'                => 'primary_nav_menu_link_font_color_bind',
			'nav_link_hover_font_color'               => 'primary_nav_menu_link_hover_font_color',
			'nav_link_hover_font_color_bind'          => 'primary_nav_menu_link_hover_font_color_bind',
			'nav_link_font_family'                    => 'primary_nav_menu_link_font_family',
			'nav_link_visited_font_color'             => 'primary_nav_menu_link_visited_font_color',
			'nav_link_visited_font_color_bind'        => 'primary_nav_menu_link_visited_font_color_bind',
			'nav_link_font_style'                     => 'primary_nav_menu_link_font_style',
			'nav_link_decoration'                     => 'primary_nav_menu_link_decoration',
			'nav_link_hover_decoration'               => 'primary_nav_menu_link_hover_decoration',
			'nav_link_text_transform'                 => 'primary_nav_menu_link_text_transform',
		);
		for ( $i = 0; $i <= 100; $i++ ) { 
			$terms['main_menu_link_'.$i] = 'primary_nav_menu_item_' . $i;
		}
		return $terms;
	}
}

new ProPhotoBetaTester();


