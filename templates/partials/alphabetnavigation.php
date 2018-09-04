<div class="alphabet-navigation">
	<a ng-click="scrollToTarget(targets[letter])"
		ng-repeat="letter in letters"
		ng-class="{available: targets.hasOwnProperty(letter), filler: ($index % 2) == 1}">
		<span class="letter-content">{{ letter }}</span>
	</a>
</div>
