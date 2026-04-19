<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_Bulk {

    public static function init() {
        add_action( 'wp_ajax_aag_bulk_get_images',  [ __CLASS__, 'ajax_get_images' ] );
        add_action( 'wp_ajax_aag_bulk_process_one', [ __CLASS__, 'ajax_process_one' ] );
    }

    // ── Seite rendern ─────────────────────────────────────────
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung.' );

        $total_images    = self::count_images( false );
        $missing_alt     = self::count_images( true );
        $nonce           = wp_create_nonce( 'aag_bulk_nonce' );
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-images-alt2"></span>
                Bulk Alt-Text Generator
            </h1>

            <!-- Übersicht -->
            <div class="aag-stats-kpi-grid" style="margin-bottom:24px">
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" id="bulk-total"><?php echo number_format( $total_images ); ?></span>
                    <span class="aag-stats-kpi-label">Bilder gesamt</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" id="bulk-missing" style="color:<?php echo $missing_alt > 0 ? '#dc2626' : '#15803d'; ?>">
                        <?php echo number_format( $missing_alt ); ?>
                    </span>
                    <span class="aag-stats-kpi-label">Ohne Alt-Text</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" id="bulk-done">0</span>
                    <span class="aag-stats-kpi-label">Verarbeitet</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" id="bulk-errors">0</span>
                    <span class="aag-stats-kpi-label" style="color:#dc2626">Fehler</span>
                </div>
            </div>

            <div class="aag-card">
                <h2>⚙️ Optionen</h2>
                <table class="form-table">
                    <tr>
                        <th>Welche Bilder?</th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="bulk_mode" value="missing" checked> Nur Bilder <strong>ohne Alt-Text</strong> (<?php echo $missing_alt; ?> Bilder)</label><br>
                                <label><input type="radio" name="bulk_mode" value="all"> Alle Bilder überschreiben (<?php echo $total_images; ?> Bilder)</label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th>Verzögerung</th>
                        <td>
                            <label>
                                <input type="number" id="bulk-delay" value="1" min="0" max="10" style="width:60px"> Sekunden zwischen Anfragen
                            </label>
                            <p class="description">Empfohlen: 1–2 Sekunden um API-Limits zu vermeiden.</p>
                        </td>
                    </tr>
                </table>

                <!-- Aktions-Buttons -->
                <div style="display:flex;gap:12px;align-items:center;margin-top:16px;flex-wrap:wrap">
                    <button type="button" class="button button-primary button-large" id="bulk-start-btn">
                        ▶ Bulk-Generierung starten
                    </button>
                    <button type="button" class="button button-large" id="bulk-stop-btn" style="display:none;color:#dc2626;border-color:#dc2626">
                        ⏹ Abbrechen
                    </button>
                    <span id="bulk-status-text" style="font-size:13px;color:#64748b"></span>
                </div>
            </div>

            <!-- Fortschrittsbalken -->
            <div class="aag-card" id="bulk-progress-card" style="display:none">
                <h2>⏳ Fortschritt</h2>
                <div class="aag-bulk-progress-track">
                    <div class="aag-bulk-progress-bar" id="bulk-progress-bar" style="width:0%"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-top:6px">
                    <span id="bulk-progress-label">0 / 0</span>
                    <span id="bulk-progress-pct">0%</span>
                </div>
            </div>

            <!-- Log -->
            <div class="aag-card" id="bulk-log-card" style="display:none">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                    <h2 style="margin:0">📋 Protokoll</h2>
                    <button type="button" class="button" id="bulk-clear-log">Leeren</button>
                </div>
                <div class="aag-bulk-log" id="bulk-log"></div>
            </div>
        </div>

        <script>
        jQuery(function($){
            var nonce      = '<?php echo esc_js( $nonce ); ?>';
            var ajaxUrl    = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
            var running    = false;
            var stopFlag   = false;
            var queue      = [];
            var doneCount  = 0;
            var errorCount = 0;

            $('#bulk-start-btn').on('click', function(){
                var mode  = $('input[name="bulk_mode"]:checked').val();
                var delay = parseInt($('#bulk-delay').val()) * 1000;

                running    = true;
                stopFlag   = false;
                doneCount  = 0;
                errorCount = 0;

                $('#bulk-start-btn').hide();
                $('#bulk-stop-btn').show();
                $('#bulk-progress-card').show();
                $('#bulk-log-card').show();
                $('#bulk-log').empty();
                $('#bulk-done').text('0');
                $('#bulk-errors').text('0');
                setStatus('Bilder werden geladen…');

                // Bilder-Liste holen
                $.post(ajaxUrl, { action: 'aag_bulk_get_images', nonce: nonce, mode: mode }, function(res){
                    if (!res.success) { setStatus('Fehler: ' + res.data.message); finishBulk(); return; }
                    queue = res.data.ids;
                    if (!queue.length) { setStatus('Keine Bilder gefunden.'); finishBulk(); return; }
                    setStatus('Starte Verarbeitung von ' + queue.length + ' Bildern…');
                    processNext( delay );
                });
            });

            $('#bulk-stop-btn').on('click', function(){ stopFlag = true; setStatus('Wird abgebrochen…'); });
            $('#bulk-clear-log').on('click', function(){ $('#bulk-log').empty(); });

            function processNext( delay ) {
                if ( stopFlag || !queue.length ) { finishBulk(); return; }

                var id    = queue.shift();
                var total = doneCount + errorCount + queue.length + 1;

                updateProgress( doneCount + errorCount, total );

                $.post(ajaxUrl, { action: 'aag_bulk_process_one', nonce: nonce, attachment_id: id }, function(res){
                    if ( res.success ) {
                        doneCount++;
                        addLog( 'success', 'Bild #' + id + ' — ' + res.data.alt );
                        $('#bulk-done').text( doneCount );
                    } else {
                        errorCount++;
                        addLog( 'error', 'Bild #' + id + ' — ' + (res.data.message || 'Fehler') );
                        $('#bulk-errors').text( errorCount );
                    }
                    updateProgress( doneCount + errorCount, total );
                    setTimeout(function(){ processNext(delay); }, delay);
                }).fail(function(){
                    errorCount++;
                    addLog('error', 'Bild #' + id + ' — Verbindungsfehler');
                    $('#bulk-errors').text( errorCount );
                    updateProgress( doneCount + errorCount, total );
                    setTimeout(function(){ processNext(delay); }, delay);
                });
            }

            function updateProgress( done, total ) {
                var pct = total > 0 ? Math.round((done/total)*100) : 0;
                $('#bulk-progress-bar').css('width', pct + '%');
                $('#bulk-progress-label').text( done + ' / ' + total );
                $('#bulk-progress-pct').text( pct + '%' );
                setStatus( done + ' von ' + total + ' verarbeitet…' );
            }

            function finishBulk() {
                running = false;
                $('#bulk-start-btn').show();
                $('#bulk-stop-btn').hide();
                var msg = stopFlag
                    ? '⏹ Abgebrochen — ' + doneCount + ' erfolgreich, ' + errorCount + ' Fehler.'
                    : '✓ Fertig — ' + doneCount + ' Alt-Texte generiert, ' + errorCount + ' Fehler.';
                setStatus( msg );
                addLog( stopFlag ? 'warn' : 'success', msg );
                // Missing-Counter aktualisieren
                var newMissing = Math.max(0, parseInt($('#bulk-missing').text().replace(/\D/g,'')) - doneCount);
                $('#bulk-missing').text(newMissing).css('color', newMissing > 0 ? '#dc2626' : '#15803d');
            }

            function setStatus(msg) { $('#bulk-status-text').text(msg); }

            function addLog(type, msg) {
                var colors = { success:'#15803d', error:'#dc2626', warn:'#92400e' };
                var icons  = { success:'✓', error:'✗', warn:'⚠' };
                var time   = new Date().toLocaleTimeString('de-DE', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
                var entry  = $('<div>').css({
                    padding: '5px 8px',
                    borderBottom: '1px solid #f1f5f9',
                    fontSize: '12px',
                    color: colors[type] || '#334155',
                    display: 'flex',
                    gap: '8px',
                }).html(
                    '<span style="color:#94a3b8;flex-shrink:0">' + time + '</span>' +
                    '<span style="flex-shrink:0">' + (icons[type]||'•') + '</span>' +
                    '<span>' + $('<span>').text(msg).html() + '</span>'
                );
                $('#bulk-log').prepend(entry);
            }
        });
        </script>
        <?php
    }

    // ── AJAX: Bilder-Liste holen ──────────────────────────────
    public static function ajax_get_images() {
        if ( ! check_ajax_referer( 'aag_bulk_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
        }

        $mode = sanitize_text_field( $_POST['mode'] ?? 'missing' );

        $args = [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        if ( $mode === 'missing' ) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [ 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '=' ],
            ];
        }

        $ids = get_posts( $args );
        wp_send_json_success( [ 'ids' => $ids, 'count' => count( $ids ) ] );
    }

    // ── AJAX: Ein Bild verarbeiten ────────────────────────────
    public static function ajax_process_one() {
        if ( ! check_ajax_referer( 'aag_bulk_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
        }

        $id = intval( $_POST['attachment_id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( [ 'message' => 'Ungültige ID.' ] );

        $image_url = wp_get_attachment_url( $id );
        if ( ! $image_url ) wp_send_json_error( [ 'message' => 'Bild nicht gefunden.' ] );

        $opts   = get_option( AAG_OPTION, [] );
        $prompt = $opts['prompt'] ?? AAG_Alt_Generator::default_prompt();

        try {
            $alt = AAG_API_Handler::generate_alt( $image_url, $prompt );
            $alt = sanitize_text_field( trim( $alt ) );
            update_post_meta( $id, '_wp_attachment_image_alt', $alt );

            // Statistik zählen
            AAG_Stats::record( $opts['provider'] ?? 'gemini' );

            wp_send_json_success( [ 'alt' => $alt, 'attachment_id' => $id ] );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    // ── Hilfsfunktionen ───────────────────────────────────────
    private static function count_images( bool $missing_only ): int {
        $args = [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];
        if ( $missing_only ) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [ 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '=' ],
            ];
        }
        return count( get_posts( $args ) );
    }
}
