<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSS_Meta_SEO {

    public static function init() {
        add_action( 'wp_ajax_mss_save_meta',  array( __CLASS__, 'ajax_save_meta' ) );
        add_action( 'wp_ajax_mss_bulk_meta',  array( __CLASS__, 'ajax_bulk_meta' ) );
    }

    public static function analyze(): array {
        $issues = array();
        $posts  = get_posts( array(
            'post_type'      => array( 'post', 'page' ),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        foreach ( $posts as $pid ) {
            $post      = get_post( $pid );
            $title     = $post->post_title;
            $content   = wp_strip_all_tags( $post->post_content );
            $meta_desc = get_post_meta( $pid, '_yoast_wpseo_metadesc', true )
                      ?: get_post_meta( $pid, '_aioseop_description', true )
                      ?: get_post_meta( $pid, '_rank_math_description', true )
                      ?: '';
            $focus_kw  = get_post_meta( $pid, '_yoast_wpseo_focuskw', true )
                      ?: get_post_meta( $pid, '_rank_math_focus_keyword', true )
                      ?: '';

            $post_issues = array();

            if ( empty( trim( $meta_desc ) ) ) {
                $post_issues[] = array( 'type' => 'missing_meta_desc', 'severity' => 'high',   'label' => 'Meta Description fehlt',                            'fix' => 'Fuege eine Meta Description (120-160 Zeichen) hinzu.' );
            } elseif ( strlen( $meta_desc ) < 120 ) {
                $post_issues[] = array( 'type' => 'short_meta_desc',   'severity' => 'medium', 'label' => 'Meta Description zu kurz (' . strlen($meta_desc) . ' Zeichen)', 'fix' => 'Empfohlen: 120-160 Zeichen.' );
            } elseif ( strlen( $meta_desc ) > 160 ) {
                $post_issues[] = array( 'type' => 'long_meta_desc',    'severity' => 'low',    'label' => 'Meta Description zu lang (' . strlen($meta_desc) . ' Zeichen)',  'fix' => 'Kuerze auf max. 160 Zeichen.' );
            }

            if ( strlen( $title ) < 30 ) {
                $post_issues[] = array( 'type' => 'short_title', 'severity' => 'medium', 'label' => 'Titel zu kurz (' . strlen($title) . ' Zeichen)',  'fix' => 'Empfohlen: 30-60 Zeichen.' );
            } elseif ( strlen( $title ) > 60 ) {
                $post_issues[] = array( 'type' => 'long_title',  'severity' => 'low',    'label' => 'Titel zu lang (' . strlen($title) . ' Zeichen)',   'fix' => 'Kuerze auf max. 60 Zeichen.' );
            }

            $word_count = str_word_count( $content );
            if ( $post->post_type === 'post' && $word_count < 300 ) {
                $post_issues[] = array( 'type' => 'thin_content', 'severity' => 'medium', 'label' => 'Zu wenig Inhalt (' . $word_count . ' Woerter)', 'fix' => 'Beitraege sollten mindestens 300 Woerter enthalten.' );
            }

            if ( empty( $focus_kw ) ) {
                $post_issues[] = array( 'type' => 'no_focus_kw', 'severity' => 'low', 'label' => 'Kein Focus-Keyword gesetzt', 'fix' => 'Setze ein Focus-Keyword in Yoast SEO oder Rank Math.' );
            }

            if ( ! empty( $post_issues ) ) {
                $issues[] = array(
                    'id'        => $pid,
                    'title'     => $title,
                    'type'      => $post->post_type,
                    'url'       => get_permalink( $pid ),
                    'edit_url'  => get_edit_post_link( $pid ),
                    'meta_desc' => $meta_desc,
                    'issues'    => $post_issues,
                    'severity'  => self::worst_severity( $post_issues ),
                );
            }
        }

        $sev_order = array( 'high' => 0, 'medium' => 1, 'low' => 2 );
        usort( $issues, function( $a, $b ) use ( $sev_order ) {
            return ( $sev_order[ $a['severity'] ] ?? 3 ) <=> ( $sev_order[ $b['severity'] ] ?? 3 );
        } );

        return $issues;
    }

    private static function worst_severity( array $issues ): string {
        foreach ( array( 'high', 'medium', 'low' ) as $s ) {
            foreach ( $issues as $i ) { if ( $i['severity'] === $s ) return $s; }
        }
        return 'low';
    }

    public static function ajax_save_meta() {
        check_ajax_referer( 'mss_meta_nonce', 'nonce' );
        $pid  = intval( $_POST['post_id'] ?? 0 );
        $post = get_post( $pid );

        if ( ! $post ) {
            wp_send_json_error( array( 'message' => 'Ungueltige Post-ID.' ) );
        }
        if ( ! current_user_can( 'edit_post', $pid ) ) {
            wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        }

        $desc = sanitize_textarea_field( $_POST['meta_desc'] ?? '' );
        update_post_meta( $pid, '_yoast_wpseo_metadesc', $desc );
        update_post_meta( $pid, '_aioseop_description',  $desc );
        update_post_meta( $pid, '_rank_math_description', $desc );
        wp_send_json_success( array( 'saved' => true ) );
    }

    public static function ajax_bulk_meta() {
        check_ajax_referer( 'mss_meta_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error();
        $pid     = intval( $_POST['post_id'] ?? 0 );
        $post    = get_post( $pid );

        if ( ! $post ) {
            wp_send_json_error( array( 'message' => 'Ungueltige Post-ID.' ) );
        }
        if ( ! current_user_can( 'edit_post', $pid ) ) {
            wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        }

        $content = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '' );
        $desc    = $post->post_title . '. ' . $content;
        $desc    = self::trim_meta_description( $desc );
        update_post_meta( $pid, '_yoast_wpseo_metadesc',  $desc );
        update_post_meta( $pid, '_aioseop_description',   $desc );
        update_post_meta( $pid, '_rank_math_description', $desc );
        wp_send_json_success( array( 'desc' => $desc ) );
    }

    private static function trim_meta_description( string $text, int $max = 160 ): string {
        $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
        if ( self::text_len( $text ) <= $max ) {
            return $text;
        }

        $limit = $max - 3;
        $cut   = self::text_substr( $text, 0, $limit );
        $sentence_end = max(
            self::text_strrpos( $cut, '.' ) ?: 0,
            self::text_strrpos( $cut, '!' ) ?: 0,
            self::text_strrpos( $cut, '?' ) ?: 0
        );

        if ( $sentence_end >= 110 ) {
            return trim( self::text_substr( $cut, 0, $sentence_end + 1 ) );
        }

        $space = self::text_strrpos( $cut, ' ' );
        if ( $space && $space >= 80 ) {
            $cut = self::text_substr( $cut, 0, $space );
        }

        return rtrim( $cut, " \t\n\r\0\x0B.,;:-" ) . '...';
    }

    private static function text_len( string $text ): int {
        return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
    }

    private static function text_substr( string $text, int $start, ?int $length = null ): string {
        if ( function_exists( 'mb_substr' ) ) {
            return null === $length ? mb_substr( $text, $start ) : mb_substr( $text, $start, $length );
        }
        return null === $length ? substr( $text, $start ) : substr( $text, $start, $length );
    }

    private static function text_strrpos( string $text, string $needle ) {
        return function_exists( 'mb_strrpos' ) ? mb_strrpos( $text, $needle ) : strrpos( $text, $needle );
    }

    public static function render_page() {
        if ( ! current_user_can('manage_options') ) wp_die();

        $nonce  = wp_create_nonce( 'mss_meta_nonce' );
        $issues = self::analyze();
        $total  = count( $issues );
        $high   = count( array_filter( $issues, function($i){ return $i['severity']==='high'; } ) );
        $medium = count( array_filter( $issues, function($i){ return $i['severity']==='medium'; } ) );
        $low    = count( array_filter( $issues, function($i){ return $i['severity']==='low'; } ) );

        $sev_colors = array( 'high'=>'#dc2626', 'medium'=>'#b45309', 'low'=>'#16a34a' );
        $sev_labels = array( 'high'=>'Kritisch', 'medium'=>'Mittel', 'low'=>'Gering' );
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-editor-textcolor"></span>
                Meta SEO Fixes
            </h1>

            <div class="aag-stats-kpi-grid">
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value"><?php echo $total; ?></span>
                    <span class="aag-stats-kpi-label">Seiten mit Problemen</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" style="color:#dc2626"><?php echo $high; ?></span>
                    <span class="aag-stats-kpi-label">Kritisch</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" style="color:#b45309"><?php echo $medium; ?></span>
                    <span class="aag-stats-kpi-label">Mittel</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" style="color:#16a34a"><?php echo $low; ?></span>
                    <span class="aag-stats-kpi-label">Gering</span>
                </div>
            </div>

            <?php if ( empty( $issues ) ) : ?>
            <div class="aag-card" style="text-align:center;padding:48px 24px">
                <h2 style="font-size:18px;color:#16a34a;margin:0 0 8px">Keine SEO-Probleme gefunden</h2>
                <p style="color:#64748b;margin:0">Alle Seiten und Beitraege sehen gut aus.</p>
            </div>
            <?php else : ?>
            <div class="aag-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
                    <h2 style="margin:0">Gefundene Probleme (<?php echo $total; ?> Seiten)</h2>
                    <button type="button" class="button button-primary" id="mss-auto-fix-all">
                        Alle fehlenden Meta Descriptions automatisch generieren (<?php echo $high; ?>)
                    </button>
                </div>

                <?php foreach ( $issues as $item ) :
                    $s     = $item['severity'];
                    $color = $sev_colors[ $s ] ?? '#888';
                    $label = $sev_labels[ $s ] ?? $s;
                    $needs_desc = array_filter( $item['issues'], function($i){ return in_array($i['type'],array('missing_meta_desc','short_meta_desc')); } );
                ?>
                <div class="mss-issue-row" id="mss-row-<?php echo $item['id']; ?>">
                    <div class="mss-issue-row-header <?php echo $s; ?>">
                        <div>
                            <span class="mss-sev-tag <?php echo $s; ?>"><?php echo $label; ?></span>
                            <strong class="mss-issue-title" style="display:block;margin-top:5px"><?php echo esc_html( $item['title'] ); ?></strong>
                            <span class="mss-issue-type"><?php echo ucfirst( $item['type'] ); ?></span>
                        </div>
                        <div style="display:flex;gap:8px;flex-shrink:0">
                            <a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" class="button button-small">Ansehen</a>
                            <a href="<?php echo esc_url( $item['edit_url'] ); ?>" target="_blank" class="button button-small">Bearbeiten</a>
                        </div>
                    </div>
                    <div class="mss-issue-body">
                        <ul class="mss-issue-list">
                            <?php foreach ( $item['issues'] as $iss ) : ?>
                            <li>
                                <span><strong><?php echo esc_html( $iss['label'] ); ?></strong>
                                <span class="mss-issue-fix"> &mdash; <?php echo esc_html( $iss['fix'] ); ?></span></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if ( ! empty( $needs_desc ) ) : ?>
                        <div class="mss-meta-editor">
                            <label>Meta Description bearbeiten</label>
                            <textarea class="mss-meta-textarea" data-id="<?php echo $item['id']; ?>"
                                      maxlength="160"
                                      placeholder="120-160 Zeichen..."><?php echo esc_textarea( $item['meta_desc'] ); ?></textarea>
                            <div class="mss-meta-editor-footer">
                                <span class="mss-char-count">0 / 160</span>
                                <div style="display:flex;gap:8px">
                                    <button type="button" class="button button-small mss-auto-desc-btn" data-id="<?php echo $item['id']; ?>">
                                        Automatisch generieren
                                    </button>
                                    <button type="button" class="button button-primary button-small mss-save-desc-btn" data-id="<?php echo $item['id']; ?>">
                                        Speichern
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(function($){
            var nonce='<?php echo esc_js($nonce);?>';
            var ajaxUrl='<?php echo esc_url(admin_url('admin-ajax.php'));?>';

            function errorMsg(res, fallback) {
                return res && res.data && res.data.message ? res.data.message : fallback;
            }

            $(document).on('input','.mss-meta-textarea',function(){
                var len=$(this).val().length;
                var c=$(this).closest('.mss-meta-editor').find('.mss-char-count');
                c.text(len+' / 160').css('color',len>=120&&len<=160?'#16a34a':(len>160?'#dc2626':'#b45309'));
            }).trigger('input');

            $(document).on('click','.mss-save-desc-btn',function(){
                var btn=$(this),id=btn.data('id'),desc=btn.closest('.mss-meta-editor').find('.mss-meta-textarea').val();
                btn.prop('disabled',true).text('Wird gespeichert...');
                $.post(ajaxUrl,{action:'mss_save_meta',nonce:nonce,post_id:id,meta_desc:desc},function(res){
                    btn.prop('disabled',false);
                    if(res.success){
                        btn.text('Gespeichert!');
                        setTimeout(function(){btn.text('Speichern');},2500);
                    } else {
                        btn.text('Fehler');
                        btn.closest('.mss-meta-editor').find('.mss-char-count').text(errorMsg(res,'Meta Description konnte nicht gespeichert werden.')).css('color','#dc2626');
                        setTimeout(function(){btn.text('Speichern');},2500);
                    }
                }).fail(function(){
                    btn.prop('disabled',false).text('Fehler');
                    btn.closest('.mss-meta-editor').find('.mss-char-count').text('Verbindungsfehler. Bitte versuche es erneut.').css('color','#dc2626');
                    setTimeout(function(){btn.text('Speichern');},2500);
                });
            });

            $(document).on('click','.mss-auto-desc-btn',function(){
                var btn=$(this),id=btn.data('id');
                btn.prop('disabled',true).text('Wird generiert...');
                $.post(ajaxUrl,{action:'mss_bulk_meta',nonce:nonce,post_id:id},function(res){
                    btn.prop('disabled',false).text('Automatisch generieren');
                    if(res.success) btn.closest('.mss-meta-editor').find('.mss-meta-textarea').val(res.data.desc).trigger('input');
                    else btn.closest('.mss-meta-editor').find('.mss-char-count').text(errorMsg(res,'Meta Description konnte nicht generiert werden.')).css('color','#dc2626');
                }).fail(function(){
                    btn.prop('disabled',false).text('Automatisch generieren');
                    btn.closest('.mss-meta-editor').find('.mss-char-count').text('Verbindungsfehler. Bitte versuche es erneut.').css('color','#dc2626');
                });
            });

            $('#mss-auto-fix-all').on('click',function(){
                if(!confirm('Alle fehlenden Meta Descriptions automatisch generieren?')) return;
                var btns=$('.mss-auto-desc-btn');
                var i=0;
                function next(){ if(i>=btns.length) return; btns.eq(i).trigger('click'); i++; setTimeout(next,600); }
                next();
            });
        });
        </script>
        <?php
    }
}
