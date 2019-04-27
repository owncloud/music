<div class="alphabet-navigation">
	<a ng-click="scrollToTarget(targets[link])"
		ng-repeat="link in links"
		ng-class="{available: targets.hasOwnProperty(link), filler: ($index % 2) == 1}">
		<span class="letter-content">{{ link }}</span>
	</a>
</div>
