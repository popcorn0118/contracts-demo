declare const wsWidgetRefresherData: {
	dashboardUrl: string;
	editorUrl: string;
}

jQuery(function () {
	const loader = new AmeFrameLoader.Loader({
		pages: [wsWidgetRefresherData.dashboardUrl],
		doneRedirectUrl: wsWidgetRefresherData.editorUrl,
		waitAfterLoad: 200,
	});
	loader.run();
});
