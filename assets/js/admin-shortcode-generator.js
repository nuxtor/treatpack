/**
 * Shortcode Generator Admin JS
 *
 * @package TreatmentPackages
 */

(function( $ ) {
    'use strict';

    var data = {
        categories: [],
        treatments: [],
    };

    // Default attribute values (omitted from output when matching)
    var defaults = {
        treatment_packages: {
            category: '',
            ids: '',
            columns: '3',
            show_sidebar: 'yes',
            show_intro: 'yes',
            intro_text: 'To purchase a treatment package, select the number of sessions and click to add it to the shopping basket.',
            orderby: 'menu_order',
            order: 'ASC',
        },
        treatment_single: {
            id: '',
            columns: '3',
        },
    };

    // Current browser target input
    var browserTarget = '';
    var browserSelections = [];

    /**
     * Initialize on DOM ready
     */
    $( document ).ready( function() {
        loadShortcodeData();
        bindEvents();
    });

    /**
     * Load categories and treatments via AJAX
     */
    function loadShortcodeData() {
        $.post( tpShortcodeGen.ajaxUrl, {
            action: 'tp_get_shortcode_data',
            nonce: tpShortcodeGen.nonce,
        }, function( response ) {
            if ( response.success ) {
                data.categories = response.data.categories;
                data.treatments = response.data.treatments;
                populateCategories();
            }
        });
    }

    /**
     * Populate category multi-select
     */
    function populateCategories() {
        var $select = $( '#tp-sg-category' );
        $select.empty();

        if ( data.categories.length === 0 ) {
            $select.append( '<option value="" disabled>' + tpShortcodeGen.i18n.noTreatments + '</option>' );
            return;
        }

        $.each( data.categories, function( i, cat ) {
            $select.append(
                $( '<option>' ).val( cat.slug ).text( cat.name )
            );
        });
    }

    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // Type selector
        $( '.tp-sg-type-card' ).on( 'click', function() {
            $( '.tp-sg-type-card' ).removeClass( 'active' );
            $( this ).addClass( 'active' );
            $( this ).find( 'input[type="radio"]' ).prop( 'checked', true );

            var type = $( this ).data( 'type' );
            if ( type === 'treatment_packages' ) {
                $( '#tp-sg-options-packages' ).show();
                $( '#tp-sg-options-single' ).hide();
            } else {
                $( '#tp-sg-options-packages' ).hide();
                $( '#tp-sg-options-single' ).show();
            }
            buildShortcode();
        });

        // Any form input change
        $( '.tp-sg-options-panel' ).on( 'change input', 'select, input, textarea', function() {
            buildShortcode();
        });

        // Show/hide intro text row
        $( '#tp-sg-show-intro' ).on( 'change', function() {
            if ( $( this ).val() === 'yes' ) {
                $( '#tp-sg-intro-text-row' ).show();
            } else {
                $( '#tp-sg-intro-text-row' ).hide();
            }
        });

        // Copy button
        $( '#tp-sg-copy-btn' ).on( 'click', function() {
            var text = $( '#tp-sg-output' ).val();
            var $btn = $( this );

            if ( navigator.clipboard && navigator.clipboard.writeText ) {
                navigator.clipboard.writeText( text ).then( function() {
                    showCopiedFeedback( $btn );
                }, function() {
                    fallbackCopy( text, $btn );
                });
            } else {
                fallbackCopy( text, $btn );
            }
        });

        // Browse treatments button
        $( '.tp-sg-browse-btn' ).on( 'click', function() {
            browserTarget = $( this ).data( 'target' );
            openBrowser();
        });

        // Clear IDs button
        $( '.tp-sg-clear-ids-btn' ).on( 'click', function() {
            var target = $( this ).data( 'target' );
            $( '#' + target ).val( '' );
            $( this ).hide();
            buildShortcode();
        });

        // Browser modal close
        $( '.tp-sg-modal-close, #tp-sg-browser-cancel' ).on( 'click', closeBrowser );

        // Browser modal backdrop click
        $( '#tp-sg-browser-modal' ).on( 'click', function( e ) {
            if ( $( e.target ).is( '#tp-sg-browser-modal' ) ) {
                closeBrowser();
            }
        });

        // Browser search
        $( '#tp-sg-browser-search' ).on( 'input', function() {
            var search = $( this ).val().toLowerCase();
            $( '.tp-sg-browser-item' ).each( function() {
                var title = $( this ).data( 'title' ).toLowerCase();
                $( this ).toggle( title.indexOf( search ) !== -1 );
            });
        });

        // Browser checkbox change
        $( '#tp-sg-browser-list' ).on( 'change', 'input[type="checkbox"]', function() {
            updateBrowserCount();
        });

        // Browser apply
        $( '#tp-sg-browser-apply' ).on( 'click', applyBrowserSelection );
    }

    /**
     * Build shortcode string from current form state
     */
    function buildShortcode() {
        var type = $( 'input[name="tp_sg_type"]:checked' ).val();
        var attrs = [];

        if ( type === 'treatment_packages' ) {
            var category = getMultiSelectValues( '#tp-sg-category' ).join( ',' );
            var ids = $( '#tp-sg-pkg-ids' ).val().trim();
            var columns = $( '#tp-sg-pkg-columns' ).val();
            var showSidebar = $( '#tp-sg-show-sidebar' ).val();
            var showIntro = $( '#tp-sg-show-intro' ).val();
            var introText = $( '#tp-sg-intro-text' ).val().trim();
            var orderby = $( '#tp-sg-orderby' ).val();
            var order = $( '#tp-sg-order' ).val();

            if ( category && category !== defaults.treatment_packages.category ) {
                attrs.push( 'category="' + category + '"' );
            }
            if ( ids && ids !== defaults.treatment_packages.ids ) {
                attrs.push( 'ids="' + ids + '"' );
            }
            if ( columns !== defaults.treatment_packages.columns ) {
                attrs.push( 'columns="' + columns + '"' );
            }
            if ( showSidebar !== defaults.treatment_packages.show_sidebar ) {
                attrs.push( 'show_sidebar="' + showSidebar + '"' );
            }
            if ( showIntro !== defaults.treatment_packages.show_intro ) {
                attrs.push( 'show_intro="' + showIntro + '"' );
            }
            if ( showIntro === 'yes' && introText !== defaults.treatment_packages.intro_text && introText !== '' ) {
                attrs.push( 'intro_text="' + introText + '"' );
            }
            if ( orderby !== defaults.treatment_packages.orderby ) {
                attrs.push( 'orderby="' + orderby + '"' );
            }
            if ( order !== defaults.treatment_packages.order ) {
                attrs.push( 'order="' + order + '"' );
            }
        } else {
            var singleIds = $( '#tp-sg-single-ids' ).val().trim();
            var singleColumns = $( '#tp-sg-single-columns' ).val();

            if ( singleIds ) {
                attrs.push( 'id="' + singleIds + '"' );
            }
            if ( singleColumns !== defaults.treatment_single.columns ) {
                attrs.push( 'columns="' + singleColumns + '"' );
            }
        }

        var shortcode = '[' + type;
        if ( attrs.length > 0 ) {
            shortcode += ' ' + attrs.join( ' ' );
        }
        shortcode += ']';

        $( '#tp-sg-output' ).val( shortcode );
    }

    /**
     * Get selected values from a multi-select
     */
    function getMultiSelectValues( selector ) {
        var values = $( selector ).val();
        return values ? values : [];
    }

    /**
     * Show "Copied!" feedback on button
     */
    function showCopiedFeedback( $btn ) {
        var originalHtml = $btn.html();
        $btn.html( '<span class="dashicons dashicons-yes"></span> ' + tpShortcodeGen.i18n.copied );
        $btn.addClass( 'tp-sg-copied' );
        setTimeout( function() {
            $btn.html( originalHtml );
            $btn.removeClass( 'tp-sg-copied' );
        }, 2000 );
    }

    /**
     * Fallback copy using textarea
     */
    function fallbackCopy( text, $btn ) {
        var $temp = $( '<textarea>' );
        $( 'body' ).append( $temp );
        $temp.val( text ).select();
        try {
            document.execCommand( 'copy' );
            showCopiedFeedback( $btn );
        } catch ( e ) {
            window.alert( tpShortcodeGen.i18n.copyFailed );
        }
        $temp.remove();
    }

    /**
     * Open treatment browser modal
     */
    function openBrowser() {
        var $list = $( '#tp-sg-browser-list' );
        $list.empty();

        // Parse current IDs from target input
        var currentIds = $( '#' + browserTarget ).val().trim();
        var selectedIds = currentIds ? currentIds.split( ',' ).map( function( id ) { return parseInt( id, 10 ); }) : [];

        if ( data.treatments.length === 0 ) {
            $list.html( '<p>' + tpShortcodeGen.i18n.noTreatments + '</p>' );
        } else {
            // Group by category
            var grouped = {};
            var uncategorized = [];

            $.each( data.treatments, function( i, treatment ) {
                if ( treatment.categories.length === 0 ) {
                    uncategorized.push( treatment );
                } else {
                    $.each( treatment.categories, function( j, catSlug ) {
                        if ( ! grouped[ catSlug ] ) {
                            grouped[ catSlug ] = [];
                        }
                        grouped[ catSlug ].push( treatment );
                    });
                }
            });

            // Build grouped list
            $.each( data.categories, function( i, cat ) {
                if ( ! grouped[ cat.slug ] || grouped[ cat.slug ].length === 0 ) {
                    return;
                }
                $list.append( '<div class="tp-sg-browser-category">' + escHtml( cat.name ) + '</div>' );
                $.each( grouped[ cat.slug ], function( j, treatment ) {
                    var checked = selectedIds.indexOf( treatment.id ) !== -1 ? ' checked' : '';
                    $list.append(
                        '<label class="tp-sg-browser-item" data-title="' + escAttr( treatment.title ) + '">' +
                            '<input type="checkbox" value="' + treatment.id + '"' + checked + '> ' +
                            '<span class="tp-sg-browser-item-title">' + escHtml( treatment.title ) + '</span>' +
                            '<span class="tp-sg-browser-item-id">#' + treatment.id + '</span>' +
                        '</label>'
                    );
                });
            });

            if ( uncategorized.length > 0 ) {
                $list.append( '<div class="tp-sg-browser-category">Uncategorized</div>' );
                $.each( uncategorized, function( i, treatment ) {
                    var checked = selectedIds.indexOf( treatment.id ) !== -1 ? ' checked' : '';
                    $list.append(
                        '<label class="tp-sg-browser-item" data-title="' + escAttr( treatment.title ) + '">' +
                            '<input type="checkbox" value="' + treatment.id + '"' + checked + '> ' +
                            '<span class="tp-sg-browser-item-title">' + escHtml( treatment.title ) + '</span>' +
                            '<span class="tp-sg-browser-item-id">#' + treatment.id + '</span>' +
                        '</label>'
                    );
                });
            }
        }

        updateBrowserCount();
        $( '#tp-sg-browser-search' ).val( '' );
        $( '#tp-sg-browser-modal' ).show();
    }

    /**
     * Close treatment browser modal
     */
    function closeBrowser() {
        $( '#tp-sg-browser-modal' ).hide();
    }

    /**
     * Update browser selected count display
     */
    function updateBrowserCount() {
        var count = $( '#tp-sg-browser-list input[type="checkbox"]:checked' ).length;
        $( '#tp-sg-browser-selected-count' ).text( count + ' selected' );
    }

    /**
     * Apply browser selection to target input
     */
    function applyBrowserSelection() {
        var ids = [];
        $( '#tp-sg-browser-list input[type="checkbox"]:checked' ).each( function() {
            ids.push( $( this ).val() );
        });

        // Deduplicate (treatments may appear in multiple categories)
        var unique = [];
        $.each( ids, function( i, id ) {
            if ( $.inArray( id, unique ) === -1 ) {
                unique.push( id );
            }
        });

        $( '#' + browserTarget ).val( unique.join( ',' ) );

        // Show/hide clear button
        if ( unique.length > 0 ) {
            $( '.tp-sg-clear-ids-btn[data-target="' + browserTarget + '"]' ).show();
        } else {
            $( '.tp-sg-clear-ids-btn[data-target="' + browserTarget + '"]' ).hide();
        }

        closeBrowser();
        buildShortcode();
    }

    /**
     * Escape HTML for safe insertion
     */
    function escHtml( str ) {
        var div = document.createElement( 'div' );
        div.appendChild( document.createTextNode( str ) );
        return div.innerHTML;
    }

    /**
     * Escape string for use in HTML attributes
     */
    function escAttr( str ) {
        return escHtml( str ).replace( /"/g, '&quot;' );
    }

})( jQuery );
