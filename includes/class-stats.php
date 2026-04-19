<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_Stats {

    const OPTION_TOTAL    = 'aag_stats_total';
    const OPTION_PROVIDER = 'aag_stats_provider';
    const OPTION_DAILY    = 'aag_stats_daily';

    // ── Einen neuen Eintrag zählen ────────────────────────────
    public static function record( string $provider ) {
        // Gesamt
        $total = intval( get_option( self::OPTION_TOTAL, 0 ) );
        update_option( self::OPTION_TOTAL, $total + 1, false );

        // Pro Anbieter
        $by_provider = get_option( self::OPTION_PROVIDER, [] );
        $by_provider[ $provider ] = intval( $by_provider[ $provider ] ?? 0 ) + 1;
        update_option( self::OPTION_PROVIDER, $by_provider, false );

        // Täglich (letzte 30 Tage)
        $daily = get_option( self::OPTION_DAILY, [] );
        $today = date( 'Y-m-d' );
        $daily[ $today ] = intval( $daily[ $today ] ?? 0 ) + 1;

        // Nur 30 Tage behalten
        $daily = array_filter( $daily, function( $date ) {
            return strtotime( $date ) >= strtotime( '-30 days' );
        }, ARRAY_FILTER_USE_KEY );

        ksort( $daily );
        update_option( self::OPTION_DAILY, $daily, false );
    }

    // ── Daten abrufen ─────────────────────────────────────────
    public static function get_total(): int {
        return intval( get_option( self::OPTION_TOTAL, 0 ) );
    }

    public static function get_by_provider(): array {
        return get_option( self::OPTION_PROVIDER, [] );
    }

    public static function get_daily(): array {
        $daily = get_option( self::OPTION_DAILY, [] );
        ksort( $daily );
        return $daily;
    }

    public static function get_last_30_days_total(): int {
        return array_sum( self::get_daily() );
    }

    public static function get_today(): int {
        $daily = self::get_daily();
        return intval( $daily[ date('Y-m-d') ] ?? 0 );
    }

    // ── Reset ─────────────────────────────────────────────────
    public static function reset() {
        delete_option( self::OPTION_TOTAL );
        delete_option( self::OPTION_PROVIDER );
        delete_option( self::OPTION_DAILY );
    }

    // ── Statistik-Seite rendern ───────────────────────────────
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung.' );

        // Reset-Aktion
        if ( isset( $_POST['aag_reset_stats'] ) && check_admin_referer( 'aag_reset_stats' ) ) {
            self::reset();
            echo '<div class="notice notice-success"><p>Statistik wurde zurückgesetzt.</p></div>';
        }

        $total    = self::get_total();
        $today    = self::get_today();
        $last30   = self::get_last_30_days_total();
        $provider = self::get_by_provider();
        $daily    = self::get_daily();

        $provider_names = [
            'gemini' => 'Google Gemini',
            'openai' => 'OpenAI',
            'claude' => 'Claude',
        ];
        $provider_colors = [
            'gemini' => '#4285f4',
            'openai' => '#10a37f',
            'claude' => '#cc785c',
        ];

        // Tages-Chart vorbereiten (letzte 14 Tage)
        $chart_days   = [];
        $chart_values = [];
        for ( $i = 13; $i >= 0; $i-- ) {
            $date           = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $chart_days[]   = date( 'd.m', strtotime( $date ) );
            $chart_values[] = intval( $daily[ $date ] ?? 0 );
        }
        $chart_max = max( array_merge( $chart_values, [1] ) );
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-chart-bar"></span>
                Statistik
            </h1>

            <!-- Kennzahlen -->
            <div class="aag-stats-kpi-grid">
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value"><?php echo number_format( $total ); ?></span>
                    <span class="aag-stats-kpi-label">Gesamt generiert</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value"><?php echo number_format( $last30 ); ?></span>
                    <span class="aag-stats-kpi-label">Letzte 30 Tage</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value"><?php echo number_format( $today ); ?></span>
                    <span class="aag-stats-kpi-label">Heute</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value"><?php echo number_format( $last30 > 0 ? round( $last30 / 30, 1 ) : 0 ); ?></span>
                    <span class="aag-stats-kpi-label">Ø pro Tag (30d)</span>
                </div>
            </div>

            <div class="aag-stats-layout">

                <!-- Tages-Chart -->
                <div class="aag-card aag-stats-chart-card">
                    <h2>📈 Letzte 14 Tage</h2>
                    <div class="aag-bar-chart">
                        <?php foreach ( $chart_values as $i => $val ) :
                            $height = $chart_max > 0 ? round( ( $val / $chart_max ) * 120 ) : 0;
                            $is_today = ( $i === 13 );
                        ?>
                        <div class="aag-bar-col">
                            <div class="aag-bar-value"><?php echo $val > 0 ? $val : ''; ?></div>
                            <div class="aag-bar" style="height:<?php echo max( $height, $val > 0 ? 4 : 0 ); ?>px;<?php echo $is_today ? 'background:#6366f1' : ''; ?>"></div>
                            <div class="aag-bar-label"><?php echo esc_html( $chart_days[ $i ] ); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pro Anbieter -->
                <div class="aag-card">
                    <h2>🔌 Pro Anbieter</h2>
                    <?php if ( empty( $provider ) ) : ?>
                        <p style="color:#94a3b8;font-size:13px">Noch keine Daten vorhanden.</p>
                    <?php else : ?>
                        <?php foreach ( $provider as $key => $count ) :
                            $name  = $provider_names[ $key ] ?? $key;
                            $color = $provider_colors[ $key ] ?? '#888';
                            $pct   = $total > 0 ? round( ( $count / $total ) * 100 ) : 0;
                        ?>
                        <div class="aag-stats-provider-row">
                            <div class="aag-stats-provider-info">
                                <span class="aag-stats-provider-dot" style="background:<?php echo esc_attr($color); ?>"></span>
                                <span class="aag-stats-provider-name"><?php echo esc_html( $name ); ?></span>
                                <span class="aag-stats-provider-count"><?php echo number_format( $count ); ?></span>
                            </div>
                            <div class="aag-stats-bar-track">
                                <div class="aag-stats-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo esc_attr($color); ?>"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Reset -->
                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9">
                        <form method="post">
                            <?php wp_nonce_field( 'aag_reset_stats' ); ?>
                            <button type="submit" name="aag_reset_stats" value="1"
                                    class="button"
                                    onclick="return confirm('Statistik wirklich zurücksetzen?')">
                                🗑 Statistik zurücksetzen
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }
}
