<div ng-hide="artists" id="emptystate">
	<span ng-hide="loading" translate>Nothing in here. Upload your music!</span>
	<span ng-show="loading" translate>Loading ...</span>
</div>

  <div class="navbar navbar-default navbar-fixed-top interpret">
    <div class="row">
      <div class="col-xs-4">
          <a class="btn btn-default navbar-btn btn-info" href="../files" ng-click="switchAnimationType('animation-goes-right')">
            - home 
            <span class="glyphicon glyphicon-search"></span>
          </a>
      </div>
      <div class="col-xs-8">
          <p class="navbar-text push-left">Interpreten</p>
      </div>
    </div>
  </div>


<!-- <ul class="artists">
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
 -->

 <div class="list-group">

  <a ng-repeat="artist in artists | orderBy:'name'" 
      href='#/artist/{{artist.id}}' 
      ng-click="switchAnimationType('animation-goes-left')" 
      class="list-group-item">
    <span class='left'>{{artist.name}}</span>
  </a>

</div>