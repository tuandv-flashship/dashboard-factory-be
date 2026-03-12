<?php

/**
 * HTMLPurifier Configuration for CMS Content
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,
    'enabled' => env('PURIFIER_ENABLED', true),

    'settings' => [
        // Default - strict, for simple text fields
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'div,b,strong,i,em,u,a[href|title|target],ul,ol,li,p[style],br,span[style],img[width|height|alt|src]',
            'CSS.AllowedProperties' => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
            'Attr.AllowedFrameTargets' => ['_blank', '_self'],
        ],

        // CMS - for rich content (posts, pages)
        'cms' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => implode(',', [
                // Block elements
                'div[class|style|id]',
                'p[class|style]',
                'h1[class|style]',
                'h2[class|style]',
                'h3[class|style]',
                'h4[class|style]',
                'h5[class|style]',
                'h6[class|style]',
                'blockquote[class|style]',
                'pre[class]',
                'code[class]',
                'hr',
                'br',
                // Lists
                'ul[class|style]',
                'ol[class|style]',
                'li[class|style]',
                // Tables
                'table[class|style|border|cellpadding|cellspacing|width]',
                'thead',
                'tbody',
                'tfoot',
                'tr[class|style]',
                'th[class|style|colspan|rowspan|width]',
                'td[class|style|colspan|rowspan|width]',
                // Inline elements
                'span[class|style]',
                'a[href|title|target|rel|class]',
                'strong',
                'b',
                'em',
                'i',
                'u',
                's',
                'strike',
                'sub',
                'sup',
                'small',
                // Media
                'img[src|alt|title|width|height|class|style]',
            ]),
            'CSS.AllowedProperties' => implode(',', [
                'font',
                'font-size',
                'font-weight',
                'font-style',
                'font-family',
                'text-decoration',
                'text-align',
                'color',
                'background-color',
                'background',
                'padding',
                'padding-left',
                'padding-right',
                'padding-top',
                'padding-bottom',
                'margin',
                'margin-left',
                'margin-right',
                'margin-top',
                'margin-bottom',
                'border',
                'border-color',
                'border-style',
                'border-width',
                'width',
                'height',
                'max-width',
                'max-height',
                'float',
                'clear',
            ]),
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
            'Attr.AllowedFrameTargets' => ['_blank', '_self'],
            'HTML.SafeIframe' => true,
            'URI.SafeIframeRegexp' => '%^(https?:)?//(www\.youtube\.com/embed/|player\.vimeo\.com/video/|www\.dailymotion\.com/embed/)%',
        ],

        // YouTube/Video embeds
        'youtube' => [
            'HTML.SafeIframe' => true,
            'URI.SafeIframeRegexp' => '%^(https?:)?//(www\.youtube\.com/embed/|player\.vimeo\.com/video/)%',
        ],

        // Description - for short text fields
        'description' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,strong,b,em,i,u,a[href|title|target],span[style]',
            'CSS.AllowedProperties' => 'font-weight,font-style,text-decoration,color',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
            'Attr.AllowedFrameTargets' => ['_blank'],
        ],
    ],
];

