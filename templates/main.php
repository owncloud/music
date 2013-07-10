
{{ script('vendor/angular/angular', 'appframework') }}
{{ script('public/app', 'appframework') }}
{{ script('public/app') }}
{{ style('style') }}


<div id="app" ng-app="Music" ng-cloak>


<script type="text/ng-template" id="main.html">
	{% include 'partials/main.php' %}
</script>

	<div id="app-navigation">

		<ul class="with-icon">
			{% include 'nav.php' %}
		</ul>

	</div>

	<div id="app-content" ng-view></div>

</div>
