<div id="radio-hint" class="sidebar-content" ng-if="contentType=='radio'">

	<div class="tutorial">
		<h2 translate>Setting up radio stations</h2>

		<ol>
			<li translate translate-params-url="'http://www.radio-browser.info/#!/search'">Use the Community Radio Browser <a href="{{url}}" target="_blank">search</a> to find the radio stations of interest. Instead of using the search, you may also browse the channels in Community Radio Browser by category or popularity.</li>

			<li translate>In the search results view, press the "diskette" icon on an individual found station to download the station as a PLS file. Alternatively, press the link "Save current list as playlist for your media player: PLS" on top of the view to get all the matching stations.</li>

			<li translate>Upload the previously obtained PLS file to your cloud using the Files app.</li>

			<li translate>Press the … symbol next to the navigation item "Internet radio" and select "Import from file". Find the previously uploaded PLS file and press "Choose". A loading spinner is shown for a while next to the "Internet radio" link. When it disappears, the importing is done and the channels should become available in the view.</li>

			<li translate>The PLS file is no longer needed after the import and you may delete it at will. You might want to keep it, though, because it is also possible to play the radio stations directly within the Files app by opening the PLS file.</li>
		</ol>

		<h2 translate>Troubleshooting</h2>

		<p translate>There are a couple of typical reasons why a configured radio station might not play on your browser:</p>

		<ol>
			<li translate>The stream is of HLS type, indicated by a URL ending with '.m3u' or '.m3u8'. For such streams, the source host must be specifically allowed by the cloud adminstator. See the 'Admin' section within the Music app Settings view for details.</li>

			<li translate>Your cloud is using the HTTPS scheme but the streamed URL uses the HTTP scheme. By default, your browser might block such accees. To overcome this, you may be able to set your browser to allow "Insecure content" on this page. On the desktop browsers, such settings can usually be found by clicking the icon right next to the address bar on its left side.</li>

			<li translate>The stream is genuinely broken or in such a format that either the Music app or your browser can't handle it. Majority of the radio stations found from Community Radio Browser should work on the Music app when a mainstream browser is being used, but still, there are numerous stations which will not work.</li>
		</ol>
	</div>

</div>
