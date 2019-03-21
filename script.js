jQuery(function () {
    jQuery('.plugin-vimeo-video').on('click.startVideo', function (event) {
        var $this = jQuery(this);
        $this.off('click.startVideo');
        $this.find('img').remove();
        $this.css('width', '');
        $this.addClass('is-player');
        var encodedIframe = $this.data('videoiframe');
        var textArea = document.createElement('textarea');
        textArea.innerHTML = encodedIframe;
        var iframeHtml = textArea.value;
        $this.prepend(jQuery(iframeHtml));
    });

    if (JSINFO.plugins.vimeo.purgelink) {
        var $link = jQuery('<a>');
        $link.text(LANG.plugins.vimeo.purgeLink);
        $link.attr('rel', 'noreferrer');
        $link.attr('href', JSINFO.plugins.vimeo.purgelink);
        jQuery('.plugin-vimeo-album').before($link);
    }
});
