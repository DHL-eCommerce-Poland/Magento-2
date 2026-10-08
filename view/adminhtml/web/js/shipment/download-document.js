define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'Ideo_Dhl/js/utils/safe-message-content',
    'mage/translate',
    'mage/loader'
], function ($, alert, createSafeMessageContent, $t) {
    'use strict';

    /**
     * Download DHL document via AJAX, displaying errors in an alert modal
     * without reloading the page or losing entered form data.
     *
     * @param {string} url
     */
    return function (url) {
        if (!url) {
            return;
        }

        if (typeof $('body').loader === 'function') {
            $('body').loader();
        }
        $('body').trigger('processStart');

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            var contentType = response.headers.get('content-type') || '';

            if (contentType.indexOf('application/json') !== -1) {
                return response.json().then(function (data) {
                    $('body').trigger('processStop');
                    var errorMsg = (data && data.message)
                        ? data.message
                        : $t('An error occurred while downloading the document.');
                    alert({
                        title: $t('Error'),
                        content: createSafeMessageContent(errorMsg)
                    });
                });
            }

            if (!response.ok) {
                return response.text().then(function () {
                    $('body').trigger('processStop');
                    alert({
                        title: $t('Error'),
                        content: createSafeMessageContent(
                            $t('Could not download document. HTTP status: %1').replace('%1', response.status)
                        )
                    });
                });
            }

            return response.blob().then(function (blob) {
                $('body').trigger('processStop');

                var disposition = response.headers.get('content-disposition') || '';
                var filename = '';
                var filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                var matches = filenameRegex.exec(disposition);
                if (matches != null && matches[1]) {
                    filename = matches[1].replace(/['"]/g, '').trim();
                }
                if (!filename) {
                    filename = 'dhl-document.pdf';
                }

                var blobUrl = window.URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.style.display = 'none';
                link.href = blobUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                setTimeout(function () {
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(blobUrl);
                }, 200);
            });
        }).catch(function (error) {
            $('body').trigger('processStop');
            alert({
                title: $t('Error'),
                content: createSafeMessageContent(
                    error.message || $t('An error occurred while communicating with the server.')
                )
            });
        });
    };
});
