jQuery(document).ready(function ($) {

    // Tabs
    var $tabsWrapper = $('#publishpress-revisions-settings-tabs');
    $tabsWrapper.find('li').click(function (e) {
        e.preventDefault();
        $tabsWrapper.children('li').filter('.nav-tab-active').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        var panel = $(this).find('a').first().attr('href');

        $('table[id^="ppr-"]').hide();
        $(panel).show();
    });

    var pprTab = 'ppr-tab-0';

    if (typeof pprSettings != 'undefined' && typeof pprSettings.tab != 'undefined') {
       pprTab = pprSettings.tab;
       $('#publishpress-revisions-settings-tabs a[href="#' + pprTab + '"]').click();
    }

    var $hiddenFields = $('input[id^="ppr-tab-"]');

    $hiddenFields.each(function () {
        var $this = $(this);
        var $wrapper = $this.next('table');
        $wrapper.attr('id', $this.attr('id'));
        $this.remove();

        if ($wrapper.attr('id') !== pprTab) {
            $wrapper.hide();
        }
    });
});
