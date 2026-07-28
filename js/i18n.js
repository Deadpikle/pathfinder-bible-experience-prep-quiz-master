(function (window) {
    'use strict';

    const data = window.PBE_I18N || { locale: 'en', messages: {} };
    window.PBEI18n = {
        locale: data.locale || 'en',
        t: function (key, parameters) {
            let message = data.messages[key] || key;
            Object.entries(parameters || {}).forEach(function (entry) {
                message = message.split(':' + entry[0]).join(String(entry[1] ?? ''));
            });
            return message;
        }
    };
})(window);
