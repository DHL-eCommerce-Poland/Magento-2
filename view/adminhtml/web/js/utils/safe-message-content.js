define([
    'jquery'
], function ($) {
    'use strict';

    return function (messageText) {
        var content = document.createElement('div');
        var lines = String(messageText || '').split(/\r\n|\r|\n/);

        $.each(lines, function (index, line) {
            if (index > 0) {
                content.appendChild(document.createElement('br'));
            }
            content.appendChild(document.createTextNode(line));
        });

        return $(content);
    };
});
