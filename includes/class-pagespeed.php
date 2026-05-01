<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSS_PageSpeed {

    const OPTION_RESULTS = 'mss_pagespeed_results';
    const OPTION_HISTORY = 'mss_pagespeed_history';

    public static function init() {
        add_action( 'wp_ajax_mss_run_pagespeed', array( __CLASS__, 'ajax_run_scan' ) );
    }

    public static function ajax_run_scan() {
        check_ajax_referer( 'mss_pagespeed_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        }

        $url      = esc_url_raw( $_POST['url'] ?? get_home_url() );
        $strategy = in_array( $_POST['strategy'] ?? 'mobile', array( 'mobile', 'desktop' ), true )
                    ? $_POST['strategy'] : 'mobile';
        $opts     = get_option( AAG_OPTION, array() );
        $api_key  = $opts['pagespeed_key'] ?? '';

        $api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
            . '?url=' . urlencode( $url )
            . '&strategy=' . $strategy
            . '&category=performance&category=seo&category=accessibility&category=best-practices'
            . ( $api_key ? '&key=' . urlencode( $api_key ) : '' );

        $response = wp_remote_get( $api_url, array( 'timeout' => 60 ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'API Fehler (HTTP ' . $code . ')';
            wp_send_json_error( array( 'message' => $msg ) );
        }

        $result = self::parse_results( $body, $url, $strategy );

        update_option( self::OPTION_RESULTS, $result, false );

        $history   = get_option( self::OPTION_HISTORY, array() );
        $history[] = array_merge( $result, array( 'scanned_at' => current_time('mysql') ) );
        $history   = array_slice( $history, -10 );
        update_option( self::OPTION_HISTORY, $history, false );

        wp_send_json_success( $result );
    }

    private static function parse_results( array $body, string $url, string $strategy ): array {
        $cats   = $body['lighthouseResult']['categories'] ?? array();
        $audits = $body['lighthouseResult']['audits']     ?? array();

        $priority_audits = array(
            'render-blocking-resources', 'unused-css-rules', 'unused-javascript',
            'uses-optimized-images', 'uses-webp-images', 'uses-text-compression',
            'efficiently-encode-images', 'meta-description', 'document-title',
            'image-alt', 'link-text', 'crawlable-anchors',
        );

        $recommendations = array();
        foreach ( $priority_audits as $audit_id ) {
            if ( ! isset( $audits[ $audit_id ] ) ) continue;
            $a = $audits[ $audit_id ];
            if ( ( $a['score'] ?? 1 ) >= 0.9 ) continue;

            $savings = '';
            if ( isset( $a['details']['overallSavingsMs'] ) ) {
                $savings = round( $a['details']['overallSavingsMs'] ) . 'ms';
            } elseif ( isset( $a['details']['overallSavingsBytes'] ) ) {
                $savings = round( $a['details']['overallSavingsBytes'] / 1024 ) . 'KB';
            }

            $recommendations[] = array(
                'id'          => $audit_id,
                'title'       => $a['title']       ?? $audit_id,
                'description' => $a['description'] ?? '',
                'score'       => $a['score']        ?? 0,
                'savings'     => $savings,
                'category'    => self::audit_category( $audit_id ),
            );
        }

        usort( $recommendations, function( $a, $b ) {
            return ( $a['score'] <=> $b['score'] );
        } );

        return array(
            'url'            => $url,
            'strategy'       => $strategy,
            'performance'    => round( ( $cats['performance']['score']    ?? 0 ) * 100 ),
            'seo'            => round( ( $cats['seo']['score']            ?? 0 ) * 100 ),
            'accessibility'  => round( ( $cats['accessibility']['score']  ?? 0 ) * 100 ),
            'best_practices' => round( ( $cats['best-practices']['score'] ?? 0 ) * 100 ),
            'fcp'            => $audits['first-contentful-paint']['displayValue']   ?? '--',
            'lcp'            => $audits['largest-contentful-paint']['displayValue'] ?? '--',
            'tbt'            => $audits['total-blocking-time']['displayValue']      ?? '--',
            'cls'            => $audits['cumulative-layout-shift']['displayValue']  ?? '--',
            'speed_index'    => $audits['speed-index']['displayValue']              ?? '--',
            'recommendations'=> $recommendations,
            'scanned_at'     => current_time( 'mysql' ),
        );
    }

    private static function audit_category( string $id ): string {
        $map = array(
            'render-blocking-resources' => 'performance',
            'unused-css-rules'          => 'performance',
            'unused-javascript'         => 'performance',
            'uses-optimized-images'     => 'images',
            'uses-webp-images'          => 'images',
            'efficiently-encode-images' => 'images',
            'uses-text-compression'     => 'performance',
            'meta-description'          => 'seo',
            'document-title'            => 'seo',
            'image-alt'                 => 'seo',
            'link-text'                 => 'seo',
            'crawlable-anchors'         => 'seo',
        );
        return isset( $map[ $id ] ) ? $map[ $id ] : 'general';
    }

    public static function get_last_scores(): ?array {
        $last = get_option( self::OPTION_RESULTS, null );
        if ( ! $last ) return null;
        return array(
            'performance'    => $last['performance'],
            'seo'            => $last['seo'],
            'accessibility'  => $last['accessibility'],
            'best_practices' => $last['best_practices'],
            'scanned_at'     => $last['scanned_at'],
        );
    }

    public static function render_page() {
        if ( ! current_user_can('manage_options') ) wp_die( 'Keine Berechtigung.' );

        $opts    = get_option( AAG_OPTION, array() );
        $last    = get_option( self::OPTION_RESULTS, null );
        $history = get_option( self::OPTION_HISTORY, array() );
        $nonce   = wp_create_nonce( 'mss_pagespeed_nonce' );

        $score_color = function($s){ return $s>=90?'#16a34a':($s>=50?'#b45309':'#dc2626'); };
        $score_ring  = function($s){ return $s>=90?'#22c55e':($s>=50?'#f59e0b':'#ef4444'); };
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-performance"></span>
                PageSpeed Scan
            </h1>

            <div class="aag-card">
                <h2>Neue Analyse starten</h2>
                <table class="form-table">
                    <tr>
                        <th><label>URL</label></th>
                        <td>
                            <input type="url" id="mss-scan-url" value="<?php echo esc_url( get_home_url() ); ?>"
                                   class="regular-text" placeholder="https://...">
                        </td>
                    </tr>
                    <tr>
                        <th>Geraet</th>
                        <td>
                            <label style="margin-right:16px"><input type="radio" name="mss_strategy" value="mobile" checked> Mobil</label>
                            <label><input type="radio" name="mss_strategy" value="desktop"> Desktop</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label>API-Key (optional)</label></th>
                        <td>
                            <input type="password" id="mss-ps-key" class="regular-text"
                                   value="<?php echo esc_attr( $opts['pagespeed_key'] ?? '' ); ?>"
                                   placeholder="Fuer hoehere Rate Limits">
                            <p class="description">
                                Kostenlos unter
                                <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank">Google Cloud Console</a> erstellen.
                            </p>
                        </td>
                    </tr>
                </table>
                <div style="display:flex;gap:12px;align-items:center;margin-top:12px">
                    <button type="button" class="button button-primary button-large" id="mss-scan-btn">
                        Scan starten
                    </button>
                    <span id="mss-scan-status" style="font-size:13px;color:#64748b"></span>
                </div>
            </div>

            <?php if ( $last ) : ?>
            <div id="mss-results-wrap">

                <div class="mss-score-grid">
                    <?php
                    $scores = array(
                        'Performance'    => $last['performance'],
                        'SEO'            => $last['seo'],
                        'Accessibility'  => $last['accessibility'],
                        'Best Practices' => $last['best_practices'],
                    );
                    foreach ( $scores as $label => $score ) :
                        $ring = $score_ring( $score );
                        $col  = $score_color( $score );
                    ?>
                    <div class="mss-score-card">
                        <div class="mss-score-ring">
                            <svg class="mss-ring-svg" viewBox="0 0 36 36">
                                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                      fill="none" stroke="#e2e8f0" stroke-width="3"/>
                                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                      fill="none" stroke="<?php echo $ring; ?>" stroke-width="3"
                                      stroke-dasharray="<?php echo $score; ?>, 100"/>
                            </svg>
                            <span class="mss-score-num" style="color:<?php echo $col; ?>"><?php echo $score; ?></span>
                        </div>
                        <span class="mss-score-label"><?php echo $label; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="aag-card">
                    <h2>Core Web Vitals &mdash; <?php echo $last['strategy']==='mobile'?'Mobil':'Desktop'; ?></h2>
                    <div class="mss-vitals-grid">
                        <?php
                        $vitals = array( 'FCP' => $last['fcp'], 'LCP' => $last['lcp'], 'TBT' => $last['tbt'], 'CLS' => $last['cls'], 'Speed Index' => $last['speed_index'] );
                        foreach ( $vitals as $name => $val ) :
                        ?>
                        <div class="mss-vital-item">
                            <span class="mss-vital-name"><?php echo $name; ?></span>
                            <span class="mss-vital-val"><?php echo esc_html( $val ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="font-size:11px;color:#94a3b8;margin:12px 0 0">
                        Gescannt: <?php echo esc_html( $last['scanned_at'] ); ?> &mdash;
                        <a href="<?php echo esc_url( $last['url'] ); ?>" target="_blank"><?php echo esc_html( $last['url'] ); ?></a>
                    </p>
                </div>

                <?php if ( ! empty( $last['recommendations'] ) ) : ?>
                <div class="aag-card">
                    <h2>Empfehlungen</h2>
                    <?php
                    $cat_labels = array( 'performance' => 'Performance', 'images' => 'Bilder', 'seo' => 'SEO', 'general' => 'Allgemein' );
                    $grouped    = array();
                    foreach ( $last['recommendations'] as $rec ) { $grouped[ $rec['category'] ][] = $rec; }
                    foreach ( $grouped as $cat => $recs ) :
                        $cat_label = isset( $cat_labels[$cat] ) ? $cat_labels[$cat] : ucfirst($cat);
                    ?>
                    <h3 style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin:16px 0 8px"><?php echo $cat_label; ?></h3>
                    <?php foreach ( $recs as $rec ) :
                        $score_pct = round( ( $rec['score'] ?? 0 ) * 100 );
                        $dot_color = $score_pct >= 90 ? '#16a34a' : ( $score_pct >= 50 ? '#b45309' : '#dc2626' );
                    ?>
                    <div class="mss-rec-item">
                        <div class="mss-rec-dot" style="background:<?php echo $dot_color; ?>"></div>
                        <div class="mss-rec-body">
                            <div class="mss-rec-title">
                                <?php echo esc_html( $rec['title'] ); ?>
                                <?php if ( $rec['savings'] ) : ?>
                                <span class="mss-rec-savings"><?php echo esc_html( $rec['savings'] ); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="mss-rec-desc"><?php echo esc_html( wp_trim_words( $rec['description'], 20 ) ); ?></p>
                        </div>
                    </div>
                    <?php endforeach; endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
            <?php endif; ?>

            <?php if ( count($history) > 1 ) : ?>
            <div class="aag-card">
                <h2>Scan-Verlauf (letzte 10)</h2>
                <table class="wp-list-table widefat fixed striped" style="border:none">
                    <thead>
                        <tr>
                            <th>Datum</th><th>URL</th><th>Geraet</th>
                            <th>Performance</th><th>SEO</th><th>Accessibility</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( array_reverse($history) as $h ) : ?>
                        <tr>
                            <td style="font-size:12px"><?php echo esc_html($h['scanned_at']); ?></td>
                            <td style="font-size:12px"><?php echo esc_html( parse_url($h['url'],PHP_URL_HOST) . (parse_url($h['url'],PHP_URL_PATH)?:'') ); ?></td>
                            <td style="font-size:12px"><?php echo $h['strategy']==='mobile'?'Mobil':'Desktop'; ?></td>
                            <td><strong style="color:<?php echo $score_color($h['performance']); ?>"><?php echo $h['performance']; ?></strong></td>
                            <td><strong style="color:<?php echo $score_color($h['seo']); ?>"><?php echo $h['seo']; ?></strong></td>
                            <td><strong style="color:<?php echo $score_color($h['accessibility']); ?>"><?php echo $h['accessibility']; ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(function($){
            var nonce='<?php echo esc_js($nonce);?>', ajaxUrl='<?php echo esc_url(admin_url('admin-ajax.php'));?>';
            $('#mss-scan-btn').on('click', function(){
                var url=$('#mss-scan-url').val(), strategy=$('input[name="mss_strategy"]:checked').val(), key=$('#mss-ps-key').val();
                if(!url){ alert('Bitte eine URL eingeben.'); return; }
                $(this).prop('disabled',true).text('Scan laeuft...');
                $('#mss-scan-status').text('Google PageSpeed API wird aufgerufen...');
                $.post(ajaxUrl,{action:'mss_run_pagespeed',nonce:nonce,url:url,strategy:strategy,api_key:key},function(res){
                    $('#mss-scan-btn').prop('disabled',false).text('Scan starten');
                    if(res.success){ $('#mss-scan-status').text('Scan abgeschlossen.'); location.reload(); }
                    else $('#mss-scan-status').html('<span style="color:#dc2626">Fehler: '+res.data.message+'</span>');
                }).fail(function(){ $('#mss-scan-btn').prop('disabled',false).text('Scan starten'); $('#mss-scan-status').html('<span style="color:#dc2626">Verbindungsfehler</span>'); });
            });
        });
        </script>
        <?php
    }
}
