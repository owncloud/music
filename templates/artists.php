<div ng-hide="artists" id="emptystate">
	<span ng-hide="loading" translate>Nothing in here. Upload your music!</span>
	<span ng-show="loading" translate>Loading ...</span>
</div>

<div class="subnav">
  <p>choose an interpret</p>
</div>

<ul class="artists">
	<li ng-repeat="artist in artists | orderBy:'name'">
    <a class='button interpret expand' href='#/artist/{{artist.id}}' ng-click="switchAnimationType('animation-goes-left')" ng-swipe-left="swipeTest = 'left'">
      <div class='artist-entry'>
        <img class='left' src='http://placehold.it/80x80&amp;text=x'>
        <span class='left'>{{artist.name}}</span>
        <i class='fa fa-chevron-right right'></i>
      </div>
    </a>
  </li>
</ul>
