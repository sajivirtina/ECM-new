<?php
/**
 * ACF fields for the Blog Detail (single article) page template.
 */

defined( 'ABSPATH' ) || exit;

acf_add_local_field_group( [
    'key'      => 'group_ecm_blog_detail',
    'title'    => 'Blog Article Content',
    'location' => [ [ [
        'param'    => 'page_template',
        'operator' => '==',
        'value'    => 'template-blog-detail.php',
    ] ] ],
    'menu_order'      => 0,
    'position'        => 'normal',
    'label_placement' => 'top',
    'active'          => true,
    'fields'          => [
        [ 'key' => 'field_ecm_bd_tab_header', 'label' => 'Article Header', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_bd_cat', 'label' => 'Category Label', 'name' => 'bd_category', 'type' => 'text', 'default_value' => 'Home Care' ],
        [
            'key' => 'field_ecm_bd_catclass', 'label' => 'Category Color', 'name' => 'bd_cat_class', 'type' => 'select',
            'choices' => [
                'blog-cat--home-care' => 'Home Care (green)',
                'blog-cat--assisted-living' => 'Assisted Living (blue)',
                'blog-cat--memory-care' => 'Memory Care (orange)',
                'blog-cat--legal' => 'Legal (purple)',
                'blog-cat--hospice' => 'Hospice (pink)',
                'blog-cat--checklist' => 'Checklist (teal)',
            ],
            'default_value' => 'blog-cat--home-care',
        ],
        [ 'key' => 'field_ecm_bd_title', 'label' => 'Article Title', 'name' => 'bd_title', 'type' => 'text', 'default_value' => 'The Complete Guide to Home Care in 2026' ],
        [ 'key' => 'field_ecm_bd_meta', 'label' => 'Meta line (author · date · read · tag)', 'name' => 'bd_meta', 'type' => 'text', 'default_value' => '✍️ ECM Editorial Team · March 15, 2026 · ⏱ 8 min read · 🔖 Free guide' ],

        [ 'key' => 'field_ecm_bd_tab_body', 'label' => 'Article Body', 'type' => 'tab' ],
        [
            'key' => 'field_ecm_bd_body', 'label' => 'Body Content', 'name' => 'bd_body',
            'type' => 'wysiwyg', 'tabs' => 'all', 'toolbar' => 'full', 'media_upload' => 1,
            'instructions' => 'Full article HTML. Leave empty to use the prototype sample article.',
        ],

        [ 'key' => 'field_ecm_bd_tab_related', 'label' => 'Related Guides', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_bd_related_sub', 'label' => 'Subtitle', 'name' => 'bd_related_sub', 'type' => 'text', 'default_value' => 'Continue reading to make the most informed decision for your family.' ],
        [
            'key' => 'field_ecm_bd_related', 'label' => 'Related Cards', 'name' => 'bd_related',
            'type' => 'repeater', 'layout' => 'block', 'min' => 0, 'max' => 6,
            'sub_fields' => [
                [ 'key' => 'field_ecm_bd_rel_icon', 'label' => 'Icon', 'name' => 'icon', 'type' => 'text' ],
                [ 'key' => 'field_ecm_bd_rel_bg', 'label' => 'Icon Background', 'name' => 'icon_bg', 'type' => 'text', 'default_value' => '#e6f4ea' ],
                [ 'key' => 'field_ecm_bd_rel_cat', 'label' => 'Category Label', 'name' => 'category', 'type' => 'text' ],
                [
                    'key' => 'field_ecm_bd_rel_catclass', 'label' => 'Category Color', 'name' => 'cat_class', 'type' => 'select',
                    'choices' => [
                        'blog-cat--home-care' => 'Home Care', 'blog-cat--assisted-living' => 'Assisted Living',
                        'blog-cat--memory-care' => 'Memory Care', 'blog-cat--legal' => 'Legal',
                        'blog-cat--hospice' => 'Hospice', 'blog-cat--checklist' => 'Checklist',
                    ],
                    'default_value' => 'blog-cat--home-care',
                ],
                [ 'key' => 'field_ecm_bd_rel_title', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ],
                [ 'key' => 'field_ecm_bd_rel_excerpt', 'label' => 'Excerpt', 'name' => 'excerpt', 'type' => 'textarea', 'rows' => 2 ],
                [ 'key' => 'field_ecm_bd_rel_meta', 'label' => 'Footer Meta', 'name' => 'meta', 'type' => 'text' ],
                [ 'key' => 'field_ecm_bd_rel_link', 'label' => 'Link', 'name' => 'link', 'type' => 'text' ],
            ],
        ],

        [ 'key' => 'field_ecm_bd_tab_ctaband', 'label' => 'CTA Band', 'type' => 'tab' ],
        [ 'key' => 'field_ecm_bd_cb_title', 'label' => 'Title (use <em>)', 'name' => 'bd_cb_title', 'type' => 'text', 'default_value' => "Ready to find home care? <em>Tell us what you need.</em>" ],
        [ 'key' => 'field_ecm_bd_cb_sub', 'label' => 'Subtitle', 'name' => 'bd_cb_sub', 'type' => 'text', 'default_value' => 'Tell us what you need and choose the providers you want to hear from. Takes about 2 minutes.' ],
        [ 'key' => 'field_ecm_bd_cb_primary', 'label' => 'Primary Button', 'name' => 'bd_cb_primary', 'type' => 'text', 'default_value' => '📋 Start Your Free Match →' ],
        [ 'key' => 'field_ecm_bd_cb_secondary', 'label' => 'Secondary Button', 'name' => 'bd_cb_secondary', 'type' => 'text', 'default_value' => '💬 Chat with advisor' ],
    ],
] );
