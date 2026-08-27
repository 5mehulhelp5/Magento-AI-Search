/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */

define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, modal, $t) {
    'use strict';

    function getErrorMessage(response) {
        if (response.responseJSON && typeof response.responseJSON.error_message === 'string') {
            return response.responseJSON.error_message;
        }

        if (response.responseJSON && typeof response.responseJSON.message === 'string') {
            return response.responseJSON.message;
        }

        return $t('The semantic search test request failed.');
    }

    function addDetail(container, label, value) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return;
        }

        $('<span>', {'class': 'ai-search-test-detail'})
            .append($('<strong>').text(label + ': '))
            .append($('<span>').text(value))
            .appendTo(container);
    }

    function createChunk(chunk) {
        var chunkElement = $('<details>', {'class': 'ai-search-test-chunk'}),
            details = $('<div>', {'class': 'ai-search-test-details'});

        $('<summary>')
            .text($t('Chunk') + ' #' + (chunk.index + 1) + ' (ID: ' + chunk.id + ')')
            .appendTo(chunkElement);
        addDetail(details, $t('Created'), chunk.created_at);
        addDetail(details, $t('Updated'), chunk.updated_at);

        if (typeof chunk.score === 'number') {
            addDetail(details, $t('Score'), chunk.score.toFixed(4));
        }

        details.appendTo(chunkElement);
        $('<pre>', {'class': 'ai-search-test-chunk-content'})
            .text(chunk.content || '')
            .appendTo(chunkElement);

        return chunkElement;
    }

    function createDocument(document) {
        var chunks = Array.isArray(document.chunks) ? document.chunks : [],
            documentElement = $('<details>', {'class': 'ai-search-test-document'}),
            details = $('<div>', {'class': 'ai-search-test-details'}),
            chunksElement = $('<div>', {'class': 'ai-search-test-chunks'}),
            chunkIndex;

        $('<summary>')
            .text(document.source_code + ' | ' + $t('Document ID') + ': ' + document.id +
                ' | ' + $t('Chunks') + ': ' + chunks.length)
            .appendTo(documentElement);
        addDetail(details, $t('Title'), document.title);
        addDetail(details, $t('Store ID'), document.store_id);
        addDetail(details, $t('Created'), document.created_at);
        addDetail(details, $t('Updated'), document.updated_at);
        details.appendTo(documentElement);

        if (chunks.length === 0) {
            $('<div>', {'class': 'message message-notice'})
                .text($t('No related chunks were found.'))
                .appendTo(chunksElement);
        }

        for (chunkIndex = 0; chunkIndex < chunks.length; chunkIndex++) {
            createChunk(chunks[chunkIndex]).appendTo(chunksElement);
        }

        chunksElement.appendTo(documentElement);

        return documentElement;
    }

    function createProduct(product) {
        var documents = Array.isArray(product.documents) ? product.documents : [],
            productElement = $('<section>', {'class': 'ai-search-test-product'}),
            title = $('<h3>', {'class': 'ai-search-test-product-title'}),
            details = $('<div>', {'class': 'ai-search-test-details'}),
            documentsElement = $('<div>', {'class': 'ai-search-test-documents'}),
            documentIndex;

        $('<span>', {'class': 'ai-search-test-position'})
            .text(product.position + '. ')
            .appendTo(title);
        $('<a>', {
            href: product.edit_url,
            target: '_blank',
            rel: 'noopener noreferrer'
        })
            .text(product.name + ' (ID: ' + product.id + ')')
            .appendTo(title);
        title.appendTo(productElement);

        if (typeof product.score === 'number') {
            addDetail(details, $t('Highest Score'), product.score.toFixed(4));
        }

        addDetail(details, $t('SKU'), product.sku);
        addDetail(details, $t('Product Type'), product.type);
        addDetail(details, $t('Documents'), documents.length);
        details.appendTo(productElement);

        if (documents.length === 0) {
            $('<div>', {'class': 'message message-notice'})
                .text($t('No related documents were found for this product and store view.'))
                .appendTo(documentsElement);
        }

        for (documentIndex = 0; documentIndex < documents.length; documentIndex++) {
            createDocument(documents[documentIndex]).appendTo(documentsElement);
        }

        documentsElement.appendTo(productElement);

        return productElement;
    }

    function createSearchConfiguration(configuration) {
        var configurationElement = $('<div>', {'class': 'ai-search-test-configuration'}),
            details = $('<div>', {'class': 'ai-search-test-details'});

        $('<strong>', {'class': 'ai-search-test-configuration-title'})
            .text($t('Current Search Configuration'))
            .appendTo(configurationElement);
        addDetail(
            details,
            $t('Collapse Results by Product'),
            configuration.collapse_results_by_product ? $t('Yes') : $t('No')
        );
        addDetail(details, $t('Minimum Score'), configuration.minimum_score);
        addDetail(details, $t('Embedder Query Template'), configuration.embedder_query_template);
        addDetail(details, $t('Vector Engine'), configuration.vector_engine);
        addDetail(details, $t('Vector Space'), configuration.vector_space);
        details.appendTo(configurationElement);

        return configurationElement;
    }

    function showError(resultsElement, message, errorMessage) {
        resultsElement.empty();
        $('<div>', {'class': 'message message-error'})
            .text('⚠️ ' + (message || $t('The semantic search test failed.')))
            .appendTo(resultsElement);

        if (typeof errorMessage === 'string' && errorMessage !== '') {
            $('<pre>', {'class': 'ai-search-test-error'})
                .text(errorMessage)
                .appendTo(resultsElement);
        }
    }

    function showResults(resultsElement, result) {
        var products = Array.isArray(result.products) ? result.products : [],
            store = result.store || {},
            configuration = result.configuration || {},
            summary = $('<div>', {'class': 'message message-success'}),
            productIndex;

        resultsElement.empty();
        summary
            .text('✅ ' + $t('Search completed.') + ' ' +
                $t('Showing') + ' ' + result.displayed_count + ' ' + $t('of') + ' ' +
                result.total_count + ' ' + $t('results for') + ' "' + result.query + '" ' +
                $t('in') + ' ' + store.name + ' (' + store.code + ').')
            .appendTo(resultsElement);
        createSearchConfiguration(configuration).appendTo(resultsElement);

        if (products.length === 0) {
            $('<div>', {'class': 'message message-notice'})
                .text($t('No products matched the query.'))
                .appendTo(resultsElement);

            return;
        }

        for (productIndex = 0; productIndex < products.length; productIndex++) {
            createProduct(products[productIndex]).appendTo(resultsElement);
        }
    }

    function addStoreOptions(selectElement, stores, selectedStoreId) {
        var storeIndex,
            option;

        $('<option>', {
            value: '',
            disabled: true,
            selected: true
        })
            .text($t('Select a store view'))
            .appendTo(selectElement);

        for (storeIndex = 0; storeIndex < stores.length; storeIndex++) {
            option = $('<option>')
                .val(stores[storeIndex].id)
                .text(stores[storeIndex].label);

            if (stores[storeIndex].id === selectedStoreId) {
                option.prop('selected', true);
            }

            option.appendTo(selectElement);
        }
    }

    function runSearch(config, queryInput, storeSelect, searchButton, resultsElement) {
        var query = $.trim(queryInput.val());

        if (query === '') {
            showError(resultsElement, $t('Enter a search query.'), '');
            queryInput.trigger('focus');

            return;
        }

        searchButton.prop('disabled', true);
        resultsElement.empty();
        $('<div>', {'class': 'message message-notice'})
            .text($t('Running semantic search...'))
            .appendTo(resultsElement);

        $.ajax({
            url: config.url,
            type: 'POST',
            dataType: 'json',
            data: {
                form_key: window.FORM_KEY,
                q: query,
                store_id: storeSelect.val()
            },
            showLoader: true
        }).done(function (response) {
            if (response.success === true && response.result) {
                showResults(resultsElement, response.result);

                return;
            }

            showError(resultsElement, response.message, response.error_message);
        }).fail(function (response) {
            showError(
                resultsElement,
                $t('The semantic search test request failed.'),
                getErrorMessage(response)
            );
        }).always(function () {
            searchButton.prop('disabled', false);
        });
    }

    function createDialog(config) {
        var dialog = $('<div>', {'class': 'ai-search-test-dialog'}),
            form = $('<div>', {'class': 'ai-search-test-form'}),
            queryField = $('<div>', {'class': 'ai-search-test-field'}),
            storeField = $('<div>', {'class': 'ai-search-test-field'}),
            queryInput = $('<input>', {
                type: 'text',
                'class': 'admin__control-text',
                placeholder: $t('Enter a search query')
            }),
            storeSelect = $('<select>', {'class': 'admin__control-select'}),
            searchButton = $('<button>', {
                type: 'button',
                'class': 'action-primary'
            }).text($t('Search')),
            resultsElement = $('<div>', {'class': 'ai-search-test-results'}),
            stores = Array.isArray(config.stores) ? config.stores : [];

        $('<label>')
            .text($t('Search Query'))
            .appendTo(queryField);
        queryInput.appendTo(queryField);
        $('<label>')
            .text($t('Store View'))
            .appendTo(storeField);
        addStoreOptions(storeSelect, stores, config.selectedStoreId);
        storeSelect.appendTo(storeField);
        queryField.appendTo(form);
        storeField.appendTo(form);
        searchButton.appendTo(form);
        form.appendTo(dialog);
        resultsElement.appendTo(dialog);
        $('<p>', {'class': 'note ai-search-test-note'})
            .text($t('This test shows at most 20 products. Storefront search uses its configured result limit and Magento filters.'))
            .appendTo(dialog);

        searchButton.on('click', function () {
            runSearch(config, queryInput, storeSelect, searchButton, resultsElement);
        });
        queryInput.on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                runSearch(config, queryInput, storeSelect, searchButton, resultsElement);
            }
        });

        modal({
            type: 'popup',
            responsive: true,
            innerScroll: true,
            title: $t('Semantic Search Test'),
            modalClass: 'ai-search-test-modal',
            buttons: []
        }, dialog);

        return {
            element: dialog,
            queryInput: queryInput
        };
    }

    return function (config, element) {
        var testSemanticSearchButton = $(element),
            dialog = createDialog(config);

        testSemanticSearchButton.on('click', function () {
            dialog.element.modal('openModal');
            dialog.queryInput.trigger('focus');
        });
    };
});
