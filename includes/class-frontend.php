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

        wp_enqueue_style(
            'aag-frontend-shortcode',
            AAG_URL . 'assets/frontend-shortcode.css',
            [],
            AAG_VERSION
        );
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
        $nonce        = wp_create_nonce( 'aag_frontend_nonce' );

        ob_start();
        ?>
        <div class="aag-sc-wrapper" id="aag-sc-<?php echo uniqid(); ?>">

            <h3 class="aag-sc-title"><?php echo esc_html( $atts['title'] ); ?></h3>

            <!-- ── Upload-Bereich ── -->
            <div class="aag-sc-upload" id="aag-upload-area">
                <div class="aag-sc-upload-icon">🖼️</div>
                <p class="aag-sc-upload-label">Bild hier hinziehen oder klicken zum Auswählen</p>
                <p class="aag-sc-upload-hint">JPG, PNG, WebP — max. 5 MB</p>
                <input type="file" id="aag-file-input" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
            </div>

            <!-- ── Vorschau nach Auswahl ── -->
            <div class="aag-sc-preview" id="aag-preview" style="display:none">
                <img id="aag-preview-img" src="" alt="Vorschau">
                <button type="button" class="aag-sc-btn-remove" id="aag-btn-remove">✕ Anderes Bild wählen</button>
            </div>

            <!-- ── Analyse-Button ── -->
            <div class="aag-sc-action">
                <button type="button" class="aag-sc-btn-analyze" id="aag-btn-analyze" disabled>
                    ✨ <?php echo esc_html( $atts['button_text'] ); ?>
                </button>
            </div>

            <!-- ── Werbeanzeige (während Analyse) ── -->
            <div class="aag-sc-ad" id="aag-ad-container" style="display:none">
                <span class="aag-sc-ad-label">Anzeige</span>
                <div class="aag-sc-ad-content">
                    <?php if ( $ad_type === 'image' && $ad_image_url ) : ?>
                        <?php if ( $ad_link ) : ?><a href="<?php echo esc_url( $ad_link ); ?>" target="_blank" rel="noopener sponsored"><?php endif; ?>
                        <img src="<?php echo esc_url( $ad_image_url ); ?>" alt="Werbung" class="aag-sc-ad-img">
                        <?php if ( $ad_link ) : ?></a><?php endif; ?>
                    <?php elseif ( $ad_type === 'html' && $ad_html ) : ?>
                        <?php echo $ad_html; ?>
                    <?php else : ?>
                        <div class="aag-sc-ad-placeholder">
                            <span>Hier könnte Ihre Werbung stehen</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="aag-sc-loader">
                    <div class="aag-sc-spinner"></div>
                    <span>KI analysiert das Bild…</span>
                </div>
            </div>

            <!-- ── Ergebnis ── -->
            <div class="aag-sc-result" id="aag-result" style="display:none">
                <div class="aag-sc-result-header">
                    <span class="aag-sc-result-icon">✅</span>
                    <strong>Generierter Alt-Text</strong>
                </div>
                <div class="aag-sc-result-text" id="aag-result-text"></div>
                <div class="aag-sc-result-actions">
                    <button type="button" class="aag-sc-btn-copy" id="aag-btn-copy">📋 Kopieren</button>
                    <button type="button" class="aag-sc-btn-reset" id="aag-btn-reset">🔄 Neues Bild</button>
                </div>
                <p class="aag-sc-result-hint">
                    Füge diesen Text als <code>alt=""</code> Attribut in dein HTML-Bild-Tag ein.
                </p>
            </div>

            <!-- ── Fehler ── -->
            <div class="aag-sc-error" id="aag-error" style="display:none"></div>

        </div>

        <script>
        (function () {
            var uploadArea  = document.getElementById('aag-upload-area');
            var fileInput   = document.getElementById('aag-file-input');
            var preview     = document.getElementById('aag-preview');
            var previewImg  = document.getElementById('aag-preview-img');
            var btnRemove   = document.getElementById('aag-btn-remove');
            var btnAnalyze  = document.getElementById('aag-btn-analyze');
            var adContainer = document.getElementById('aag-ad-container');
            var resultBox   = document.getElementById('aag-result');
            var resultText  = document.getElementById('aag-result-text');
            var btnCopy     = document.getElementById('aag-btn-copy');
            var btnReset    = document.getElementById('aag-btn-reset');
            var errorBox    = document.getElementById('aag-error');
            var selectedFile = null;

            uploadArea.addEventListener('click', function (e) {
                if (btnRemove.contains(e.target)) return;
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
                    preview.style.display = 'block';
                    btnAnalyze.disabled = false;
                    hideError();
                };
                reader.readAsDataURL(file);
            }

            btnRemove.addEventListener('click', resetAll);
            btnReset.addEventListener('click',  resetAll);

            function resetAll() {
                selectedFile = null;
                fileInput.value = '';
                previewImg.src = '';
                uploadArea.style.display = 'block';
                preview.style.display    = 'none';
                adContainer.style.display = 'none';
                resultBox.style.display   = 'none';
                resultText.textContent    = '';
                btnAnalyze.disabled = true;
                hideError();
            }

            btnAnalyze.addEventListener('click', function () {
                if (!selectedFile) return;
                btnAnalyze.disabled = true;
                adContainer.style.display = 'block';
                resultBox.style.display   = 'none';
                hideError();

                var reader = new FileReader();
                reader.onload = function (e) {
                    var base64   = e.target.result.split(',')[1];
                    var mimeType = selectedFile.type;

                    var formData = new FormData();
                    formData.append('action',     'aag_frontend_generate');
                    formData.append('nonce',      '<?php echo esc_js( $nonce ); ?>');
                    formData.append('image_data', base64);
                    formData.append('mime_type',  mimeType);

                    fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
                        method: 'POST',
                        body:   formData,
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        adContainer.style.display = 'none';
                        if (data.success) {
                            resultText.textContent  = data.data.alt;
                            resultBox.style.display = 'block';
                        } else {
                            showError(data.data.message || 'Fehler bei der Analyse.');
                            btnAnalyze.disabled = false;
                        }
                    })
                    .catch(function () {
                        adContainer.style.display = 'none';
                        showError('Verbindungsfehler. Bitte versuche es erneut.');
                        btnAnalyze.disabled = false;
                    });
                };
                reader.readAsDataURL(selectedFile);
            });

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

    // ── AJAX: Bild als base64 empfangen → Alt-Text generieren ──
    public static function ajax_frontend_generate() {
        if ( ! check_ajax_referer( 'aag_frontend_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Sicherheitsfehler.' ] );
        }

        $opts   = get_option( AAG_OPTION, [] );
        $prompt = $opts['prompt'] ?? AAG_Alt_Generator::default_prompt();

        $image_b64 = sanitize_text_field( $_POST['image_data'] ?? '' );
        $mime_type = sanitize_mime_type( $_POST['mime_type'] ?? 'image/jpeg' );

        if ( empty( $image_b64 ) ) {
            wp_send_json_error( [ 'message' => 'Kein Bild empfangen.' ] );
        }

        // Temporäre URL simulieren – wir übergeben base64 direkt an den Handler
        try {
            $alt_text = AAG_API_Handler::generate_alt_from_base64( $image_b64, $mime_type, $prompt );
            $alt_text = sanitize_text_field( trim( $alt_text ) );
            wp_send_json_success( [ 'alt' => $alt_text ] );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }
}
