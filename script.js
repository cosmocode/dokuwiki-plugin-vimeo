jQuery(function (){
    jQuery('.plugin-vimeo-video').on('click.startVideo', function(event) {
        const $this = jQuery(this);
        $this.off('click.startVideo');
        $this.find('img').remove();
        const encodedIframe = $this.data('videoiframe');
        var textArea = document.createElement('textarea');
        textArea.innerHTML = encodedIframe;
        const iframeHtml = textArea.value;
        $this.prepend(jQuery(iframeHtml));
    });
});
