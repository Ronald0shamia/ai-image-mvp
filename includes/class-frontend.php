<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_Frontend {

    public static function init() {
        add_shortcode( 'aag_preview', array( __CLASS__, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_aag_frontend_generate',        array( __CLASS__, 'ajax_frontend_generate' ) );
        add_action( 'wp_ajax_nopriv_aag_frontend_generate', array( __CLASS__, 'ajax_frontend_generate' ) );
        add_action( 'wp_ajax_aag_frontend_seo',             array( __CLASS__, 'ajax_frontend_seo' ) );
        add_action( 'wp_ajax_nopriv_aag_frontend_seo',      array( __CLASS__, 'ajax_frontend_seo' ) );
    }

    public static function enqueue_assets() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'aag_preview' ) ) return;
        wp_enqueue_style( 'aag-frontend-shortcode', AAG_URL . 'assets/frontend-shortcode.css', array(), AAG_VERSION );
    }

    public static function render_shortcode( $atts ): string {
        $atts = shortcode_atts( array(
            'title'       => 'SEO & Alt-Text Generator',
            'button_text' => 'Alt-Text generieren',
        ), $atts, 'aag_preview' );

        $opts         = get_option( AAG_OPTION, array() );
        $ad_type      = $opts['ad_type']      ?? 'image';
        $ad_image_url = $opts['ad_image_url'] ?? '';
        $ad_link      = $opts['ad_link']      ?? '';
        $ad_html      = $opts['ad_html']      ?? '';
        $popup_delay  = intval( $opts['ad_popup_delay'] ?? 0 );
        $nonce        = wp_create_nonce( 'aag_frontend_nonce' );
        $uid          = 'aag' . uniqid();

        if ( $ad_type === 'image' && $ad_image_url ) {
            $ad_content = $ad_link
                ? '<a href="' . esc_url($ad_link) . '" target="_blank" rel="noopener sponsored"><img src="' . esc_url($ad_image_url) . '" alt="Anzeige" class="aag-popup-ad-img"></a>'
                : '<img src="' . esc_url($ad_image_url) . '" alt="Anzeige" class="aag-popup-ad-img">';
        } elseif ( $ad_type === 'html' && $ad_html ) {
            $ad_content = $ad_html;
        } else {
            $ad_content = '<div class="aag-popup-ad-placeholder"><span>Anzeige</span></div>';
        }

        ob_start();
        ?>
        <div class="aag-sc-wrapper" id="<?php echo esc_attr($uid); ?>">

            <!-- ── TAB NAVIGATION ─────────────────────── -->
            <div class="aag-tabs">
                <button type="button" class="aag-tab active" data-tab="alt">Bild Alt-Text</button>
                <button type="button" class="aag-tab" data-tab="seo">SEO Titel &amp; Beschreibung</button>
            </div>

            <!-- ════════════════════════════════════════
                 TAB 1: BILD → ALT-TEXT
            ════════════════════════════════════════ -->
            <div class="aag-tab-panel" data-panel="alt">

                <div class="aag-sc-upload" id="<?php echo $uid; ?>-upload">
                    <div class="aag-sc-upload-icon">&#128444;</div>
                    <p class="aag-sc-upload-label">Bild hier ablegen oder klicken zum Auswaehlen</p>
                    <p class="aag-sc-upload-hint">JPG, PNG, WebP &mdash; max. 5 MB</p>
                    <input type="file" id="<?php echo $uid; ?>-file" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
                </div>

                <div class="aag-sc-preview" id="<?php echo $uid; ?>-preview" style="display:none">
                    <img id="<?php echo $uid; ?>-img" src="" alt="Vorschau">
                    <button type="button" class="aag-sc-btn-remove" id="<?php echo $uid; ?>-remove">Anderes Bild waehlen</button>
                </div>

                <div class="aag-sc-action">
                    <button type="button" class="aag-sc-btn-analyze" id="<?php echo $uid; ?>-analyze" disabled>
                        <?php echo esc_html($atts['button_text']); ?>
                    </button>
                </div>

                <div class="aag-sc-result" id="<?php echo $uid; ?>-result" style="display:none">
                    <div class="aag-sc-result-header"><strong>Generierter Alt-Text</strong></div>
                    <div class="aag-sc-result-text" id="<?php echo $uid; ?>-result-text"></div>
                    <div class="aag-sc-result-actions">
                        <button type="button" class="aag-sc-btn-copy" id="<?php echo $uid; ?>-copy">Kopieren</button>
                        <button type="button" class="aag-sc-btn-reset" id="<?php echo $uid; ?>-reset">Neues Bild</button>
                    </div>
                    <p class="aag-sc-result-hint">Fuege diesen Text als <code>alt=""</code> in dein Bild-Tag ein.</p>
                </div>

                <div class="aag-sc-error" id="<?php echo $uid; ?>-error" style="display:none"></div>
            </div>

            <!-- ════════════════════════════════════════
                 TAB 2: URL → SEO TITEL + BESCHREIBUNG
            ════════════════════════════════════════ -->
            <div class="aag-tab-panel" data-panel="seo" style="display:none">

                <div class="aag-seo-input-wrap">
                    <label class="aag-seo-label" for="<?php echo $uid; ?>-url">Website-URL eingeben</label>
                    <div class="aag-seo-url-row">
                        <input type="url"
                               id="<?php echo $uid; ?>-url"
                               class="aag-seo-url-input"
                               placeholder="https://beispiel.de/meine-seite">
                        <button type="button" class="aag-sc-btn-analyze" id="<?php echo $uid; ?>-seo-btn">
                            SEO-Texte generieren
                        </button>
                    </div>
                    <p class="aag-seo-hint">Die KI analysiert die Seite und generiert einen optimierten Titel und eine Meta Description.</p>
                </div>

                <div class="aag-sc-error" id="<?php echo $uid; ?>-seo-error" style="display:none"></div>

                <div class="aag-seo-result" id="<?php echo $uid; ?>-seo-result" style="display:none">

                    <!-- Titel -->
                    <div class="aag-seo-result-block">
                        <div class="aag-seo-result-header">
                            <span class="aag-seo-result-label">SEO-Titel</span>
                            <span class="aag-seo-char-count" id="<?php echo $uid; ?>-title-count">0 / 60</span>
                        </div>
                        <div class="aag-seo-result-text" id="<?php echo $uid; ?>-seo-title" contenteditable="true"></div>
                        <div class="aag-seo-result-actions">
                            <button type="button" class="aag-sc-btn-copy aag-copy-field" data-target="<?php echo $uid; ?>-seo-title">Kopieren</button>
                            <a class="aag-sc-btn-copy aag-yoast-link"
                               href="<?php echo esc_url(admin_url('admin.php?page=wpseo_tools&tool=bulk-editor#top#title')); ?>"
                               target="_blank">In Yoast SEO bearbeiten</a>
                        </div>
                    </div>

                    <!-- Meta Description -->
                    <div class="aag-seo-result-block">
                        <div class="aag-seo-result-header">
                            <span class="aag-seo-result-label">Meta Description</span>
                            <span class="aag-seo-char-count" id="<?php echo $uid; ?>-desc-count">0 / 160</span>
                        </div>
                        <div class="aag-seo-result-text" id="<?php echo $uid; ?>-seo-desc" contenteditable="true"></div>
                        <div class="aag-seo-result-actions">
                            <button type="button" class="aag-sc-btn-copy aag-copy-field" data-target="<?php echo $uid; ?>-seo-desc">Kopieren</button>
                            <a class="aag-sc-btn-copy aag-yoast-link"
                               href="<?php echo esc_url(admin_url('admin.php?page=wpseo_tools&tool=bulk-editor#top#description')); ?>"
                               target="_blank">In Yoast SEO bearbeiten</a>
                        </div>
                    </div>

                    <!-- Google-Vorschau -->
                    <div class="aag-google-preview">
                        <p class="aag-google-preview-label">Google-Vorschau</p>
                        <div class="aag-google-card">
                            <div class="aag-google-url" id="<?php echo $uid; ?>-preview-url"></div>
                            <div class="aag-google-title" id="<?php echo $uid; ?>-preview-title"></div>
                            <div class="aag-google-desc" id="<?php echo $uid; ?>-preview-desc"></div>
                        </div>
                    </div>

                    <button type="button" class="aag-sc-btn-reset" id="<?php echo $uid; ?>-seo-reset" style="margin-top:12px">Neue URL analysieren</button>
                </div>
            </div>

        </div><!-- end .aag-sc-wrapper -->

        <!-- Ad Popup -->
        <div class="aag-popup-overlay" id="<?php echo $uid; ?>-popup" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="aag-popup-box">
                <div class="aag-popup-header">
                    <span class="aag-popup-label">Anzeige</span>
                    <div class="aag-popup-countdown" id="<?php echo $uid; ?>-countdown" style="display:none"></div>
                    <button type="button" class="aag-popup-close" id="<?php echo $uid; ?>-close" aria-label="Schliessen">&#x2715;</button>
                </div>
                <div class="aag-popup-ad-content"><?php echo $ad_content; ?></div>
                <div class="aag-popup-loader">
                    <div class="aag-popup-spinner"></div>
                    <span>KI analysiert...</span>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var uid        = '<?php echo esc_js($uid); ?>';
            var ajaxUrl    = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
            var nonce      = '<?php echo esc_js($nonce); ?>';
            var popupDelay = <?php echo intval($popup_delay); ?>;

            /* ── helpers ── */
            function q(id){ return document.getElementById(id); }
            var uploadArea = q(uid+'-upload'), fileInput = q(uid+'-file'),
                preview    = q(uid+'-preview'), previewImg = q(uid+'-img'),
                btnRemove  = q(uid+'-remove'),  btnAnalyze = q(uid+'-analyze'),
                resultBox  = q(uid+'-result'),  resultText = q(uid+'-result-text'),
                btnCopy    = q(uid+'-copy'),    btnReset   = q(uid+'-reset'),
                errorBox   = q(uid+'-error'),
                popup      = q(uid+'-popup'),   popupClose = q(uid+'-close'),
                countdown  = q(uid+'-countdown'),
                seoBtn     = q(uid+'-seo-btn'), seoUrl     = q(uid+'-url'),
                seoError   = q(uid+'-seo-error'), seoResult = q(uid+'-seo-result'),
                seoTitle   = q(uid+'-seo-title'), seoDesc   = q(uid+'-seo-desc'),
                seoReset   = q(uid+'-seo-reset'),
                titleCount = q(uid+'-title-count'), descCount  = q(uid+'-desc-count'),
                prevUrl    = q(uid+'-preview-url'), prevTitle  = q(uid+'-preview-title'),
                prevDesc   = q(uid+'-preview-desc'),
                selectedFile = null, cdTimer = null;

            /* ── Tab switching ── */
            var wrapper = document.getElementById(uid);
            wrapper.querySelectorAll('.aag-tab').forEach(function(btn){
                btn.addEventListener('click', function(){
                    wrapper.querySelectorAll('.aag-tab').forEach(function(b){ b.classList.remove('active'); });
                    wrapper.querySelectorAll('.aag-tab-panel').forEach(function(p){ p.style.display='none'; });
                    btn.classList.add('active');
                    wrapper.querySelector('.aag-tab-panel[data-panel="'+btn.dataset.tab+'"]').style.display='block';
                });
            });

            /* ── Upload ── */
            uploadArea.addEventListener('click', function(e){ if(!btnRemove.contains(e.target)) fileInput.click(); });
            uploadArea.addEventListener('dragover', function(e){ e.preventDefault(); uploadArea.classList.add('drag-over'); });
            uploadArea.addEventListener('dragleave', function(){ uploadArea.classList.remove('drag-over'); });
            uploadArea.addEventListener('drop', function(e){ e.preventDefault(); uploadArea.classList.remove('drag-over'); if(e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); });
            fileInput.addEventListener('change', function(){ if(this.files[0]) handleFile(this.files[0]); });

            function handleFile(file){
                if(!file.type.startsWith('image/')){ showError(errorBox,'Bitte nur Bilddateien hochladen.'); return; }
                if(file.size > 5*1024*1024){ showError(errorBox,'Datei zu gross. Maximal 5 MB.'); return; }
                selectedFile = file;
                var reader = new FileReader();
                reader.onload = function(e){
                    previewImg.src = e.target.result;
                    uploadArea.style.display = 'none';
                    preview.style.display    = 'block';
                    btnAnalyze.disabled      = false;
                    hideError(errorBox);
                };
                reader.readAsDataURL(file);
            }

            btnRemove.addEventListener('click', resetAlt);
            btnReset.addEventListener('click', resetAlt);
            function resetAlt(){
                selectedFile = null; fileInput.value=''; previewImg.src='';
                uploadArea.style.display='block'; preview.style.display='none';
                resultBox.style.display='none'; resultText.textContent='';
                btnAnalyze.disabled=true; closePopup(); hideError(errorBox);
            }

            /* ── Popup ── */
            function openPopup(){
                popup.classList.add('active'); popup.setAttribute('aria-hidden','false');
                document.body.style.overflow='hidden';
                if(popupDelay>0){
                    var s=popupDelay; countdown.style.display='block';
                    countdown.textContent='Schliesst in '+s+'s';
                    cdTimer=setInterval(function(){ s--; countdown.textContent='Schliesst in '+s+'s'; if(s<=0){clearInterval(cdTimer);closePopup();} },1000);
                }
            }
            function closePopup(){
                popup.classList.remove('active'); popup.setAttribute('aria-hidden','true');
                document.body.style.overflow='';
                if(cdTimer){clearInterval(cdTimer);cdTimer=null;}
                countdown.style.display='none';
            }
            popupClose.addEventListener('click', closePopup);
            popup.addEventListener('click', function(e){ if(e.target===popup) closePopup(); });
            document.addEventListener('keydown', function(e){ if(e.key==='Escape') closePopup(); });

            /* ── Alt-Text Analyse ── */
            btnAnalyze.addEventListener('click', function(){
                if(!selectedFile) return;
                btnAnalyze.disabled=true; resultBox.style.display='none'; hideError(errorBox); openPopup();
                var reader=new FileReader();
                reader.onload=function(e){
                    var base64=e.target.result.split(',')[1], mime=selectedFile.type;
                    var fd=new FormData();
                    fd.append('action','aag_frontend_generate'); fd.append('nonce',nonce);
                    fd.append('image_data',base64); fd.append('mime_type',mime);
                    fetch(ajaxUrl,{method:'POST',body:fd})
                    .then(function(r){return r.json();})
                    .then(function(data){
                        closePopup(); btnAnalyze.disabled=false;
                        if(data.success){ resultText.textContent=data.data.alt; resultBox.style.display='block'; resultBox.scrollIntoView({behavior:'smooth',block:'nearest'}); }
                        else showError(errorBox, errorMsg(data,'Fehler bei der Analyse.'));
                    }).catch(function(){ closePopup(); btnAnalyze.disabled=false; showError(errorBox,'Verbindungsfehler.'); });
                };
                reader.readAsDataURL(selectedFile);
            });

            btnCopy.addEventListener('click', function(){
                var text=resultText.textContent; if(!text) return;
                navigator.clipboard.writeText(text).then(function(){ btnCopy.textContent='Kopiert!'; setTimeout(function(){ btnCopy.textContent='Kopieren'; },2000); });
            });

            /* ── SEO Analyse ── */
            seoBtn.addEventListener('click', function(){
                var url = seoUrl.value.trim();
                if(!url){ showError(seoError,'Bitte eine URL eingeben.'); return; }
                if(!url.startsWith('http')){ showError(seoError,'Bitte eine vollstaendige URL mit https:// eingeben.'); return; }
                seoBtn.disabled=true; seoBtn.textContent='Wird analysiert...';
                seoResult.style.display='none'; hideError(seoError); openPopup();

                var fd=new FormData();
                fd.append('action','aag_frontend_seo'); fd.append('nonce',nonce); fd.append('url',url);
                fetch(ajaxUrl,{method:'POST',body:fd})
                .then(function(r){return r.json();})
                .then(function(data){
                    closePopup(); seoBtn.disabled=false; seoBtn.textContent='SEO-Texte generieren';
                    if(data.success){
                        var d=data.data;
                        seoTitle.textContent = d.title;
                        seoDesc.textContent  = d.description;
                        prevUrl.textContent  = url.replace(/^https?:\/\//,'');
                        prevTitle.textContent= d.title;
                        prevDesc.textContent = d.description;
                        updateCount(seoTitle, titleCount, 60);
                        updateCount(seoDesc,  descCount,  160);
                        seoResult.style.display='block';
                        seoResult.scrollIntoView({behavior:'smooth',block:'nearest'});
                    } else showError(seoError, errorMsg(data,'Fehler bei der Analyse.'));
                }).catch(function(){ closePopup(); seoBtn.disabled=false; seoBtn.textContent='SEO-Texte generieren'; showError(seoError,'Verbindungsfehler.'); });
            });

            /* Live Zeichenzaehler bei Bearbeitung */
            seoTitle.addEventListener('input', function(){ updateCount(seoTitle,titleCount,60); prevTitle.textContent=seoTitle.textContent; });
            seoDesc.addEventListener('input',  function(){ updateCount(seoDesc, descCount, 160); prevDesc.textContent=seoDesc.textContent; });

            function updateCount(el, counter, max){
                var len=el.textContent.length;
                counter.textContent=len+' / '+max;
                counter.style.color=len>max?'#dc2626':(len>=Math.round(max*0.75)?'#16a34a':'#94a3b8');
            }

            function errorMsg(data, fallback){
                return data && data.data && data.data.message ? data.data.message : fallback;
            }

            /* Kopieren fuer SEO-Felder */
            wrapper.querySelectorAll('.aag-copy-field').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var target=document.getElementById(btn.dataset.target);
                    if(!target) return;
                    navigator.clipboard.writeText(target.textContent).then(function(){
                        var orig=btn.textContent; btn.textContent='Kopiert!';
                        setTimeout(function(){ btn.textContent=orig; },2000);
                    });
                });
            });

            seoReset.addEventListener('click', function(){
                seoUrl.value=''; seoResult.style.display='none';
                seoTitle.textContent=''; seoDesc.textContent='';
                hideError(seoError);
            });

            /* ── Utils ── */
            function showError(el,msg){ el.textContent=msg; el.style.display='block'; }
            function hideError(el){ el.style.display='none'; }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /* ── AJAX: Alt-Text ──────────────────────────────────── */
    public static function ajax_frontend_generate() {
        if ( ! check_ajax_referer( 'aag_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Sicherheitsfehler.' ) );
        }
        if ( ! self::check_rate_limit( 'alt', 10, 10 * MINUTE_IN_SECONDS ) ) {
            wp_send_json_error( array( 'message' => 'Zu viele Anfragen. Bitte versuche es spaeter erneut.' ), 429 );
        }
        $opts      = get_option( AAG_OPTION, array() );
        $prompt    = $opts['prompt'] ?? AAG_Alt_Generator::default_prompt();
        $prompt    = AAG_Alt_Generator::inject_language( $prompt, $opts['language'] ?? 'auto' );
        $image     = self::validate_frontend_image(
            $_POST['image_data'] ?? '',
            $_POST['mime_type'] ?? 'image/jpeg'
        );

        if ( is_wp_error( $image ) ) {
            wp_send_json_error( array( 'message' => $image->get_error_message() ) );
        }
        try {
            $alt_text = AAG_API_Handler::generate_alt_from_base64( $image['base64'], $image['mime'], $prompt );
            $alt_text = sanitize_text_field( trim( $alt_text ) );
            AAG_Stats::record( $opts['provider'] ?? 'gemini' );
            wp_send_json_success( array( 'alt' => $alt_text ) );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }

    /* ── AJAX: SEO Titel + Meta Description ─────────────── */
    public static function ajax_frontend_seo() {
        if ( ! check_ajax_referer( 'aag_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Sicherheitsfehler.' ) );
        }
        if ( ! self::check_rate_limit( 'seo', 20, 10 * MINUTE_IN_SECONDS ) ) {
            wp_send_json_error( array( 'message' => 'Zu viele Anfragen. Bitte versuche es spaeter erneut.' ), 429 );
        }

        $url = self::validate_public_url( $_POST['url'] ?? '' );
        if ( is_wp_error( $url ) ) {
            wp_send_json_error( array( 'message' => $url->get_error_message() ) );
        }

        // Seiten-Inhalt abrufen
        $response = self::fetch_public_url( $url, array(
            'timeout'             => 20,
            'limit_response_size' => 1024 * 1024,
            'user-agent'          => 'Mozilla/5.0 (compatible; MRS-SEO-Bot/1.0)',
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Seite konnte nicht geladen werden: ' . $response->get_error_message() ) );
        }

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) {
            wp_send_json_error( array( 'message' => 'Seite lieferte keinen Inhalt.' ) );
        }

        // Relevanten Text-Inhalt extrahieren
        // Title-Tag
        preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $title_match );
        $existing_title = isset($title_match[1]) ? trim(strip_tags($title_match[1])) : '';

        // Meta description
        preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\'][^>]*>/is', $html, $desc_match );
        $existing_desc = isset($desc_match[1]) ? trim($desc_match[1]) : '';

        // H1
        preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $html, $h1_match );
        $h1 = isset($h1_match[1]) ? trim(strip_tags($h1_match[1])) : '';

        // Sichtbaren Text-Inhalt extrahieren (max 2000 Zeichen)
        $text_content = preg_replace( '/<(script|style|nav|header|footer|aside)[^>]*>.*?<\/\1>/is', '', $html );
        $text_content = strip_tags( $text_content );
        $text_content = preg_replace( '/\s+/', ' ', $text_content );
        $text_content = trim( self::text_substr( $text_content, 0, 2000 ) );

        // Sprache bestimmen
        $opts     = get_option( AAG_OPTION, array() );
        $language = $opts['language'] ?? 'auto';
        $lang_names = array(
            'de'=>'German','en'=>'English','fr'=>'French','es'=>'Spanish',
            'it'=>'Italian','nl'=>'Dutch','pt'=>'Portuguese','pl'=>'Polish',
            'tr'=>'Turkish','ar'=>'Arabic','zh'=>'Chinese','ja'=>'Japanese',
        );
        if ( $language === 'auto' ) {
            $lang_instruction = 'Use the same language as the page content.';
        } else {
            $lang_instruction = 'Write in ' . ( $lang_names[$language] ?? 'English' ) . '.';
        }

        $prompt = 'You are an SEO expert. Based on this webpage content, generate:
1. An SEO-optimized page title (max 60 characters, compelling, includes main keyword)
2. A complete meta description (120-160 characters, one natural sentence, no cut-off ending)

' . $lang_instruction . '

Page URL: ' . $url . '
Existing title: ' . $existing_title . '
H1: ' . $h1 . '
Page content excerpt: ' . $text_content . '

Return ONLY valid JSON in this exact format, nothing else:
{"title":"your title here","description":"your description here"}';

        try {
            $raw = AAG_API_Handler::generate_text( $prompt, 500, 0.2 );
            $decoded = self::parse_seo_json( $raw );

            if ( ! $decoded ) {
                $retry_prompt = $prompt . "\n\nReturn minified JSON only. No markdown. No explanation. No code fence. Use exactly these keys: title, description.";
                $raw = AAG_API_Handler::generate_text( $retry_prompt, 500, 0.1 );
                $decoded = self::parse_seo_json( $raw );
            }
        } catch ( Exception $e ) {
            $decoded = null;
        }

        if ( ! $decoded ) {
            $decoded = self::fallback_seo_result( $existing_title, $h1, $text_content );
        }

        $title = sanitize_text_field( trim( $decoded['title'] ) );
        $desc  = self::trim_seo_description( sanitize_textarea_field( trim( $decoded['description'] ) ) );

        AAG_Stats::record( $opts['provider'] ?? 'gemini' );
        wp_send_json_success( array( 'title' => $title, 'description' => $desc ) );
    }

    private static function parse_seo_json( string $raw ): ?array {
        $raw = trim( html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' ) );
        $raw = preg_replace( '/^```(?:json)?\s*/i', '', $raw );
        $raw = preg_replace( '/\s*```$/', '', $raw );

        $start = strpos( $raw, '{' );
        $end   = strrpos( $raw, '}' );
        if ( $start !== false && $end !== false && $end > $start ) {
            $raw = substr( $raw, $start, $end - $start + 1 );
        }

        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) && isset( $decoded['title'], $decoded['description'] ) ) {
            return $decoded;
        }

        $raw = preg_replace( '/,\s*([}\]])/', '$1', $raw );
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) && isset( $decoded['title'], $decoded['description'] ) ) {
            return $decoded;
        }

        return null;
    }

    private static function fallback_seo_result( string $existing_title, string $h1, string $text_content ): array {
        $title = trim( $existing_title ?: $h1 );
        if ( empty( $title ) ) {
            $title = wp_trim_words( $text_content, 8, '' );
        }
        $title = self::trim_seo_title( $title );

        $description = self::trim_seo_description( $text_content );
        if ( self::text_len( $description ) < 80 && $title ) {
            $description = self::trim_seo_description( $title . '. ' . $text_content );
        }

        return array(
            'title'       => $title ?: 'SEO Titel',
            'description' => $description ?: 'Entdecke diese Seite und erfahre mehr ueber Inhalte, Leistungen und Vorteile.',
        );
    }

    private static function trim_seo_title( string $text, int $max = 60 ): string {
        $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
        if ( self::text_len( $text ) <= $max ) {
            return $text;
        }

        $cut = self::text_substr( $text, 0, $max );
        $space = self::text_strrpos( $cut, ' ' );
        if ( $space && $space >= 30 ) {
            $cut = self::text_substr( $cut, 0, $space );
        }
        return rtrim( $cut, " \t\n\r\0\x0B.,;:-" );
    }

    private static function check_rate_limit( string $bucket, int $limit, int $window ): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ip = is_string( $ip ) ? sanitize_text_field( wp_unslash( $ip ) ) : 'unknown';
        $key = 'aag_frontend_rl_' . md5( $bucket . '|' . $ip );

        $count = intval( get_transient( $key ) );
        if ( $count >= $limit ) {
            return false;
        }

        set_transient( $key, $count + 1, $window );
        return true;
    }

    private static function trim_seo_description( string $text, int $max = 160 ): string {
        $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
        if ( self::text_len( $text ) <= $max ) {
            return $text;
        }

        $limit = $max - 3;
        $cut = self::text_substr( $text, 0, $limit );
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

    private static function validate_frontend_image( $raw_b64, $raw_mime ) {
        $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
        $max_bytes     = 5 * 1024 * 1024;
        $mime_type     = is_string( $raw_mime ) ? sanitize_mime_type( wp_unslash( $raw_mime ) ) : 'image/jpeg';
        $image_b64     = is_string( $raw_b64 ) ? wp_unslash( $raw_b64 ) : '';

        if ( preg_match( '/^data:([^;]+);base64,(.*)$/s', $image_b64, $matches ) ) {
            $mime_type = sanitize_mime_type( $matches[1] );
            $image_b64 = $matches[2];
        }

        $image_b64 = preg_replace( '/\s+/', '', $image_b64 );
        if ( empty( $image_b64 ) ) {
            return new WP_Error( 'aag_no_image', 'Kein Bild empfangen.' );
        }

        if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
            return new WP_Error( 'aag_invalid_mime', 'Dieser Bildtyp ist nicht erlaubt.' );
        }

        if ( strlen( $image_b64 ) > ceil( $max_bytes * 4 / 3 ) + 4 ) {
            return new WP_Error( 'aag_image_too_large', 'Datei zu gross. Maximal 5 MB.' );
        }

        $binary = base64_decode( $image_b64, true );
        if ( false === $binary ) {
            return new WP_Error( 'aag_invalid_base64', 'Bilddaten sind ungueltig.' );
        }

        if ( strlen( $binary ) > $max_bytes ) {
            return new WP_Error( 'aag_image_too_large', 'Datei zu gross. Maximal 5 MB.' );
        }

        $info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $binary ) : false;
        if ( ! is_array( $info ) || empty( $info['mime'] ) || ! in_array( $info['mime'], $allowed_mimes, true ) ) {
            return new WP_Error( 'aag_invalid_image', 'Die Datei ist kein gueltiges Bild.' );
        }

        if ( $info['mime'] !== $mime_type ) {
            $mime_type = $info['mime'];
        }

        return array(
            'base64' => base64_encode( $binary ),
            'mime'   => $mime_type,
        );
    }

    private static function validate_public_url( $raw_url ) {
        $url = is_string( $raw_url ) ? esc_url_raw( wp_unslash( $raw_url ) ) : '';
        if ( empty( $url ) ) {
            return new WP_Error( 'aag_no_url', 'Keine URL angegeben.' );
        }

        $parts  = wp_parse_url( $url );
        $scheme = strtolower( $parts['scheme'] ?? '' );
        $host   = strtolower( $parts['host'] ?? '' );

        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || empty( $host ) ) {
            return new WP_Error( 'aag_invalid_url', 'Bitte eine gueltige http- oder https-URL angeben.' );
        }

        if ( in_array( $host, array( 'localhost', 'localhost.localdomain' ), true ) || substr( $host, -6 ) === '.local' ) {
            return new WP_Error( 'aag_private_url', 'Interne URLs sind nicht erlaubt.' );
        }

        $ips = self::resolve_host_ips( $host );
        if ( empty( $ips ) ) {
            return new WP_Error( 'aag_unresolved_url', 'Die URL konnte nicht aufgeloest werden.' );
        }

        foreach ( $ips as $ip ) {
            if ( ! self::is_public_ip( $ip ) ) {
                return new WP_Error( 'aag_private_url', 'Interne URLs sind nicht erlaubt.' );
            }
        }

        return $url;
    }

    private static function fetch_public_url( string $url, array $args, int $redirects = 3 ) {
        $args['redirection'] = 0;
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || $redirects <= 0 ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 300 || $code >= 400 ) {
            return $response;
        }

        $location = wp_remote_retrieve_header( $response, 'location' );
        if ( empty( $location ) ) {
            return $response;
        }

        $next_url = wp_http_validate_url( $location ) ? $location : wp_normalize_path( $location );
        if ( strpos( $next_url, 'http://' ) !== 0 && strpos( $next_url, 'https://' ) !== 0 ) {
            $next_url = WP_Http::make_absolute_url( $location, $url );
        }

        $next_url = self::validate_public_url( $next_url );
        if ( is_wp_error( $next_url ) ) {
            return $next_url;
        }

        return self::fetch_public_url( $next_url, $args, $redirects - 1 );
    }

    private static function resolve_host_ips( string $host ): array {
        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            return array( $host );
        }

        $ips = array();
        $a_records = function_exists( 'gethostbynamel' ) ? gethostbynamel( $host ) : false;
        if ( is_array( $a_records ) ) {
            $ips = array_merge( $ips, $a_records );
        }

        if ( function_exists( 'dns_get_record' ) ) {
            $aaaa_records = @dns_get_record( $host, DNS_AAAA );
            if ( is_array( $aaaa_records ) ) {
                foreach ( $aaaa_records as $record ) {
                    if ( ! empty( $record['ipv6'] ) ) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        return array_values( array_unique( $ips ) );
    }

    private static function is_public_ip( string $ip ): bool {
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            return (bool) filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            return (bool) filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        return false;
    }

}
