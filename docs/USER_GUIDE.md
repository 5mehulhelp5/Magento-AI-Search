# User Guide: AI Search module

This guide explains how to install and configure the AI Search module and prepare it for
storefront use. The sections follow the order in which a new installation is normally set
up. The guide also covers monitoring, testing, other configuration options, and uninstallation.

## Table of contents

- [0. Use cases](#0-use-cases)
- [1. Installing the module](#1-installing-the-module)
- [2. Choosing an AI provider](#2-choosing-an-ai-provider)
  - [2.1. Understanding costs](#21-understanding-costs)
- [3. Configuring the AI server](#3-configuring-the-ai-server)
  - [3.1. Local AI server](#31-local-ai-server)
  - [3.2. Cloud AI server](#32-cloud-ai-server)
  - [3.3. Validating the AI server connection](#33-validating-the-ai-server-connection)
- [4. Preparing your catalog](#4-preparing-your-catalog)
  - [4.1. Configuring product documents](#41-configuring-product-documents)
  - [4.2. Enabling catalog processing](#42-enabling-catalog-processing)
- [5. Monitoring the module](#5-monitoring-the-module)
- [6. Testing the search](#6-testing-the-search)
- [7. Enabling the search on the storefront](#7-enabling-the-search-on-the-storefront)
- [8. Other configurations](#8-other-configurations)
  - [8.1. Critical configurations](#81-critical-configurations)
  - [8.2. Dynamic documents](#82-dynamic-documents)
  - [8.3. Embedder document template](#83-embedder-document-template)
  - [8.4. Data processing optimization](#84-data-processing-optimization)
- [9. Developer operations](#9-developer-operations)
- [10. Uninstalling the module](#10-uninstalling-the-module)
- [11. Support and license](#11-support-and-license)
- [12. Further information](#12-further-information)

## 0. Use cases

Semantic search is useful when product content explains compatibility, features, flavor, comfort,
materials, or intended use. Shoppers can describe what they need instead of using exact catalog
terms.

The Admin search test can also reveal descriptions that do not clearly express these details.
Search quality depends on the indexed content, while Magento continues to handle filters,
visibility, and catalog rules.

## 1. Installing the module

Run the following commands from the Magento root directory:

```shell
composer require davidbel/magento-ai-search
bin/magento module:enable DavidBel_AiSearch
bin/magento setup:upgrade
```

## 2. Choosing an AI provider

For development and testing, the AI server can run locally without API usage costs. For
production, it can run on a remote or cloud service. The module supports OpenAI-compatible APIs,
including the hosted OpenAI API, the Google Gemini OpenAI-compatible API, LM Studio, Ollama, and
llama.cpp. It also supports the native Google Gemini API.

A wide range of embedding models can be used through these supported endpoints, including OpenAI
text-embedding models, Gemini Embedding, EmbeddingGemma, BGE, E5, Nomic Embed, Qwen Embedding,
and Mistral Embed. The right provider and model depend on the company's requirements, policies,
AI strategy, and whether the configuration is intended for testing or production.

### 2.1. Understanding costs

The module is open source and has no license fee. It reuses Magento's existing OpenSearch service,
so the main additional usage cost is normally the selected embedding provider. A local AI server
can avoid API usage costs entirely.

For a catalog of 100,000 products in two languages with 1 million submitted searches per month,
the estimated first-year embedding cost is **$6.53 with OpenAI** or **$48.96 with Google Gemini**.
These estimates exclude existing Magento, OpenSearch, hosting, and operational costs. See the
[Cost Analysis](COST_ANALYSIS.md) for the assumptions, calculations, and comparison with managed
search services.

## 3. Configuring the AI server

The following examples provide two options for configuring the AI server. You can use LM Studio
as a free local option or Google AI Studio as a low-cost cloud option.

### 3.1. Local AI server

In this example, we use LM Studio with the default local AI server configuration.

> **About LM Studio**
>
> LM Studio is a cross-platform desktop application that lets you run AI models on your local
> computer for free. It provides a way to test this module without paying for a cloud AI service.
> After installing LM Studio, search for and download the EmbeddingGemma 300M model. Open the
> **Developer** tab, load the model, and start the local server. LM Studio displays the local
> server URL that you can use in the module configuration.
>
> [Visit the official LM Studio website](https://lmstudio.ai/)

Go to **Stores > Settings > Configuration > AI Search > Semantic Search Source > AI Server**,
then configure the following settings:

| Setting | Value |
|---|---|
| **Embedding Endpoint URL** | `http://<your-lm-studio-host>:1234/v1/embeddings` |
| **Embedding API Protocol** | `OpenAI-Compatible` |
| **API Key** | Your LM Studio API key, or leave empty for an unauthenticated endpoint |
| **Embedding Model** | `text-embedding-embeddinggemma-300m-qat` |

Replace `<your-lm-studio-host>` with an address that Magento can reach. Use `localhost`
when Magento and LM Studio run directly on the same computer. If Magento runs inside a container,
use `host.docker.internal` in most cases.

Leave all other settings at their default values for this example.

### 3.2. Cloud AI server

In this example, we use the official Google AI Studio with a native Gemini embedding model.

> **About Google AI Studio**
>
> Google AI Studio requires a Google account. Processing the Magento sample data may require the
> paid Gemini API tier, a linked Google Cloud billing account, and prepaid credit. Google currently
> requires a minimum prepayment of `$10`. A `$25` balance provides enough budget to process the
> Magento sample data multiple times.
>
> [Open the official Google AI Studio](https://aistudio.google.com/)

Go to **Stores > Settings > Configuration > AI Search > Semantic Search Source > AI Server**,
then configure the following settings:

| Setting | Value |
|---|---|
| **Embedding Endpoint URL** | `https://generativelanguage.googleapis.com/v1beta` |
| **Embedding API Protocol** | `Google Gemini Native` |
| **API Key** | Your Google AI Studio API key |
| **Embedding Model** | `gemini-embedding-001` |

Leave all other settings at their default values for this example.

### 3.3. Validating the AI server connection

After saving these settings, go to **Stores > Settings > Configuration > AI Search > Semantic
Search Source > AI Server > Test Connection**, then click **Test Connection**. A successful test
displays a check mark and the message **Connection successful**. This confirms that Magento can
connect to the AI server and generate embeddings using the configured endpoint, model, protocol,
and authentication.

## 4. Preparing your catalog

After validating the AI server connection in the previous steps, the next step is to prepare your
catalog. The module will process the product data, generate vector embeddings through the AI
server, and publish them to OpenSearch.

### 4.1. Configuring product documents

Go to **Stores > Settings > Configuration > AI Search > Semantic Search Source > Document
Configuration** to define which product content the module should process.

The module represents each product through one or more documents. The product name is generally
a good document title.

The product description is the recommended document because it normally contains the most useful
searchable information. Add other meaningful long-text attributes only when needed. One document
is ideal, and only a few should normally be necessary.

Use **Html To Text** when an attribute may contain HTML, as product descriptions commonly do. Use
**Text As Is** for attributes that contain prepared plain text. For the product description, use
**Yes: Include Child Products** when the catalog contains configurable, grouped, or bundle
products so their child product content can also be included.

Use the following document configuration for this example:

| Setting | Value |
|---|---|
| **Enable Document Title** | `Yes` |
| **Document Title** | `Product Name` |
| **Documents: Attribute** | `Description` |
| **Documents: Composition** | `Yes: Include Child Products` |
| **Documents: Parsing Strategy** | `Html To Text` |
| **Enable Dynamic Document** | `No` |

### 4.2. Enabling catalog processing

To enable this process, stay on **Stores > Settings > Configuration > AI Search > Semantic Search
Source**, scroll up to **Indexing Scope > Store Views for Indexing**, then select the store views
that the module should index.

Catalog processing starts automatically as soon as the selected store views are saved.

## 5. Monitoring the module

Make sure Magento cron is running. It should already be active on a fully configured Magento
installation.

Go to **System > AI Search > Dashboard** to monitor catalog processing. **Indexing Progress** shows
how many products are waiting for the first processing stage. **Ingestion Pipeline Progress** shows
the second stage, including pending, successfully processed, retrying, and failed items.

More processing details are available under **System > AI Search**:

- **Documents** shows the product documents stored by the module for inspection and debugging.
- **Chunks** shows the prepared text chunks. The AI server converts each chunk into a vector, which
  is then published to OpenSearch.
- **Backlog** shows the current and recent ingestion progress. Old backlog items are cleaned daily
  after their configured retention period.

## 6. Testing the search

After the dashboard shows that catalog processing completed successfully, go to **System > AI
Search > Dashboard** and click **Test Semantic Search**. Enter a search query, select the store
view to search, then click **Search**.

The results show the matching products, their related documents, and the matching chunks returned
by OpenSearch. This detailed view is intended for debugging and tuning the search configuration.

## 7. Enabling the search on the storefront

After the catalog has been processed and semantic search has been tested in Admin, the final step
is to enable it for the storefront. Semantic search is disabled on the storefront by default.

Go to **Stores > Settings > Configuration > AI Search > Semantic Search Result**, select the
desired store view from the scope selector, then use the following configuration:

| Setting | Value |
|---|---|
| **General: Enabled** | `Yes` |
| **General: Use Previous Semantic Index During Rebuild** | `Yes` |
| **Search Relevance: Collapse Results by Product** | `Yes` |
| **Search Relevance: Product Result Limit** | `1000` |
| **Search Relevance: Minimum Score** | `0.46` |
| **Embedding: Embedder Query Template** | `task: search result \| query: {text}` |

Using the previous semantic index during a rebuild keeps the current index available for
storefront searches while its replacement is being prepared. After saving the configuration,
Magento will use semantic search for storefront queries and fall back to its default search if
semantic search is unavailable.

The **Minimum Score** depends on the selected AI model and vector configuration. A higher score
represents a closer semantic match. A practical starting range is usually between `0.3` and `0.8`, and the module default is `0.46`.

Both the LM Studio and Google AI Studio examples in this guide use the first query template below.
Google embedding models also document task-specific alternatives for other search behaviors.

| Query template | Description |
|---|---|
| `task: search result \| query: {text}` | Searches for products or documents that match the query. This is the recommended template for product search. |
| `task: question answering \| query: {text}` | Searches for documents that may answer a question. |
| `task: fact checking \| query: {text}` | Searches for documents that may support or contradict a statement. |
| `task: code retrieval \| query: {text}` | Searches for code using a natural-language query. |

Use **Test Semantic Search** button in the **Search Relevance** group to test and tune these values in
Admin.

## 8. Other configurations

The module provides further configuration options beyond the recommended setup described above.
Leave these settings at their default values unless you understand their effect on catalog
processing, AI server usage, and OpenSearch.

### 8.1. Critical configurations

Critical configurations are located under **Stores > Settings > Configuration > AI Search >
Semantic Search Source**. Changing any setting on this page, except the **Embedding Endpoint
URL**, **API Key**, and AI server **Request Timeout**, automatically triggers a full AI Search
rebuild. The selected catalog is processed again, new embeddings are requested, and a new
OpenSearch index is prepared. This may make many AI server requests and take a significant amount
of time for big catalogs.

The warning at the top of the page explains this behavior. Every field that triggers a full
rebuild is also marked with a tooltip in Admin.

| Setting | Description |
|---|---|
| **Store Views for Indexing** | Selects the store views whose eligible products are processed and indexed. |
| **Embedding API Protocol** | Selects the request and response format used to communicate with the AI server. |
| **Embedding Model** | Identifies the embedding model used for documents and search queries. |
| **Vector Dimensions** | Defines the number of values in each embedding vector. It must match the selected model. |
| **Embedder Document Template** | Formats the document title and chunk text before sending them to the embedding model. |
| **Maximum Chunk Tokens** | Sets the estimated maximum size of each text chunk. |
| **Chunk Overlap Tokens** | Repeats part of the previous chunk to preserve context between neighboring chunks. |
| **Estimated Characters per Token** | Controls how the module estimates token counts while splitting text. |
| **Vector Engine** | Selects the OpenSearch engine used for vector indexing and nearest-neighbor search. |
| **Vector Space** | Selects the distance calculation used to compare query and document vectors. |
| **Enable Document Title** | Controls whether a product attribute is included as the document title. |
| **Document Title** | Selects the product attribute used as the document title. |
| **Documents** | Selects the product attributes, composition behavior, and parsing strategy used to create documents. |
| **Enable Dynamic Document** | Controls whether the store view receives an additional template-generated document. |
| **Dynamic Document** | Defines the attributes, composition, parsing, and text fragments used to build the additional document. |

### 8.2. Dynamic documents

A dynamic document combines selected product attributes into one additional natural-language
document. Each row defines one document part. Placeholders in the template must match the selected
attribute codes, using braces such as `{name}` or `{color}`. If a product has no value for one of
the selected attributes, that part is omitted. The remaining parts are joined in their configured
order.

To try the feature, select the desired store view under **Stores > Settings > Configuration > AI
Search > Semantic Search Source > Document Configuration**, set **Enable Dynamic Document** to
`Yes`, and add the following parts:

| Attributes | Composition | Parsing Strategy | Template |
|---|---|---|---|
| **Name** | `No: Current Product Only` | `Text As Is` | `This product is called {name}.` |
| **Color**, **Size** | `Yes: Include Child Products` | `Text As Is` | `It is available in {color}, with size options including {size}.` |
| **Material** | `Yes: Include Child Products` | `Text As Is` | `It is made from {material}.` |

For a product named **Alpine Puffer Jacket**, available in **Red** and **Blue**, offered in
**Small**, **Medium**, and **Large**, and made from **recycled polyester**, the generated document
would be:

> This product is called Alpine Puffer Jacket. It is available in Red and Blue, with size options
> including Small, Medium, and Large. It is made from recycled polyester.

Save the configuration to start the required full rebuild. Review the generated document and its
chunks under **System > AI Search > Documents** before using it in production.

### 8.3. Embedder document template

The **Embedder Document Template** defines the text sent to the embedding model for every document
chunk. The default template is `title: {title} | text: {text}`. The `{title}` placeholder is
replaced with the configured document title, or `none` when no title is available. The `{text}`
placeholder is replaced with the current chunk text.

Both AI server examples in this guide use this default document template. A different embedding
model may require another format, so follow the documentation for the selected model before
changing it.

### 8.4. Data processing optimization

The settings under **Stores > Settings > Configuration > AI Search > Semantic Data Processing**
control batch sizes, concurrency, worker runtimes, retries, cleanup, and index activation. The
defaults are recommended unless performance testing shows that they should be adjusted.

| Group and setting | Default | Description |
|---|---:|---|
| **Document Processing: Batch Size** | `200` | Sets how many products are loaded and processed in one document batch. |
| **Vector Embedding: Batch Size** | `100` | Sets how many pending items are included in one embedding batch. |
| **Vector Embedding: Concurrent Requests** | `3` | Sets how many embedding batches may be sent to the AI server at the same time. |
| **Vector Embedding: Maximum Runtime (Seconds)** | `600` | Limits how long one worker run continues selecting new embedding batches. |
| **Vector Embedding: Cron Schedule** | `* * * * *` | Runs embedding and OpenSearch upsert processing every minute. |
| **Vector Delete: Batch Size** | `1000` | Sets how many pending vector deletions are processed in one batch. |
| **Vector Delete: Maximum Runtime (Seconds)** | `600` | Limits how long one worker run continues selecting vector deletion batches. |
| **Vector Delete: Cron Schedule** | `* * * * *` | Runs OpenSearch vector deletion processing every minute. |
| **Retry: Attempt Threshold** | `3` | Defines the maximum number of failed processing attempts for upsert and delete backlog items. |
| **Retry: Cron Schedule** | `*/5 * * * *` | Makes eligible failed backlog items available for another attempt every five minutes. |
| **Cleanup: Result Retention (Hours)** | `24` | Retains completed and outdated backlog results for this many hours before cleanup. |
| **Cleanup: Cron Schedule** | `0 0 * * *` | Removes retained backlog records and obsolete physical indexes daily at midnight. |
| **Indexer: Lock Timeout (Seconds)** | `10` | Limits how long index version management waits to acquire its lock. |
| **Indexer: Minimum Successful Full Reindex (%)** | `50` | Sets the minimum successful share of a completed full rebuild required before the new index can become active. |

Each Vector Embedding cron job starts one worker run. The worker can send up
to `Batch Size × Concurrent Requests` backlog items at once and continues selecting batches until
no pending work remains or Maximum Runtime is reached.

Vector Delete processes up to its Batch
Size in each OpenSearch request and also continues until no pending deletions remain or its Maximum
Runtime is reached.

## 9. Developer operations

To rebuild all AI Search documents and chunks, run the following command from the Magento root
directory:

```shell
bin/magento indexer:reindex davidbel_ai_search_product_indexer
```

The reindex creates the backlog items required for embedding and OpenSearch indexing. To process
them immediately instead of waiting for the scheduled cron job, run:

```shell
bin/magento cron:run --group=davidbel_ai_search
```

Indexer, cron, batch, and error information is written to:

```text
var/log/davidbel/ai-search.log
```

To follow new log entries from the Magento root directory, run:

```shell
tail -f var/log/davidbel/ai-search.log
```

## 10. Uninstalling the module

Test the removal in a non-production environment and back up the database before proceeding.
Then run the standard Magento module uninstall command from the Magento root directory:

```shell
bin/magento module:uninstall --remove-data --clear-static-content DavidBel_AiSearch
```

The `--remove-data` option removes the module database tables, configuration, indexer state,
processing records, and physical OpenSearch indexes. Magento also disables the module,
removes it from the deployment configuration, removes the Composer package, clears the cache,
and updates generated classes.

## 11. Support and license

The module is published under the [MIT License](https://github.com/DavidBelicza/Magento-AI-Search/blob/main/LICENSE) by David Belicza, a certified Adobe Commerce Expert.

If you find a bug or would like to request a feature, open an
[issue on GitHub](https://github.com/DavidBelicza/Magento-AI-Search/issues).

## 12. Further information

For architecture, compatibility, performance measurements, and other technical details, see
the project's [README file](https://github.com/DavidBelicza/Magento-AI-Search#readme).
