<?php

declare(strict_types=1);

/**
 * Nested under `media` so error-code keys like `media.reorder_conflict` resolve
 * directly via `__('shared::media.media.reorder_conflict')`.
 * Per spec 048-media-collections-phase1/data-model.md §7.
 */
return [
    'media' => [
        'exceeds_max_files' => 'Too many files for this collection.',
        'exceeds_max_size' => 'File is too large.',
        'unsupported_mime' => 'File type is not supported.',
        'invalid_dimensions' => 'Image dimensions are not allowed for this collection.',
        'aspect_ratio_violation' => 'Image must be square (1:1 aspect ratio).',
        'publish_requires_image' => 'At least one image is required before publishing.',
        'reorder_conflict' => 'The media order has changed since you last loaded it. Refresh and try again.',
        'reorder_incomplete' => 'Reorder request must include every item currently in the collection.',
        'reorder_unknown_media' => 'Reorder request references a file that is not in this collection.',
        'reorder_not_reorderable' => 'This collection cannot be reordered.',
        'missing_slide_metadata' => 'Slide index and image role are required for this upload.',
        'svg_security_violation' => 'This SVG could not be safely processed and was rejected.',
        'delete_below_min_published' => 'Cannot delete: this would leave the published item without any media. Unpublish first or upload a replacement.',
        'delete_only_required' => 'Cannot delete the only file on a required document; upload a replacement instead.',
        'not_found' => 'Media file not found.',
    ],
    'cdn' => [
        'bust_failed' => 'Failed to refresh the CDN. Please retry.',
    ],
];
