define([], function () {
    'use strict';

    try {
        window.sessionStorage.removeItem('dhl_selected_service_point');
    } catch (e) {
        console.warn('DHL Clear Service Point: Error removing point from storage on success page:', e);
    }

    return function () {};
});
