<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_Usage_Tracker {

    const META_KEY = '_aag_usage_cache';
    const META_TS  = '_aag_usage_cached_at';
    const CACHE_TTL = 12 * HOUR_IN_SECONDS; // Cache 12 Stunden gültig

    public static function init() {
        // Spalte in der Medienbibliothek (Listenansicht)
        add_filter( 'manage_media_columns',        [ __CLASS__, 'add_column' ] );
        add_action( 'manage_media_custom_column',  [ __CLASS__, 'render_column' ], 10, 2 );
        add_filter( 'manage_upload_sortable_columns', [ __CLASS__, 'sortable_columns' ] );

        // Detailinfo in der Anhang-Bearbeitungsseite
        add_filter( 'attachment_fields_to_edit', [ __CLASS__, 'add_usage_field' ], 10, 2 );

        // AJAX: Cache neu aufbauen
        add_action( 'wp_ajax_aag_refresh_usage',  [ __CLASS__, 'ajax_refresh' ] );

        // Automatisch neu scannen wenn Post/Seite gespeichert wird
        add_action( 'save_post',   [ __CLASS__, 'invalidate_on_save' ], 10, 1 );
        add_action( 'delete_post', [ __CLASS__, 'invalidate_on_save' ], 10, 1 );

        // Admin-Assets
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // Eigene Seite (Vollansicht aller Bilder mit Usage)
        // wird vom Admin-Menü aufgerufen
    }

    // ── Spalte ───────────────────────────────────────────────
    public static function add_column( array $cols ): array {
        $cols['aag_usage'] = '📍 Verwendet in';
        return $cols;
    }

    public static function sortable_columns( array $cols ): array {
        $cols['aag_usage'] = 'aag_usage';
        return $cols;
    }

    public static function render_column( string $col, int $post_id ) {
        if ( $col !== 'aag_usage' ) return;
        if ( ! wp_attachment_is_image( $post_id ) ) { echo '—'; return; }

        $usage = self::get_usage( $post_id );
        $count = count( $usage );

        if ( $count === 0 ) {
            echo '<span style="color:#94a3b8;font-size:12px">Nicht verwendet</span>';
            return;
        }

        echo '<span class="aag-usage-badge" style="background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:600">';
        echo $count . 'x';
        echo '</span> ';
        echo '<a href="#" class="aag-usage-toggle" data-id="' . $post_id . '" style="font-size:12px">Details</a>';
        echo '<div class="aag-usage-list" id="aag-ul-' . $post_id . '" style="display:none;margin-top:6px">';
        foreach ( array_slice( $usage, 0, 5 ) as $item ) {
            echo '<div style="font-size:11px;padding:2px 0;border-bottom:1px solid #f1f5f9">';
            echo '<a href="' . esc_url( get_edit_post_link( $item['id'] ) ) . '" target="_blank" style="color:#1d4ed8">';
            echo esc_html( $item['title'] );
            echo '</a>';
            echo ' <span style="color:#94a3b8">(' . esc_html( $item['type'] ) . ')</span>';
            echo '</div>';
        }
        if ( $count > 5 ) {
            echo '<div style="font-size:11px;color:#94a3b8;margin-top:4px">+ ' . ( $count - 5 ) . ' weitere</div>';
        }
        echo '</div>';
    }

    // ── Anhang-Bearbeitungsseite ──────────────────────────────
    public static function add_usage_field( array $fields, WP_Post $post ): array {
        if ( ! wp_attachment_is_image( $post->ID ) ) return $fields;

        $usage     = self::get_usage( $post->ID );
        $count     = count( $usage );
        $cached_at = get_post_meta( $post->ID, self::META_TS, true );
        $age       = $cached_at ? human_time_diff( $cached_at ) . ' alt' : 'noch nie';

        $html = '<div class="aag-usage-detail">';

        if ( $count === 0 ) {
            $html .= '<p style="color:#94a3b8;margin:0">Dieses Bild wird nirgendwo verwendet.</p>';
        } else {
            $html .= '<p style="margin:0 0 8px;font-weight:600;color:#1e293b">' . $count . 'x verwendet</p>';
            $html .= '<div style="max-height:180px;overflow-y:auto">';
            foreach ( $usage as $item ) {
                $html .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:12px">';
                $html .= '<a href="' . esc_url( get_edit_post_link( $item['id'] ) ) . '" target="_blank" style="color:#1d4ed8;font-weight:500">' . esc_html( $item['title'] ) . '</a>';
                $html .= '<span style="color:#94a3b8;margin-left:8px;flex-shrink:0">' . esc_html( ucfirst( $item['type'] ) ) . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '<div style="margin-top:10px;display:flex;align-items:center;gap:10px">';
        $html .= '<button type="button" class="button aag-refresh-usage-btn" data-id="' . $post->ID . '">🔄 Neu scannen</button>';
        $html .= '<span style="font-size:11px;color:#94a3b8">Cache: ' . esc_html( $age ) . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        $fields['aag_usage'] = [
            'label' => '📍 Verwendet in',
            'input' => 'html',
            'html'  => $html,
        ];

        return $fields;
    }

    // ── Assets ────────────────────────────────────────────────
    public static function enqueue_assets( string $hook ) {
        if ( ! in_array( $hook, [ 'upload.php', 'post.php' ] ) ) return;
        ?>
        <style>
        .aag-usage-list a:hover { text-decoration:underline; }
        .column-aag_usage { width: 140px; }
        </style>
        <script>
        jQuery(function($){
            // Toggle in Listenansicht
            $(document).on('click', '.aag-usage-toggle', function(e){
                e.preventDefault();
                var id = $(this).data('id');
                $('#aag-ul-' + id).toggle();
                $(this).text( $('#aag-ul-' + id).is(':visible') ? 'Schließen' : 'Details' );
            });
            // Neu scannen Button
            $(document).on('click', '.aag-refresh-usage-btn', function(){
                var btn = $(this);
                var id  = btn.data('id');
                btn.prop('disabled', true).text('⏳ Wird gescannt…');
                $.post(ajaxurl, {
                    action: 'aag_refresh_usage',
                    nonce:  '<?php echo wp_create_nonce("aag_refresh_usage"); ?>',
                    id:     id,
                }, function(res){
                    btn.prop('disabled', false).text('🔄 Neu scannen');
                    if (res.success) location.reload();
                });
            });
        });
        </script>
        <?php
    }

    // ── AJAX: Cache neu aufbauen ──────────────────────────────
    public static function ajax_refresh() {
        check_ajax_referer( 'aag_refresh_usage', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error();

        $id    = intval( $_POST['id'] ?? 0 );
        $usage = self::scan_usage( $id );
        wp_send_json_success( [ 'count' => count( $usage ) ] );
    }

    // ── Cache invalidieren wenn Post gespeichert ──────────────
    public static function invalidate_on_save( int $post_id ) {
        // Alle Bild-IDs im gespeicherten Content suchen und deren Cache löschen
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type === 'attachment' ) return;

        // Bild-IDs aus dem Content extrahieren
        $ids = self::extract_image_ids_from_post( $post );
        foreach ( $ids as $img_id ) {
            delete_post_meta( $img_id, self::META_KEY );
            delete_post_meta( $img_id, self::META_TS );
        }

        // Auch Featured Images
        $thumb = get_post_thumbnail_id( $post_id );
        if ( $thumb ) {
            delete_post_meta( $thumb, self::META_KEY );
            delete_post_meta( $thumb, self::META_TS );
        }
    }

    // ── Usage ermitteln (mit Cache) ───────────────────────────
    public static function get_usage( int $attachment_id ): array {
        $cached_at = get_post_meta( $attachment_id, self::META_TS, true );

        // Cache gültig?
        if ( $cached_at && ( time() - intval( $cached_at ) ) < self::CACHE_TTL ) {
            $cached = get_post_meta( $attachment_id, self::META_KEY, true );
            if ( is_array( $cached ) ) return $cached;
        }

        return self::scan_usage( $attachment_id );
    }

    // ── Vollständiger Scan ────────────────────────────────────
    public static function scan_usage( int $attachment_id ): array {
        $url      = wp_get_attachment_url( $attachment_id );
        $filename = basename( $url ?? '' );
        $results  = [];
        $seen     = [];

        if ( ! $url ) {
            self::save_cache( $attachment_id, [] );
            return [];
        }

        // 1. Alle veröffentlichten Posts/Pages/CPTs durchsuchen
        $posts = get_posts( [
            'post_type'      => 'any',
            'post_status'    => [ 'publish', 'draft', 'private', 'pending' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $posts as $pid ) {
            if ( isset( $seen[ $pid ] ) ) continue;
            $post = get_post( $pid );
            if ( ! $post ) continue;

            $found = false;

            // a) Im post_content (klassischer Editor, Gutenberg HTML)
            if ( $post->post_content &&
                 ( strpos( $post->post_content, $filename ) !== false ||
                   strpos( $post->post_content, (string) $attachment_id ) !== false ) ) {
                $found = true;
            }

            // b) Als Featured Image
            if ( ! $found && get_post_thumbnail_id( $pid ) == $attachment_id ) {
                $found = true;
            }

            // c) In Post-Meta (z.B. ACF, Elementor, Divi etc.)
            if ( ! $found ) {
                $meta = get_post_meta( $pid );
                foreach ( $meta as $key => $values ) {
                    foreach ( $values as $v ) {
                        if ( strpos( (string)$v, $filename ) !== false ||
                             strpos( (string)$v, '"' . $attachment_id . '"' ) !== false ||
                             (string)$v === (string)$attachment_id ) {
                            $found = true;
                            break 2;
                        }
                    }
                }
            }

            if ( $found ) {
                $seen[ $pid ] = true;
                $results[] = [
                    'id'    => $pid,
                    'title' => $post->post_title ?: __( '(ohne Titel)', 'ai-alt-gen' ),
                    'type'  => self::get_post_type_label( $post->post_type ),
                    'url'   => get_permalink( $pid ),
                    'status'=> $post->post_status,
                ];
            }
        }

        // 2. Widget-Bereiche (Sidebars, Footer etc.)
        $widget_usage = self::scan_widgets( $filename, $attachment_id );
        foreach ( $widget_usage as $w ) {
            $results[] = $w;
        }

        // 3. Theme-Customizer (background image, header image etc.)
        $theme_mods = get_theme_mods();
        if ( $theme_mods ) {
            $theme_str = serialize( $theme_mods );
            if ( strpos( $theme_str, $filename ) !== false ) {
                $results[] = [
                    'id'    => 0,
                    'title' => 'Theme-Einstellungen (Customizer)',
                    'type'  => 'Theme',
                    'url'   => admin_url( 'customize.php' ),
                    'status'=> 'active',
                ];
            }
        }

        self::save_cache( $attachment_id, $results );
        return $results;
    }

    private static function scan_widgets( string $filename, int $att_id ): array {
        $found   = [];
        $sidebars = get_option( 'sidebars_widgets', [] );

        foreach ( $sidebars as $sidebar_id => $widget_ids ) {
            if ( ! is_array( $widget_ids ) || $sidebar_id === 'wp_inactive_widgets' ) continue;
            foreach ( $widget_ids as $widget_id ) {
                $type   = preg_replace( '/-\d+$/', '', $widget_id );
                $number = preg_replace( '/^.+-/', '', $widget_id );
                $data   = get_option( 'widget_' . $type, [] );
                if ( ! isset( $data[ $number ] ) ) continue;
                $str = serialize( $data[ $number ] );
                if ( strpos( $str, $filename ) !== false || strpos( $str, '"' . $att_id . '"' ) !== false ) {
                    $found[] = [
                        'id'    => 0,
                        'title' => 'Widget: ' . $sidebar_id . ' / ' . $widget_id,
                        'type'  => 'Widget',
                        'url'   => admin_url( 'widgets.php' ),
                        'status'=> 'active',
                    ];
                }
            }
        }
        return $found;
    }

    private static function get_post_type_label( string $type ): string {
        $labels = [
            'post'       => 'Beitrag',
            'page'       => 'Seite',
            'attachment' => 'Medien',
            'product'    => 'Produkt',
        ];
        if ( isset( $labels[ $type ] ) ) return $labels[ $type ];
        $obj = get_post_type_object( $type );
        return $obj ? $obj->labels->singular_name : $type;
    }

    private static function extract_image_ids_from_post( WP_Post $post ): array {
        $ids = [];
        if ( preg_match_all( '/wp-image-(\d+)/', $post->post_content, $m ) ) {
            $ids = array_merge( $ids, $m[1] );
        }
        if ( preg_match_all( '/"id":(\d+)/', $post->post_content, $m ) ) {
            $ids = array_merge( $ids, $m[1] );
        }
        return array_unique( array_map( 'intval', $ids ) );
    }

    private static function save_cache( int $id, array $data ) {
        update_post_meta( $id, self::META_KEY, $data );
        update_post_meta( $id, self::META_TS,  time() );
    }

    // ── Übersichtsseite ───────────────────────────────────────
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung.' );

        // Filter
        $filter = sanitize_text_field( $_GET['filter'] ?? 'all' );

        // Alle Bilder laden
        $images = get_posts( [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        // Usage sammeln
        $rows = [];
        foreach ( $images as $img_id ) {
            $usage = self::get_usage( $img_id );
            $count = count( $usage );
            if ( $filter === 'unused'  && $count > 0  ) continue;
            if ( $filter === 'used'    && $count === 0 ) continue;
            $rows[] = [
                'id'    => $img_id,
                'title' => get_the_title( $img_id ) ?: basename( get_attached_file( $img_id ) ),
                'thumb' => wp_get_attachment_image_url( $img_id, 'thumbnail' ),
                'count' => $count,
                'usage' => $usage,
                'alt'   => get_post_meta( $img_id, '_wp_attachment_image_alt', true ),
            ];
        }

        // Sortieren: meistgenutzt zuerst
        usort( $rows, fn($a,$b) => $b['count'] - $a['count'] );

        $total      = count( $images );
        $used_count = count( array_filter( $rows, fn($r) => $r['count'] > 0 ) );
        $unused     = $total - $used_count;
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-visibility"></span>
                Bild-Verwendung
            </h1>

            <!-- KPIs -->
            <div class="aag-stats-kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value"><?php echo $total; ?></span>
                    <span class="aag-stats-kpi-label">Bilder gesamt</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" style="color:#15803d"><?php echo $used_count; ?></span>
                    <span class="aag-stats-kpi-label">In Verwendung</span>
                </div>
                <div class="aag-stats-kpi">
                    <span class="aag-stats-kpi-value" style="color:<?php echo $unused > 0 ? '#dc2626' : '#15803d'; ?>"><?php echo $unused; ?></span>
                    <span class="aag-stats-kpi-label">Nicht verwendet</span>
                </div>
            </div>

            <!-- Filter-Tabs -->
            <div class="aag-usage-tabs">
                <a href="?page=ai-alt-usage&filter=all"    class="aag-usage-tab <?php echo $filter === 'all'    ? 'active' : ''; ?>">Alle (<?php echo $total; ?>)</a>
                <a href="?page=ai-alt-usage&filter=used"   class="aag-usage-tab <?php echo $filter === 'used'   ? 'active' : ''; ?>">In Verwendung (<?php echo $used_count; ?>)</a>
                <a href="?page=ai-alt-usage&filter=unused" class="aag-usage-tab <?php echo $filter === 'unused' ? 'active' : ''; ?>">Nicht verwendet (<?php echo $unused; ?>)</a>
            </div>

            <div class="aag-card" style="padding:0;overflow:hidden">
                <table class="wp-list-table widefat fixed striped" style="border:none">
                    <thead>
                        <tr>
                            <th style="width:64px">Bild</th>
                            <th>Dateiname</th>
                            <th style="width:100px">Alt-Text</th>
                            <th style="width:90px">Verwendet</th>
                            <th>Seiten / Beiträge</th>
                            <th style="width:90px">Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $rows ) ) : ?>
                        <tr><td colspan="6" style="text-align:center;padding:32px;color:#94a3b8">Keine Bilder gefunden.</td></tr>
                        <?php else : ?>
                        <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <td>
                                <?php if ( $row['thumb'] ) : ?>
                                    <img src="<?php echo esc_url( $row['thumb'] ); ?>"
                                         style="width:48px;height:48px;object-fit:cover;border-radius:6px">
                                <?php else : ?>
                                    <div style="width:48px;height:48px;background:#f1f5f9;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#94a3b8">?</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="font-size:13px"><?php echo esc_html( $row['title'] ); ?></strong>
                            </td>
                            <td>
                                <?php if ( $row['alt'] ) : ?>
                                    <span style="color:#15803d;font-size:12px">✓</span>
                                <?php else : ?>
                                    <span style="color:#dc2626;font-size:12px">✗ Fehlt</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $row['count'] > 0 ) : ?>
                                    <span style="background:#e0f2fe;color:#0369a1;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600">
                                        <?php echo $row['count']; ?>x
                                    </span>
                                <?php else : ?>
                                    <span style="color:#94a3b8;font-size:12px">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( ! empty( $row['usage'] ) ) : ?>
                                    <div style="font-size:12px">
                                        <?php foreach ( array_slice( $row['usage'], 0, 3 ) as $u ) : ?>
                                        <div style="padding:2px 0">
                                            <a href="<?php echo esc_url( get_edit_post_link( $u['id'] ) ?: $u['url'] ); ?>"
                                               target="_blank" style="color:#1d4ed8">
                                                <?php echo esc_html( $u['title'] ); ?>
                                            </a>
                                            <span style="color:#94a3b8"> (<?php echo esc_html( $u['type'] ); ?>)</span>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if ( $row['count'] > 3 ) : ?>
                                            <span style="color:#94a3b8">+<?php echo $row['count'] - 3; ?> weitere</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else : ?>
                                    <span style="color:#94a3b8;font-size:12px">Nirgendwo eingebunden</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>"
                                   class="button button-small">Bearbeiten</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p style="margin-top:16px;font-size:12px;color:#94a3b8">
                Cache-Dauer: 12 Stunden. Wird automatisch aktualisiert wenn Seiten gespeichert werden.
            </p>
        </div>

        <style>
        .aag-usage-tabs { display:flex; gap:0; margin-bottom:0; border-bottom:2px solid #e2e8f0; }
        .aag-usage-tab { padding:10px 20px; font-size:13px; color:#64748b; text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-2px; transition:color .15s,border-color .15s; }
        .aag-usage-tab:hover { color:#6366f1; }
        .aag-usage-tab.active { color:#6366f1; border-bottom-color:#6366f1; font-weight:600; }
        </style>
        <?php
    }
}
