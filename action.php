<?php

class action_plugin_vimeo extends DokuWiki_Action_Plugin
{
    /**
     * Register handlers
     */
    function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook(
            'DOKUWIKI_STARTED', 'AFTER', $this,
            'jsinfo'
        );
    }

    /**
     * Add sectok to JavaScript to secure ajax requests
     *
     * @param Doku_Event $event
     * @param            $param
     */
    function jsinfo(Doku_Event $event, $param)
    {
        global $JSINFO;
        global $ID;

        if (auth_ismanager()) {
            $JSINFO['plugins']['vimeo']['purgelink'] = wl($ID, ['purge' => 'true'], false, '&');
        } else {
            $JSINFO['plugins']['vimeo']['purgelink'] = '';
        }
    }
}
