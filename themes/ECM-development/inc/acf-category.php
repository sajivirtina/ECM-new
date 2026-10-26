<?php
/**
 * ACF fields for the Category page template.
 * Static/editable parts only — provider grid, breadcrumb, hero title, and
 * category description are rendered dynamically by category.js from data.js
 * based on the ?type= and ?city= query parameters.
 */

defined( 'ABSPATH' ) || exit;

acf_add_local_field_group( [
    'key'      => 'group_ecm_category',
    'title'    => 'Category Page Content',
    'location' => [ [ [
        'param'    => 'page_template',
        'operator' => '==',
        'value'    => 'template-category.php',
    ] ] ],
    'menu_order'      => 0,
    'position'        => 'normal',
    'label_placement' => 'top',
    'active'          => true,
    'fields'          => [

        [ 'key' => 'field_ecm_cat_tab_hero', 'label' => 'Hero', 'type' => 'tab' ],
        [
            'key' => 'field_ecm_catp_cta_text', 'label' => 'Hero CTA Button Text',
            'name' => 'catp_cta_text', 'type' => 'text', 'default_value' => '📋 Find Providers',
        ],
        [
            'key' => 'field_ecm_catp_chat_text', 'label' => 'Chat Link Text',
            'name' => 'catp_chat_text', 'type' => 'text', 'default_value' => '💬 Talk to Carrie',
        ],
        [
            'key' => 'field_ecm_catp_view_all_text', 'label' => 'View All Providers Link Text',
            'name' => 'catp_view_all_text', 'type' => 'text', 'default_value' => 'View All Providers ↓',
        ],
        [
            'key' => 'field_ecm_catp_meta', 'label' => 'Hero Meta Items', 'name' => 'catp_meta',
            'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 6,
            'sub_fields' => [
                [ 'key' => 'field_ecm_catp_meta_icon', 'label' => 'Icon', 'name' => 'icon', 'type' => 'text' ],
                [ 'key' => 'field_ecm_catp_meta_text', 'label' => 'Text', 'name' => 'text', 'type' => 'text' ],
            ],
        ],

        [ 'key' => 'field_ecm_cat_tab_providers', 'label' => 'Providers Section', 'type' => 'tab' ],
        [
            'key' => 'field_ecm_catp_providers_subtitle', 'label' => 'Providers Section Subtitle',
            'name' => 'catp_providers_subtitle', 'type' => 'text',
            'default_value' => 'Sorted by tier and rating. Click any card to see full details.',
        ],

        [ 'key' => 'field_ecm_cat_tab_guides', 'label' => 'Guides Section', 'type' => 'tab' ],
        [
            'key' => 'field_ecm_catp_guides_subtitle', 'label' => 'Guides Subtitle',
            'name' => 'catp_guides_subtitle', 'type' => 'text',
            'default_value' => 'Short, practical reads for families caring for someone they love.',
        ],
        [
            'key' => 'field_ecm_catp_guides', 'label' => 'Guide Cards', 'name' => 'catp_guides',
            'type' => 'repeater', 'layout' => 'block', 'min' => 0, 'max' => 6,
            'sub_fields' => [
                [ 'key' => 'field_ecm_catp_guide_img', 'label' => 'Image', 'name' => 'image', 'type' => 'image', 'return_format' => 'array', 'preview_size' => 'thumbnail' ],
                [ 'key' => 'field_ecm_catp_guide_tag', 'label' => 'Tag', 'name' => 'tag', 'type' => 'text' ],
                [ 'key' => 'field_ecm_catp_guide_title', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ],
                [ 'key' => 'field_ecm_catp_guide_excerpt', 'label' => 'Excerpt', 'name' => 'excerpt', 'type' => 'textarea', 'rows' => 2 ],
                [ 'key' => 'field_ecm_catp_guide_read', 'label' => 'Read Time', 'name' => 'read_time', 'type' => 'text' ],
                [ 'key' => 'field_ecm_catp_guide_link', 'label' => 'Link URL', 'name' => 'link', 'type' => 'text' ],
            ],
        ],

        [ 'key' => 'field_ecm_cat_tab_ctaband', 'label' => 'CTA Band', 'type' => 'tab' ],
        [
            'key' => 'field_ecm_catp_ctaband_title', 'label' => 'Title (use <em> for emphasis)',
            'name' => 'catp_ctaband_title', 'type' => 'text', 'default_value' => "Not sure who <em>to pick?</em>",
        ],
        [
            'key' => 'field_ecm_catp_ctaband_sub', 'label' => 'Subtitle',
            'name' => 'catp_ctaband_sub', 'type' => 'text',
            'default_value' => "Answer a few questions and we'll show you verified providers near your loved one. You choose up to 10.",
        ],
        [
            'key' => 'field_ecm_catp_ctaband_primary', 'label' => 'Primary Button Text',
            'name' => 'catp_ctaband_primary', 'type' => 'text', 'default_value' => '📋 Start Your Free Match →',
        ],
        [
            'key' => 'field_ecm_catp_ctaband_secondary', 'label' => 'Secondary Button Text',
            'name' => 'catp_ctaband_secondary', 'type' => 'text', 'default_value' => '💬 Chat with advisor',
        ],
    ],
] );
