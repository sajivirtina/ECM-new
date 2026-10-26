<?php
/**
 * Theme override of wp-job-manager-listing-payments'
 * resources/templates/package-selection.php — confirmed theme-overridable
 * from the plugin's own get_job_manager_template() call:
 *   get_job_manager_template( 'package-selection.php', $args, 'listing-payments', ... );
 * → override path is yourtheme/listing-payments/package-selection.php
 * (WPJM's standard "yourtheme / $template_path / $template_name" rule).
 *
 * The "Purchase Package" section is suppressed only inside the provider
 * dashboard's own embedded "+ Add new listing" flow
 * (/provider-dashboard/listings/?action=add, detected via
 * pd_is_dashboard_request()) — that flow only lets a provider consume a
 * package they already own, since it's reached by someone who's already a
 * provider. Everywhere else (the public /add-your-listing/ page — the
 * entry point for brand-new sign-ups via login.php/register.php, or anyone
 * landing on it directly) this renders the original plugin section so a
 * package-less new user can actually pick and buy one instead of hitting a
 * dead end. Confirmed live: a fresh subscriber account with zero packages
 * visiting /add-your-listing/ directly saw "You don't have any packages
 * available to use yet." with nothing purchasable — PS2-I50.
 *
 * Field names/values ("job_package", "user-{id}") kept byte-for-byte
 * identical to the original so the rest of the plugin's flow (which reads
 * $_POST['job_package'] and expects that exact "user-{id}" format) works
 * completely unchanged.
 *
 * @var array $packages      WC Products purchasable.
 * @var array $user_packages User's already-owned packages.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

$pd_embedded_add = function_exists( 'pd_is_dashboard_request' ) && pd_is_dashboard_request();

/**
 * astoundify_wpjmlp_get_user_packages() returns one row per individually
 * PURCHASED package instance, not one per product — a provider who bought
 * "Basic Listing" 3 separate times (3 distinct orders, each still unused)
 * gets 3 identical-looking "Basic Listing / 0 jobs posted out of 1" radio
 * options with nothing to tell them apart. Collapses those into one radio
 * per distinct product, with an "(N available)" suffix when there's more
 * than one — still submits as a specific "user-{key}" package row (the
 * first one in the group; any instance of the same product is equally
 * usable), so the rest of the plugin's flow is untouched.
 *
 * @param array $raw_user_packages Keyed exactly like $user_packages.
 * @return array Same shape, deduplicated by product ID.
 */
if ( ! function_exists( 'ecm_dedupe_user_packages' ) ) {
	function ecm_dedupe_user_packages( array $raw_user_packages ) : array {
		$grouped = [];
		foreach ( $raw_user_packages as $key => $candidate ) {
			$product_id = astoundify_wpjmlp_get_package( $candidate )->get_product_id();
			if ( ! isset( $grouped[ $product_id ] ) ) {
				$grouped[ $product_id ] = [ 'key' => $key, 'package' => $candidate, 'count' => 0 ];
			}
			$grouped[ $product_id ]['count']++;
		}
		$deduped = [];
		foreach ( $grouped as $group ) {
			$deduped[ $group['key'] ] = $group['package'];
			$deduped[ $group['key'] ]->ecm_available_count = $group['count']; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments -- attaching a display-only property to the DB-row object.
		}
		return $deduped;
	}
}
$user_packages = $user_packages ? ecm_dedupe_user_packages( $user_packages ) : $user_packages;
?>

<?php if ( $pd_embedded_add ) : ?>
	<?php if ( $user_packages ) :
	$get_package = isset( $_GET['selected_package'] ) ? intval( $_GET['selected_package'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, pre-selects a radio option.

	// Default to the visitor's Free package specifically when nothing was
	// explicitly requested — previously just checked whichever owned package
	// happened to render first, so a provider with multiple owned packages
	// landing here directly (not through a link carrying ?choose_package,
	// e.g. a bookmark or typing the URL) could land on Premium/Featured
	// pre-selected by accident. Confirmed live on staging.
	$free_product_id = function_exists( 'ecm_package_product_ids' ) ? ( ecm_package_product_ids()['free'] ?? 0 ) : 0;
	$default_key     = null;
	if ( ! $get_package && $free_product_id ) {
		foreach ( $user_packages as $key => $candidate ) {
			if ( $free_product_id === astoundify_wpjmlp_get_package( $candidate )->get_product_id() ) {
				$default_key = $key;
				break;
			}
		}
	}

	$checked = $get_package ? 0 : 1; // unchanged fallback for when there's no Free package to default to
	?>
	<ul class="job_packages">
		<li class="package-section"><?php esc_html_e( 'Your Packages:', 'wp-job-manager-listing-payments' ); ?></li>
		<?php foreach ( $user_packages as $key => $raw_package ) :
			$available_count = $raw_package->ecm_available_count ?? 1;
			$package         = astoundify_wpjmlp_get_package( $raw_package );
			$is_checked      = null !== $default_key ? ( $key === $default_key ) : ( 1 === $checked );
			?>
			<li class="user-job-package">
				<input type="radio" <?php checked( $is_checked, true ); ?> name="job_package" value="user-<?php echo esc_attr( $key ); ?>" id="user-package-<?php echo esc_attr( $package->get_id() ); ?>" />
				<label for="user-package-<?php echo esc_attr( $package->get_id() ); ?>">
					<?php echo esc_html( $package->get_title() ); ?>
					<?php if ( $available_count > 1 ) : ?>
						<?php echo esc_html( sprintf( '(%d available)', $available_count ) ); ?>
					<?php endif; ?>
				</label><br/>
				<?php
					if ( $package->get_limit() ) {
						$total_limit = $package->get_limit() * $available_count;
						printf( esc_html( _n( '%1$s listing used out of %2$d', '%1$s listings used out of %2$d', $package->get_count(), 'wp-job-manager-listing-payments' ) ), esc_html( $package->get_count() ), esc_html( $total_limit ) );
					} else {
						printf( esc_html( _n( '%s listing used', '%s listings used', $package->get_count(), 'wp-job-manager-listing-payments' ) ), esc_html( $package->get_count() ) );
					}
					if ( $package->get_duration() ) {
						printf( ', ' . esc_html( _n( 'listed for %s day', 'listed for %s days', $package->get_duration(), 'wp-job-manager-listing-payments' ) ), esc_html( $package->get_duration() ) );
					}
					$checked = 0;
				?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php else : ?>
		<p>
			<?php esc_html_e( "You don't have any packages available to use yet.", 'eldercare-matters' ); ?>
			<a href="<?php echo esc_url( home_url( '/plans/' ) ); ?>">
				<?php esc_html_e( 'Purchase a package →', 'eldercare-matters' ); ?>
			</a>
		</p>
	<?php endif; ?>

<?php elseif ( $packages || $user_packages ) :
	// Original plugin template's own logic (resources/templates/package-selection.php),
	// restored as-is for this public-page branch — including its own default-radio
	// behavior, which the dashboard branch above intentionally overrides but this one
	// doesn't need to (a first-time visitor has no owned package to default to anyway).
	$get_package = isset( $_GET['selected_package'] ) ? intval( $_GET['selected_package'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, pre-selects a radio option.
	$checked     = 1;
	?>
	<ul class="job_packages">
		<?php if ( $user_packages ) :
			$checked = $get_package ? 0 : 1;
			?>
			<li class="package-section"><?php esc_html_e( 'Your Packages:', 'wp-job-manager-listing-payments' ); ?></li>
			<?php foreach ( $user_packages as $key => $raw_package ) :
				$available_count = $raw_package->ecm_available_count ?? 1;
				$package         = astoundify_wpjmlp_get_package( $raw_package );
				?>
				<li class="user-job-package">
					<input type="radio" <?php checked( $checked, 1 ); ?> name="job_package" value="user-<?php echo esc_attr( $key ); ?>" id="user-package-<?php echo esc_attr( $package->get_id() ); ?>" />
					<label for="user-package-<?php echo esc_attr( $package->get_id() ); ?>">
						<?php echo esc_html( $package->get_title() ); ?>
						<?php if ( $available_count > 1 ) : ?>
							<?php echo esc_html( sprintf( '(%d available)', $available_count ) ); ?>
						<?php endif; ?>
					</label><br/>
					<?php
						if ( $package->get_limit() ) {
							$total_limit = $package->get_limit() * $available_count;
							printf( esc_html( _n( '%1$s listing used out of %2$d', '%1$s listings used out of %2$d', $package->get_count(), 'wp-job-manager-listing-payments' ) ), esc_html( $package->get_count() ), esc_html( $total_limit ) );
						} else {
							printf( esc_html( _n( '%s listing used', '%s listings used', $package->get_count(), 'wp-job-manager-listing-payments' ) ), esc_html( $package->get_count() ) );
						}
						if ( $package->get_duration() ) {
							printf( ', ' . esc_html( _n( 'listed for %s day', 'listed for %s days', $package->get_duration(), 'wp-job-manager-listing-payments' ) ), esc_html( $package->get_duration() ) );
						}
						$checked = 0;
					?>
				</li>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php if ( $packages ) :
			$checked = $get_package ? $get_package : $checked;
			?>
			<li class="package-section"><?php esc_html_e( 'Purchase Package:', 'wp-job-manager-listing-payments' ); ?></li>
			<?php
			// Card-style key for each internal package key, matching Plans &
			// Pricing's own pl_cards 'style' field (template-plans-pricing.php) —
			// 'standard'/'premium' display as "Premium"/"Featured" there, a
			// long-documented naming mismatch in this codebase.
			$pkg_card_styles = [ 'free' => 'free', 'basic' => 'basic', 'standard' => 'popular', 'premium' => 'featured' ];
			foreach ( $packages as $key => $package ) :
				$product = wc_get_product( $package );
				if ( ! $product || ! $product->is_type( [ 'job_package', 'job_package_subscription' ] ) || ! $product->is_purchasable() ) {
					continue;
				}
				$pid = $get_package ? $product->get_id() : 1;

				// Real, admin-controlled name/price/tagline from Plans & Pricing's
				// Featured card (ecm_get_plan_card(), inc/plan-cards.php) instead of
				// this WC product's own excerpt — that excerpt is old filler copy
				// ("This is the the Basic Listing package...") with no real price
				// shown for anything but Free. Falls back to the WC product's own
				// title/price if no matching card is found (e.g. content not filled
				// in yet), so this never renders blank.
				$pkg_internal_key = array_search( $product->get_id(), function_exists( 'ecm_package_product_ids' ) ? ecm_package_product_ids() : [], true );
				$pkg_card         = $pkg_internal_key ? ecm_get_plan_card( $pkg_card_styles[ $pkg_internal_key ] ?? '' ) : null;
				$pkg_name         = $pkg_card['name'] ?? $product->get_title();
				$pkg_tagline      = $pkg_card['tagline'] ?? '';
				if ( $pkg_card ) {
					$pkg_price_line = ( '0' === (string) ( $pkg_card['price'] ?? '' ) )
						? esc_html__( 'Free', 'eldercare-matters' )
						: '$' . esc_html( $pkg_card['price'] ) . ' ' . esc_html( $pkg_card['price_unit'] ?? '/ month' );
				} else {
					$pkg_price_line = $product->get_price_html() ? wp_kses_post( $product->get_price_html() ) : esc_html__( 'Free', 'wp-job-manager-listing-payments' );
				}
				?>
				<li class="job-package">
					<input type="radio" <?php checked( $checked, $pid ); ?> name="job_package" value="<?php echo esc_attr( $product->get_id() ); ?>" id="package-<?php echo esc_attr( $product->get_id() ); ?>" />
					<label for="package-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $pkg_name ); ?></label><br/>
					<?php echo wp_kses_post( $pkg_price_line ); ?>
					<?php if ( $pkg_tagline ) : ?>
						 — <?php echo esc_html( $pkg_tagline ); ?>
					<?php endif; ?>
					<?php if ( ! $get_package ) { $checked = 0; } ?>
				</li>
			<?php endforeach; ?>
		<?php endif; ?>
	</ul>
<?php else : ?>
	<p><?php esc_html_e( 'No packages found', 'wp-job-manager-listing-payments' ); ?></p>
<?php endif; ?>
