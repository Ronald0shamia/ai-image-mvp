(function () {
    var el        = wp.element.createElement;
    var addFilter = wp.hooks.addFilter;
    var Fragment  = wp.element.Fragment;
    var useState  = wp.element.useState;
    var aag       = window.aagData || {};

    addFilter(
        'editor.BlockEdit',
        'aag/add-alt-button',
        function (BlockEdit) {
            return function (props) {
                if (props.name !== 'core/image') {
                    return el(BlockEdit, props);
                }

                var _useState = useState(false),
                    loading   = _useState[0],
                    setLoading = _useState[1];

                var _useStateMsg = useState(''),
                    message      = _useStateMsg[0],
                    setMessage   = _useStateMsg[1];

                function handleGenerate() {
                    var id  = props.attributes.id;
                    var url = props.attributes.url;
                    if (!id || !url) {
                        setMessage('⚠ Kein Bild ausgewählt.');
                        return;
                    }

                    setLoading(true);
                    setMessage('');

                    jQuery.post(aag.ajaxUrl, {
                        action:        'aag_generate_alt',
                        nonce:         aag.nonce,
                        attachment_id: id,
                        image_url:     url,
                    }, function (res) {
                        setLoading(false);
                        if (res.success) {
                            props.setAttributes({ alt: res.data.alt });
                            setMessage('✓ ' + res.data.alt);
                        } else {
                            setMessage('⚠ ' + (res.data.message || 'Fehler'));
                        }
                    }).fail(function () {
                        setLoading(false);
                        setMessage('⚠ Verbindungsfehler');
                    });
                }

                return el(
                    Fragment, null,
                    el(BlockEdit, props),
                    props.isSelected && el(
                        'div',
                        { style: { padding: '8px 0', display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' } },
                        el(
                            'button',
                            {
                                type:      'button',
                                className: 'components-button is-secondary is-small',
                                onClick:   handleGenerate,
                                disabled:  loading,
                                style:     { gap: '6px', display: 'flex', alignItems: 'center' }
                            },
                            loading ? '⏳' : '✨',
                            loading
                                ? (aag.labels.loading || 'Wird generiert…')
                                : (aag.labels.generate || 'Alt-Text generieren')
                        ),
                        message && el(
                            'span',
                            {
                                style: {
                                    fontSize: '12px',
                                    color: message.startsWith('✓') ? '#15803d' : '#dc2626'
                                }
                            },
                            message
                        )
                    )
                );
            };
        }
    );
})();
