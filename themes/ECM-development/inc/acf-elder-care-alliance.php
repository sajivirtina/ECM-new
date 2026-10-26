<?php
/**
 * ACF fields for the Elder Care Matters Alliance page template.
 */

defined( 'ABSPATH' ) || exit;

acf_add_local_field_group( [
	'key'      => 'group_ecm_alc',
	'title'    => 'Elder Care Matters Alliance Content',
	'location' => [ [ [
		'param'    => 'page_template',
		'operator' => '==',
		'value'    => 'template-elder-care-alliance.php',
	] ] ],
	'menu_order'      => 0,
	'position'        => 'normal',
	'label_placement' => 'top',
	'active'          => true,
	'fields'          => [

		[ 'key' => 'field_ecm_alc_tab_hero', 'label' => 'Hero', 'type' => 'tab' ],
		[ 'key' => 'field_ecm_alc_title', 'label' => 'Title', 'name' => 'alc_title', 'type' => 'text', 'default_value' => 'The Elder Care Matters Alliance' ],

		[ 'key' => 'field_ecm_alc_tab_content', 'label' => 'Alliance Description', 'type' => 'tab' ],
		[ 'key' => 'field_ecm_alc_heading', 'label' => 'Heading', 'name' => 'alc_heading', 'type' => 'text', 'default_value' => 'National Network of America’s TOP Elder Care Professionals' ],
		[ 'key' => 'field_ecm_alc_intro', 'label' => 'Intro Paragraph', 'name' => 'alc_intro', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'The ElderCare Matters Alliance is a national, multidisciplinary network of Elder Care Professionals who help American families plan for and deal with a wide range of Elder Care issues. This professional alliance consists of:' ],
		[
			'key' => 'field_ecm_alc_items', 'label' => 'Professional Types', 'name' => 'alc_items',
			'type' => 'repeater', 'layout' => 'table', 'min' => 0,
			'sub_fields' => [
				[ 'key' => 'field_ecm_alc_item_text', 'label' => 'Item', 'name' => 'item', 'type' => 'text' ],
			],
		],
		[ 'key' => 'field_ecm_alc_body', 'label' => 'Body (blank line = new paragraph)', 'name' => 'alc_body', 'type' => 'textarea', 'rows' => 5, 'default_value' => "The members of the ElderCare Matters Alliance are some of America’s TOP Elder Care Experts, with years of experience providing families with the help that they need to plan for and deal with their Issues of Aging.\n\nMembership in this Elder Care Alliance will help you promote your expertise in Elder Care Matters to hundreds of thousands of families and will provide you with the opportunity to have your articles about Elder Care issues published on ElderCareMatters.com – America’s National Directory of Elder Care Resources." ],
		[ 'key' => 'field_ecm_alc_reminder', 'label' => 'Closing Reminder', 'name' => 'alc_reminder', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'REMINDER – Membership in the ElderCare Matters Alliance is provided FREE of Charge when you get a Featured Listing on ElderCareMatters.com (details below).' ],

		[ 'key' => 'field_ecm_alc_tab_widget', 'label' => 'Featured Listing Widget', 'type' => 'tab' ],
		[ 'key' => 'field_ecm_alc_fw_note', 'label' => 'Note', 'name' => 'alc_fw_note', 'type' => 'message', 'message' => 'The popup itself (price/features/button) is shared with the Featured Directory Listings page and comes from the Plans & Pricing page\'s Featured card — edit it there. This only controls the CTA band text that opens it.' ],
		[ 'key' => 'field_ecm_alc_cta_title', 'label' => 'CTA Band Title', 'name' => 'alc_cta_title', 'type' => 'text', 'default_value' => 'Want to join the Alliance?' ],
		[ 'key' => 'field_ecm_alc_cta_sub', 'label' => 'CTA Band Subtitle', 'name' => 'alc_cta_sub', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Get a Featured Listing to unlock free Alliance membership, "Featured / Top of Page" placement, and more.' ],
	],
] );
