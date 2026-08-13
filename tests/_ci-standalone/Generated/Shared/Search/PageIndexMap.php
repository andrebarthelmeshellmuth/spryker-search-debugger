<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

/*
 * CHECKED-IN TEST FIXTURE, NOT a live-generated artifact — see the CI-portability write-up.
 *
 * `Generated\Shared\Search\PageIndexMap` is produced by `spryker/search`'s own map generator
 * (`vendor/bin/console search:setup:source-map`), and unlike `Generated\Shared\Transfer\*`, its CONTENT
 * is a project-wide aggregate: it carries constants contributed by every package a real project has
 * installed (e.g. the SCORES field here comes from the sibling spryker-community/search-ranking package's
 * own mapping extension, not from this package). That makes it impossible to generate correctly from this
 * package alone in a standalone CI environment, and this package's own Portable-tagged tests reference it.
 *
 * This is a POINT-IN-TIME SNAPSHOT, generated once from a real project (the b2b-demo-marketplace
 * demoshop) that has every relevant sibling package installed, then copied here verbatim and committed.
 * It exists purely so the Portable test subset can run without a live multi-package project.
 *
 * When it goes stale: only if the project-wide search field set itself changes (a new/removed mapping
 * constant from this or a sibling package) — NOT on every unrelated code change. That is an infrequent,
 * deliberate event (changing search mappings is a bigger decision on its own), and a Portable test
 * starting to fail because it references a constant this snapshot doesn't have is the exact, legible
 * signal that regeneration is due — not a silent drift.
 *
 * To regenerate: from a real project with every relevant sibling package installed, run
 * `vendor/bin/console search:setup:source-map`, then copy the resulting
 * `src/Generated/Shared/Search/PageIndexMap.php` here verbatim.
 */

namespace Generated\Shared\Search;

use Spryker\Shared\Search\AbstractIndexMap;

/**
 * !!! THIS FILE IS AUTO-GENERATED, EVERY CHANGE WILL BE LOST WITH THE NEXT RUN OF SEARCH MAP GENERATOR
 * !!! DO NOT CHANGE ANYTHING IN THIS FILE
 */
class PageIndexMap extends AbstractIndexMap
{

    const MERCHANT_REFERENCES = 'merchant_references';
    const PRODUCT_LISTS = 'product-lists';
    const PRODUCT_LISTS_BLACKLISTS = 'product-lists.blacklists';
    const PRODUCT_LISTS_WHITELISTS = 'product-lists.whitelists';
    const SEARCH_RESULT_DATA = 'search-result-data';
    const TYPE = 'type';
    const STORE = 'store';
    const IS_ACTIVE = 'is-active';
    const ACTIVE_FROM = 'active-from';
    const ACTIVE_TO = 'active-to';
    const LOCALE = 'locale';
    const FULL_TEXT = 'full-text';
    const FULL_TEXT_BOOSTED = 'full-text-boosted';
    const STRING_FACET = 'string-facet';
    const STRING_FACET_FACET_NAME = 'string-facet.facet-name';
    const STRING_FACET_FACET_VALUE = 'string-facet.facet-value';
    const INTEGER_FACET = 'integer-facet';
    const INTEGER_FACET_FACET_NAME = 'integer-facet.facet-name';
    const INTEGER_FACET_FACET_VALUE = 'integer-facet.facet-value';
    const COMPLETION_TERMS = 'completion-terms';
    const SUGGESTION_TERMS = 'suggestion-terms';
    const STRING_SORT = 'string-sort';
    const INTEGER_SORT = 'integer-sort';
    const CATEGORY = 'category';
    const CATEGORY_DIRECT_PARENTS = 'category.direct-parents';
    const CATEGORY_ALL_PARENTS = 'category.all-parents';
    const SCORES = 'scores';

    /**
     * @var array
     */
    protected $metadata = [
        self::MERCHANT_REFERENCES => [
            'type' => 'keyword',
        ],
        self::PRODUCT_LISTS => [
            'type' => 'object',
        ],
        self::PRODUCT_LISTS_BLACKLISTS => [
            'type' => 'integer',
        ],
        self::PRODUCT_LISTS_WHITELISTS => [
            'type' => 'integer',
        ],
        self::SEARCH_RESULT_DATA => [
            'type' => 'object',
        ],
        self::TYPE => [
            'type' => 'keyword',
        ],
        self::STORE => [
            'type' => 'keyword',
        ],
        self::IS_ACTIVE => [
            'type' => 'boolean',
        ],
        self::ACTIVE_FROM => [
            'type' => 'date',
        ],
        self::ACTIVE_TO => [
            'type' => 'date',
        ],
        self::LOCALE => [
            'type' => 'keyword',
        ],
        self::FULL_TEXT => [
            'type' => 'text',
            'analyzer' => 'fulltext_index_analyzer',
            'search_analyzer' => 'fulltext_search_analyzer',
        ],
        self::FULL_TEXT_BOOSTED => [
            'type' => 'text',
            'analyzer' => 'fulltext_index_analyzer',
            'search_analyzer' => 'fulltext_search_analyzer',
        ],
        self::STRING_FACET => [
            'type' => 'nested',
        ],
        self::STRING_FACET_FACET_NAME => [
            'type' => 'keyword',
        ],
        self::STRING_FACET_FACET_VALUE => [
            'type' => 'keyword',
        ],
        self::INTEGER_FACET => [
            'type' => 'nested',
        ],
        self::INTEGER_FACET_FACET_NAME => [
            'type' => 'keyword',
        ],
        self::INTEGER_FACET_FACET_VALUE => [
            'type' => 'integer',
        ],
        self::COMPLETION_TERMS => [
            'type' => 'keyword',
            'normalizer' => 'lowercase_normalizer',
        ],
        self::SUGGESTION_TERMS => [
            'type' => 'text',
            'analyzer' => 'suggestion_analyzer',
        ],
        self::STRING_SORT => [
            'type' => 'object',
        ],
        self::INTEGER_SORT => [
            'type' => 'object',
        ],
        self::CATEGORY => [
            'type' => 'object',
        ],
        self::CATEGORY_DIRECT_PARENTS => [
            'type' => 'integer',
        ],
        self::CATEGORY_ALL_PARENTS => [
            'type' => 'integer',
        ],
        self::SCORES => [
            'type' => 'object',
            'dynamic' => '1',
        ],
    ];

}
