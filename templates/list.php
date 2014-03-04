<div ng-hide="artists" id="emptystate">
  <span ng-hide="loading" translate>Nothing in here. Upload your music!</span>
  <span ng-show="loading" translate>Loading ...</span>
</div>

<div class="navbar navbar-default navbar-fixed-top interpret">
  <div class="row">
    <div class="col-xs-4">
      <a class="btn btn-default navbar-btn btn-info" href="../files" ng-click="switchAnimationType('animation-goes-right')">
        <img class="control small svg" alt="{{'Previous' | translate }}"
        src="<?php p(OCP\image_path('music', 'new/angle_left.svg')) ?>" />
        home
      </a>
    </div>
    <div class="col-xs-4">
      <p class="navbar-text push-left">Interpreten</p>
    </div>
    <div class="col-xs-4">
      <a class="btn btn-default navbar-btn btn-info playing-btn" href="playing">
        <span> Now <br/> 
          Playing
        </span>  
        <img class="control small svg" alt="{{'Previous' | translate }}"
        src="<?php p(OCP\image_path('music', 'new/angle_right.svg')) ?>" />
      </a>
    </div>
  </div>
</div>

<div ng-show="filter == 'artist'" class="list-group interpret-list">
  <a ng-repeat="artist in artists | orderBy:'name'" 
    ng-click="artistClicked(artist)" 
    class="list-group-item">
    <span class='left'>{{artist.name}}</span>
  </a>
</div>

<div ng-show="filter == 'album'" class="list-group interpret-list">
  <a ng-repeat="album in albums | orderBy:'name'" 
    ng-click="albumClicked(album)" 
    class="list-group-item">
    <span class='left'>{{album.name}}</span>
  </a>
</div>

<div ng-show="filter == 'track'" class="list-group interpret-list">
  <a ng-repeat="track in tracks | orderBy:'title'" 
    ng-click="trackClicked(track)" 
    class="list-group-item">
    <span class='left'>{{track.title}}</span>
  </a>
</div>

<div class="navbar navbar-default navbar-fixed-bottom interpret">
  <div class="row">
    <div class="col-xs-4">
        <a class="btn btn-default navbar-btn btn-info" ng-click="artistFilterClicked()">
          Interpreten
        </a>
    </div>
    <div class="col-xs-4">
        <a class="btn btn-default navbar-btn btn-info" ng-click="albumFilterClicked()">
          Alben
        </a>
    </div>
    <div class="col-xs-4">
        <a class="btn btn-default navbar-btn btn-info" ng-click="trackFilterClicked()">
          Tracks 
        </a>
    </div>
  </div>
</div>