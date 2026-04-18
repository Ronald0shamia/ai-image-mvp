jQuery(function ($) {
    // Anbieter-Karten toggling
    $('input[name="aag_settings[provider]"]').on('change', function () {
        var val = $(this).val();
        $('.aag-provider-card').removeClass('active');
        $(this).closest('.aag-provider-card').addClass('active');
        $('.aag-provider-fields').hide();
        $('.aag-provider-fields[data-provider="' + val + '"]').show();
    });

    // API-Key anzeigen/verstecken
    $(document).on('click', '.aag-toggle-key', function () {
        var inp = $(this).prev('input');
        inp.attr('type', inp.attr('type') === 'password' ? 'text' : 'password');
    });

    // Default-Prompt wiederherstellen
    $('.aag-reset-prompt').on('click', function () {
        if (confirm('Standard-Prompt wiederherstellen?')) {
            $('.aag-prompt-editor').val($(this).data('default'));
        }
    });

    // Attachment-Seite: Button-Klick
    $(document).on('click', '.aag-generate-btn', function () {
        var btn  = $(this);
        var id   = btn.data('id');
        var url  = btn.data('url');
        var nonce = btn.data('nonce');
        var status = $('#aag-status-' + id);

        btn.prop('disabled', true).text('⏳ Wird generiert…');
        status.text('').css('color', '');

        $.post(ajaxurl, {
            action:        'aag_generate_alt',
            nonce:         nonce,
            attachment_id: id,
            image_url:     url,
        }, function (res) {
            btn.prop('disabled', false).html('<span class="aag-btn-icon">✨</span> Alt-Text generieren');
            if (res.success) {
                var altField = $('input[name="attachments[' + id + '][image_alt]"]');
                altField.val(res.data.alt).trigger('change');
                status.text('✓ ' + res.data.alt).css('color', '#15803d');
            } else {
                status.text('⚠ ' + (res.data.message || 'Fehler')).css('color', '#dc2626');
            }
        }).fail(function () {
            btn.prop('disabled', false).html('<span class="aag-btn-icon">✨</span> Alt-Text generieren');
            status.text('⚠ Verbindungsfehler').css('color', '#dc2626');
        });
    });
});
