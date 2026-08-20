/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */

define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, alert, $t) {
    'use strict';

    function getErrorMessage(response) {
        if (response.responseJSON && typeof response.responseJSON.message === 'string') {
            return response.responseJSON.message;
        }

        return $t('The test request failed.');
    }

    function showMessage(title, message) {
        alert({
            title: title,
            content: $('<div>').text(message).html()
        });
    }

    return function (config, element) {
        var testEmbedderConnectionButton = $(element);

        testEmbedderConnectionButton.on('click', function () {
            testEmbedderConnectionButton.prop('disabled', true);

            $.ajax({
                url: config.url,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: window.FORM_KEY
                },
                showLoader: true
            }).done(function (response) {
                if (response.success === true) {
                    showMessage($t('Connection Test'), response.message);

                    return;
                }

                showMessage($t('Connection Test Failed'), response.message || $t('The test request failed.'));
            }).fail(function (response) {
                showMessage($t('Connection Test Failed'), getErrorMessage(response));
            }).always(function () {
                testEmbedderConnectionButton.prop('disabled', false);
            });
        });
    };
});
