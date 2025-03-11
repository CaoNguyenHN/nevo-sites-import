(function( $ ) {
	'use strict';

	var sseImport = {
		counts: {
			posts: 0,
			media: 0,
			comments: 0,
			terms: 0,
		},
		completed: {
			posts: 0,
			media: 0,
			comments: 0,
			terms: 0,
		},

		updateDelta: function( type, delta ) {
			this.completed[ type ] += delta;

			var self = this;
			requestAnimationFrame(function () {
				self.render();
			});
		},
		render: function() {
			var totalCount = 0;
			var totalCompleted = 0;

			Object.values( this.counts ).forEach( function( count, i ) {
				totalCount += count;
			});

			Object.values( this.completed ).forEach( function( count, i ) {
				totalCompleted += count;
			});

			var $button = NevoSitesImport.$currentPreview.find( '.nevo-sites-import-preview-action-button' );

			var buttonText = NevoSitesImportScriptsData.strings[ 'action_importing_contents' ] + ' (' + Math.round( totalCompleted / totalCount * 100 ) + '%)';
			$button.html( buttonText );
		}
	};

	var NevoSitesImport = {

		currentGridFilters: {},

		currentPreviewInfo: {},

		$currentPreview: null,

		$container: $( '.nevo-sites-import-wrap' ),

		$filters: $( '.nevo-sites-import-wrap .wp-filter' ),

		$browser: $( '.nevo-sites-import-wrap .theme-browser' ),

		$grid: $( '.nevo-sites-import-wrap .themes' ),
		
		templates: {
			selectBuilder: wp.template( 'nevo-sites-import-select-builder' ),
			filters: wp.template( 'nevo-sites-import-filters' ),
			gridItems: wp.template( 'nevo-sites-import-grid-items' ),
			noSiteFound: wp.template( 'nevo-sites-import-no-site-found' ),
			preview: wp.template( 'nevo-sites-import-preview' ),
			loadMore: wp.template( 'nevo-sites-import-load-more' ),
		},

		/**
		 * ====================================================
		 * Core functions
		 * ====================================================
		 */

		init: function() {
			NevoSitesImport.initBinds();

			// Start the page by fetching builders and categories list.
			NevoSitesImport.loadSiteFilters();
		},

		initBinds: function() {
			NevoSitesImport.$container.on( 'click', '.wp-filter .nevo-sites-import-builders-filter a', NevoSitesImport.clickBuilderFilter );
			NevoSitesImport.$container.on( 'click', '.wp-filter .nevo-sites-import-categories-filter a', NevoSitesImport.clickCategoryFilter );
			NevoSitesImport.$container.on( 'keyup', '.wp-filter .wp-filter-search', NevoSitesImport.submitSearchFilter );

			NevoSitesImport.$container.on( 'click', '.nevo-sites-import-load-more button', NevoSitesImport.clickLoadMore );
			NevoSitesImport.$container.on( 'click', '.theme-screenshot, .more-details', NevoSitesImport.openSitePreview );
			NevoSitesImport.$container.on( 'click', '.close-full-overlay', NevoSitesImport.closeSitePreview );

			NevoSitesImport.$container.on( 'click', '.nevo-sites-import-preview-required-plugin-button[data-status="not_installed"]', NevoSitesImport.clickInstallPlugin );
			NevoSitesImport.$container.on( 'click', '.nevo-sites-import-preview-required-plugin-button[data-status="inactive"]', NevoSitesImport.clickActivatePlugin );

			NevoSitesImport.$container.on( 'click', '.nevo-sites-import-preview-action-button[data-status="upgrade_required"]', NevoSitesImport.clickUpgrade );
			NevoSitesImport.$container.on( 'click', '.nevo-sites-import-preview-action-button[data-status="ready_to_import"]', NevoSitesImport.clickImport );

			NevoSitesImport.$container.on( 'click', '.nevo-sites-import-preview-action-button[data-status="finished"]', NevoSitesImport.clickVisitSite );
		},

		loadSiteFilters: function() {
			$( 'body' ).addClass( 'loading-content' );

			$.ajax({
				method: 'GET',
				dataType: 'JSON',
				url: NevoSitesImportScriptsData.api_url + 'site_filters/',
				cache: false,
			})
			.done(function( response, status, XHR ) {
				NevoSitesImport.$filters.append( NevoSitesImport.templates.filters( response ) );

				$( 'body' ).removeClass( 'loading-content' );

				var $selectedBuilder = NevoSitesImport.$filters.find( '.nevo-sites-import-builders-filter a[data-id="' + NevoSitesImportScriptsData.selected_builder + '"]' );

				if ( 0 < $selectedBuilder.length ) {
					$selectedBuilder.addClass( 'current' );

					NevoSitesImport.currentGridFilters.builder = NevoSitesImportScriptsData.selected_builder;
					NevoSitesImport.currentGridFilters.page = 1;

					NevoSitesImport.loadSitesGrid( true );
				} else {
					NevoSitesImport.showBuilderSelector();
				}
			});
		},

		showBuilderSelector: function() {
			NevoSitesImport.$grid.html( NevoSitesImport.templates.selectBuilder() );
		},

		resetSitesGrid: function() {
			NevoSitesImport.$grid.empty();
		},

		loadSitesGrid: function( isReset ) {
			$( 'body' ).addClass( 'loading-content' );

			if ( isReset ) {
				NevoSitesImport.resetSitesGrid();
			}

			var args = $.extend({
				builder: null,
				category: null,
				search: null,
				page: 1,
				per_page: 15,
				license_key: NevoSitesImportScriptsData.license_key,
			}, NevoSitesImport.currentGridFilters );

			// Whether to include dev_mode
			if ( NevoSitesImportScriptsData.dev_mode ) {
				args.dev_mode = 1;
			}

			var $loadMoreButton = NevoSitesImport.$container.find( '.nevo-sites-import-load-more' );
			if ( 0 < $loadMoreButton.length ) {
				$loadMoreButton.remove();
			}

			var queryString = '';
			$.each( args, function( key, value ) {
				if ( null === value || '' === value ) return;

				queryString += '&' + key + '=' + value;
			});
			queryString = queryString.replace( '&', '?' );

			var $loadMoreButton = NevoSitesImport.$browser.find( '.nevo-sites-load-more' );
			if ( 0 < $loadMoreButton.length ) {
				$loadMoreButton.remove();
			}

			$.ajax({
				method: 'GET',
				dataType: 'JSON',
				url: NevoSitesImportScriptsData.api_url + 'sites/' + queryString,
				cache: false,
			})
			.done(function( response, status, XHR ) {
				NevoSitesImport.$grid.append( NevoSitesImport.templates.gridItems( response ) );

				if ( 0 < response.length ) {
					NevoSitesImport.$browser.append( NevoSitesImport.templates.loadMore() );
				} else {
					if ( isReset ) {
						NevoSitesImport.$grid.append( NevoSitesImport.templates.noSiteFound() );
					}
				}

				$( 'body' ).removeClass( 'loading-content' );
			});
		},

		changePluginButtonStatus: function( plugin, status ) {
			plugin.status = status;

			var $button = NevoSitesImport.$currentPreview.find( '.nevo-sites-import-preview-required-plugin-button[data-slug="' + plugin.slug + '"]' ),
			    text = NevoSitesImportScriptsData.strings[ 'plugin_' + status ],
			    isDisabled, addClass;

			switch ( status ) {
				case 'not_installed':
				case 'inactive':
					isDisabled = false;
					addClass = 'button-secondary';
					break;

				case 'installing':
				case 'activating':
					isDisabled = true;
					addClass = 'button-secondary installing disabled';
					break;

				case 'active':
					isDisabled = true;
					addClass = 'button-link updated-message disabled';
					break;
			}

			// Change plugin status text.
			$button.html( text );

			// Enable / disable button.
			$button.prop( 'disabled', isDisabled );
			$button.removeClass( 'button-primary button-secondary button-link installing updated-message disabled' );
			$button.addClass( addClass );

			// Change button status attribute.
			$button.attr( 'data-status', status );

			// Check if ready to import.
			if ( NevoSitesImport.isReadyToImport() ) {
				NevoSitesImport.changeActionButtonStatus( 'ready_to_import' );
			} else {
				NevoSitesImport.changeActionButtonStatus( 'plugins_not_active' );
			}
		},

		isReadyToImport: function() {
			var ready = true;

			$.each( NevoSitesImport.currentPreviewInfo.required_plugins, function( i, plugin ) {
				ready = ready && ( 'active' === plugin.status ? true : false );
			});

			return ready;
		},

		changeActionButtonStatus: function( status ) {
			NevoSitesImport.currentPreviewInfo.import_status = status;

			var $button = NevoSitesImport.$currentPreview.find( '.nevo-sites-import-preview-action-button' ),
			    text = NevoSitesImportScriptsData.strings[ 'action_' + status ],
				isDisabled, addClass;

			switch ( status ) {
				case 'upgrade_required':
					isDisabled = false;
					addClass = 'button-secondary';
					break;

				case 'plugins_not_active':
				case 'action_finished':
					isDisabled = true;
					addClass = 'button-secondary disabled';
					break;

				case 'ready_to_import':
					isDisabled = false;
					addClass = 'button-primary';
					break;

				case 'preparing_resources':
				case 'preparing_contents':
				case 'importing_contents':
				case 'importing_customizer':
				case 'importing_widgets':
				case 'importing_options':
				case 'finalizing_import':
					isDisabled = true;
					addClass = 'button-secondary installing disabled';
					break;

				case 'finished':
					isDisabled = false;
					addClass = 'button-primary updated-message';
					break;
			}

			// Change button text.
			$button.html( text );

			// Enable / disable button.
			$button.prop( 'disabled', isDisabled );
			$button.removeClass( 'button-primary button-secondary button-link installing updated-message disabled' );
			$button.addClass( addClass );

			// Change button status attribute.
			$button.attr( 'data-status', status );
		},

		installPlugin: function( plugin ) {
			if ( 'not_installed' !== plugin.status ) {
				alert( NevoSitesImportScriptsData.strings.plugin_error_invalid );
				return;
			}

			var log = 'Installing plugin: ' + plugin.name;
			console.log( log );

			var $otherButtons = NevoSitesImport.$currentPreview.find( '.nevo-sites-import-preview-required-plugin-button' ).not( '[data-slug="' + plugin.slug + '"]' );

			$otherButtons.prop( 'disabled', true );

			NevoSitesImport.changePluginButtonStatus( plugin, 'installing' );

			return $.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=nevo_sites_import__install_plugin',
				cache: false,
				data: {
					action: 'nevo_sites_import__install_plugin',
					plugin_slug: plugin.slug,
					_ajax_nonce: NevoSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					NevoSitesImport.changePluginButtonStatus( plugin, 'inactive' );

					$otherButtons.prop( 'disabled', false );

					NevoSitesImport.activatePlugin( plugin );
				} else {
					alert( NevoSitesImportScriptsData.strings.plugin_error_invalid );
				}
			});
		},

		activatePlugin: function( plugin ) {
			if ( 'inactive' !== plugin.status ) {
				alert( NevoSitesImportScriptsData.strings.plugin_error_invalid );
				return;
			}

			var log = 'Activating plugin: ' + plugin.name;
			console.log( log );

			var $otherButtons = NevoSitesImport.$currentPreview.find( '.nevo-sites-import-preview-required-plugin-button' ).not( '[data-slug="' + plugin.slug + '"]' );

			$otherButtons.prop( 'disabled', true );

			NevoSitesImport.changePluginButtonStatus( plugin, 'activating' );

			return $.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=nevo_sites_import__activate_plugin',
				cache: false,
				data: {
					action: 'nevo_sites_import__activate_plugin',
					plugin_path: plugin.path,
					_ajax_nonce: NevoSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					$otherButtons.prop( 'disabled', false );

					NevoSitesImport.changePluginButtonStatus( plugin, 'active' );
				} else {
					alert( NevoSitesImportScriptsData.strings.plugin_error_invalid );
				}
			});
		},

		import: function() {
			if ( ! confirm( NevoSitesImportScriptsData.strings.confirm_import ) ) {
				return;
			}

			if ( ! NevoSitesImport.isReadyToImport() ) {
				alert( NevoSitesImportScriptsData.strings.import_error_invalid );
				return;
			}

			var log = 'Fetching site data for last validation.';
			console.log( log );

			NevoSitesImport.changeActionButtonStatus( 'validating_data' );

			window.addEventListener( 'beforeunload', NevoSitesImport.confirmTabClosing );

			var args = $.extend({
				license_key: NevoSitesImportScriptsData.license_key,
			}, NevoSitesImport.currentGridFilters );
			
			// Whether to include dev_mode
			if ( NevoSitesImportScriptsData.dev_mode ) {
				args.dev_mode = 1;
			}

			var queryString = '';
			$.each( args, function( key, value ) {
				if ( null === value || '' === value ) return;

				queryString += '&' + key + '=' + value;
			});
			queryString = queryString.replace( '&', '?' );

			$.ajax({
				method: 'GET',
				dataType: 'JSON',
				url: NevoSitesImportScriptsData.api_url + 'sites/' + NevoSitesImport.currentPreviewInfo.id + '/' + queryString,
				cache: false,
			})
			.done(function( response, status, XHR ) {
				if ( status ) {
					// Step 1: Preparing import.
					NevoSitesImport.preparingImport();
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		preparingImport: function() {
			var log = 'Preparing import';
			console.log( log );

			NevoSitesImport.changeActionButtonStatus( 'preparing_import' );

			$.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=nevo_sites_import__prepare_import',
				cache: false,
				data: {
					action: 'nevo_sites_import__prepare_import',
					info: {
						slug: NevoSitesImport.currentPreviewInfo.slug,
						required_plugins: NevoSitesImport.currentPreviewInfo.required_plugins,
						required_pro_modules: NevoSitesImport.currentPreviewInfo.required_pro_modules,
						contents_xml_file_url: NevoSitesImport.currentPreviewInfo.contents_xml_file_url,
						customizer_json_file_url: NevoSitesImport.currentPreviewInfo.customizer_json_file_url,
						widgets_json_file_url: NevoSitesImport.currentPreviewInfo.widgets_json_file_url,
						options_json_file_url: NevoSitesImport.currentPreviewInfo.options_json_file_url,
					},
					_ajax_nonce: NevoSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( status ) {
					// Step 2: Importing data from contents.xml.
					NevoSitesImport.importContents();
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		importContents: function() {
			var log = 'Preparing Contents XML file';
			console.log( log );

			NevoSitesImport.changeActionButtonStatus( 'importing_contents' );

			$.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=nevo_sites_import__prepare_contents',
				cache: false,
				data: {
					action: 'nevo_sites_import__prepare_contents',
					_ajax_nonce: NevoSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {

					/**
					 * Importing via SSE
					 */

					var log = 'Importing content and media files';
					console.log( log );

					// Create new EventSource WebAPI instance for processing import via AJAX request.
					var eventSource = new EventSource( ajaxurl + '?action=nevo_sites_import__import_contents&_ajax_nonce=' + NevoSitesImportScriptsData.nonce );

					eventSource.addEventListener( 'message', function( e ) {
						var data = JSON.parse( e.data );
						switch ( data.action ) {
							// Called before import process starts.
							case 'setCounts':
								// Update counts info.
								sseImport.counts = data.counts;

								// Render
								sseImport.render();
								break;

							//  Called during the import process to update the progress.
							case 'updateDelta':
								sseImport.updateDelta( data.type, data.delta );
								break;

							// Called when the import process is completed.
							case 'complete':
								eventSource.close();

								if ( false === data.error ) {
									// Step 3: Importing customizer settings.
									NevoSitesImport.importCustomizer();
								} else {
									alert( 'Error: ' + log + '\n' + data.error );
								}
								break;
						}
					}, false );

					eventSource.addEventListener( 'error', function( e ) {
						eventSource.close();
						alert( 'Error: ' + log );
					}, false );

				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		importCustomizer: function() {
			var log = 'Importing customizer settings';
			console.log( log );

			NevoSitesImport.changeActionButtonStatus( 'importing_customizer' );

			$.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=nevo_sites_import__import_customizer',
				cache: false,
				data: {
					action: 'nevo_sites_import__import_customizer',
					_ajax_nonce: NevoSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {

					// Step 4: Importing data from widgets.json.
					NevoSitesImport.importWidgets();
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		importWidgets: function() {
			var log = 'Importing widgets';
			console.log( log );

			NevoSitesImport.changeActionButtonStatus( 'importing_widgets' );

			$.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=nevo_sites_import__import_widgets',
				cache: false,
				data: {
					action: 'nevo_sites_import__import_widgets',
					_ajax_nonce: NevoSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					// Step 5: Importing data from options.json.
					NevoSitesImport.importOptions();
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		importOptions: function() {
			var log = 'Importing other options';
			console.log( log );

			NevoSitesImport.changeActionButtonStatus( 'importing_options' );

			$.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=nevo_sites_import__import_options',
				cache: false,
				data: {
					action: 'nevo_sites_import__import_options',
					_ajax_nonce: NevoSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					// Step 6: Finalizing import.
					NevoSitesImport.finalizeImport();
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		finalizeImport: function() {
			var log = 'Finalizing import';
			console.log( log );

			NevoSitesImport.changeActionButtonStatus( 'finalizing_import' );

			$.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=nevo_sites_import__finalize_import',
				cache: false,
				data: {
					action: 'nevo_sites_import__finalize_import',
					_ajax_nonce: NevoSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					// Finished!
					NevoSitesImport.changeActionButtonStatus( 'finished' );

					window.removeEventListener( 'beforeunload', NevoSitesImport.confirmTabClosing );
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		/**
		 * ====================================================
		 * Event handler functions
		 * ====================================================
		 */

		clickBuilderFilter: function( event ) {
			event.preventDefault();

			var $link = $( this ),
			    $filterLinks = $( '.nevo-sites-import-builders-filter a' ),
			    builder = parseInt( $link.attr( 'data-id' ) );

			if ( $link.hasClass( 'current' ) ) {
				return;
			}

			$filterLinks.removeClass( 'current' );
			$link.addClass( 'current' );

			NevoSitesImport.currentGridFilters.builder = builder;
			NevoSitesImport.currentGridFilters.page = 1;

			$.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=nevo_sites_import__select_builder',
				cache: false,
				data: {
					action: 'nevo_sites_import__select_builder',
					builder: builder,
					_ajax_nonce: NevoSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});

			NevoSitesImport.loadSitesGrid( true );
		},

		clickCategoryFilter: function( event ) {
			event.preventDefault();

			var $link = $( this ),
			    $filterLinks = $( '.nevo-sites-import-categories-filter a' ),
			    $filterSearch = $( '.wp-filter-search' ),
			    category = parseInt( $link.attr( 'data-id' ) );

			if ( $link.hasClass( 'current' ) ) {
				return;
			}

			$filterLinks.removeClass( 'current' );
			$link.addClass( 'current' );

			$filterSearch.val( '' );

			delete NevoSitesImport.currentGridFilters.search;
			if ( -1 === category ) {
				delete NevoSitesImport.currentGridFilters.category;
			} else {
				NevoSitesImport.currentGridFilters.category = category;
			}
			NevoSitesImport.currentGridFilters.page = 1;

			if ( undefined !== NevoSitesImport.currentGridFilters.builder ) {
				NevoSitesImport.loadSitesGrid( true );
			}
		},

		submitSearchFilter: function( event ) {
			event.preventDefault();

			var $search = $( this ),
			    $filterLinks = $( '.nevo-sites-import-categories-filter a' ),
			    keywords = $search.val();

			if ( 0 < keywords.length ) {
				$filterLinks.removeClass( 'current' );
				NevoSitesImport.currentGridFilters.search = keywords;
			} else {
				$filterLinks.filter( '[data-id="-1"]' ).addClass( 'current' );
				delete NevoSitesImport.currentGridFilters.search;
			}

			delete NevoSitesImport.currentGridFilters.category;
			NevoSitesImport.currentGridFilters.page = 1;

			if ( undefined !== NevoSitesImport.currentGridFilters.builder ) {
				NevoSitesImport.loadSitesGrid( true );
			}
		},

		clickLoadMore: function( event ) {
			event.preventDefault();

			NevoSitesImport.currentGridFilters.page = NevoSitesImport.currentGridFilters.page + 1;

			NevoSitesImport.$browser.find( '.nevo-sites-load-more' ).remove();

			NevoSitesImport.loadSitesGrid();
		},

		openSitePreview: function( event ) {
			event.preventDefault();

			var $item = $( this ).closest( '.theme' ),
				data = JSON.parse( $item.attr( 'data-info' ) );

			NevoSitesImport.$currentPreview = $( NevoSitesImport.templates.preview( data ) );

			NevoSitesImport.$container.append( NevoSitesImport.$currentPreview );

			NevoSitesImport.currentPreviewInfo = data;
			NevoSitesImport.currentPreviewInfo.import_status = null;

			switch ( NevoSitesImport.currentPreviewInfo.status ) {
				case 'require_higher_license_plan':
					NevoSitesImport.changeActionButtonStatus( 'upgrade_required' );
					break;

				default:
					if ( 0 < NevoSitesImport.currentPreviewInfo.required_plugins.length ) {
						var plugins_status = {};

						$.ajax({
							method: 'POST',
							dataType: 'JSON',
							url: ajaxurl + '?do=nevo_sites_import__get_plugins_status',
							cache: false,
							data: {
								action: 'nevo_sites_import__get_plugins_status',
								plugins: data.required_plugins,
								_ajax_nonce: NevoSitesImportScriptsData.nonce,
							},
						})
						.done(function( response, status, XHR ) {
							if ( response.success ) {
								$.each( response.data, function( index, status ) {
									NevoSitesImport.changePluginButtonStatus( NevoSitesImport.currentPreviewInfo.required_plugins[ index ], status );
								});
							} else {
								alert( NevoSitesImportScriptsData.strings.site_error_invalid );
							}
						});
					} else {
						NevoSitesImport.changeActionButtonStatus( 'ready_to_import' );
					}
					break;
			}
		},

		closeSitePreview: function( event ) {
			event.preventDefault();

			var close = true;

			if ( -1 < [ 'preparing_import', 'preparing_contents', 'importing_contents', 'importing_customizer', 'importing_widgets', 'importing_options' ].indexOf( NevoSitesImport.currentPreviewInfo.import_status ) ) {
				if ( ! confirm( NevoSitesImportScriptsData.strings.confirm_close_importing ) ) {
					close = false;
				}
			}

			if ( close ) {
				NevoSitesImport.$currentPreview = null;
				NevoSitesImport.currentPreviewInfo = {};

				$( '.nevo-sites-import-preview' ).remove();
			}
		},

		clickInstallPlugin: function( event ) {
			event.preventDefault();

			var $button = $( this ),
			    index = $button.attr( 'data-index' ),
			    plugin = NevoSitesImport.currentPreviewInfo.required_plugins[ index ];

			NevoSitesImport.installPlugin( plugin );
		},

		clickActivatePlugin: function( event ) {
			event.preventDefault();

			var $button = $( this ),
			    index = $button.attr( 'data-index' ),
			    plugin = NevoSitesImport.currentPreviewInfo.required_plugins[ index ];

			NevoSitesImport.activatePlugin( plugin );
		},

		clickImport: function( event ) {
			event.preventDefault();

			NevoSitesImport.import();
		},

		clickUpgrade: function( event ) {
			window.open( 'https://nevothemes.com/pricing/?utm_source=nevo-sites-import&utm_medium=demo-site-preview&utm_campaign=upgrade-license' );
		},

		clickVisitSite: function( event ) {
			event.preventDefault();

			window.location = NevoSitesImportScriptsData.home_url;
		},

		confirmTabClosing: function( event ) {
			event.returnValue = '';
		},
	}

	$(function() {
		NevoSitesImport.init();
	});

})( jQuery );