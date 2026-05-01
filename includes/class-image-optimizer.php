<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSS_Image_Optimizer {

    const META_OPTIMIZED = '_mss_optimized';
    const META_ORIG_SIZE = '_mss_orig_size';
    const META_OPT_SIZE  = '_mss_opt_size';
    const META_WEBP_URL  = '_mss_webp_url';

    public static function init() {
        add_action( 'wp_ajax_mss_optimize_image', array( __CLASS__, 'ajax_optimize_one' ) );
        add_action( 'wp_ajax_mss_get_image_list', array( __CLASS__, 'ajax_get_list' ) );
        add_action( 'add_attachment',             array( __CLASS__, 'maybe_auto_optimize' ) );
    }

    public static function maybe_auto_optimize( int $attachment_id ) {
        $opts = get_option( AAG_OPTION, array() );
        if ( ! empty( $opts['auto_webp'] ) && wp_attachment_is_image( $attachment_id ) ) {
            self::optimize( $attachment_id );
        }
    }

    public static function optimize( int $id ): array {
        $file = get_attached_file( $id );
        if ( ! $file || ! file_exists( $file ) ) {
            throw new Exception( 'Datei nicht gefunden.' );
        }

        $mime      = get_post_mime_type( $id );
        $orig_size = filesize( $file );
        $webp_url  = '';

        if ( extension_loaded('gd') && in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
            self::compress_file( $file, $mime );
        }

        if ( extension_loaded('gd') && function_exists('imagewebp') ) {
            $webp_path = self::convert_to_webp( $file, $mime );
            if ( $webp_path ) {
                $webp_url = str_replace( ABSPATH, site_url('/'), $webp_path );
            }
        }

        $new_size = filesize( $file );

        update_post_meta( $id, self::META_OPTIMIZED, 1 );
        update_post_meta( $id, self::META_ORIG_SIZE, $orig_size );
        update_post_meta( $id, self::META_OPT_SIZE,  $new_size );
        if ( $webp_url ) update_post_meta( $id, self::META_WEBP_URL, $webp_url );

        return array(
            'orig_size' => $orig_size,
            'new_size'  => $new_size,
            'saved_kb'  => round( ( $orig_size - $new_size ) / 1024, 1 ),
            'saved_pct' => $orig_size > 0 ? round( ( ( $orig_size - $new_size ) / $orig_size ) * 100, 1 ) : 0,
            'webp_url'  => $webp_url,
            'has_webp'  => ! empty( $webp_url ),
        );
    }

    private static function compress_file( string $file, string $mime ) {
        try {
            if ( $mime === 'image/jpeg' ) {
                $img = imagecreatefromjpeg( $file );
                if ( $img ) { imagejpeg( $img, $file, 82 ); imagedestroy( $img ); }
            } elseif ( $mime === 'image/png' ) {
                $img = imagecreatefrompng( $file );
                if ( $img ) {
                    imagealphablending( $img, false );
                    imagesavealpha( $img, true );
                    imagepng( $img, $file, 7 );
                    imagedestroy( $img );
                }
            }
        } catch ( Exception $e ) {}
    }

    private static function convert_to_webp( string $file, string $mime ): ?string {
        $webp_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $file );
        if ( $webp_path === $file ) $webp_path .= '.webp';

        try {
            $img = null;
            if ( $mime === 'image/jpeg' )     $img = imagecreatefromjpeg( $file );
            elseif ( $mime === 'image/png' )  { $img = imagecreatefrompng( $file ); if ($img){ imagealphablending($img,false); imagesavealpha($img,true); } }
            elseif ( $mime === 'image/gif' )  $img = imagecreatefromgif( $file );

            if ( $img ) {
                imagewebp( $img, $webp_path, 82 );
                imagedestroy( $img );
                return file_exists( $webp_path ) ? $webp_path : null;
            }
        } catch ( Exception $e ) {}
        return null;
    }

    public static function ajax_get_list() {
        check_ajax_referer( 'mss_optimizer_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error();

        $mode = sanitize_text_field( $_POST['mode'] ?? 'unoptimized' );
        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );
        if ( $mode === 'unoptimized' ) {
            $args['meta_query'] = array( array( 'key' => self::META_OPTIMIZED, 'compare' => 'NOT EXISTS' ) );
        }
        $ids = get_posts( $args );
        wp_send_json_success( array( 'ids' => $ids, 'count' => count( $ids ) ) );
    }

    public static function ajax_optimize_one() {
        check_ajax_referer( 'mss_optimizer_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error();

        $id = intval( $_POST['attachment_id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( array( 'message' => 'Ungueltige ID.' ) );

        try {
            $result = self::optimize( $id );
            wp_send_json_success( array_merge( $result, array( 'id' => $id ) ) );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }

    public static function render_page() {
        if ( ! current_user_can('manage_options') ) wp_die();

        $nonce   = wp_create_nonce( 'mss_optimizer_nonce' );
        $gd_ok   = extension_loaded('gd');
        $webp_ok = function_exists('imagewebp');

        $all_images = get_posts( array(
            'post_type'      => 'attachment',
            'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );
        $total = count( $all_images );

        $opt_ids = get_posts( array(
            'post_type'      => 'attachment',
            'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => self::META_OPTIMIZED,
            'meta_value'     => 1,
        ) );
        $optimized   = count( $opt_ids );
        $total_saved = 0;
        foreach ( $opt_ids as $oid ) {
            $orig = intval( get_post_meta( $oid, self::META_ORIG_SIZE, true ) );
            $new  = intval( get_post_meta( $oid, self::META_OPT_SIZE,  true ) );
            $total_saved += max( 0, $orig - $new );
        }
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-format-image"></span>
                Bilder optimieren
            </h1>

            <div class="aag-card" style="margin-bottom:16px">
                <h2>System-Voraussetzungen</h2>
                <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:13px">
                    <span style="color:<?php echo $gd_ok?'#16a34a':'#dc2626'; ?>;font-weight:500">
                        GD-Bibliothek: <?php echo $gd_ok?'Verfuegbar':'Nicht verfuegbar'; ?>
                    </span>
                    <span style="color:<?php echo $webp_ok?'#16a34a':'#dc2626'; ?>;font-weight:500">
                        WebP-Unterstuetzung: <?php echo $webp_ok?'Verfuegbar':'Nicht verfuegbar'; ?>
                    </span>
                    <span style="color:#16a34a;font-weight:500">PHP <?php echo PHP_VERSION; ?></span>
                </div>
                <?php if ( ! $gd_ok ) : ?>
                <p style="color:#dc2626;font-size:12px;margin:8px 0 0">
                    Die GD-Bibliothek ist nicht aktiviert. Bitte beim Hoster aktivieren lassen.
                </p>
                <?php endif; ?>
            </div>

            <div class="aag-stats-kpi-grid">
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value"><?php echo $total; ?></span>
                    <span class="aag-stats-kpi-label">Bilder gesamt</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" style="color:#16a34a"><?php echo $optimized; ?></span>
                    <span class="aag-stats-kpi-label">Optimiert</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" style="color:<?php echo ($total-$optimized)>0?'#dc2626':'#16a34a'; ?>">
                        <?php echo $total - $optimized; ?>
                    </span>
                    <span class="aag-stats-kpi-label">Ausstehend</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" style="color:#2563eb"><?php echo round( $total_saved / 1024 ); ?> KB</span>
                    <span class="aag-stats-kpi-label">Gesamt gespart</span>
                </div>
            </div>

            <div class="aag-card">
                <h2>Optionen</h2>
                <table class="form-table">
                    <tr>
                        <th>Welche Bilder?</th>
                        <td>
                            <label style="display:block;margin-bottom:8px">
                                <input type="radio" name="opt_mode" value="unoptimized" checked>
                                Nur noch nicht optimierte Bilder (<?php echo $total-$optimized; ?>)
                            </label>
                            <label>
                                <input type="radio" name="opt_mode" value="all">
                                Alle Bilder erneut optimieren (<?php echo $total; ?>)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>JPEG-Qualitaet</th>
                        <td>
                            <input type="range" id="opt-quality" min="60" max="95" value="82" style="width:160px">
                            <span id="opt-quality-val">82</span>%
                        </td>
                    </tr>
                    <tr>
                        <th>WebP erstellen</th>
                        <td>
                            <label>
                                <input type="checkbox" id="opt-webp" <?php echo !$webp_ok?'disabled':''; ?> checked>
                                WebP-Version zusaetzlich zur Original-Datei erstellen
                                <?php echo !$webp_ok?' <span style="color:#94a3b8;font-size:12px">(GD ohne WebP-Unterstuetzung)</span>':''; ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Pause</th>
                        <td>
                            <label>
                                <input type="number" id="opt-delay" value="0" min="0" max="5" style="width:60px">
                                Sekunden zwischen Bildern
                            </label>
                        </td>
                    </tr>
                </table>

                <div style="display:flex;gap:12px;align-items:center;margin-top:16px;flex-wrap:wrap">
                    <button type="button" class="button button-primary button-large" id="opt-start-btn">
                        Optimierung starten
                    </button>
                    <button type="button" class="button button-large" id="opt-stop-btn"
                            style="display:none;color:#dc2626;border-color:#fecaca">
                        Abbrechen
                    </button>
                    <span id="opt-status" style="font-size:13px;color:#64748b"></span>
                </div>
            </div>

            <div class="aag-card" id="opt-progress-card" style="display:none">
                <h2>Fortschritt</h2>
                <div class="aag-bulk-progress-track">
                    <div class="aag-bulk-progress-bar" id="opt-progress-bar" style="width:0%"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-top:6px">
                    <span id="opt-progress-label">0 / 0</span>
                    <span id="opt-progress-pct">0%</span>
                </div>
            </div>

            <div class="aag-card" id="opt-log-card" style="display:none">
                <h2>Protokoll</h2>
                <div class="aag-bulk-log" id="opt-log"></div>
            </div>
        </div>

        <script>
        jQuery(function($){
            var nonce   = '<?php echo esc_js( $nonce ); ?>';
            var ajaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
            var queue   = [], done = 0, errors = 0, stop = false;

            $('#opt-quality').on('input', function(){ $('#opt-quality-val').text($(this).val()); });

            $('#opt-start-btn').on('click', function(){
                var mode  = $('input[name="opt_mode"]:checked').val();
                var delay = parseInt($('#opt-delay').val()) * 1000;
                done = 0; errors = 0; stop = false;
                $('#opt-start-btn').hide(); $('#opt-stop-btn').show();
                $('#opt-progress-card,#opt-log-card').show();
                $('#opt-log').empty(); $('#opt-status').text('Bilder werden geladen...');

                $.post(ajaxUrl,{action:'mss_get_image_list',nonce:nonce,mode:mode},function(res){
                    if(!res.success){ $('#opt-status').text('Fehler: '+res.data.message); finish(); return; }
                    queue = res.data.ids;
                    if(!queue.length){ $('#opt-status').text('Keine Bilder zum Optimieren.'); finish(); return; }
                    processNext(delay);
                });
            });

            $('#opt-stop-btn').on('click',function(){ stop=true; $('#opt-status').text('Wird abgebrochen...'); });

            function processNext(delay){
                if(stop||!queue.length){ finish(); return; }
                var id=queue.shift(), total=done+errors+queue.length+1;
                updateProg(done+errors,total);

                $.post(ajaxUrl,{action:'mss_optimize_image',nonce:nonce,attachment_id:id},function(res){
                    if(res.success){ done++; var d=res.data; addLog('ok','Bild #'+id+': gespart '+d.saved_kb+'KB ('+d.saved_pct+'%)'+(d.has_webp?' + WebP':'')); }
                    else            { errors++; addLog('err','Bild #'+id+': '+(res.data.message||'Fehler')); }
                    updateProg(done+errors,total);
                    setTimeout(function(){ processNext(delay); }, delay);
                }).fail(function(){
                    errors++; addLog('err','Bild #'+id+': Verbindungsfehler');
                    updateProg(done+errors,done+errors+queue.length);
                    setTimeout(function(){ processNext(delay); }, delay);
                });
            }

            function updateProg(d,t){
                var pct=t>0?Math.round(d/t*100):0;
                $('#opt-progress-bar').css('width',pct+'%');
                $('#opt-progress-label').text(d+' / '+t);
                $('#opt-progress-pct').text(pct+'%');
                $('#opt-status').text(d+' von '+t+' verarbeitet...');
            }

            function finish(){
                $('#opt-start-btn').show(); $('#opt-stop-btn').hide();
                var msg=(stop?'Abgebrochen':'Fertig')+' -- '+done+' optimiert, '+errors+' Fehler.';
                $('#opt-status').text(msg); addLog(stop?'warn':'ok',msg);
            }

            function addLog(type,msg){
                var c={ok:'#16a34a',err:'#dc2626',warn:'#b45309'};
                var t=new Date().toLocaleTimeString('de-DE');
                $('#opt-log').prepend($('<div>').css({padding:'5px 10px',borderBottom:'1px solid #f1f5f9',fontSize:'12px',color:c[type]||'#334155',display:'flex',gap:'10px'})
                    .html('<span style="color:#94a3b8">'+t+'</span><span>'+$('<span>').text(msg).html()+'</span>'));
            }
        });
        </script>
        <?php
    }
}
