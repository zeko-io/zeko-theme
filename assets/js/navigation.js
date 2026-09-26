/**
 * File navigation.js.
 *
 * Handles toggling the navigation menu for small screens and enables TAB key
 * navigation support for dropdown menus.
 */
( function() {
    const siteNavigation = document.getElementById( 'site-navigation' );

    // Return early if the navigation don't exist.
    if ( siteNavigation ) {
        const button = siteNavigation.getElementsByTagName( 'button' )[ 0 ];

        // Return early if the button don't exist.
        if ( 'undefined' !== typeof button ) {
            const container = siteNavigation.querySelector( '.menu-primary-menu-container' );
            const menu = siteNavigation.querySelector( '#primary-menu' );

            // Hide menu toggle button if menu is empty and return early.
            if ( 'undefined' !== typeof menu ) {
                if ( ! menu.classList.contains( 'nav-menu' ) ) {
                    menu.classList.add( 'nav-menu' );
                }

                // Toggle the .toggled class and the aria-expanded value each time the button is clicked.
                button.addEventListener( 'click', function() {
                    siteNavigation.classList.toggle( 'toggled' );

                    if ( button.getAttribute( 'aria-expanded' ) === 'true' ) {
                        button.setAttribute( 'aria-expanded', 'false' );
                        if ( container ) container.classList.remove( 'show' );
                        menu.classList.remove( 'show' );
                    } else {
                        button.setAttribute( 'aria-expanded', 'true' );
                        if ( container ) container.classList.add( 'show' );
                        menu.classList.add( 'show' );
                    }
                } );

                // Get all the link elements within the menu.
                const links = menu.getElementsByTagName( 'a' );

                // Toggle focus each time a menu link is focused or blurred.
                for ( const link of links ) {
                    link.addEventListener( 'focus', toggleFocus, true );
                    link.addEventListener( 'blur', toggleFocus, true );
                }
            }
        }
    }

    // ── Header gear "more" menu ─────────────────────────
    const gear = document.querySelector( '.zeko-nav-gear' );
    if ( gear ) {
        const gearButton = gear.querySelector( '.zeko-nav-gear__toggle' );
        const gearPanel = gear.querySelector( '.zeko-nav-gear__panel' );

        if ( gearButton && gearPanel ) {
            gearButton.addEventListener( 'click', function( e ) {
                e.stopPropagation();
                const open = gear.classList.toggle( 'is-open' );
                gearButton.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
                gearPanel.setAttribute( 'aria-hidden', open ? 'false' : 'true' );
            } );
        }
    }

    // Close the mobile menu and the gear when clicking outside them.
    document.addEventListener( 'click', function( event ) {
        if ( siteNavigation && ! siteNavigation.contains( event.target ) ) {
            siteNavigation.classList.remove( 'toggled' );
            const button = siteNavigation.getElementsByTagName( 'button' )[ 0 ];
            if ( button ) {
                button.setAttribute( 'aria-expanded', 'false' );
            }
            const container = siteNavigation.querySelector( '.menu-primary-menu-container' );
            const menu = siteNavigation.querySelector( '#primary-menu' );
            if ( container ) container.classList.remove( 'show' );
            if ( menu ) menu.classList.remove( 'show' );
        }

        if ( gear && ! gear.contains( event.target ) ) {
            gear.classList.remove( 'is-open' );
            const gearButton = gear.querySelector( '.zeko-nav-gear__toggle' );
            const gearPanel = gear.querySelector( '.zeko-nav-gear__panel' );
            if ( gearButton ) gearButton.setAttribute( 'aria-expanded', 'false' );
            if ( gearPanel ) gearPanel.setAttribute( 'aria-hidden', 'true' );
        }
    } );

    // Close the menu and the gear with the Escape key.
    document.addEventListener( 'keydown', function( event ) {
        if ( 'Escape' === event.key ) {
            if ( siteNavigation && siteNavigation.classList.contains( 'toggled' ) ) {
                siteNavigation.classList.remove( 'toggled' );
                const button = siteNavigation.getElementsByTagName( 'button' )[ 0 ];
                if ( button ) {
                    button.setAttribute( 'aria-expanded', 'false' );
                    button.focus();
                }
                const container = siteNavigation.querySelector( '.menu-primary-menu-container' );
                const menu = siteNavigation.querySelector( '#primary-menu' );
                if ( container ) container.classList.remove( 'show' );
                if ( menu ) menu.classList.remove( 'show' );
            }

            if ( gear ) {
                gear.classList.remove( 'is-open' );
                const gearButton = gear.querySelector( '.zeko-nav-gear__toggle' );
                const gearPanel = gear.querySelector( '.zeko-nav-gear__panel' );
                if ( gearButton ) {
                    gearButton.setAttribute( 'aria-expanded', 'false' );
                    gearButton.focus();
                }
                if ( gearPanel ) gearPanel.setAttribute( 'aria-hidden', 'true' );
            }
        }
    } );

    /**
     * Sets or removes .focus class on an element.
     */
    function toggleFocus() {
        if ( event.type === 'focus' || event.type === 'blur' ) {
            let self = this;
            // Move up through the ancestors of the current link until we hit .nav-menu.
            while ( ! self.classList.contains( 'nav-menu' ) ) {
                // On li elements toggle the class .focus.
                if ( 'li' === self.tagName.toLowerCase() ) {
                    self.classList.toggle( 'focus' );
                }
                self = self.parentNode;
            }
        }
    }
}() );