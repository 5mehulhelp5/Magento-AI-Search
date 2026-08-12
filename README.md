<!--
davidbel/ai-search by David Belicza
SPDX-License-Identifier: MIT
https://github.com/DavidBelicza/Magento-AI-Search
-->

# Magento AI Search

## 🏗️ Architecture

```mermaid
flowchart TB
    Catalog["Magento catalog<br/>products, attributes, store scopes"]

    subgraph INDEXING["AI Search &nbsp;&middot;&nbsp; indexing, runs in the background"]
        direction LR
        Projection["Store-scoped<br/>meaning projection"]
        Queue[("Durable work queue")]
        Workers["Embedding workers"]
        Activation["Versioned publication<br/>and atomic activation"]
        Projection --> Queue --> Workers --> Activation
    end

    subgraph VECTOR["Vector infrastructure"]
        direction TB
        Model["Embedding model<br/>OpenAI-compatible API"]
        Index[("OpenSearch k-NN index<br/>live and rebuild versions")]
    end

    subgraph SEARCHING["AI Search &nbsp;&middot;&nbsp; storefront search, at request time"]
        direction LR
        QueryVector["Query embedding"]
        Retrieval["Store-filtered<br/>vector retrieval"]
        Merge["Semantic and lexical<br/>rank merge"]
        QueryVector --> Retrieval --> Merge
    end

    Query["Storefront search query"]
    Results["Ranked product results"]

    Catalog -->|"tracked changes"| Projection
    Workers <--> Model
    Activation -->|"zero-downtime swap"| Index

    Query --> QueryVector
    QueryVector <--> Model
    Index --> Retrieval
    Merge --> Results
    Query -.->|"semantic layer unavailable:<br/>falls back to Magento search"| Merge

    class Projection,Queue,Workers,Activation,QueryVector,Retrieval,Merge module;
    class Model,Index vector;
    class Catalog,Query,Results platform;

    classDef module fill:#F26322,color:#FFFFFF,stroke:#C2410C,stroke-width:2px;
    classDef vector fill:#00C853,color:#FFFFFF,stroke:#00963F,stroke-width:2px;
    classDef platform fill:#FFF3EC,color:#7C2D12,stroke:#F26322,stroke-width:1.5px;

    style INDEXING fill:none,stroke:#F26322,stroke-width:2px;
    style SEARCHING fill:none,stroke:#F26322,stroke-width:2px;
    style VECTOR fill:none,stroke:#00C853,stroke-width:2px;

    linkStyle default stroke:#C2410C,stroke-width:2px;
```

## ⚙️ System requirements

| Distribution | Status |
|---|---|
| Magento Open Source | ✅ Supported |
| Adobe Commerce | 🧪 To be tested |
| Adobe Commerce on Cloud | 🧪 To be tested |
| Mage-OS | 🧪 To be tested |

| Magento | PHP | OpenSearch | Status |
|---:|---:|---:|---|
| 2.4.9 | 8.5 | 3 | 🧪 To be tested |
| 2.4.9 | 8.5 | 2 | 🧪 To be tested |
| 2.4.9 | 8.4 | 3 | ✅ Supported |
| 2.4.9 | 8.4 | 2 | 🧪 To be tested |
| 2.4.8-p3+ | 8.4 | 3 | 🧪 To be tested |
| 2.4.8 | 8.4 | 2 | 🧪 To be tested |
| 2.4.8-p3+ | 8.3 | 3 | 🧪 To be tested |
| 2.4.8 | 8.3 | 2 | 🧪 To be tested |
| 2.4.7-p10 | 8.3 | 3 | 🧪 To be tested |
| 2.4.7 | 8.3 | 2 | 🧪 To be tested |

OpenSearch must include the k-NN plugin, which is bundled with the standard OpenSearch
distribution normally used with Magento.

## 📦 Install

```shell
composer require davidbel/ai-search
```
