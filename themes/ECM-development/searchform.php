<?php
/**
 * Search form template.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

$search_query = get_search_query();
?>

<form class="ecm-site-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
    <label class="screen-reader-text" for="ecm-site-search-field">
        <?php esc_html_e( 'Search for:', 'eldercare-matters' ); ?>
    </label>
    <div class="ecm-site-search__fields">
        <input
            id="ecm-site-search-field"
            class="ecm-site-search__input"
            type="search"
            name="s"
            value="<?php echo esc_attr( $search_query ); ?>"
            placeholder="<?php esc_attr_e( 'Search guides, providers, and care topics', 'eldercare-matters' ); ?>"
        >
        <button class="ecm-site-search__button" type="submit">
            <?php esc_html_e( 'Search', 'eldercare-matters' ); ?>
        </button>
    </div>
</form>
