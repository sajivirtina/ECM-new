<?php
/**
 * ACF fields for the Contact Us page template.
 */

defined( 'ABSPATH' ) || exit;

acf_add_local_field_group( [
	'key'      => 'group_ecm_ct',
	'title'    => 'Contact Us Content',
	'location' => [ [ [
		'param'    => 'page_template',
		'operator' => '==',
		'value'    => 'template-contact.php',
	] ] ],
	'menu_order'      => 0,
	'position'        => 'normal',
	'label_placement' => 'top',
	'active'          => true,
	'fields'          => [

		[ 'key' => 'field_ecm_ct_tab_hero', 'label' => 'Hero', 'type' => 'tab' ],
		[ 'key' => 'field_ecm_ct_title', 'label' => 'Title (use <em>)', 'name' => 'ct_title', 'type' => 'text', 'default_value' => 'Contact <em>Us</em>' ],
		[ 'key' => 'field_ecm_ct_sub', 'label' => 'Subtitle', 'name' => 'ct_sub', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Pick the option that fits and we will point you the right way.' ],

		[ 'key' => 'field_ecm_ct_tab_cards', 'label' => 'Cards', 'type' => 'tab' ],
		[
			'key' => 'field_ecm_ct_cards', 'label' => 'Cards', 'name' => 'ct_cards',
			'type' => 'repeater', 'layout' => 'block', 'min' => 0, 'button_label' => 'Add card',
			'instructions' => 'Leave empty to use the four default cards. Each card shows either a button (opens the Find Care request form, or links to a page) or an email address.',
			'sub_fields' => [
				[ 'key' => 'field_ecm_ct_card_icon', 'label' => 'Icon (an emoji)', 'name' => 'icon', 'type' => 'text', 'maxlength' => 4 ],
				[ 'key' => 'field_ecm_ct_card_title', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ],
				[ 'key' => 'field_ecm_ct_card_text', 'label' => 'Text', 'name' => 'text', 'type' => 'textarea', 'rows' => 2 ],
				[
					'key' => 'field_ecm_ct_card_action', 'label' => 'Card action', 'name' => 'action', 'type' => 'select', 'default_value' => 'link',
					'choices' => [
						'modal' => 'Button: open the Find Care request form',
						'link'  => 'Button: link to a page',
						'email' => 'Email address',
					],
				],
				[ 'key' => 'field_ecm_ct_card_label', 'label' => 'Button text', 'name' => 'label', 'type' => 'text' ],
				[ 'key' => 'field_ecm_ct_card_url', 'label' => 'Button link (e.g. /plans/)', 'name' => 'url', 'type' => 'text' ],
				[ 'key' => 'field_ecm_ct_card_email', 'label' => 'Email address', 'name' => 'email', 'type' => 'text' ],
				[ 'key' => 'field_ecm_ct_card_note', 'label' => 'Small note under the card', 'name' => 'note', 'type' => 'text' ],
			],
		],

		[ 'key' => 'field_ecm_ct_tab_form', 'label' => 'Contact Form', 'type' => 'tab' ],
		[ 'key' => 'field_ecm_ct_form_title', 'label' => 'Form heading', 'name' => 'ct_form_title', 'type' => 'text', 'default_value' => 'Send us a message' ],
		[ 'key' => 'field_ecm_ct_form_sub', 'label' => 'Text under the heading (optional)', 'name' => 'ct_form_sub', 'type' => 'text' ],
		[
			'key' => 'field_ecm_ct_form_id', 'label' => 'WPForms form ID', 'name' => 'ct_form_id', 'type' => 'number', 'min' => 0, 'step' => 1,
			'instructions' => 'The number from WPForms, All Forms, ID column. Leave empty to show no form.',
		],
	],
] );
