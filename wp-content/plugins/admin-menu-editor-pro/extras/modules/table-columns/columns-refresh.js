"use strict";
jQuery(function () {
    const loader = new AmeFrameLoader.Loader({
        pages: wsAmeTableColumnsRefreshData.pageUrls,
        doneRedirectUrl: wsAmeTableColumnsRefreshData.redirectUrl,
        pageLoadTimeout: 30000,
        waitAfterLoad: 300,
        progressBarSelector: '#ame-tc-refresh-progress'
    });
    loader.run();
});
//# sourceMappingURL=columns-refresh.js.map