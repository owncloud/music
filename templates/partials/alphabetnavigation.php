<div class="alphabet-navigation">
	<a du-smooth-scroll="{{ letter }}" offset="{{ scrollOffset() }}"
		ng-repeat="letter in letters"
		ng-class="{available: letterAvailable[letter], filler: ($index % 2) == 1}">
		<span class="letter-content">{{ letter }}</span>
	</a>
</div>
