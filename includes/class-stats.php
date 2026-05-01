<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_Stats {

    const OPTION_TOTAL    = 'aag_stats_total';
    const OPTION_PROVIDER = 'aag_stats_provider';
    const OPTION_DAILY    = 'aag_stats_daily';

    public static function record( string $provider ) {
        $total = intval( get_option( self::OPTION_TOTAL, 0 ) );
        update_option( self::OPTION_TOTAL, $total + 1, false );

        $by_provider = get_option( self::OPTION_PROVIDER, array() );
        $by_provider[ $provider ] = intval( $by_provider[ $provider ] ?? 0 ) + 1;
        update_option( self::OPTION_PROVIDER, $by_provider, false );

        $daily = get_option( self::OPTION_DAILY, array() );
        $today = date( 'Y-m-d' );
        $daily[ $today ] = intval( $daily[ $today ] ?? 0 ) + 1;

        $cutoff = strtotime( '-30 days' );
        foreach ( array_keys( $daily ) as $date ) {
            if ( strtotime( $date ) < $cutoff ) {
                unset( $daily[ $date ] );
            }
        }
        ksort( $daily );
        update_option( self::OPTION_DAILY, $daily, false );
    }

    public static function get_total(): int {
        return intval( get_option( self::OPTION_TOTAL, 0 ) );
    }

    public static function get_by_provider(): array {
        return get_option( self::OPTION_PROVIDER, array() );
    }

    public static function get_daily(): array {
        $daily = get_option( self::OPTION_DAILY, array() );
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

    public static function reset() {
        delete_option( self::OPTION_TOTAL );
        delete_option( self::OPTION_PROVIDER );
        delete_option( self::OPTION_DAILY );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung.' );

        if ( isset( $_POST['aag_reset_stats'] ) && check_admin_referer( 'aag_reset_stats' ) ) {
            self::reset();
            echo '<div class="notice notice-success"><p>Statistik wurde zurueckgesetzt.</p></div>';
        }

        $total    = self::get_total();
        $today    = self::get_today();
        $last30   = self::get_last_30_days_total();
        $provider = self::get_by_provider();
        $daily    = self::get_daily();

        $provider_names  = array( 'gemini' => 'Google Gemini', 'openai' => 'OpenAI', 'claude' => 'Claude' );
        $provider_colors = array( 'gemini' => '#4285f4', 'openai' => '#10a37f', 'claude' => '#cc785c' );

        $chart_days   = array();
        $chart_values = array();
        for ( $i = 13; $i >= 0; $i-- ) {
            $date           = date( 'Y-m-d', strtotime( '-' . $i . ' days' ) );
            $chart_days[]   = date( 'd.m', strtotime( $date ) );
            $chart_values[] = intval( $daily[ $date ] ?? 0 );
        }
        $chart_max = max( array_merge( $chart_values, array(1) ) );
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-chart-bar"></span>
                Statistik
            </h1>

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
                    <span class="aag-stats-kpi-label">Durchschnitt / Tag</span>
                </div>
            </div>

            <div class="aag-stats-layout">
                <div class="aag-card aag-stats-chart-card">
                    <h2>Letzte 14 Tage</h2>
                    <div class="aag-bar-chart">
                        <?php foreach ( $chart_values as $i => $val ) :
                            $height   = $chart_max > 0 ? round( ( $val / $chart_max ) * 120 ) : 0;
                            $is_today = ( $i === 13 );
                            $bg       = $is_today ? 'background:#2563eb' : '';
                        ?>
                        <div class="aag-bar-col">
                            <div class="aag-bar-value"><?php echo $val > 0 ? $val : ''; ?></div>
                            <div class="aag-bar" style="height:<?php echo max( $height, $val > 0 ? 4 : 0 ); ?>px;<?php echo $bg; ?>"></div>
                            <div class="aag-bar-label"><?php echo esc_html( $chart_days[ $i ] ); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="aag-card">
                    <h2>Pro Anbieter</h2>
                    <?php if ( empty( $provider ) ) : ?>
                        <p style="color:#94a3b8;font-size:13px">Noch keine Daten vorhanden.</p>
                    <?php else : ?>
                        <?php foreach ( $provider as $key => $count ) :
                            $name  = isset( $provider_names[ $key ] ) ? $provider_names[ $key ] : $key;
                            $color = isset( $provider_colors[ $key ] ) ? $provider_colors[ $key ] : '#888';
                            $pct   = $total > 0 ? round( ( $count / $total ) * 100 ) : 0;
                        ?>
                        <div class="aag-stats-provider-row">
                            <div class="aag-stats-provider-info">
                                <span class="aag-stats-provider-dot" style="background:<?php echo esc_attr( $color ); ?>"></span>
                                <span class="aag-stats-provider-name"><?php echo esc_html( $name ); ?></span>
                                <span class="aag-stats-provider-count"><?php echo number_format( $count ); ?></span>
                            </div>
                            <div class="aag-stats-bar-track">
                                <div class="aag-stats-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo esc_attr( $color ); ?>"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9">
                        <form method="post">
                            <?php wp_nonce_field( 'aag_reset_stats' ); ?>
                            <button type="submit" name="aag_reset_stats" value="1" class="button"
                                    onclick="return confirm('Statistik wirklich zuruecksetzen?')">
                                Statistik zuruecksetzen
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
