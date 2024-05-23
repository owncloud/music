<div id="favorite-toggle">
	<span class="fav-button icon-star" ng-click="setFavorite(1)" ng-if="!isFavorite()"
		title="{{ 'Set favorite' | translate }}" alt="{{ 'Set favorite' | translate }}"></span>
	<span class="fav-button icon-starred" ng-click="setFavorite(0)" ng-if="isFavorite()"
		title="{{ 'Unset favorite' | translate }}" alt="{{ 'Unset favorite' | translate }}"></span>
</div>