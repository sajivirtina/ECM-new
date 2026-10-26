<?php
/**
 * ACF fields for the Search ("Find Care") page template.
 * The search bar, category chips, and provider grid are rendered by search.js.
 */

defined( 'ABSPATH' ) || exit;

acf_add_local_field_group( [
    'key'      => 'group_ecm_search',
    'title'    => 'Search Page Content',
    'location' => [ [ [
        'param'    => 'page_template',
        'operator' => '==',
        'value'    => 'template-search.php',
    ] ] ],
    'menu_order'      => 0,
    'position'        => 'normal',
    'label_placement' => 'top',
    'active'          => true,
    'fields'          => [
        [ 'key' => 'field_ecm_srch_tab_hero', 'label' => 'Hero', 'type' => 'tab' ],
        [
            'key' => 'field_ecm_srch_title_prefix', 'label' => 'Hero Title (text before location)',
            'name' => 'srch_title_prefix', 'type' => 'text', 'default_value' => 'Find the right care in',
        ],
        [
            'key' => 'field_ecm_srch_sub', 'label' => 'Hero Subtitle',
            'name' => 'srch_sub', 'type' => 'text',
            'default_value' => 'Search verified elder care providers by need, city, or name.',
        ],
        [
            'key' => 'field_ecm_srch_placeholder', 'label' => 'Search Input Placeholder',
            'name' => 'srch_placeholder', 'type' => 'text', 'default_value' => "Try 'memory care' or 'Sunrise'…",
        ],

        [ 'key' => 'field_ecm_srch_tab_ctaband', 'label' => 'CTA Band', 'type' => 'tab' ],
        [
            'key' => 'field_ecm_srch_cb_title', 'label' => 'Title (use <em>)',
            'name' => 'srch_cb_title', 'type' => 'text', 'default_value' => "Can't find the right fit? <em>We'll match you.</em>",
        ],
        [
            'key' => 'field_ecm_srch_cb_sub', 'label' => 'Subtitle',
            'name' => 'srch_cb_sub', 'type' => 'text',
            'default_value' => "Tell us what you need, we'll hand-pick up to 3 verified providers.",
        ],
        [
            'key' => 'field_ecm_srch_cb_primary', 'label' => 'Primary Button Text',
            'name' => 'srch_cb_primary', 'type' => 'text', 'default_value' => '📋 Start Your Free Match →',
        ],
        [
            'key' => 'field_ecm_srch_cb_secondary', 'label' => 'Secondary Button Text',
            'name' => 'srch_cb_secondary', 'type' => 'text', 'default_value' => '💬 Chat with advisor',
        ],
    ],
] );
