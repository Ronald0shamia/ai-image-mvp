(function () {
    var el = wp.element.createElement, addFilter = wp.hooks.addFilter, Fragment = wp.element.Fragment, useState = wp.element.useState;
    var aag = window.aagData || {};

    addFilter('editor.BlockEdit', 'aag/add-alt-button', function (BlockEdit) {
        return function (props) {
            if (props.name !== 'core/image') return el(BlockEdit, props);
            var _loading = useState(false), loading = _loading[0], setLoading = _loading[1];
            var _msg = useState(''), message = _msg[0], setMessage = _msg[1];

            function handleGenerate() {
                var id = props.attributes.id, url = props.attributes.url;
                if (!id || !url) { setMessage('Kein Bild ausgewaehlt.'); return; }
                setLoading(true); setMessage('');
                jQuery.post(aag.ajaxUrl, { action:'aag_generate_alt', nonce:aag.nonce, attachment_id:id }, function (res) {
                    setLoading(false);
                    if (res.success) { props.setAttributes({ alt: res.data.alt }); setMessage(res.data.alt); }
                    else setMessage(res.data.message || 'Fehler');
                }).fail(function () { setLoading(false); setMessage('Verbindungsfehler'); });
            }

            return el(Fragment, null, el(BlockEdit, props),
                props.isSelected && el('div', { style:{ padding:'8px 0', display:'flex', alignItems:'center', gap:'10px', flexWrap:'wrap' } },
                    el('button', { type:'button', className:'components-button is-secondary is-small', onClick:handleGenerate, disabled:loading },
                        loading ? (aag.labels&&aag.labels.loading?aag.labels.loading:'Wird generiert...') : (aag.labels&&aag.labels.generate?aag.labels.generate:'Alt-Text generieren')
                    ),
                    message && el('span', { style:{ fontSize:'12px', color: message.startsWith('Kein')||message.includes('fehler')||message.includes('Fehler') ? '#dc2626':'#16a34a' } }, message)
                )
            );
        };
    });
})();
