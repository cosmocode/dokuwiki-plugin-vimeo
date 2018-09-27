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
        try {
            $data = $this->getAlbumVideos($albumID);
        } catch (Exception $e) {
            $data = ['errors' => [$e->getMessage() . '; Code: ' . $e->getCode()]];
        }

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

        $this->addAdminPurgeLink($renderer);
        $renderer->doc .= '<div class="plugin-vimeo-album">';

        if (!empty($data['errors'])) {
            foreach ($data['errors'] as $error) {
                msg('Vimeo Plugin Error: ' . hsc($error), -1);
            }
        }

        if (!empty($data['videos'])) {
            $videos = $data['videos'];
            foreach ($videos as $video) {
                $this->renderVideo($renderer, $video);
            }
        }

        $renderer->doc .= '</div>';

        return true;
    }

    /**
     * Add a link for managers+ with which they can purge the current page's cache and trigger a new request to vimeo
     *
     * @param Doku_Renderer $renderer
     */
    protected function addAdminPurgeLink(Doku_Renderer $renderer)
    {
        if (!auth_ismanager()) {
            return;
        }

        global $ID;
        $href = wl($ID, ['purge' => 'true']);
        $reloadLink = '<a href="' . $href . '" rel="noreferrer">' . $this->getLang('purgeLink') . '</a>';
        $renderer->doc .= $reloadLink;
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
    protected function getAlbumVideos($albumID)
    {
        $accessToken = $this->getConf('accessToken');
        if (empty($accessToken)) {
            throw new RuntimeException('Vimeo access token not configured! Please see documentation.');
        }

        $fields = 'name,description,embed.html,pictures.sizes,privacy,release_time';
        $endpoint = '/me/albums/' . $albumID . '/videos?sort=manual&per_page=100&fields=' . $fields;
        $errors = [];
        $respData = $this->sendVimeoRequest($accessToken, $endpoint, $errors);
        $videos = $respData['data'];

        if (!empty($respData['paging']['next'])) {
            while (true) {
                $respData = $this->sendVimeoRequest($accessToken, $respData['paging']['next'], $errors);
                $videos = array_merge($videos, $respData['videos']);
                if (empty($respData['paging']['next'])) {
                    break;
                }
            }
        }

        return [
            'videos' => $videos,
            'errors' => $errors,
        ];
    }

    /**
     * Make a single request to Vimeo and return the parsed body
     *
     * @param string $accessToken The access token
     * @param string $endpoint    The endpoint to which to connect, must begin with a /
     * @param array  $errors      If the rate-limit is hit, then an error-message is written in here
     *
     * @return mixed
     *
     * @throws RuntimeException  If the server returns an error
     */
    protected function sendVimeoRequest($accessToken, $endpoint, &$errors)
    {
        $http = new \DokuHTTPClient();
        $http->headers['Authorization'] = 'Bearer ' . $accessToken;
        $http->agent = 'DokuWiki HTTP Client (Vimeo Plugin)';
        $base = 'https://api.vimeo.com';
        $url = $base . $endpoint;
        $http->sendRequest($url);

        $body = $http->resp_body;
        $respData = json_decode($body, true);

        if (!empty($respData['error'])) {
            dbglog($http->resp_headers, __FILE__ . ': ' . __LINE__);
            throw new RuntimeException(
                $respData['error'] . ' ' . $respData['developer_message'],
                $respData['error_code']
            );
        }

        $remainingRateLimit = $http->resp_headers['x-ratelimit-remaining'];
        if ($remainingRateLimit < 10) {
            dbglog($http->resp_headers, __FILE__ . ': ' . __LINE__);
            $errors[] = 'The remaining Vimeo rate-limit is very low. Please check back in 15min or later';
        }

        return $respData;
    }

    /**
     * Render a preview image and put the video iframe-html into a data attribute
     *
     * This offers all available images in a srcset, so the browser can decide which to load
     *
     * @param Doku_Renderer $renderer
     * @param array         $video    The video data
     */
    protected function renderVideo(Doku_Renderer $renderer, $video)
    {
        $title = hsc($video['name']);
        if ($video['privacy']['embed'] === 'private') {
            msg(sprintf($this->getLang('embed_deactivated'), $title), 2);
            return;
        }
        $thumbnailWidthPercent = $this->getConf('thumbnailWidthPercent');
        $widthAttr = 'style="width: ' . $thumbnailWidthPercent . '%;"';
        $renderer->doc .= '<div class="plugin-vimeo-video"' . $widthAttr . ' data-videoiframe="' . hsc($video['embed']['html']) . '">';
        $renderer->doc .= '<figure>';
        $src = $video['pictures']['sizes'][2]['link_with_play_button'];
        $srcset = [];
        foreach ($video['pictures']['sizes'] as $picture) {
            $srcset [] = $picture['link_with_play_button'] . ' ' . $picture['width'] . 'w';
        }
        $renderer->doc .= '<img srcset="' . implode(',', $srcset) . '" src="' . $src . '" alt="' . $title . '">';
        $caption = $this->createCaption($video);
        $renderer->doc .= '<figcaption>' . $caption . '</figcaption>';
        $renderer->doc .= '</figure>';
        $renderer->doc .= '</div>';
    }

    /**
     * Build the caption for a video
     *
     * @param array $video the video data
     *
     * @return string HTML for the video caption
     */
    protected function createCaption($video) {
        $title = '<span class="vimeo-video-title">' . hsc($video['name']) . '</span>';

        $releaseDateObject = new \DateTime($video['release_time']);
        $releaseTime = dformat($releaseDateObject->format('U'));
        $releaseString = '<span class="vimeo-video-releaseTime">'
            . $this->getLang('released')
            . ' <time>' . $releaseTime .'</time></span>';

        $description = "<span class='vimeo-video-description'>" . hsc($video['description']) . '</span>';

        return $title . $releaseString . $description;

    }
}

