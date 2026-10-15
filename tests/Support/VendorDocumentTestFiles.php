<?php

declare(strict_types=1);

namespace Tests\Support;

final class VendorDocumentTestFiles
{
    public static function minimalPdf(): string
    {
        return "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 1 1]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF";
    }
}
