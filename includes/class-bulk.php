<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_Bulk {

    public static function init() {
        add_action( 'wp_ajax_aag_bulk_get_images',  array( __CLASS__, 'ajax_get_images' ) );
        add_action( 'wp_ajax_aag_bulk_process_one', array( __CLASS__, 'ajax_process_one' ) );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung.' );

        $total_images = self::count_images( false );
        $missing_alt  = self::count_images( true );
        $nonce        = wp_create_nonce( 'aag_bulk_nonce' );
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-images-alt2"></span>
                Bulk Alt-Text Generator
            </h1>

            <div class="aag-stats-kpi-grid">
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" id="bulk-total"><?php echo number_format( $total_images ); ?></span>
                    <span class="aag-stats-kpi-label">Bilder gesamt</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" id="bulk-missing" style="color:<?php echo $missing_alt > 0 ? '#dc2626' : '#16a34a'; ?>">
                        <?php echo number_format( $missing_alt ); ?>
                    </span>
                    <span class="aag-stats-kpi-label">Ohne Alt-Text</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" id="bulk-done">0</span>
                    <span class="aag-stats-kpi-label">Verarbeitet</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" id="bulk-errors" style="color:#dc2626">0</span>
                    <span class="aag-stats-kpi-label">Fehler</span>
                </div>
            </div>

            <div class="aag-card">
                <h2>Optionen</h2>
                <table class="form-table">
                    <tr>
                        <th>Welche Bilder?</th>
                        <td>
                            <label style="display:block;margin-bottom:8px">
                                <input type="radio" name="bulk_mode" value="missing" checked>
                                Nur Bilder ohne Alt-Text (<?php echo number_format( $missing_alt ); ?> Bilder)
                            </label>
                            <label>
                                <input type="radio" name="bulk_mode" value="all">
                                Alle Bilder inklusive vorhandene Alt-Texte ueberschreiben (<?php echo number_format( $total_images ); ?> Bilder)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Pause zwischen Anfragen</th>
                        <td>
                            <label>
                                <input type="number" id="bulk-delay" value="1" min="0" max="10" style="width:60px"> Sekunden
                            </label>
                            <p class="description">Empfohlen: 1 Sekunde, um API-Rate-Limits zu vermeiden.</p>
                        </td>
                    </tr>
                </table>

                <div style="display:flex;gap:12px;align-items:center;margin-top:16px;flex-wrap:wrap">
                    <button type="button" class="button button-primary button-large" id="bulk-start-btn">
                        Bulk-Generierung starten
                    </button>
                    <button type="button" class="button button-large" id="bulk-stop-btn"
                            style="display:none;color:#dc2626;border-color:#fecaca">
                        Abbrechen
                    </button>
                    <span id="bulk-status-text" style="font-size:13px;color:#64748b"></span>
                </div>
            </div>

            <div class="aag-card" id="bulk-progress-card" style="display:none">
                <h2>Fortschritt</h2>
                <div class="aag-bulk-progress-track">
                    <div class="aag-bulk-progress-bar" id="bulk-progress-bar" style="width:0%"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-top:6px">
                    <span id="bulk-progress-label">0 / 0</span>
                    <span id="bulk-progress-pct">0%</span>
                </div>
            </div>

            <div class="aag-card" id="bulk-log-card" style="display:none">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                    <h2 style="margin:0">Protokoll</h2>
                    <button type="button" class="button" id="bulk-clear-log">Leeren</button>
                </div>
                <div class="aag-bulk-log" id="bulk-log"></div>
            </div>
        </div>

        <script>
        jQuery(function($){
            var nonce   = '<?php echo esc_js( $nonce ); ?>';
            var ajaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
            var queue   = [], done = 0, errors = 0, stop = false;

            $('#bulk-start-btn').on('click', function(){
                var mode  = $('input[name="bulk_mode"]:checked').val();
                var delay = parseInt($('#bulk-delay').val()) * 1000;
                done = 0; errors = 0; stop = false;

                $('#bulk-start-btn').hide();
                $('#bulk-stop-btn').show();
                $('#bulk-progress-card,#bulk-log-card').show();
                $('#bulk-log').empty();
                $('#bulk-done,#bulk-errors').text('0');
                setStatus('Bilder werden geladen...');

                $.post(ajaxUrl, { action:'aag_bulk_get_images', nonce:nonce, mode:mode }, function(res){
                    if (!res.success) { setStatus('Fehler: ' + res.data.message); finish(); return; }
                    queue = res.data.ids;
                    if (!queue.length) { setStatus('Keine Bilder gefunden.'); finish(); return; }
                    setStatus('Verarbeite ' + queue.length + ' Bilder...');
                    processNext(delay);
                });
            });

            $('#bulk-stop-btn').on('click', function(){ stop = true; setStatus('Wird abgebrochen...'); });
            $('#bulk-clear-log').on('click', function(){ $('#bulk-log').empty(); });

            function processNext(delay){
                if (stop || !queue.length) { finish(); return; }
                var id    = queue.shift();
                var total = done + errors + queue.length + 1;
                updateProg(done + errors, total);

                $.post(ajaxUrl, { action:'aag_bulk_process_one', nonce:nonce, attachment_id:id }, function(res){
                    if (res.success) { done++; addLog('ok', 'Bild #' + id + ': ' + res.data.alt); $('#bulk-done').text(done); }
                    else             { errors++; addLog('err', 'Bild #' + id + ': ' + (res.data.message||'Fehler')); $('#bulk-errors').text(errors); }
                    updateProg(done + errors, total);
                    setTimeout(function(){ processNext(delay); }, delay);
                }).fail(function(){
                    errors++; addLog('err', 'Bild #' + id + ': Verbindungsfehler'); $('#bulk-errors').text(errors);
                    updateProg(done + errors, done + errors + queue.length);
                    setTimeout(function(){ processNext(delay); }, delay);
                });
            }

            function updateProg(d, t){
                var pct = t > 0 ? Math.round(d/t*100) : 0;
                $('#bulk-progress-bar').css('width', pct + '%');
                $('#bulk-progress-label').text(d + ' / ' + t);
                $('#bulk-progress-pct').text(pct + '%');
                setStatus(d + ' von ' + t + ' verarbeitet...');
            }

            function finish(){
                $('#bulk-start-btn').show(); $('#bulk-stop-btn').hide();
                var msg = (stop ? 'Abgebrochen' : 'Fertig') + ' -- ' + done + ' generiert, ' + errors + ' Fehler.';
                setStatus(msg);
                addLog(stop ? 'warn' : 'ok', msg);
                var newMissing = Math.max(0, parseInt($('#bulk-missing').text().replace(/\D/g,'')) - done);
                $('#bulk-missing').text(newMissing).css('color', newMissing > 0 ? '#dc2626' : '#16a34a');
            }

            function setStatus(msg){ $('#bulk-status-text').text(msg); }

            function addLog(type, msg){
                var colors = { ok:'#16a34a', err:'#dc2626', warn:'#b45309' };
                var t = new Date().toLocaleTimeString('de-DE');
                $('#bulk-log').prepend(
                    $('<div>').css({ padding:'5px 10px', borderBottom:'1px solid #f1f5f9', fontSize:'12px', color:colors[type]||'#334155', display:'flex', gap:'10px' })
                    .html('<span style="color:#94a3b8;flex-shrink:0">' + t + '</span><span>' + $('<span>').text(msg).html() + '</span>')
                );
            }
        });
        </script>
        <?php
    }

    public static function ajax_get_images() {
        if ( ! check_ajax_referer( 'aag_bulk_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        }

        $mode = sanitize_text_field( $_POST['mode'] ?? 'missing' );
        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );

        if ( $mode === 'missing' ) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array( 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '=' ),
            );
        }

        $ids = get_posts( $args );
        wp_send_json_success( array( 'ids' => $ids, 'count' => count( $ids ) ) );
    }

    public static function ajax_process_one() {
        if ( ! check_ajax_referer( 'aag_bulk_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        }

        $id = intval( $_POST['attachment_id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( array( 'message' => 'Ungueltige ID.' ) );

        $image_url = wp_get_attachment_url( $id );
        if ( ! $image_url ) wp_send_json_error( array( 'message' => 'Bild nicht gefunden.' ) );

        $opts   = get_option( AAG_OPTION, array() );
        $prompt = $opts['prompt'] ?? AAG_Alt_Generator::default_prompt();
        $prompt = AAG_Alt_Generator::inject_language( $prompt, $opts['language'] ?? 'auto' );

        try {
            $alt = AAG_API_Handler::generate_alt( $image_url, $prompt );
            $alt = sanitize_text_field( trim( $alt ) );
            update_post_meta( $id, '_wp_attachment_image_alt', $alt );
            AAG_Stats::record( $opts['provider'] ?? 'gemini' );
            wp_send_json_success( array( 'alt' => $alt, 'attachment_id' => $id ) );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }

    private static function count_images( bool $missing_only ): int {
        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );
        if ( $missing_only ) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array( 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '=' ),
            );
        }
        return count( get_posts( $args ) );
    }
}
