<?php
/**
 * Global CSRF bootstrap.
 *
 * Renders the CSRF meta tag and teaches both jQuery and native fetch() to attach the
 * token to every same-origin mutating request. Existing call sites (including
 * DataTables server-side processing and hand-written fetch() calls) then work without
 * being rewritten one by one.
 *
 * Include once per page, after jQuery is loaded.
 */
?>
<?= csrf_meta() ?>
<script>
(function () {
    var meta = document.querySelector('meta[name="<?= esc(csrf_header(), 'attr') ?>"]');
    if (!meta) {
        return;
    }

    var headerName = meta.getAttribute('name');
    var token      = meta.getAttribute('content');

    window.APP_CSRF = { header: headerName, token: token };

    // jQuery-backed calls: $.ajax, $.post, $.getJSON, DataTables server-side processing...
    function applyToJQuery() {
        if (!window.jQuery) {
            return false;
        }

        var defaults = jQuery.ajaxSettings.headers || {};
        defaults[headerName] = token;
        jQuery.ajaxSetup({ headers: defaults });

        return true;
    }

    if (!applyToJQuery()) {
        // jQuery may be loaded after this partial on some pages.
        document.addEventListener('DOMContentLoaded', applyToJQuery);
    }

    // Native fetch() calls, which jQuery does not cover.
    if (window.fetch) {
        var nativeFetch = window.fetch.bind(window);

        window.fetch = function (input, init) {
            init = init || {};

            var url    = (typeof input === 'string') ? input : (input && input.url);
            var method = ((init.method || (input && input.method) || 'GET') + '').toUpperCase();
            var mutating = method !== 'GET' && method !== 'HEAD';

            if (url && mutating) {
                var sameOrigin = false;
                try {
                    sameOrigin = new URL(url, window.location.origin).origin === window.location.origin;
                } catch (e) {
                    sameOrigin = false;
                }

                if (sameOrigin) {
                    var headers = new Headers(init.headers || (input && input.headers) || undefined);
                    if (!headers.has(headerName)) {
                        headers.set(headerName, token);
                    }
                    // jQuery sets this automatically; mirror it so the server treats
                    // fetch() failures the same way (403 instead of a redirect).
                    if (!headers.has('X-Requested-With')) {
                        headers.set('X-Requested-With', 'XMLHttpRequest');
                    }

                    init = Object.assign({}, init, { headers: headers });
                }
            }

            return nativeFetch(input, init);
        };
    }
})();
</script>
