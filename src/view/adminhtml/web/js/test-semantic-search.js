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

        return $t('The semantic search test request failed.');
    }

    function showResult(result) {
        alert({
            title: $t('Semantic Search Test'),
            content: $('<div>').text(result).html()
        });
    }

    function showError(message) {
        alert({
            title: $t('Semantic Search Test Failed'),
            content: $('<div>').text(message).html()
        });
    }

    return function (config, element) {
        var testSemanticSearchButton = $(element);

        testSemanticSearchButton.on('click', function () {
            testSemanticSearchButton.prop('disabled', true);

            $.ajax({
                url: config.url,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: window.FORM_KEY
                },
                showLoader: true
            }).done(function (response) {
                if (response.success === true && typeof response.result === 'string') {
                    showResult(response.result);

                    return;
                }

                showError(response.message || $t('The semantic search test request failed.'));
            }).fail(function (response) {
                showError(getErrorMessage(response));
            }).always(function () {
                testSemanticSearchButton.prop('disabled', false);
            });
        });
    };
});
