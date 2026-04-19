<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_Frontend {

    public static function init() {
        add_shortcode( 'aag_preview', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_aag_frontend_generate',        [ __CLASS__, 'ajax_frontend_generate' ] );
        add_action( 'wp_ajax_nopriv_aag_frontend_generate', [ __CLASS__, 'ajax_frontend_generate' ] );
    }

    public static function enqueue_assets() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'aag_preview' ) ) return;
        wp_enqueue_style( 'aag-frontend-shortcode', AAG_URL . 'assets/frontend-shortcode.css', [], AAG_VERSION );
    }

    public static function render_shortcode( $atts ): string {
        $atts = shortcode_atts( [
            'title'       => 'Alt-Text Generator',
            'button_text' => 'Alt-Text generieren',
        ], $atts, 'aag_preview' );

        $opts         = get_option( AAG_OPTION, [] );
        $ad_type      = $opts['ad_type']      ?? 'image';
        $ad_image_url = $opts['ad_image_url'] ?? '';
        $ad_link      = $opts['ad_link']      ?? '';
        $ad_html      = $opts['ad_html']      ?? '';
        $popup_delay  = intval( $opts['ad_popup_delay'] ?? 0 ); // Sekunden bis Popup sich schließt (0 = manuell)
        $nonce        = wp_create_nonce( 'aag_frontend_nonce' );
        $uid          = 'aag' . uniqid();

        // Ad-Inhalt aufbauen
        $ad_content = '';
        if ( $ad_type === 'image' && $ad_image_url ) {
            $ad_content = $ad_link
                ? '<a href="' . esc_url( $ad_link ) . '" target="_blank" rel="noopener sponsored"><img src="' . esc_url( $ad_image_url ) . '" alt="Werbung" class="aag-popup-ad-img"></a>'
                : '<img src="' . esc_url( $ad_image_url ) . '" alt="Werbung" class="aag-popup-ad-img">';
        } elseif ( $ad_type === 'html' && $ad_html ) {
            $ad_content = $ad_html;
        } else {
            $ad_content = '<div class="aag-popup-ad-placeholder"><span>Hier könnte Ihre Werbung stehen</span></div>';
        }

        ob_start();
        ?>
        <div class="aag-sc-wrapper" id="<?php echo esc_attr( $uid ); ?>">

            <h3 class="aag-sc-title"><?php echo esc_html( $atts['title'] ); ?></h3>

            <!-- Upload -->
            <div class="aag-sc-upload" id="<?php echo $uid; ?>-upload">
                <div class="aag-sc-upload-icon">🖼️</div>
                <p class="aag-sc-upload-label">Bild hier hinziehen oder klicken zum Auswählen</p>
                <p class="aag-sc-upload-hint">JPG, PNG, WebP — max. 5 MB</p>
                <input type="file" id="<?php echo $uid; ?>-file" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
            </div>

            <!-- Vorschau -->
            <div class="aag-sc-preview" id="<?php echo $uid; ?>-preview" style="display:none">
                <img id="<?php echo $uid; ?>-img" src="" alt="Vorschau">
                <button type="button" class="aag-sc-btn-remove" id="<?php echo $uid; ?>-remove">✕ Anderes Bild wählen</button>
            </div>

            <!-- Button -->
            <div class="aag-sc-action">
                <button type="button" class="aag-sc-btn-analyze" id="<?php echo $uid; ?>-analyze" disabled>
                    ✨ <?php echo esc_html( $atts['button_text'] ); ?>
                </button>
            </div>

            <!-- Ergebnis -->
            <div class="aag-sc-result" id="<?php echo $uid; ?>-result" style="display:none">
                <div class="aag-sc-result-header">
                    <span class="aag-sc-result-icon">✅</span>
                    <strong>Generierter Alt-Text</strong>
                </div>
                <div class="aag-sc-result-text" id="<?php echo $uid; ?>-result-text"></div>
                <div class="aag-sc-result-actions">
                    <button type="button" class="aag-sc-btn-copy" id="<?php echo $uid; ?>-copy">📋 Kopieren</button>
                    <button type="button" class="aag-sc-btn-reset" id="<?php echo $uid; ?>-reset">🔄 Neues Bild</button>
                </div>
                <p class="aag-sc-result-hint">Füge diesen Text als <code>alt=""</code> Attribut in dein HTML-Bild-Tag ein.</p>
            </div>

            <!-- Fehler -->
            <div class="aag-sc-error" id="<?php echo $uid; ?>-error" style="display:none"></div>

        </div>

        <!-- ── AD POPUP (außerhalb des Wrappers, global im Body) ── -->
        <div class="aag-popup-overlay" id="<?php echo $uid; ?>-popup" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="aag-popup-box">
                <div class="aag-popup-header">
                    <span class="aag-popup-label">Anzeige</span>
                    <div class="aag-popup-countdown" id="<?php echo $uid; ?>-countdown" style="display:none"></div>
                    <button type="button" class="aag-popup-close" id="<?php echo $uid; ?>-close" aria-label="Schließen">✕</button>
                </div>
                <div class="aag-popup-ad-content">
                    <?php echo $ad_content; ?>
                </div>
                <div class="aag-popup-loader">
                    <div class="aag-popup-spinner"></div>
                    <span>KI analysiert das Bild…</span>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var uid         = '<?php echo esc_js( $uid ); ?>';
            var ajaxUrl     = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
            var nonce       = '<?php echo esc_js( $nonce ); ?>';
            var popupDelay  = <?php echo intval( $popup_delay ); ?>;

            var wrap        = document.getElementById(uid);
            var uploadArea  = document.getElementById(uid + '-upload');
            var fileInput   = document.getElementById(uid + '-file');
            var preview     = document.getElementById(uid + '-preview');
            var previewImg  = document.getElementById(uid + '-img');
            var btnRemove   = document.getElementById(uid + '-remove');
            var btnAnalyze  = document.getElementById(uid + '-analyze');
            var resultBox   = document.getElementById(uid + '-result');
            var resultText  = document.getElementById(uid + '-result-text');
            var btnCopy     = document.getElementById(uid + '-copy');
            var btnReset    = document.getElementById(uid + '-reset');
            var errorBox    = document.getElementById(uid + '-error');
            var popup       = document.getElementById(uid + '-popup');
            var popupClose  = document.getElementById(uid + '-close');
            var countdown   = document.getElementById(uid + '-countdown');
            var selectedFile = null;
            var countdownTimer = null;

            // ── Upload ──
            uploadArea.addEventListener('click', function (e) {
                if (btnRemove && btnRemove.contains(e.target)) return;
                fileInput.click();
            });
            uploadArea.addEventListener('dragover',  function (e) { e.preventDefault(); uploadArea.classList.add('drag-over'); });
            uploadArea.addEventListener('dragleave', function ()  { uploadArea.classList.remove('drag-over'); });
            uploadArea.addEventListener('drop', function (e) {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
                if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
            });
            fileInput.addEventListener('change', function () {
                if (this.files[0]) handleFile(this.files[0]);
            });

            function handleFile(file) {
                if (!file.type.startsWith('image/')) { showError('Bitte nur Bilddateien (JPG, PNG, WebP) hochladen.'); return; }
                if (file.size > 5 * 1024 * 1024)    { showError('Das Bild ist zu groß. Maximal 5 MB erlaubt.');       return; }
                selectedFile = file;
                var reader = new FileReader();
                reader.onload = function (e) {
                    previewImg.src = e.target.result;
                    uploadArea.style.display = 'none';
                    preview.style.display    = 'block';
                    btnAnalyze.disabled      = false;
                    hideError();
                };
                reader.readAsDataURL(file);
            }

            // ── Reset ──
            btnRemove.addEventListener('click', resetAll);
            btnReset.addEventListener('click',  resetAll);

            function resetAll() {
                selectedFile = null;
                fileInput.value = '';
                previewImg.src  = '';
                uploadArea.style.display = 'block';
                preview.style.display   = 'none';
                resultBox.style.display = 'none';
                resultText.textContent  = '';
                btnAnalyze.disabled     = true;
                closePopup();
                hideError();
            }

            // ── Popup öffnen / schließen ──
            function openPopup() {
                popup.classList.add('active');
                popup.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';

                if (popupDelay > 0) {
                    var secs = popupDelay;
                    countdown.style.display = 'block';
                    countdown.textContent   = 'Schließt in ' + secs + 's';
                    countdownTimer = setInterval(function () {
                        secs--;
                        countdown.textContent = 'Schließt in ' + secs + 's';
                        if (secs <= 0) { clearInterval(countdownTimer); closePopup(); }
                    }, 1000);
                }
            }

            function closePopup() {
                popup.classList.remove('active');
                popup.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
                countdown.style.display = 'none';
            }

            popupClose.addEventListener('click', closePopup);
            popup.addEventListener('click', function (e) {
                if (e.target === popup) closePopup(); // Klick auf Overlay schließt
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closePopup();
            });

            // ── Analyse starten ──
            btnAnalyze.addEventListener('click', function () {
                if (!selectedFile) return;
                btnAnalyze.disabled = true;
                resultBox.style.display = 'none';
                hideError();
                openPopup(); // Popup sofort zeigen

                var reader = new FileReader();
                reader.onload = function (e) {
                    var base64   = e.target.result.split(',')[1];
                    var mimeType = selectedFile.type;
                    var formData = new FormData();
                    formData.append('action',     'aag_frontend_generate');
                    formData.append('nonce',      nonce);
                    formData.append('image_data', base64);
                    formData.append('mime_type',  mimeType);

                    fetch(ajaxUrl, { method: 'POST', body: formData })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        closePopup();
                        btnAnalyze.disabled = false;
                        if (data.success) {
                            resultText.textContent  = data.data.alt;
                            resultBox.style.display = 'block';
                            resultBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        } else {
                            showError(data.data.message || 'Fehler bei der Analyse.');
                        }
                    })
                    .catch(function () {
                        closePopup();
                        btnAnalyze.disabled = false;
                        showError('Verbindungsfehler. Bitte versuche es erneut.');
                    });
                };
                reader.readAsDataURL(selectedFile);
            });

            // ── Kopieren ──
            btnCopy.addEventListener('click', function () {
                var text = resultText.textContent;
                if (!text) return;
                navigator.clipboard.writeText(text).then(function () {
                    btnCopy.textContent = '✓ Kopiert!';
                    setTimeout(function () { btnCopy.textContent = '📋 Kopieren'; }, 2000);
                });
            });

            function showError(msg) { errorBox.textContent = msg; errorBox.style.display = 'block'; }
            function hideError()    { errorBox.style.display = 'none'; }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public static function ajax_frontend_generate() {
        if ( ! check_ajax_referer( 'aag_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Sicherheitsfehler.' ] );
        }

        $opts      = get_option( AAG_OPTION, [] );
        $prompt    = $opts['prompt'] ?? AAG_Alt_Generator::default_prompt();
        $prompt    = AAG_Alt_Generator::inject_language( $prompt, $opts['language'] ?? 'auto' );
        $image_b64 = sanitize_text_field( $_POST['image_data'] ?? '' );
        $mime_type = sanitize_mime_type( $_POST['mime_type']   ?? 'image/jpeg' );

        if ( empty( $image_b64 ) ) {
            wp_send_json_error( [ 'message' => 'Kein Bild empfangen.' ] );
        }

        try {
            $alt_text = AAG_API_Handler::generate_alt_from_base64( $image_b64, $mime_type, $prompt );
            $alt_text = sanitize_text_field( trim( $alt_text ) );
            AAG_Stats::record( $opts['provider'] ?? 'gemini' );
            wp_send_json_success( [ 'alt' => $alt_text ] );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }
}
