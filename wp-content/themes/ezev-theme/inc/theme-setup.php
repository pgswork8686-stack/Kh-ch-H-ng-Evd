<?php
/**
 * EZEV Theme Setup & Supports
 */

if (!defined('ABSPATH')) { exit; }

function ezev_theme_setup(): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);
    add_theme_support('responsive-embeds');

    register_nav_menus([
        'primary' => __('Primary Navigation', 'ezev-theme'),
        'footer'  => __('Footer Links', 'ezev-theme'),
    ]);
}
add_action('after_setup_theme', 'ezev_theme_setup');