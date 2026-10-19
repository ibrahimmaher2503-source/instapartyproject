<?php

declare(strict_types=1);

return [
    'wishlist' => [
        'created' => 'Wishlist created successfully.',
        'updated' => 'Wishlist updated successfully.',
        'deleted' => 'Wishlist deleted successfully.',
        'item_added' => 'Service added to wishlist.',
        'item_removed' => 'Service removed from wishlist.',
        'already_exists' => 'This service is already in your wishlist.',
    ],

    'search' => [
        'no_results' => 'No services found matching your search.',
        'guest' => 'Guest',
        'filters_only' => 'Filters only',
        'empty_query' => 'Legacy empty search',
        'no_filters' => 'No filters',
    ],

    'filters' => [
        'product_types' => 'Product types',
        'city' => 'City',
        'max_price' => 'Maximum price',
        'locale' => 'Language',
    ],

    'saved_search' => [
        'saved' => 'Search saved successfully.',
        'deleted' => 'Saved search deleted successfully.',
    ],
    'account' => [
        'add_favorite' => 'Add to favorites',
        'remove_favorite' => 'Remove from favorites',
        'save_search' => 'Save this search',
        'search_label' => 'Name this search',
        'run_search' => 'Run search',
        'delete_search' => 'Delete',
        'saved_on' => 'Saved :date',
        'no_saved_searches' => 'You have no saved searches yet.',
    ],

    'reindex_services' => 'Reindex Services',
    'reindex_success' => 'Service reindexing has been queued successfully.',
    'reindex_confirm' => 'This will queue a full reindex of all published services. Do you want to continue?',
    'wishlist_added' => 'Service added to wishlist.',
    'wishlist_removed' => 'Service removed from wishlist.',
    'search_placeholder' => 'Search services...',

    'nav' => [
        'saved_searches' => 'Saved Searches',
        'search_logs' => 'Search Logs',
        'wishlists' => 'Wishlists',
    ],

    'models' => [
        'saved_search' => [
            'singular' => 'Saved Search',
            'plural' => 'Saved Searches',
        ],
        'search_log' => [
            'singular' => 'Search Log',
            'plural' => 'Search Logs',
        ],
        'wishlist' => [
            'singular' => 'Wishlist',
            'plural' => 'Wishlists',
        ],
    ],

    'columns' => [
        'public_id' => 'Public ID',
        'user_id' => 'User',
        'label' => 'Label',
        'filters' => 'Filters',
        'query' => 'Search Query',
        'locale' => 'Language',
        'results_count' => 'Results Count',
        'clicked_service_id' => 'Clicked Service',
        'name' => 'Name',
        'items_count' => 'Items Count',
    ],

    'package_recommendations' => [
        'name_en' => 'Name (EN)',
        'description_en' => 'Description (EN)',
        'name_ar' => 'Name (AR)',
        'description_ar' => 'Description (AR)',
        'nav' => 'Homepage Packages',
        'singular' => 'Homepage Package',
        'plural' => 'Homepage Packages',
        'content' => 'Package content',
        'content_help' => 'Manage homepage recommendations linked to an occasion and suggested budget. This does not create or manage bundled vendor services; that workflow is not available here.',
        'occasion' => 'Matching occasion',
        'presentation' => 'Homepage presentation',
        'hero' => 'Package image',
        'published' => 'Published on homepage',
        'budget' => 'Suggested budget',
        'budget_help' => 'Enter integer minor units. For EGP, 100 means EGP 1.00.',
    ],
];
