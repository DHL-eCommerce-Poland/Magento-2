define([
    'Magento_Ui/js/form/element/abstract',
    'mage/translate'
], function (Abstract, $t) {
    'use strict';

    var MAX_FILE_SIZE = 10 * 1024 * 1024,
        PDF_SIGNATURE = [37, 80, 68, 70, 45];

    return Abstract.extend({
        defaults: {
            elementTmpl: 'Ideo_Dhl/form/element/pdf-invoice',
            fileName: ''
        },

        /**
         * Initialize the observable filename used by the upload control.
         *
         * @returns {Object}
         */
        initObservable: function () {
            this._super().observe('fileName');

            return this;
        },

        /**
         * Preserve a useful label for an invoice already stored as Base64.
         *
         * @returns {Object}
         */
        initialize: function () {
            this._super();
            this.value.subscribe(this.onInvoiceValueChange.bind(this));
            this.onInvoiceValueChange(this.value());

            return this;
        },

        /**
         * Open the hidden PDF file input.
         *
         * @returns {void}
         */
        openFileDialog: function () {
            var fileControl = document.getElementById(this.uid + '-pdf-input');

            if (fileControl) {
                fileControl.click();
            }
        },

        /**
         * Read a selected PDF into the field as Base64 without uploading a file.
         *
         * @param {Object} data
         * @param {Event} event
         * @returns {void}
         */
        onFileSelected: function (data, event) {
            var fileControl = event.target,
                file = fileControl.files && fileControl.files[0],
                signatureReader;

            if (!file) {
                return;
            }

            this.error('');

            if (!/\.pdf$/i.test(file.name)) {
                this.rejectFile(fileControl, $t('Only PDF files are allowed for VAT Invoice.'));
                return;
            }

            if (file.size > MAX_FILE_SIZE) {
                this.rejectFile(fileControl, $t('File size exceeds the 10MB limit.'));
                return;
            }

            signatureReader = new FileReader();
            signatureReader.onload = function (readEvent) {
                var bytes = new Uint8Array(readEvent.target.result),
                    signatureMatches = PDF_SIGNATURE.every(function (expectedByte, index) {
                        return bytes[index] === expectedByte;
                    });

                if (!signatureMatches) {
                    this.rejectFile(fileControl, $t('Only PDF files are allowed for VAT Invoice.'));
                    return;
                }

                this.readPdfAsBase64(file, fileControl);
            }.bind(this);
            signatureReader.onerror = function () {
                this.rejectFile(fileControl, $t('Failed to read PDF file.'));
            }.bind(this);
            signatureReader.readAsArrayBuffer(file.slice(0, PDF_SIGNATURE.length));
        },

        /**
         * Read a validated PDF and store only its Base64 contents in the form data.
         *
         * @param {File} file
         * @param {HTMLInputElement} fileControl
         * @returns {void}
         */
        readPdfAsBase64: function (file, fileControl) {
            var reader = new FileReader();

            reader.onload = function (event) {
                var dataUrl = event.target.result || '',
                    separatorIndex = dataUrl.indexOf(',');

                if (separatorIndex === -1) {
                    this.rejectFile(fileControl, $t('Failed to read PDF file.'));
                    return;
                }

                this.value(dataUrl.substring(separatorIndex + 1));
                this.fileName(file.name);
                fileControl.value = '';
            }.bind(this);
            reader.onerror = function () {
                this.rejectFile(fileControl, $t('Failed to read PDF file.'));
            }.bind(this);
            reader.readAsDataURL(file);
        },

        /**
         * Show a selection error and reset the file input so the same file can be retried.
         *
         * @param {HTMLInputElement} fileControl
         * @param {String} message
         * @returns {void}
         */
        rejectFile: function (fileControl, message) {
            this.error(message);
            fileControl.value = '';
        },

        /**
         * Clear the invoice value and its displayed filename.
         *
         * @returns {void}
         */
        removeFile: function () {
            this.value('');
            this.fileName('');
            this.error('');
        },

        /**
         * Keep the filename observable in sync with stored or removed invoice content.
         *
         * @param {String} value
         * @returns {void}
         */
        onInvoiceValueChange: function (value) {
            if (!value) {
                this.fileName('');
            } else if (!this.fileName()) {
                this.fileName($t('VAT Invoice'));
            }
        }
    });
});
