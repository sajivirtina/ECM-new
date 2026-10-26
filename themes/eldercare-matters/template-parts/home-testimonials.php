<?php
/**
 * Testimonials section — all content from ACF "Testimonials" tab.
 */

$tag          = ecm_get_field( 'testi_tag',      'Trusted by Families' );
$title        = ecm_get_field( 'testi_title',    'What Families Are Saying' );
$subtitle     = ecm_get_field( 'testi_subtitle', 'Real experiences from caregivers and families across the United States.' );
$testimonials = get_field( 'testimonials' );
?>

<!-- Testimonials -->
<section class="testimonials">
    <div class="section-header reveal section-header--testimonials">
        <div class="section-tag"><?php echo esc_html( $tag ); ?></div>
        <h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
        <p class="section-sub"><?php echo esc_html( $subtitle ); ?></p>
        <div class="section-divider"></div>
    </div>

    <div class="testi-grid">
        <?php if ( $testimonials ) : ?>
            <?php foreach ( $testimonials as $t ) :
                $stars    = $t['stars'] ?? '★★★★★';
                $quote    = $t['quote'] ?? '';
                $name     = $t['name'] ?? '';
                $location = $t['location'] ?? '';
                $avatar   = $t['avatar'] ?? [];
                $av_url   = $avatar['url'] ?? '';
                $av_alt   = $avatar['alt'] ?? $name;
            ?>
            <div class="testi-card reveal">
                <div class="testi-stars"><?php echo esc_html( $stars ); ?></div>
                <p class="testi-text">"<?php echo esc_html( $quote ); ?>"</p>
                <div class="testi-author">
                    <?php if ( $av_url ) : ?>
                    <div class="testi-avatar">
                        <img src="<?php echo esc_url( $av_url ); ?>" alt="<?php echo esc_attr( $av_alt ); ?>">
                    </div>
                    <?php endif; ?>
                    <div>
                        <div class="testi-name"><?php echo esc_html( $name ); ?></div>
                        <div class="testi-loc"><?php echo esc_html( $location ); ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        <?php else : ?>
        <!-- Fallback testimonials — add via ACF "Testimonials" tab -->
        <div class="testi-card reveal">
            <div class="testi-stars">★★★★★</div>
            <p class="testi-text">"We found an elder law attorney within 24 hours who helped us navigate guardianship for my father. The process was stress-free and completely free for us."</p>
            <div class="testi-author">
                <div class="testi-avatar">
                    <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=80&auto=format&fit=crop" alt="Jennifer L.">
                </div>
                <div>
                    <div class="testi-name">Jennifer L.</div>
                    <div class="testi-loc">Austin, TX</div>
                </div>
            </div>
        </div>
        <div class="testi-card reveal">
            <div class="testi-stars">★★★★★</div>
            <p class="testi-text">"I was overwhelmed trying to find memory care options for my mother. ElderCareMatters matched us with three excellent facilities in our area within the same day."</p>
            <div class="testi-author">
                <div class="testi-avatar">
                    <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=80&auto=format&fit=crop" alt="Michael R.">
                </div>
                <div>
                    <div class="testi-name">Michael R.</div>
                    <div class="testi-loc">Chicago, IL</div>
                </div>
            </div>
        </div>
        <div class="testi-card reveal">
            <div class="testi-stars">★★★★★</div>
            <p class="testi-text">"The chat advisor was surprisingly helpful — it asked exactly the right questions and within hours we had verified home care providers reaching out to us."</p>
            <div class="testi-author">
                <div class="testi-avatar">
                    <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=80&auto=format&fit=crop" alt="Sandra K.">
                </div>
                <div>
                    <div class="testi-name">Sandra K.</div>
                    <div class="testi-loc">Miami, FL</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
