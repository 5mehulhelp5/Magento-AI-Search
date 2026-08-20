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

    function getValue(value) {
        if (typeof value === 'string' && value !== '') {
            return value;
        }

        return $t('Not available');
    }

    function getApiKeyValue(value) {
        if (value === true) {
            return $t('Yes');
        }

        if (value === false) {
            return $t('No');
        }

        return $t('Not available');
    }

    function addDetail(content, label, value) {
        $('<div>')
            .append($('<strong>').text(label + ': '))
            .append($('<span>').text(value))
            .appendTo(content);
    }

    function getContent(response) {
        var configuration = response.configuration || {},
            content = $('<div>'),
            status = response.success === true ? '✅' : '⚠️';

        $('<div>')
            .text(status + ' ' + (response.message || $t('The test request failed.')))
            .appendTo(content);
        $('<br>').appendTo(content);

        addDetail(content, $t('URL'), getValue(configuration.url));
        addDetail(content, $t('Protocol'), getValue(configuration.protocol));
        addDetail(content, $t('API Key'), getApiKeyValue(configuration.api_key_configured));
        addDetail(content, $t('Model'), getValue(configuration.model));

        if (typeof response.error_message === 'string' && response.error_message !== '') {
            addDetail(content, $t('Error'), response.error_message);
        }

        return content.html();
    }

    function showResponse(response) {
        alert({
            title: response.success === true ? $t('Connection Test') : $t('Connection Test Failed'),
            content: getContent(response)
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
                showResponse(response);
            }).fail(function (response) {
                showResponse({
                    success: false,
                    message: $t('The test request failed.'),
                    error_message: getErrorMessage(response)
                });
            }).always(function () {
                testEmbedderConnectionButton.prop('disabled', false);
            });
        });
    };
});
