/**
 * Main theme JavaScript file
 *
 * Contains general theme functionality and enhancements.
 * Mobile menu is handled by navigation.js — do not duplicate here.
 */

jQuery(document).ready(function($) {
    'use strict';

    var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Smooth scrolling for anchor links
    // Exclude dashboard nav links — they have their own JS handler
    $('a[href*="#"]:not([href="#"]):not(.zeko-dashboard-nav a):not([class*="zeko-nav"])').on('click', function() {
        if (location.pathname.replace(/^[\/]/, '') == this.pathname.replace(/^[\/]/, '') &&
            location.hostname == this.hostname) {
            var target = $(this.hash);
            target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
            if (target.length) {
                if (prefersReducedMotion) {
                    $('html, body').scrollTop(target.offset().top - 100);
                } else {
                    $('html, body').animate({
                        scrollTop: target.offset().top - 100
                    }, 1000);
                }
                return false;
            }
        }
    });

    // Add active class to current menu item
    $('.main-navigation a').each(function() {
        if ($(this).attr('href') === window.location.href) {
            $(this).addClass('current-menu-item');
        }
    });

    // Back to top button
    var backToTopText = (typeof zekoData !== 'undefined' && zekoData.i18n && zekoData.i18n.backToTopText)
        ? zekoData.i18n.backToTopText
        : 'Back to Top';
    var backToTop = $('<button class="back-to-top" aria-label="' + backToTopText + '">' +
        '<span class="dashicons dashicons-arrow-up-alt"></span></button>');

    $('body').append(backToTop);

    $(window).scroll(function() {
        if ($(this).scrollTop() > 300) {
            backToTop.fadeIn();
        } else {
            backToTop.fadeOut();
        }
    });

    backToTop.on('click', function(e) {
        e.preventDefault();
        if (prefersReducedMotion) {
            $('html, body').scrollTop(0);
        } else {
            $('html, body').animate({scrollTop: 0}, 800);
        }
    });

    // ── Mobile dropdown toggle ──────────────────────────
    $('#primary-menu .menu-item-has-children > a').on('click', function(e) {
        if (window.innerWidth > 768) return;
        e.preventDefault();
        var subMenu = $(this).siblings('.sub-menu');
        $('#primary-menu .sub-menu').not(subMenu).removeClass('show');
        subMenu.toggleClass('show');
    });

    // Add responsive class to videos and iframes
    $('iframe[src*="youtube.com"], iframe[src*="vimeo.com"], object, embed').wrap('<div class="video-container" />');

    // Add placeholder for comment form if not present
    var commentPlaceholder = (typeof zekoData !== 'undefined' && zekoData.i18n && zekoData.i18n.commentPlaceholder)
        ? zekoData.i18n.commentPlaceholder
        : 'Enter your comment here...';
    if ($('#comment').length && $('#comment').attr('placeholder') === undefined) {
        $('#comment').attr('placeholder', commentPlaceholder);
    }

    // Add focus styles for keyboard navigation
    $('a, button, input, select, textarea').on('keydown', function(e) {
        if (e.which === 9) {
            $(this).addClass('keyboard-focus');
        }
    }).on('mouseup', function() {
        $(this).removeClass('keyboard-focus');
    });

    // Lazy loading for images
    if ('loading' in HTMLImageElement.prototype) {
        $('img.lazyload').each(function() {
            $(this).attr('loading', 'lazy');
        });
    } else {
        $('img.lazyload').each(function() {
            var img = $(this);
            img.attr('src', img.data('src'));
        });
    }

    // ── Mini-cart toggle ───────────────────────────────────
    $('.zeko-mini-cart-toggle').on('click', function(e) {
        e.stopPropagation();
        var wrapper = $(this).closest('.zeko-header-mini-cart');
        var isOpen = wrapper.hasClass('is-open');
        // Close all other open panels first.
        $('.zeko-header-mini-cart.is-open').not(wrapper).removeClass('is-open')
            .find('.zeko-mini-cart-toggle').attr('aria-expanded', 'false');
        wrapper.toggleClass('is-open', !isOpen);
        $(this).attr('aria-expanded', !isOpen);
    });

    // Close mini-cart when clicking outside.
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.zeko-header-mini-cart').length) {
            $('.zeko-header-mini-cart.is-open').removeClass('is-open')
                .find('.zeko-mini-cart-toggle').attr('aria-expanded', 'false');
        }
    });

    // ── Search panel toggle ────────────────────────────────
    $('.header-search-toggle').on('click', function() {
        var panel = $('#header-search-panel');
        var isOpen = panel.hasClass('is-open');
        panel.toggleClass('is-open', !isOpen);
        $(this).attr('aria-expanded', !isOpen);
        panel.attr('aria-hidden', isOpen);
        if (!isOpen) {
            panel.find('.search-field').focus();
        }
    });

    // Close search on ESC.
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            var panel = $('#header-search-panel');
            if (panel.hasClass('is-open')) {
                panel.removeClass('is-open').attr('aria-hidden', 'true');
                $('.header-search-toggle').attr('aria-expanded', 'false');
            }
            // Also close mini-cart.
            $('.zeko-header-mini-cart.is-open').removeClass('is-open')
                .find('.zeko-mini-cart-toggle').attr('aria-expanded', 'false');
        }
    });

    // Add external link indicator
    $('a[href^="http"]:not([href*="' + window.location.host + '"])').each(function() {
        $(this).addClass('external-link');
    });
});

// Window load events
jQuery(window).on('load', function() {
    setTimeout(function() {
        jQuery('body').addClass('loaded');
    }, 500);
});
