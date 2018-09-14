<?php
/**
 * DokuWiki Plugin vimeo (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */

class syntax_plugin_vimeo extends DokuWiki_Syntax_Plugin
{
    /**
     * @return string Syntax mode type
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType()
    {
        return 'block';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort()
    {
        return 100;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('{{vimeoAlbum>.+?}}', $mode, 'plugin_vimeo');
    }

    /**
     * Handle matches of the vimeo syntax
     *
     * @param string       $match   The match of the syntax
     * @param int          $state   The state of the handler
     * @param int          $pos     The position in the document
     * @param Doku_Handler $handler The handler
     *
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $albumID = substr($match, strlen('{{vimeoAlbum>'), -2);
        $videos = $this->getAlbumVideos($albumID);
        $data = ['videos' => $videos];

        return $data;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string        $mode     Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     *
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode !== 'xhtml') {
            return false;
        }

        $videos = $data['videos'];

        $renderer->doc .= '<div class="plugin-vimeo-album">';

        foreach ($videos as $video) {
            $renderer->doc .= '<div class="plugin-vimeo-video" data-videoiframe="' . hsc($video['embed']['html']) . '">';
            $renderer->doc .= '<figure>';
            $src = $video['pictures']['sizes'][2]['link_with_play_button'];
            $srcset = [];
            foreach ($video['pictures']['sizes'] as $picture) {
                $srcset [] = $picture['link_with_play_button'] . ' ' . $picture['width'] . 'w';
            }
            $caption = $video['name'];
            $renderer->doc .= '<img srcset="' . implode(',', $srcset) . '" src="' . $src . '" alt="' . $caption . '">';
            $renderer->doc .= '<figcaption>' . $caption . '</figcaption>';
            $renderer->doc .= '</figure>';
            $renderer->doc .= '</div>';
        }

        $renderer->doc .= '</div>';

        return true;
    }

    /**
     * Get all video data for the given album id
     *
     * The albumID must be owned be the user that provided the configured access token
     *
     * This also gets the paged videos in further requests if there are more than 100 videos
     *
     * @param string $albumID
     *
     * @return array data for the videos in the album
     */
    protected function getAlbumVideos($albumID) {
        $http = new \DokuHTTPClient();
        $http->headers['Authorization'] = 'Bearer ' . $this->getConf('accessToken');
        $http->agent = 'DokuWiki HTTP Client (Vimeo Plugin)';
        $fields = 'name,embed.html,pictures.sizes';
        $base = 'https://api.vimeo.com';
        $url = $base . '/me/albums/' . $albumID . '/videos?per_page=100&fields=' . $fields;
        $http->sendRequest($url);

        $body = $http->resp_body;
        $respData = json_decode($body, true);
        $videos = $respData['data'];

        if (empty($respData['paging']['next'])) {
            return $videos;
        }

        while(true) {
            $url = $base . $respData['paging']['next'];
            $http->sendRequest($url);
            $body = $http->resp_body;
            $respData = json_decode($body, true);
            $videos = array_merge($videos, $respData['videos']);
            if (empty($respData['paging']['next'])) {
                break;
            }
        }

        return $videos;
    }
}

