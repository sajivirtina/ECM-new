<?php
/**
 * Masked lead payload — Privacy Compliance: Developer Requirements, items
 * 26-29.
 *
 * A masked payload contains exactly 5 fields and nothing else: care
 * category, care subcategory, city/state of the care location, timing, and
 * a scrubbed care_summary. No name, phone, email, street address, or raw
 * notes may appear anywhere in it — enforced here at the data-building
 * layer so every caller (distribution email, future unmask-window API)
 * gets the same guarantee rather than re-implementing the scrub.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Strips phone numbers, email addresses, street addresses, and the
 * submitter's own name from free-text notes. Works sentence-by-sentence:
 * a sentence containing any of those is dropped whole rather than leaving
 * a grammatically broken fragment behind (item 29) — e.g. "call me at
 * 555-123-4567, we're at 42 Oak Street, ask for Linda" (one sentence, no
 * terminal punctuation) becomes '' entirely once its phone/address/name
 * are found, rather than "call me at , we're at , ask for ".
 */
function ecm_scrub_care_summary( string $description, string $first_name, string $last_name, string $phone, string $email ) : string {
	$description = trim( $description );
	if ( '' === $description ) {
		return '';
	}

	$phone_pattern   = '/(\+?1[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}\b/';
	$email_pattern   = '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/';
	$address_pattern = '/\b\d+\s+[A-Za-z0-9.\'-]+(?:\s+[A-Za-z0-9.\'-]+){0,3}\s+(Street|St|Avenue|Ave|Road|Rd|Boulevard|Blvd|Lane|Ln|Drive|Dr|Court|Ct|Way|Trail|Trl|Circle|Cir|Place|Pl)\.?\b/i';

	$name_patterns = [];
	foreach ( [ $first_name, $last_name ] as $name_part ) {
		$name_part = trim( $name_part );
		if ( strlen( $name_part ) >= 2 ) {
			$name_patterns[] = '/\b' . preg_quote( $name_part, '/' ) . '\b/i';
		}
	}

	// Split into sentences on ./!/? followed by whitespace, or a literal
	// newline — keeps the delimiter out of each piece.
	$sentences = preg_split( '/(?<=[.!?])\s+|\n+/', $description, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! $sentences ) {
		$sentences = [ $description ];
	}

	$kept = [];
	foreach ( $sentences as $sentence ) {
		$has_pii = preg_match( $phone_pattern, $sentence )
			|| preg_match( $email_pattern, $sentence )
			|| preg_match( $address_pattern, $sentence );

		if ( ! $has_pii ) {
			foreach ( $name_patterns as $pattern ) {
				if ( preg_match( $pattern, $sentence ) ) {
					$has_pii = true;
					break;
				}
			}
		}

		if ( ! $has_pii ) {
			$kept[] = trim( $sentence );
		}
	}

	return trim( implode( ' ', $kept ) );
}

/**
 * Builds the masked payload for a request — exactly these 5 fields, never
 * more. The request is locked to one category per item 60, so this is the
 * same payload for every picked provider; it never includes name, phone,
 * email, street address, or raw notes.
 *
 * NOTE: the intake form only collects one care-type level today, not a
 * separate subcategory — 'subcategory' is a placeholder until the form
 * collects one.
 *
 * @return array{category:string,subcategory:string,location:string,timing:string,care_summary:string}
 */
function ecm_build_masked_payload( WP_Post $lead ) : array {
	$first_name  = (string) get_post_meta( $lead->ID, '_ecm_first_name', true );
	$last_name   = (string) get_post_meta( $lead->ID, '_ecm_last_name', true );
	$phone       = (string) get_post_meta( $lead->ID, '_ecm_phone', true );
	$email       = (string) get_post_meta( $lead->ID, '_ecm_email', true );
	$description = (string) get_post_meta( $lead->ID, '_ecm_description', true );
	$city        = (string) get_post_meta( $lead->ID, '_ecm_city', true );
	$state       = (string) get_post_meta( $lead->ID, '_ecm_state', true );
	$urgency     = (string) get_post_meta( $lead->ID, '_ecm_urgency', true );
	$care_type   = (string) get_post_meta( $lead->ID, '_ecm_care_type', true );

	return [
		'category'     => $care_type,
		'subcategory'  => '',
		'location'     => trim( $city . ( $state ? ', ' . $state : '' ), ', ' ),
		'timing'       => $urgency,
		'care_summary' => ecm_scrub_care_summary( $description, $first_name, $last_name, $phone, $email ),
	];
}
