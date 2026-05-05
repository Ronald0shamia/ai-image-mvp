jQuery(function ($) {
    var aag = window.aagData || {};

    function messageFromResponse(res, fallback) {
        return res && res.data && res.data.message ? res.data.message : fallback;
    }

    function setStatus(status, message, ok) {
        status.empty().append(
            $('<span>').css('color', ok ? '#16a34a' : '#dc2626').text(message)
        );
    }

    $(document).on('click', '.aag-generate-btn', function () {
        var btn = $(this), id = btn.data('id'), status = $('#aag-status-' + id);
        if (!id) return;
        btn.prop('disabled', true).text('Wird generiert...');
        status.text('').css('color', '');

        $.post(aag.ajaxUrl, { action: 'aag_generate_alt', nonce: aag.nonce, attachment_id: id })
        .done(function (res) {
            btn.prop('disabled', false).text('Alt-Text generieren');
            if (res.success) {
                $('input#attachment_alt, input[name="attachments[' + id + '][image_alt]"], #attachment-details-alt-text').val(res.data.alt).trigger('change');
                setStatus(status, res.data.alt, true);
            } else {
                setStatus(status, messageFromResponse(res, 'Alt-Text konnte nicht generiert werden.'), false);
            }
        })
        .fail(function () {
            btn.prop('disabled', false).text('Alt-Text generieren');
            setStatus(status, 'Verbindungsfehler. Bitte pruefe deine Verbindung und versuche es erneut.', false);
        });
    });

    $(document).on('click', '.aag-refresh-usage-btn', function () {
        var btn = $(this), id = btn.data('id');
        var status = btn.siblings('.aag-library-status, .aag-status').first();
        btn.prop('disabled', true).text('Wird gescannt...');
        $.post(aag.ajaxUrl, { action: 'aag_refresh_usage', nonce: aag.nonce, id: id }, function (res) {
            btn.prop('disabled', false).text('Neu scannen');
            if (res.success) location.reload();
            else if (status.length) setStatus(status, messageFromResponse(res, 'Bild-Verwendung konnte nicht aktualisiert werden.'), false);
        }).fail(function () {
            btn.prop('disabled', false).text('Neu scannen');
            if (status.length) setStatus(status, 'Verbindungsfehler. Bitte versuche es erneut.', false);
        });
    });

    $(document).on('click', '.aag-usage-toggle', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#aag-ul-' + id).toggle();
        $(this).text($('#aag-ul-' + id).is(':visible') ? 'Schliessen' : 'Details');
    });

    function tryInjectMediaLibraryButton() {
        var sidebar  = $('.attachment-details, .media-sidebar .attachment-info');
        var altInput = sidebar.find('.setting[data-setting="alt"] input, input.attachment-alt');
        if (!altInput.length || sidebar.find('.aag-library-btn').length) return;

        var attachmentId = getSelectedAttachmentId();
        if (!attachmentId) return;

        var btn = $('<button>', { type: 'button', class: 'button aag-library-btn', html: 'Alt-Text generieren', css: { marginTop: '6px', width: '100%', display: 'block' } });
        var status = $('<p>', { class: 'aag-library-status', css: { fontSize: '12px', margin: '4px 0 0', minHeight: '16px' } });

        altInput.after(status).after(btn);

        btn.on('click', function () {
            var currentId = getSelectedAttachmentId();
            if (!currentId) return;
            btn.prop('disabled', true).text('Wird generiert...');
            status.text('').css('color', '');

            $.post(aag.ajaxUrl, { action: 'aag_generate_alt', nonce: aag.nonce, attachment_id: currentId })
            .done(function (res) {
                btn.prop('disabled', false).text('Alt-Text generieren');
                if (res.success) {
                    altInput.val(res.data.alt).trigger('change').trigger('input');
                    if (wp.media && wp.media.frame) {
                        try { wp.media.frame.state().get('selection').first().set('alt', res.data.alt); } catch(e){}
                    }
                    setStatus(status, res.data.alt, true);
                } else {
                    setStatus(status, messageFromResponse(res, 'Alt-Text konnte nicht generiert werden.'), false);
                }
            })
            .fail(function () {
                btn.prop('disabled', false).text('Alt-Text generieren');
                setStatus(status, 'Verbindungsfehler. Bitte pruefe deine Verbindung und versuche es erneut.', false);
            });
        });
    }

    function getSelectedAttachmentId() {
        var urlMatch = window.location.search.match(/[?&]post=(\d+)/);
        if (urlMatch) return parseInt(urlMatch[1]);
        if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
            try { var sel = wp.media.frame.state().get('selection'); if (sel && sel.length) return sel.first().get('id'); } catch(e){}
        }
        var selected = $('.attachment.selected');
        if (selected.length) return parseInt(selected.first().data('id') || selected.first().attr('data-id'));
        return null;
    }

    $(document).on('click', '.attachment', function () { setTimeout(tryInjectMediaLibraryButton, 400); });
});
