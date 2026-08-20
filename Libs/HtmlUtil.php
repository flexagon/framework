<?php
/**
 * HtmlUtil
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */

namespace _Flexagon\Libs;

/**
 * Helpers that emit HTML directly or tidy up a markup fragment.
 *
 * The strip* methods are regex based conveniences for trimming markup you
 * already control. They are **not** an XSS filter: use a real sanitiser for
 * HTML that came from a user.
 */
class HtmlUtil
{
    /**
     * Send the browser to $url and stop.
     *
     * Prefers a Location header and falls back to a script tag only when the
     * response has already started, since at that point the header can no
     * longer be sent.
     *
     * @param string $url
     * @return void
     */
    public static function redirectPage($url): void {
        if ( !headers_sent() ) {
            header('Location: ' . $url);
            exit();
        }

        self::redirectPageViaJS($url);
    }

    /**
     * The URL often comes from a request parameter, so it is emitted as a JSON
     * literal instead of being pasted between quotes, where a crafted value
     * could close the string and the script tag.
     *
     * Note this does not restrict the destination: validate against your own
     * allow-list before redirecting somewhere a user supplied.
     *
     * @param string $url
     * @return void
     */
    public static function redirectPageViaJS($url): void {
        $encodedUrl = json_encode(
            (string)$url,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        echo "<script type=\"text/javascript\">window.location = {$encodedUrl};</script>";
        exit();
    }

    /**
     * @param string $imgHtml
     * @return array
     */
    public static function extractImageUrl(string $imgHtml): array
    {
        $imageUrlArray = [];
        if (preg_match_all('/<img [^<>]*src=["\']([^"\']+)[^<>]*>/i', $imgHtml, $matches)) {
            foreach ( $matches[1] as $match ) {
                $imageUrlArray[] = $match;
            }
        }
        return $imageUrlArray;
    }

    /**
     * Reduce every start tag to its name, keeping only src and href.
     *
     * @param string $htmlElement
     * @return string
     */
    public static function stripHtmlAttributes(string $htmlElement): string {
        $result = preg_replace(
            "/<([a-z][a-z0-9]*)(?:[^>]*(\s(src|href)=['\"][^'\"]*['\"]))?[^>]*?(\/?)>/is",
            '<$1$2$4>',
            $htmlElement
        );

        return is_null($result) ? $htmlElement : $result;
    }

    /**
     * Remove every occurrence of one tag along with its contents.
     *
     * @param string $html
     * @param string $tag element name, e.g. 'script'
     * @return string the input unchanged when the tag name cannot be matched
     */
    public static function stripSpecificTag(string $html, string $tag): string {
        $tag = trim($tag);
        if ( $tag === '' ) {
            return $html;
        }

        $quotedTag = preg_quote($tag, '/');
        $result = preg_replace('/<' . $quotedTag . '\b.*?<\/\s*' . $quotedTag . '\s*>/is', '', $html);

        return is_null($result) ? $html : $result;
    }

    /**
     * @param string $html
     * @param array $tagsArray element names
     * @return string
     */
    public static function stripSpecificTags(string $html, array $tagsArray ): string {
        foreach ( $tagsArray as $tag ) {
            $html = self::stripSpecificTag($html, (string)$tag);
        }
        return $html;
    }

    /**
     * Strip the attributes from every start tag, keeping the text around them.
     *
     * @param string $html
     * @return string
     */
    public static function stripArgumentFromTags( string $html ): string
    {
        $regEx = '/([^<]*<\s*[a-z](?:[0-9]|[a-z]{0,9}))(?:(?:\s*[a-z\-]{2,14}\s*=\s*(?:"[^"]*"|\'[^\']*\'))*)(\s*\/?>[^<]*)/i';  

        $chunks = preg_split($regEx, $html, -1,  PREG_SPLIT_DELIM_CAPTURE);
        if ( $chunks === false ) {
            return $html;
        }

        $strippedString = '';
        $chunkCount = count($chunks);
        for ($n = 0; $n < $chunkCount; $n++) {
            $strippedString .= $chunks[$n];
        }

        return $strippedString;
    }

    /**
     * Replace spaces with %20.
     *
     * Only spaces: this is for tidying a path that is otherwise already
     * encoded. Use rawurlencode() to encode a value in full.
     *
     * @param string $string
     * @return string
     */
    public static function encodeUrl(string $string): string
    {
        return str_replace(" ", "%20",$string);
    }

    /**
     * @param string $string
     * @return string
     * @deprecated Renamed to encodeUrl(). Kept so that existing callers keep
     *             working; it will go away in a future major version.
     */
    public static function urlEncode(string $string): string
    {
        return self::encodeUrl($string);
    }
}
