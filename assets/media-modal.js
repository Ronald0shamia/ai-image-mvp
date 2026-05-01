jQuery(function ($) {
    if (typeof wp === 'undefined' || !wp.media) return;
    var aag = window.aagData || {};

    function injectButton(frame) {
        frame.on('open', function () {
            setTimeout(function () {
                var sidebar = frame.$el.find('.attachment-details, .attachment-info');
                if (!sidebar.length || sidebar.find('.aag-media-btn').length) return;
                var btn = $('<button>', { type:'button', class:'button aag-media-btn', html: aag.labels && aag.labels.generate ? aag.labels.generate : 'Alt-Text generieren', css:{ marginTop:'8px', width:'100%' } });
                var status = $('<div class="aag-media-status"></div>').css({ marginTop:'6px', fontSize:'12px' });
                sidebar.append(btn).append(status);

                btn.on('click', function () {
                    var selection = frame.state().get('selection');
                    if (!selection || !selection.length) return;
                    var attachment = selection.first().toJSON();
                    btn.prop('disabled', true).text('Wird generiert...');
                    status.text('').css('color','');

                    $.post(aag.ajaxUrl, { action:'aag_generate_alt', nonce:aag.nonce, attachment_id:attachment.id }, function (res) {
                        btn.prop('disabled', false).text(aag.labels && aag.labels.generate ? aag.labels.generate : 'Alt-Text generieren');
                        if (res.success) {
                            frame.$el.find('input.attachment-alt, [data-setting="alt"] input').val(res.data.alt).trigger('change');
                            status.html('<span style="color:#16a34a">' + res.data.alt + '</span>');
                        } else {
                            status.html('<span style="color:#dc2626">' + (res.data.message || 'Fehler') + '</span>');
                        }
                    }).fail(function () {
                        btn.prop('disabled', false).text('Alt-Text generieren');
                        status.html('<span style="color:#dc2626">Verbindungsfehler</span>');
                    });
                });
            }, 300);
        });
    }

    wp.media.view.MediaFrame.Select.prototype.initialize = (function (orig) {
        return function () { orig.apply(this, arguments); injectButton(this); };
    })(wp.media.view.MediaFrame.Select.prototype.initialize);
});
