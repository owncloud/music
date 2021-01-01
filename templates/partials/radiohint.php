<div id="radio-hint" class="sidebar-content" ng-if="contentType=='radio'">

	<h2 translate>Setting up radio channels</h2>

	<ol class="tutorial">
		<li translate ng-init="crb_search_url = 'http://www.radio-browser.info/#!/search'">
			Use the Community Radio Browser <a href="{{crb_search_url}}" target="_blank">search</a> to find the radio channels of interest.
			Instead of using the search, you may also browse the channels in Community Radio Browser by category or popularity.
		</li>
		<li translate>
			In the search results view, press the "diskette" icon on an individual found station to download the station as a PLS file.
			Alternatively, press the link "Save current list as playlist for your media player: PLS" on top of the view to get all the 
			matching stations.
		</li>
		<li translate>
			Upload the previously obtained PLS file to your cloud using the Files app.
		</li>
		<li translate>
			Press the … symbol next to the navigation item "Internet radio" and select "Import from file".
			Find the previously uploaded PLS file and press "Choose". A loading spinner is shown for a while next to the
			"Internet radio" link. When it dissappears, the importing is done and the channels should become available in the view.
		</li>
		<li translate>
			The PLS file is no longer needed after the import and you may delete it at will. You might want to keep it, though,
			because it is also possible to play the radio stations directly within the Files app by opening the PLS file.
		</li>
	</ol>

</div>
