jQuery(function ($) {
    var aag = window.aagData || {};

    // ── 1. Einzelbild-Bearbeitungsseite (post.php?post=X) ──
    $(document).on('click', '.aag-generate-btn', function () {
        var btn    = $(this);
        var id     = btn.data('id');
        var status = $('#aag-status-' + id);

        if ( ! id ) return;

        btn.prop('disabled', true).text('⏳ Wird generiert…');
        status.text('').css('color', '');

        $.post(aag.ajaxUrl, {
            action:        'aag_generate_alt',
            nonce:         aag.nonce,
            attachment_id: id,
        })
        .done(function (res) {
            btn.prop('disabled', false).html('✨ Alt-Text generieren');
            if (res.success) {
                // Alt-Feld auf der Bearbeitungsseite aktualisieren
                var altInput = $('input#attachment_alt, input[name="attachments[' + id + '][image_alt]"], #attachment-details-alt-text');
                altInput.val(res.data.alt).trigger('change');
                status.html('<span style="color:#15803d">✓ ' + res.data.alt + '</span>');
            } else {
                status.html('<span style="color:#dc2626">⚠ ' + ( res.data.message || 'Fehler' ) + '</span>');
            }
        })
        .fail(function () {
            btn.prop('disabled', false).html('✨ Alt-Text generieren');
            status.html('<span style="color:#dc2626">⚠ Verbindungsfehler</span>');
        });
    });

    // ── 2. Medienbibliothek Grid-Ansicht (upload.php) ──
    // WordPress öffnet ein Modal wenn man auf ein Bild klickt.
    // Wir haken uns in das wp.media Frame-Event ein.
    if ( typeof wp !== 'undefined' && wp.media && wp.media.frames && wp.media.frames.browse ) {
        injectIntoFrame( wp.media.frames.browse );
    }

    // Auf das Frame-Erstell-Event warten
    $( document ).on( 'click', '.attachment', function () {
        setTimeout( tryInjectMediaLibraryButton, 400 );
    });

    function tryInjectMediaLibraryButton() {
        var sidebar   = $( '.attachment-details, .media-sidebar .attachment-info' );
        var altInput  = sidebar.find( '.setting[data-setting="alt"] input, input.attachment-alt' );
        var container = sidebar.find( '.setting[data-setting="alt"], .alt-text-settings' );

        if ( ! altInput.length || sidebar.find('.aag-library-btn').length ) return;

        var attachmentId = getSelectedAttachmentId();
        if ( ! attachmentId ) return;

        var btn    = $('<button>', {
            type:  'button',
            class: 'button aag-library-btn',
            html:  '✨ Alt-Text generieren',
            css:   { marginTop: '6px', width: '100%', display: 'block' }
        });
        var status = $('<p>', {
            class: 'aag-library-status',
            css:   { fontSize: '12px', margin: '4px 0 0', minHeight: '16px' }
        });

        if ( container.length ) {
            container.after( status ).after( btn );
        } else {
            altInput.after( status ).after( btn );
        }

        btn.on('click', function () {
            var currentId = getSelectedAttachmentId();
            if ( ! currentId ) return;

            btn.prop('disabled', true).text('⏳ Wird generiert…');
            status.text('').css('color', '');

            $.post(aag.ajaxUrl, {
                action:        'aag_generate_alt',
                nonce:         aag.nonce,
                attachment_id: currentId,
            })
            .done(function (res) {
                btn.prop('disabled', false).html('✨ Alt-Text generieren');
                if (res.success) {
                    altInput.val(res.data.alt).trigger('change').trigger('input');
                    // Auch das WP-Backbone-Model aktualisieren
                    if (wp.media && wp.media.frame) {
                        var sel = wp.media.frame.state().get('selection');
                        if (sel && sel.length) {
                            sel.first().set('alt', res.data.alt);
                        }
                    }
                    status.html('<span style="color:#15803d">✓ ' + res.data.alt + '</span>');
                } else {
                    status.html('<span style="color:#dc2626">⚠ ' + (res.data.message || 'Fehler') + '</span>');
                }
            })
            .fail(function () {
                btn.prop('disabled', false).html('✨ Alt-Text generieren');
                status.html('<span style="color:#dc2626">⚠ Verbindungsfehler</span>');
            });
        });
    }

    function getSelectedAttachmentId() {
        // Methode 1: URL-Parameter (post.php?post=X)
        var urlMatch = window.location.search.match(/[?&]post=(\d+)/);
        if (urlMatch) return parseInt(urlMatch[1]);

        // Methode 2: wp.media Selection
        if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
            try {
                var sel = wp.media.frame.state().get('selection');
                if (sel && sel.length) return sel.first().get('id');
            } catch(e) {}
        }

        // Methode 3: Ausgewähltes Element im Grid
        var selected = $('.attachment.selected');
        if (selected.length) {
            return parseInt(selected.first().data('id') || selected.first().attr('data-id'));
        }

        return null;
    }

    function injectIntoFrame(frame) {
        if (!frame) return;
        frame.on('selection:toggle selection:reset open', function(){
            setTimeout(tryInjectMediaLibraryButton, 300);
        });
    }
});
