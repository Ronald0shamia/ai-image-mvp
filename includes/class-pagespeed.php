<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSS_PageSpeed {

    const OPTION_RESULTS = 'mss_pagespeed_results';
    const OPTION_HISTORY = 'mss_pagespeed_history';

    public static function init() {
        add_action( 'wp_ajax_mss_run_pagespeed', [ __CLASS__, 'ajax_run_scan' ] );
        add_action( 'admin_enqueue_scripts',      [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'mss-pagespeed' ) === false ) return;
        wp_enqueue_style( 'mss-pagespeed', MSS_URL . 'assets/admin.css', [], MSS_VERSION );
    }

    // ── AJAX Scan ─────────────────────────────────────────────
    public static function ajax_run_scan() {
        check_ajax_referer( 'mss_pagespeed_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( ['message' => 'Keine Berechtigung.'] );

        $url      = esc_url_raw( $_POST['url'] ?? get_home_url() );
        $strategy = in_array( $_POST['strategy'] ?? 'mobile', ['mobile','desktop'] ) ? $_POST['strategy'] : 'mobile';
        $opts     = get_option( MSS_OPTION, [] );
        $api_key  = $opts['pagespeed_key'] ?? '';

        $api_url = add_query_arg( [
            'url'      => urlencode( $url ),
            'strategy' => $strategy,
            'category' => ['performance', 'seo', 'accessibility', 'best-practices'],
            'key'       => $api_key ?: '',
        ], 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' );

        // category muss mehrfach übergeben werden
        $api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . urlencode($url)
            . '&strategy=' . $strategy
            . '&category=performance&category=seo&category=accessibility&category=best-practices'
            . ( $api_key ? '&key=' . $api_key : '' );

        $response = wp_remote_get( $api_url, [ 'timeout' => 60 ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( ['message' => $response->get_error_message()] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? "API Fehler (HTTP {$code})";
            wp_send_json_error( ['message' => $msg] );
        }

        $result = self::parse_results( $body, $url, $strategy );

        // Speichern
        update_option( self::OPTION_RESULTS, $result, false );

        // History (max 10 Einträge)
        $history   = get_option( self::OPTION_HISTORY, [] );
        $history[] = array_merge( $result, ['scanned_at' => current_time('mysql')] );
        $history   = array_slice( $history, -10 );
        update_option( self::OPTION_HISTORY, $history, false );

        wp_send_json_success( $result );
    }

    private static function parse_results( array $body, string $url, string $strategy ): array {
        $cats       = $body['lighthouseResult']['categories'] ?? [];
        $audits     = $body['lighthouseResult']['audits']     ?? [];
        $fcp        = $audits['first-contentful-paint']['displayValue']  ?? '—';
        $lcp        = $audits['largest-contentful-paint']['displayValue'] ?? '—';
        $tbt        = $audits['total-blocking-time']['displayValue']      ?? '—';
        $cls        = $audits['cumulative-layout-shift']['displayValue']  ?? '—';
        $speed_idx  = $audits['speed-index']['displayValue']              ?? '—';

        // Wichtige Empfehlungen extrahieren
        $recommendations = [];
        $priority_audits = [
            'render-blocking-resources', 'unused-css-rules', 'unused-javascript',
            'uses-optimized-images', 'uses-webp-images', 'uses-text-compression',
            'efficiently-encode-images', 'meta-description', 'document-title',
            'image-alt', 'link-text', 'crawlable-anchors',
        ];

        foreach ( $priority_audits as $audit_id ) {
            if ( ! isset( $audits[ $audit_id ] ) ) continue;
            $a = $audits[ $audit_id ];
            if ( ( $a['score'] ?? 1 ) >= 0.9 ) continue; // Nur Probleme

            $savings = '';
            if ( isset( $a['details']['overallSavingsMs'] ) ) {
                $savings = round( $a['details']['overallSavingsMs'] ) . 'ms';
            } elseif ( isset( $a['details']['overallSavingsBytes'] ) ) {
                $savings = round( $a['details']['overallSavingsBytes'] / 1024 ) . 'KB';
            }

            $recommendations[] = [
                'id'          => $audit_id,
                'title'       => $a['title']       ?? $audit_id,
                'description' => $a['description'] ?? '',
                'score'       => $a['score']        ?? 0,
                'savings'     => $savings,
                'category'    => self::audit_category( $audit_id ),
            ];
        }

        // Nach Score sortieren (schlechteste zuerst)
        usort( $recommendations, fn($a,$b) => $a['score'] <=> $b['score'] );

        return [
            'url'             => $url,
            'strategy'        => $strategy,
            'performance'     => round( ( $cats['performance']['score']     ?? 0 ) * 100 ),
            'seo'             => round( ( $cats['seo']['score']             ?? 0 ) * 100 ),
            'accessibility'   => round( ( $cats['accessibility']['score']   ?? 0 ) * 100 ),
            'best_practices'  => round( ( $cats['best-practices']['score']  ?? 0 ) * 100 ),
            'fcp'             => $fcp,
            'lcp'             => $lcp,
            'tbt'             => $tbt,
            'cls'             => $cls,
            'speed_index'     => $speed_idx,
            'recommendations' => $recommendations,
            'scanned_at'      => current_time( 'mysql' ),
        ];
    }

    private static function audit_category( string $id ): string {
        $map = [
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
        ];
        return $map[ $id ] ?? 'general';
    }

    // ── Seite rendern ─────────────────────────────────────────
    public static function render_page() {
        if ( ! current_user_can('manage_options') ) wp_die('Keine Berechtigung.');

        $opts     = get_option( MSS_OPTION, [] );
        $last     = get_option( self::OPTION_RESULTS, null );
        $history  = get_option( self::OPTION_HISTORY, [] );
        $nonce    = wp_create_nonce('mss_pagespeed_nonce');
        $home_url = get_home_url();

        $score_color = fn($s) => $s >= 90 ? '#15803d' : ( $s >= 50 ? '#b45309' : '#dc2626' );
        $score_bg    = fn($s) => $s >= 90 ? '#f0fdf4' : ( $s >= 50 ? '#fffbeb' : '#fef2f2' );
        $score_ring  = fn($s) => $s >= 90 ? '#22c55e' : ( $s >= 50 ? '#f59e0b' : '#ef4444' );
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-performance"></span>
                ⚡ PageSpeed Scan
            </h1>

            <!-- Scan-Formular -->
            <div class="aag-card">
                <h2>🔍 Neue Analyse starten</h2>
                <table class="form-table">
                    <tr>
                        <th><label>URL scannen</label></th>
                        <td>
                            <input type="url" id="mss-scan-url" value="<?php echo esc_url($home_url); ?>"
                                   class="regular-text" placeholder="https://...">
                        </td>
                    </tr>
                    <tr>
                        <th>Gerät</th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="mss_strategy" value="mobile" checked> 📱 Mobile</label>
                                &nbsp;&nbsp;
                                <label><input type="radio" name="mss_strategy" value="desktop"> 🖥 Desktop</label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th><label>PageSpeed API-Key</label></th>
                        <td>
                            <input type="password" id="mss-ps-key" class="regular-text"
                                   value="<?php echo esc_attr($opts['pagespeed_key'] ?? ''); ?>"
                                   placeholder="Optional — für höhere Rate Limits">
                            <p class="description">Kostenlos unter <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank">Google Cloud Console</a> erstellen.</p>
                        </td>
                    </tr>
                </table>
                <div style="display:flex;gap:12px;align-items:center">
                    <button type="button" class="button button-primary button-large" id="mss-scan-btn">
                        ▶ Scan starten
                    </button>
                    <span id="mss-scan-status" style="font-size:13px;color:#64748b"></span>
                </div>
            </div>

            <!-- Ergebnisse -->
            <?php if ( $last ) : ?>
            <div id="mss-results-wrap">
                <?php self::render_results( $last, $score_color, $score_bg, $score_ring ); ?>
            </div>
            <?php else : ?>
            <div id="mss-results-wrap"></div>
            <?php endif; ?>

            <!-- History -->
            <?php if ( count($history) > 1 ) : ?>
            <div class="aag-card" style="margin-top:20px">
                <h2>📅 Scan-Verlauf (letzte 10)</h2>
                <table class="wp-list-table widefat fixed striped" style="border:none">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>URL</th>
                            <th>Gerät</th>
                            <th>Performance</th>
                            <th>SEO</th>
                            <th>Accessibility</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( array_reverse($history) as $h ) : ?>
                        <tr>
                            <td style="font-size:12px"><?php echo esc_html($h['scanned_at']); ?></td>
                            <td style="font-size:12px"><?php echo esc_html(parse_url($h['url'],PHP_URL_HOST) . (parse_url($h['url'],PHP_URL_PATH) ?: '')); ?></td>
                            <td><?php echo $h['strategy'] === 'mobile' ? '📱' : '🖥'; ?></td>
                            <td><span style="color:<?php echo $score_color($h['performance']); ?>;font-weight:600"><?php echo $h['performance']; ?></span></td>
                            <td><span style="color:<?php echo $score_color($h['seo']); ?>;font-weight:600"><?php echo $h['seo']; ?></span></td>
                            <td><span style="color:<?php echo $score_color($h['accessibility']); ?>;font-weight:600"><?php echo $h['accessibility']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(function($){
            var nonce   = '<?php echo esc_js($nonce); ?>';
            var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

            $('#mss-scan-btn').on('click', function(){
                var url      = $('#mss-scan-url').val();
                var strategy = $('input[name="mss_strategy"]:checked').val();
                var key      = $('#mss-ps-key').val();

                if (!url) { alert('Bitte eine URL eingeben.'); return; }

                $(this).prop('disabled', true).text('⏳ Wird gescannt…');
                $('#mss-scan-status').text('Google PageSpeed API wird aufgerufen…');

                $.post(ajaxUrl, {
                    action:   'mss_run_pagespeed',
                    nonce:    nonce,
                    url:      url,
                    strategy: strategy,
                    api_key:  key,
                }, function(res){
                    $('#mss-scan-btn').prop('disabled', false).text('▶ Scan starten');
                    if (res.success) {
                        $('#mss-scan-status').text('✓ Scan abgeschlossen!');
                        location.reload();
                    } else {
                        $('#mss-scan-status').html('<span style="color:#dc2626">⚠ ' + res.data.message + '</span>');
                    }
                }).fail(function(){
                    $('#mss-scan-btn').prop('disabled', false).text('▶ Scan starten');
                    $('#mss-scan-status').html('<span style="color:#dc2626">⚠ Verbindungsfehler</span>');
                });
            });
        });
        </script>
        <?php
    }

    public static function render_results( array $r, callable $color, callable $bg, callable $ring ) {
        $scores = [
            'Performance'   => $r['performance'],
            'SEO'           => $r['seo'],
            'Accessibility' => $r['accessibility'],
            'Best Practices'=> $r['best_practices'],
        ];
        $vitals = [
            'FCP'         => $r['fcp'],
            'LCP'         => $r['lcp'],
            'TBT'         => $r['tbt'],
            'CLS'         => $r['cls'],
            'Speed Index' => $r['speed_index'],
        ];
        ?>
        <!-- Score-Karten -->
        <div class="mss-score-grid">
            <?php foreach ( $scores as $label => $score ) : ?>
            <div class="mss-score-card" style="background:<?php echo $bg($score); ?>;border-color:<?php echo $ring($score); ?>20">
                <div class="mss-score-ring" style="--ring-color:<?php echo $ring($score); ?>">
                    <svg viewBox="0 0 36 36" class="mss-ring-svg">
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                              fill="none" stroke="#e2e8f0" stroke-width="3"/>
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                              fill="none" stroke="<?php echo $ring($score); ?>" stroke-width="3"
                              stroke-dasharray="<?php echo $score; ?>, 100"/>
                    </svg>
                    <span class="mss-score-num" style="color:<?php echo $color($score); ?>"><?php echo $score; ?></span>
                </div>
                <span class="mss-score-label"><?php echo $label; ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Core Web Vitals -->
        <div class="aag-card" style="margin-top:16px">
            <h2>📊 Core Web Vitals — <?php echo $r['strategy'] === 'mobile' ? '📱 Mobile' : '🖥 Desktop'; ?></h2>
            <div class="mss-vitals-grid">
                <?php foreach ( $vitals as $name => $val ) : ?>
                <div class="mss-vital-item">
                    <span class="mss-vital-name"><?php echo $name; ?></span>
                    <span class="mss-vital-val"><?php echo esc_html($val); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <p style="font-size:11px;color:#94a3b8;margin:12px 0 0">
                Gescannt: <?php echo esc_html($r['scanned_at']); ?> —
                URL: <a href="<?php echo esc_url($r['url']); ?>" target="_blank"><?php echo esc_html($r['url']); ?></a>
            </p>
        </div>

        <!-- Empfehlungen -->
        <?php if ( ! empty( $r['recommendations'] ) ) : ?>
        <div class="aag-card" style="margin-top:16px">
            <h2>💡 Smart Recommendations</h2>
            <?php
            $cat_icons = ['performance'=>'⚡','images'=>'🖼','seo'=>'🔍','general'=>'🔧'];
            $grouped = [];
            foreach ( $r['recommendations'] as $rec ) {
                $grouped[$rec['category']][] = $rec;
            }
            foreach ( $grouped as $cat => $recs ) :
                $icon = $cat_icons[$cat] ?? '🔧';
            ?>
            <h3 style="font-size:14px;margin:16px 0 8px;text-transform:capitalize">
                <?php echo $icon; ?> <?php echo ucfirst($cat); ?>
            </h3>
            <?php foreach ( $recs as $rec ) :
                $score_pct = round(($rec['score'] ?? 0) * 100);
                $c = $score_pct >= 90 ? '#15803d' : ($score_pct >= 50 ? '#b45309' : '#dc2626');
            ?>
            <div class="mss-rec-item">
                <div class="mss-rec-header">
                    <span class="mss-rec-dot" style="background:<?php echo $c; ?>"></span>
                    <strong class="mss-rec-title"><?php echo esc_html($rec['title']); ?></strong>
                    <?php if ($rec['savings']) : ?>
                    <span class="mss-rec-savings">Einsparung: <?php echo esc_html($rec['savings']); ?></span>
                    <?php endif; ?>
                </div>
                <p class="mss-rec-desc"><?php echo esc_html(wp_trim_words($rec['description'],20)); ?></p>
            </div>
            <?php endforeach; endforeach; ?>
        </div>
        <?php endif; ?>
        <?php
    }

    // Für Dashboard-Widget
    public static function get_last_scores(): ?array {
        $last = get_option( self::OPTION_RESULTS, null );
        if ( ! $last ) return null;
        return [
            'performance'    => $last['performance'],
            'seo'            => $last['seo'],
            'accessibility'  => $last['accessibility'],
            'best_practices' => $last['best_practices'],
            'scanned_at'     => $last['scanned_at'],
        ];
    }
}
