<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSS_Meta_SEO {

    public static function init() {
        add_action( 'wp_ajax_mss_save_meta',    [ __CLASS__, 'ajax_save_meta' ] );
        add_action( 'wp_ajax_mss_bulk_meta',    [ __CLASS__, 'ajax_bulk_meta' ] );
    }

    // ── Analyse ───────────────────────────────────────────────
    public static function analyze(): array {
        $issues = [];

        $posts = get_posts([
            'post_type'      => ['post','page'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

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

            $post_issues = [];

            // 1. Fehlende Meta Description
            if ( empty( trim($meta_desc) ) ) {
                $post_issues[] = [
                    'type'     => 'missing_meta_desc',
                    'severity' => 'high',
                    'label'    => 'Meta Description fehlt',
                    'fix'      => 'Füge eine Meta Description (120–160 Zeichen) hinzu.',
                ];
            } elseif ( strlen($meta_desc) < 120 ) {
                $post_issues[] = [
                    'type'     => 'short_meta_desc',
                    'severity' => 'medium',
                    'label'    => 'Meta Description zu kurz (' . strlen($meta_desc) . ' Zeichen)',
                    'fix'      => 'Empfohlen: 120–160 Zeichen.',
                ];
            } elseif ( strlen($meta_desc) > 160 ) {
                $post_issues[] = [
                    'type'     => 'long_meta_desc',
                    'severity' => 'low',
                    'label'    => 'Meta Description zu lang (' . strlen($meta_desc) . ' Zeichen)',
                    'fix'      => 'Kürze auf max. 160 Zeichen.',
                ];
            }

            // 2. Title-Länge
            if ( strlen($title) < 30 ) {
                $post_issues[] = [
                    'type'     => 'short_title',
                    'severity' => 'medium',
                    'label'    => 'Titel zu kurz (' . strlen($title) . ' Zeichen)',
                    'fix'      => 'Empfohlen: 30–60 Zeichen.',
                ];
            } elseif ( strlen($title) > 60 ) {
                $post_issues[] = [
                    'type'     => 'long_title',
                    'severity' => 'low',
                    'label'    => 'Titel zu lang (' . strlen($title) . ' Zeichen)',
                    'fix'      => 'Kürze auf max. 60 Zeichen.',
                ];
            }

            // 3. Sehr dünner Content
            $word_count = str_word_count( $content );
            if ( $post->post_type === 'post' && $word_count < 300 ) {
                $post_issues[] = [
                    'type'     => 'thin_content',
                    'severity' => 'medium',
                    'label'    => 'Dünner Inhalt (' . $word_count . ' Wörter)',
                    'fix'      => 'Beiträge sollten mindestens 300 Wörter haben.',
                ];
            }

            // 4. Kein Focus Keyword
            if ( empty($focus_kw) ) {
                $post_issues[] = [
                    'type'     => 'no_focus_kw',
                    'severity' => 'low',
                    'label'    => 'Kein Focus-Keyword gesetzt',
                    'fix'      => 'Setze ein Focus-Keyword in Yoast SEO oder Rank Math.',
                ];
            }

            if ( ! empty($post_issues) ) {
                $issues[] = [
                    'id'        => $pid,
                    'title'     => $title,
                    'type'      => $post->post_type,
                    'url'       => get_permalink($pid),
                    'edit_url'  => get_edit_post_link($pid),
                    'meta_desc' => $meta_desc,
                    'issues'    => $post_issues,
                    'severity'  => self::worst_severity($post_issues),
                ];
            }
        }

        // Sortieren: high first
        $sev_order = ['high'=>0,'medium'=>1,'low'=>2];
        usort($issues, fn($a,$b) => ($sev_order[$a['severity']]??3) <=> ($sev_order[$b['severity']]??3));

        return $issues;
    }

    private static function worst_severity( array $issues ): string {
        $order = ['high','medium','low'];
        foreach ($order as $s) {
            foreach ($issues as $i) { if ($i['severity']===$s) return $s; }
        }
        return 'low';
    }

    // ── AJAX: Meta Description speichern ─────────────────────
    public static function ajax_save_meta() {
        check_ajax_referer( 'mss_meta_nonce', 'nonce' );
        if ( ! current_user_can('edit_posts') ) wp_send_json_error();

        $pid  = intval( $_POST['post_id'] ?? 0 );
        $desc = sanitize_textarea_field( $_POST['meta_desc'] ?? '' );

        // Yoast, AIOSEO, Rank Math — in welche auch immer verfügbar
        update_post_meta( $pid, '_yoast_wpseo_metadesc', $desc );
        update_post_meta( $pid, '_aioseop_description',  $desc );
        update_post_meta( $pid, '_rank_math_description',$desc );

        wp_send_json_success(['saved'=>true]);
    }

    public static function ajax_bulk_meta() {
        check_ajax_referer( 'mss_meta_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error();

        $pid  = intval( $_POST['post_id'] ?? 0 );
        $opts = get_option( MSS_OPTION, [] );

        $post    = get_post($pid);
        $content = wp_trim_words( wp_strip_all_tags($post->post_content), 30, '' );
        $title   = $post->post_title;

        // Einfache auto-generierte Description
        $desc = $title . '. ' . $content;
        $desc = substr($desc, 0, 155) . (strlen($desc)>155?'…':'');

        update_post_meta($pid,'_yoast_wpseo_metadesc',$desc);
        update_post_meta($pid,'_aioseop_description', $desc);
        update_post_meta($pid,'_rank_math_description',$desc);

        wp_send_json_success(['desc'=>$desc]);
    }

    // ── Seite rendern ─────────────────────────────────────────
    public static function render_page() {
        if (!current_user_can('manage_options')) wp_die();

        $nonce  = wp_create_nonce('mss_meta_nonce');
        $issues = self::analyze();
        $total  = count($issues);
        $high   = count(array_filter($issues,fn($i)=>$i['severity']==='high'));
        $medium = count(array_filter($issues,fn($i)=>$i['severity']==='medium'));
        $low    = count(array_filter($issues,fn($i)=>$i['severity']==='low'));

        $sev_colors = ['high'=>'#dc2626','medium'=>'#b45309','low'=>'#15803d'];
        $sev_bg     = ['high'=>'#fef2f2','medium'=>'#fffbeb','low'=>'#f0fdf4'];
        $sev_labels = ['high'=>'🔴 Kritisch','medium'=>'🟡 Mittel','low'=>'🟢 Gering'];
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-editor-textcolor"></span>
                📝 Meta SEO Fixes
            </h1>

            <div class="aag-stats-kpi-grid" style="grid-template-columns:repeat(4,1fr)">
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
                    <span class="aag-stats-kpi-value" style="color:#15803d"><?php echo $low; ?></span>
                    <span class="aag-stats-kpi-label">Gering</span>
                </div>
            </div>

            <?php if ( empty($issues) ) : ?>
            <div class="aag-card" style="text-align:center;padding:40px">
                <div style="font-size:48px;margin-bottom:12px">🎉</div>
                <h2>Keine SEO-Probleme gefunden!</h2>
                <p style="color:#64748b">Deine Website sieht gut aus.</p>
            </div>
            <?php else : ?>

            <div class="aag-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                    <h2 style="margin:0">SEO-Probleme (<?php echo $total; ?> Seiten)</h2>
                    <button type="button" class="button button-primary" id="mss-auto-fix-all">
                        ⚡ Alle fehlenden Meta Descriptions auto-generieren
                    </button>
                </div>

                <?php foreach ( $issues as $item ) :
                    $s = $item['severity'];
                ?>
                <div class="mss-meta-row" id="mss-row-<?php echo $item['id']; ?>"
                     style="border:1px solid <?php echo $sev_colors[$s]; ?>30;border-radius:10px;padding:16px;margin-bottom:12px;background:<?php echo $sev_bg[$s]; ?>20">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
                        <div>
                            <span style="font-size:11px;background:<?php echo $sev_bg[$s]; ?>;color:<?php echo $sev_colors[$s]; ?>;padding:2px 8px;border-radius:12px;font-weight:600;border:1px solid <?php echo $sev_colors[$s]; ?>30">
                                <?php echo $sev_labels[$s]; ?>
                            </span>
                            <strong style="display:block;margin-top:6px;font-size:14px"><?php echo esc_html($item['title']); ?></strong>
                            <span style="font-size:12px;color:#64748b"><?php echo ucfirst($item['type']); ?></span>
                        </div>
                        <div style="display:flex;gap:8px">
                            <a href="<?php echo esc_url($item['url']); ?>" target="_blank" class="button button-small">👁 Ansehen</a>
                            <a href="<?php echo esc_url($item['edit_url']); ?>" target="_blank" class="button button-small">✏️ Bearbeiten</a>
                        </div>
                    </div>

                    <!-- Issues-Liste -->
                    <div style="margin-top:10px">
                        <?php foreach ($item['issues'] as $iss) : ?>
                        <div style="display:flex;gap:8px;align-items:flex-start;padding:4px 0;font-size:13px">
                            <span style="color:<?php echo $sev_colors[$iss['severity']]; ?>;flex-shrink:0">●</span>
                            <span><strong><?php echo esc_html($iss['label']); ?></strong> — <?php echo esc_html($iss['fix']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Meta-Description Editor (bei fehlendem/kurzem Meta) -->
                    <?php $needs_desc = array_filter($item['issues'], fn($i)=>in_array($i['type'],['missing_meta_desc','short_meta_desc'])); ?>
                    <?php if (!empty($needs_desc)) : ?>
                    <div class="mss-meta-editor" style="margin-top:12px;padding-top:12px;border-top:1px solid <?php echo $sev_colors[$s]; ?>20">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">
                            Meta Description bearbeiten:
                        </label>
                        <textarea class="mss-desc-input" data-id="<?php echo $item['id']; ?>"
                                  style="width:100%;height:70px;font-size:13px;border-radius:6px;border:1px solid #e2e8f0;padding:8px;resize:vertical"
                                  maxlength="160"
                                  placeholder="120–160 Zeichen…"><?php echo esc_textarea($item['meta_desc']); ?></textarea>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">
                            <span class="mss-char-count" style="font-size:11px;color:#94a3b8">0 / 160</span>
                            <div style="display:flex;gap:8px">
                                <button type="button" class="button button-small mss-auto-desc-btn" data-id="<?php echo $item['id']; ?>">
                                    🤖 Auto-generieren
                                </button>
                                <button type="button" class="button button-primary button-small mss-save-desc-btn" data-id="<?php echo $item['id']; ?>">
                                    💾 Speichern
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(function($){
            var nonce='<?php echo esc_js($nonce);?>';
            var ajaxUrl='<?php echo esc_url(admin_url('admin-ajax.php'));?>';

            // Zeichenzähler
            $(document).on('input','.mss-desc-input',function(){
                var len=$(this).val().length;
                var c=$(this).closest('.mss-meta-editor').find('.mss-char-count');
                c.text(len+' / 160').css('color',len>=120&&len<=160?'#15803d':(len>160?'#dc2626':'#b45309'));
            }).trigger('input');

            // Speichern
            $(document).on('click','.mss-save-desc-btn',function(){
                var btn=$(this),id=btn.data('id'),desc=btn.closest('.mss-meta-editor').find('.mss-desc-input').val();
                btn.prop('disabled',true).text('⏳');
                $.post(ajaxUrl,{action:'mss_save_meta',nonce:nonce,post_id:id,meta_desc:desc},function(res){
                    btn.prop('disabled',false).text('💾 Speichern');
                    if(res.success) btn.text('✓ Gespeichert!').css('background','#15803d');
                    setTimeout(function(){btn.text('💾 Speichern').css('background','');},2500);
                });
            });

            // Auto-generieren (einzeln)
            $(document).on('click','.mss-auto-desc-btn',function(){
                var btn=$(this),id=btn.data('id');
                btn.prop('disabled',true).text('⏳');
                $.post(ajaxUrl,{action:'mss_bulk_meta',nonce:nonce,post_id:id},function(res){
                    btn.prop('disabled',false).text('🤖 Auto-generieren');
                    if(res.success) btn.closest('.mss-meta-editor').find('.mss-desc-input').val(res.data.desc).trigger('input');
                });
            });

            // Alle auto-generieren
            $('#mss-auto-fix-all').on('click',function(){
                if(!confirm('Fehlende Meta Descriptions für alle '+<?php echo $high; ?>+' kritischen Seiten auto-generieren?')) return;
                var btns=$('.mss-auto-desc-btn');
                var i=0;
                function next(){
                    if(i>=btns.length) return;
                    btns.eq(i).trigger('click');
                    i++;
                    setTimeout(next,500);
                }
                next();
            });
        });
        </script>
        <?php
    }
}
