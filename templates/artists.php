<div ng-hide="artists" id="emptystate">
	<span ng-hide="loading" translate>Nothing in here. Upload your music!</span>
	<span ng-show="loading" translate>Loading ...</span>
</div>

  <div class="navbar navbar-default navbar-fixed-top interpret">
    <div class="row">
      <div class="col-xs-4">
          <a class="btn btn-default navbar-btn btn-info" href="../files" ng-click="switchAnimationType('animation-goes-right')">
            &lsaquo; home
          </a>
      </div>
      <div class="col-xs-8">
          <p class="navbar-text push-left">Interpreten</p>
      </div>
    </div>
  </div>

 <div class="list-group interpret-list">

  <a ng-repeat="artist in artists | orderBy:'name'" 
      href='artist/{{artist.id}}' 
      ng-click="switchAnimationType('animation-goes-left')" 
      class="list-group-item">
    <span class='left'>{{artist.name}}</span>
  </a>

</div>