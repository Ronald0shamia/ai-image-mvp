jQuery(function ($) {
    if (typeof wp === 'undefined' || !wp.media) return;

    var aag = window.aagData || {};

    function injectButton(frame) {
        frame.on('open', function () {
            setTimeout(function () {
                var sidebar = frame.$el.find('.attachment-details, .attachment-info');
                if (!sidebar.length || sidebar.find('.aag-media-btn').length) return;

                var btn = $('<button>', {
                    type:  'button',
                    class: 'button aag-media-btn',
                    html:  '✨ ' + (aag.labels.generate || 'Alt-Text generieren'),
                    css:   { marginTop: '8px', width: '100%' }
                });

                var status = $('<div class="aag-media-status"></div>').css({
                    marginTop: '6px', fontSize: '12px'
                });

                sidebar.append(btn).append(status);

                btn.on('click', function () {
                    var selection = frame.state().get('selection');
                    if (!selection || !selection.length) return;

                    var attachment = selection.first().toJSON();
                    var id  = attachment.id;
                    var url = attachment.url;

                    btn.prop('disabled', true).text('⏳ ' + (aag.labels.loading || 'Wird generiert…'));
                    status.text('').css('color', '');

                    $.post(aag.ajaxUrl, {
                        action:        'aag_generate_alt',
                        nonce:         aag.nonce,
                        attachment_id: id,
                        image_url:     url,
                    }, function (res) {
                        btn.prop('disabled', false).html('✨ ' + (aag.labels.generate || 'Alt-Text generieren'));
                        if (res.success) {
                            var altInput = frame.$el.find('input.attachment-alt, [data-setting="alt"] input');
                            altInput.val(res.data.alt).trigger('change');
                            status.text('✓ ' + res.data.alt).css('color', '#15803d');
                        } else {
                            status.text('⚠ ' + (res.data.message || 'Fehler')).css('color', '#dc2626');
                        }
                    }).fail(function () {
                        btn.prop('disabled', false).html('✨ ' + (aag.labels.generate || 'Alt-Text generieren'));
                        status.text('⚠ Verbindungsfehler').css('color', '#dc2626');
                    });
                });
            }, 300);
        });
    }

    $(document).on('click', '.insert-media, .add_media', function () {
        if (wp.media.frames.frame) {
            injectButton(wp.media.frames.frame);
        }
    });

    wp.media.view.MediaFrame.Select.prototype.initialize = (function (orig) {
        return function () {
            orig.apply(this, arguments);
            injectButton(this);
        };
    })(wp.media.view.MediaFrame.Select.prototype.initialize);
});
